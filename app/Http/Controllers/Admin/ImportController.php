<?php

namespace App\Http\Controllers\Admin;

use App\Enums\AuditAction;
use App\Enums\ImportBatchStatus;
use App\Enums\ImportTemplateType;
use App\Http\Controllers\Controller;
use App\Jobs\FetchPhidiasPhotos;
use App\Models\AuditLog;
use App\Models\ImportBatch;
use App\Models\SchoolYear;
use App\Support\Import\ImportService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Administración · Importar: sube un archivo, simula (sin guardar nada), muestra el reporte
 * fila por fila, importa al confirmar y permite revertir un lote completo.
 */
class ImportController extends Controller
{
    public function index(Request $request): Response
    {
        $schoolYears = SchoolYear::orderByDesc('starts_on')->get();
        $activeYearId = SchoolYear::where('is_active', true)->value('id');

        $batches = ImportBatch::with(['schoolYear', 'user'])
            ->whereIn('template_type', array_map(fn (ImportTemplateType $t) => $t->value, ImportService::SUPPORTED))
            ->latest('id')
            ->limit(30)
            ->get();

        $selected = $request->integer('lote') > 0 ? ImportBatch::with(['schoolYear', 'user'])->find($request->integer('lote')) : null;

        return Inertia::render('admin/importar', [
            'schoolYears' => $schoolYears->map(fn (SchoolYear $y) => [
                'id' => $y->id,
                'name' => $y->name,
                'is_read_only' => $y->is_read_only,
            ])->values(),
            'activeYearId' => $activeYearId,
            'templates' => collect(ImportService::SUPPORTED)->map(fn (ImportTemplateType $t) => [
                'value' => $t->value,
                'label' => $t->label(),
                'has_template' => $t !== ImportTemplateType::PhidiasStudents,
            ])->values(),
            'maxMb' => (int) config('seguimiento.migracion.max_mb', 20),
            'batches' => $batches->map(fn (ImportBatch $b) => $this->batchSummary($b))->values(),
            'selected' => $selected ? [
                ...$this->batchSummary($selected),
                'report' => collect($selected->error_report ?? [])->except(['undo', 'file_path'])->all(),
            ] : null,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $maxKb = (int) config('seguimiento.migracion.max_mb', 20) * 1024;

        $data = $request->validate([
            // Por extensión y no por contenido: el .xls de Phidias es una tabla HTML por dentro.
            'file' => ['required', 'file', 'extensions:xlsx,xls,csv', "max:{$maxKb}"],
            'template_type' => ['required', Rule::in(array_map(fn (ImportTemplateType $t) => $t->value, ImportService::SUPPORTED))],
            'school_year_id' => ['required', 'integer', 'exists:school_years,id'],
            'group_code' => ['nullable', 'string', 'max:20'],
            'create_students' => ['boolean'],
            'download_photos' => ['boolean'],
        ], [], [
            'file' => 'archivo',
            'template_type' => 'tipo de importación',
            'school_year_id' => 'año escolar',
        ]);

        $file = $request->file('file');
        abort_if($file === null, 422);

        $type = ImportTemplateType::from($data['template_type']);
        $year = SchoolYear::findOrFail($data['school_year_id']);
        $hash = hash_file('sha256', $file->getRealPath()) ?: '';
        $extension = strtolower($file->getClientOriginalExtension() ?: 'xlsx');
        $path = $file->storeAs('imports', $hash.'.'.$extension, 'local');
        abort_if($path === false, 500, 'No se pudo guardar el archivo.');

        $options = [
            'group_code' => $data['group_code'] ?? null,
            'create_students' => (bool) ($data['create_students'] ?? true),
            'download_photos' => (bool) ($data['download_photos'] ?? true),
        ];

        $report = ImportService::importer($type, $year, $request->user(), $options)
            ->simulate(Storage::disk('local')->path($path), $file->getClientOriginalName());

        $previous = ImportBatch::where('file_hash', $hash)
            ->where('template_type', $type->value)
            ->where('school_year_id', $year->id)
            ->where('status', ImportBatchStatus::Imported->value)
            ->latest('id')
            ->first();

        if ($previous !== null) {
            array_unshift($report->warnings, [
                'sheet' => null,
                'row' => null,
                'message' => "Este mismo archivo ya se importó el {$previous->created_at?->format('d/m/Y H:i')} (lote #{$previous->id}). Los aportes y estrategias idénticos se omitirán, pero revisa que no estés duplicando información.",
            ]);
        }

        $batch = ImportBatch::create([
            'file_name' => mb_substr($file->getClientOriginalName(), 0, 255),
            'file_hash' => $hash,
            'template_type' => $type,
            'school_year_id' => $year->id,
            'status' => ImportBatchStatus::Validated,
            'rows_read' => $report->rowsRead,
            'rows_imported' => $report->rowsImported,
            'rows_failed' => $report->rowsFailed,
            'error_report' => [...$report->toArray(), 'options' => $options, 'file_path' => $path],
            'user_id' => $request->user()->id,
        ]);

        return to_route('admin.importar.index', ['lote' => $batch->id]);
    }

    public function confirm(Request $request, ImportBatch $batch): RedirectResponse
    {
        abort_unless($batch->status === ImportBatchStatus::Validated, 422, 'Este lote ya se procesó.');

        $stored = $batch->error_report ?? [];
        $path = (string) ($stored['file_path'] ?? '');
        abort_unless($path !== '' && Storage::disk('local')->exists($path), 410, 'El archivo de la simulación ya no está disponible. Vuelve a subirlo.');

        $report = ImportService::importer($batch->template_type, $batch->schoolYear, $request->user(), $stored['options'] ?? [])
            ->import(Storage::disk('local')->path($path), $batch->file_name, $batch);

        $batch->update([
            'status' => $report->rowsImported > 0 ? ImportBatchStatus::Imported : ImportBatchStatus::Failed,
            'rows_read' => $report->rowsRead,
            'rows_imported' => $report->rowsImported,
            'rows_failed' => $report->rowsFailed,
            'error_report' => [...$report->toArray(), 'options' => $stored['options'] ?? [], 'file_path' => $path],
            'user_id' => $request->user()->id,
        ]);

        $this->audit($request, AuditAction::Import, $batch, ['created' => $report->created, 'rows_imported' => $report->rowsImported]);

        if ($report->photos !== [] && $batch->status === ImportBatchStatus::Imported) {
            FetchPhidiasPhotos::dispatch($batch->id);
        }

        return to_route('admin.importar.index', ['lote' => $batch->id]);
    }

    public function revert(Request $request, ImportBatch $batch): RedirectResponse
    {
        abort_unless($batch->status === ImportBatchStatus::Imported, 422, 'Solo se puede revertir un lote importado.');

        $reverted = ImportService::revert($batch);

        $batch->update([
            'status' => ImportBatchStatus::Reverted,
            'error_report' => [...($batch->error_report ?? []), 'reverted' => $reverted, 'reverted_at' => now()->toIso8601String(), 'undo' => []],
        ]);

        $this->audit($request, AuditAction::Revert, $batch, $reverted);

        return to_route('admin.importar.index', ['lote' => $batch->id]);
    }

    /** Borra una simulación que no se va a importar. */
    public function destroy(ImportBatch $batch): RedirectResponse
    {
        abort_unless($batch->status === ImportBatchStatus::Validated, 422, 'Solo se pueden descartar simulaciones.');

        $path = (string) ($batch->error_report['file_path'] ?? '');
        $batch->delete();

        if ($path !== '' && ! ImportBatch::where('file_hash', $batch->file_hash)->exists()) {
            Storage::disk('local')->delete($path);
        }

        return to_route('admin.importar.index');
    }

    /** Plantilla descargable en Excel con los encabezados exactos que espera el importador. */
    public function template(string $type): StreamedResponse
    {
        $templateType = ImportTemplateType::tryFrom($type);
        abort_unless($templateType !== null && in_array($templateType, ImportService::SUPPORTED, true), 404);
        abort_if($templateType === ImportTemplateType::PhidiasStudents, 404, 'Este archivo se exporta directamente desde Phidias.');

        $spreadsheet = new Spreadsheet;
        $sheet = $spreadsheet->getActiveSheet();
        $headers = ImportService::templateHeaders($templateType);
        $isCurrentFormat = $headers === null;

        if ($headers === null) {
            // Formato actual "Seguimiento y acuerdos": se reproduce la cabecera que usa el colegio.
            $sheet->setTitle('mitad periodo I');
            $sheet->setCellValue('A1', 'SEGUIMIENTO Y ACUERDOS – 10A');
            $sheet->mergeCells('A1:L1');
            $sheet->fromArray(['Periodo:', 'Periodo 1', 'Seguimiento:', 'Seguimiento 1', 'Fecha:', now()->format('Y-m-d'), null, null, null, 'Director(a) de grupo:', 'Nombre del director'], null, 'A3');
            $sheet->setCellValue('G6', 'Dpto de apoyo/Grupo focal');
            $headers = ['Estudiante', 'Fortalezas / avances', 'Aspectos por mejorar', 'Asignaturas para tener en cuenta', 'Estrategias', 'Responsables', 'Psicología', 'Fonoaudiología', 'Terapia ocupacional', 'Neuropsicología', 'Coordinación', 'Observación'];
            $sheet->fromArray($headers, null, 'A7');
            $sheet->getStyle('A1')->getFont()->setBold(true)->setSize(14);
            $sheet->getStyle('A7:L7')->getFont()->setBold(true);
            $sheet->getStyle('A7:L7')->getFill()->setFillType('solid')->getStartColor()->setRGB('DCE6F1');
        } else {
            $sheet->setTitle('Datos');
            $sheet->fromArray($headers, null, 'A1');
            $last = chr(ord('A') + count($headers) - 1);
            $sheet->getStyle("A1:{$last}1")->getFont()->setBold(true);
            $sheet->getStyle("A1:{$last}1")->getFill()->setFillType('solid')->getStartColor()->setRGB('DCE6F1');
            $sheet->fromArray($templateType === ImportTemplateType::StudentsEnrollments
                ? ['TVS-0001', 'Ana María Pérez Gómez', '10A', 'DP', 'Sí']
                : ['docente@tvs.edu.co', 'Nombre Apellido', '10A', 'Matemáticas', 'No'], null, 'A2');
        }

        foreach (range(1, count($headers)) as $i) {
            $sheet->getColumnDimensionByColumn($i)->setWidth($isCurrentFormat ? 22 : 26);
        }

        $fileName = 'plantilla-'.str_replace('_', '-', $templateType->value).'.xlsx';

        return response()->streamDownload(function () use ($spreadsheet) {
            (new Xlsx($spreadsheet))->save('php://output');
        }, $fileName, ['Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet']);
    }

    /** @return array<string, mixed> */
    private function batchSummary(ImportBatch $b): array
    {
        return [
            'id' => $b->id,
            'file_name' => $b->file_name,
            'template_label' => $b->template_type->label(),
            'school_year' => $b->schoolYear->name,
            'status' => $b->status->value,
            'status_label' => $b->status->label(),
            'rows_read' => $b->rows_read,
            'rows_imported' => $b->rows_imported,
            'rows_failed' => $b->rows_failed,
            'user' => $b->user->name,
            'created_at' => $b->created_at?->format('d/m/Y H:i'),
            'updated_at' => $b->updated_at?->format('d/m/Y H:i'),
        ];
    }

    /** @param array<string, mixed> $changes */
    private function audit(Request $request, AuditAction $action, ImportBatch $batch, array $changes): void
    {
        AuditLog::create([
            'user_id' => $request->user()?->id,
            'action' => $action,
            'auditable_type' => ImportBatch::class,
            'auditable_id' => $batch->id,
            'changes' => ['file' => $batch->file_name, 'template' => $batch->template_type->value, ...$changes],
            'ip' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'created_at' => now(),
        ]);
    }
}
