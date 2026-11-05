<?php

namespace App\Support\Import;

use App\Models\Grade;
use App\Models\Setting;
use App\Models\Subject;

/**
 * Plantilla "Calificaciones de asignaturas (IB)": importa las notas periódicas de los estudiantes.
 */
class GradesImporter extends Importer
{
    use ResolvesRecords;

    /** Encabezados de la plantilla descargable, en orden. */
    public const TEMPLATE = ['Código institucional', 'Nombre completo', 'Grupo', 'Asignatura', 'Periodo', 'Nivel'];

    /** @var array<string, list<string>> */
    private const HEADERS = [
        'code' => ['codigo institucional', 'codigo', 'documento', 'id'],
        'name' => ['nombre completo', 'nombre', 'estudiante', 'nombres y apellidos', 'apellidos y nombres'],
        'group' => ['grupo', 'curso'],
        'subject' => ['asignatura', 'materia', 'subject'],
        'period' => ['periodo', 'period', 'corte'],
        'level' => ['nivel', 'calificacion', 'nota', 'grade', 'level'],
    ];

    protected function process(array $sheets, ImportReport $report): void
    {
        $sheet = $sheets[0] ?? null;
        $headerRow = $sheet ? $this->findHeaderRow($sheet, self::HEADERS['name']) : null;

        if ($sheet === null || $headerRow === null) {
            $report->error(null, null, 'No se encontró la cabecera con «Nombre completo» o «Estudiante». Usa la plantilla descargable.');

            return;
        }

        $mapped = $this->mapHeaders($sheet->rows[$headerRow], self::HEADERS);
        $columns = $mapped['columns'];

        foreach (['name', 'group', 'subject', 'period', 'level'] as $req) {
            if (! isset($columns[$req])) {
                $report->error($sheet->name, $headerRow, "Falta la columna requerida «{$req}».");

                return;
            }
        }

        $minLevel = (int) Setting::get('ib_escala_min', config('seguimiento.nivel_minimo', 1));
        $maxLevel = (int) Setting::get('ib_escala_max', config('seguimiento.nivel_maximo', 7));
        $maxPeriods = (int) Setting::get('ib_periodos', config('seguimiento.periodos', 3));

        $subjects = Subject::active()->get();

        foreach ($sheet->rows as $r => $cells) {
            if ($r <= $headerRow) {
                continue;
            }

            $report->rowsRead++;
            $name = trim($cells[$columns['name']] ?? '');
            $groupCode = trim($cells[$columns['group']] ?? '');
            $code = isset($columns['code']) ? ($cells[$columns['code']] ?? null) : null;
            $subjectRaw = trim($cells[$columns['subject']] ?? '');
            $periodRaw = trim($cells[$columns['period']] ?? '');
            $levelRaw = trim($cells[$columns['level']] ?? '');

            if ($name === '' && $code === '') {
                continue;
            }

            $group = $this->resolveGroup($groupCode, null, false, $report, $sheet->name, $r);
            if ($group === null) {
                $report->rowsFailed++;

                continue;
            }

            $student = $this->resolveStudent($name, $code, $group, false, $report, $sheet->name, $r);
            if ($student === null) {
                $report->rowsFailed++;

                continue;
            }

            // Buscar asignatura
            $subject = $subjects->first(function (Subject $s) use ($subjectRaw) {
                return NameMatcher::normalize($s->name) === NameMatcher::normalize($subjectRaw)
                    || mb_strtolower($s->code ?? '') === mb_strtolower($subjectRaw);
            });

            if ($subject === null) {
                // Búsqueda aproximada
                $subject = $subjects->first(fn (Subject $s) => NameMatcher::similarity($s->name, $subjectRaw) >= 80);
            }

            if ($subject === null) {
                $report->error($sheet->name, $r, "La asignatura «{$subjectRaw}» no existe en el catálogo.");
                $report->rowsFailed++;

                continue;
            }

            $period = (int) preg_replace('/\D/', '', $periodRaw);
            if ($period < 1 || $period > $maxPeriods) {
                $report->error($sheet->name, $r, "Periodo «{$periodRaw}» inválido. Debe estar entre 1 y {$maxPeriods}.");
                $report->rowsFailed++;

                continue;
            }

            $level = (int) $levelRaw;
            if ($level < $minLevel || $level > $maxLevel) {
                $report->error($sheet->name, $r, "Nivel «{$levelRaw}» fuera de la escala permitida ({$minLevel}–{$maxLevel}).");
                $report->rowsFailed++;

                continue;
            }

            $gradeRecord = Grade::updateOrCreate(
                [
                    'student_id' => $student->id,
                    'subject_id' => $subject->id,
                    'school_year_id' => $this->schoolYear->id,
                    'period' => $period,
                ],
                [
                    'level' => $level,
                    'recorded_by' => $this->user->id,
                    'import_batch_id' => $this->batch?->id,
                    'source_file' => $this->fileName,
                    'source_row' => $r,
                ]
            );

            $report->rowsImported++;
            $report->count('Calificaciones registradas');
            $report->addPreview([
                'Estudiante' => $student->full_name,
                'Grupo' => $group->code,
                'Asignatura' => $subject->name,
                'Periodo' => "Periodo {$period}",
                'Nivel' => $level,
            ]);
        }
    }
}
