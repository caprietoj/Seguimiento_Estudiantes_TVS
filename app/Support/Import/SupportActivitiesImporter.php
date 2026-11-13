<?php

namespace App\Support\Import;

use App\Enums\SupportActivityType;
use App\Enums\SupportArea;
use App\Models\Enrollment;
use App\Models\Group;
use App\Models\SupportActivity;
use Carbon\CarbonImmutable;
use Throwable;

/**
 * Plantilla «Actividades del Dpto. de Apoyo (estadística)»: lo que hoy se consolida a mano en
 * el informe de gestión (estudiantes en seguimiento, familias atendidas, reuniones de seguimiento,
 * PIAR, intervenciones en crisis, admisiones, comités…). Cada fila es una actividad, o una cifra
 * agregada en «Cantidad» (p. ej. 3 reuniones de seguimiento en 7A durante el II semestre).
 */
class SupportActivitiesImporter extends Importer
{
    use ResolvesRecords;

    /** Encabezados de la plantilla descargable, en orden. */
    public const TEMPLATE = ['Área', 'Tipo de actividad', 'Grupo', 'Estudiante', 'Código institucional', 'Cantidad', 'Fecha', 'Semestre', 'Descripción'];

    /** @var array<string, list<string>> */
    private const HEADERS = [
        'area' => ['area', 'especialidad'],
        'type' => ['tipo de actividad', 'tipo', 'actividad'],
        'group' => ['grupo', 'curso'],
        'name' => ['estudiante', 'nombre', 'nombre completo'],
        'code' => ['codigo institucional', 'codigo'],
        'quantity' => ['cantidad', 'valor numerico', 'numero', 'n'],
        'date' => ['fecha'],
        'semester' => ['semestre'],
        'description' => ['descripcion', 'observacion', 'observaciones', 'detalle'],
    ];

    protected function process(array $sheets, ImportReport $report): void
    {
        $processed = 0;

        foreach ($sheets as $sheet) {
            // Solo las hojas con la columna «Área»: las hojas de ayuda o de notas se ignoran.
            $headerRow = $this->findHeaderRow($sheet, self::HEADERS['area']);

            if ($headerRow === null) {
                continue;
            }

            $columns = $this->mapHeaders($sheet->rows[$headerRow], self::HEADERS)['columns'];

            if (! isset($columns['type'])) {
                $report->error($sheet->name, $headerRow, 'Falta la columna «Tipo de actividad». Usa la plantilla descargable.');

                continue;
            }

            $processed++;

            foreach ($sheet->rows as $r => $cells) {
                if ($r > $headerRow) {
                    $this->processRow($sheet->name, $r, $cells, $columns, $report);
                }
            }
        }

        if ($processed === 0) {
            $report->error(null, null, 'No se encontró la cabecera de la plantilla (columnas «Área» y «Tipo de actividad»). Usa la plantilla descargable.');
        }
    }

    /**
     * @param  array<int, string>  $cells
     * @param  array<string, int>  $columns
     */
    private function processRow(string $sheet, int $r, array $cells, array $columns, ImportReport $report): void
    {
        $get = fn (string $key): string => isset($columns[$key]) ? trim($cells[$columns[$key]] ?? '') : '';

        if (implode('', array_map('trim', $cells)) === '') {
            return;
        }

        $report->rowsRead++;
        $fail = function (string $message) use ($report, $sheet, $r): void {
            $report->error($sheet, $r, $message);
            $report->rowsFailed++;
        };

        $area = SupportArea::fromText($get('area'));
        if ($area === null) {
            $fail("Área «{$get('area')}» no reconocida (Psicología, Terapia ocupacional, Fonoaudiología, Neuropsicología o Todo el departamento).");

            return;
        }

        $type = SupportActivityType::fromText($get('type'));
        if ($type === null) {
            $fail("Tipo de actividad «{$get('type')}» no reconocido. Usa uno de la plantilla.");

            return;
        }

        $quantityRaw = $get('quantity');
        $quantity = $quantityRaw === '' ? 1 : (int) round((float) str_replace(',', '.', $quantityRaw));
        if ($quantityRaw !== '' && (! is_numeric(str_replace(',', '.', $quantityRaw)) || $quantity < 0 || $quantity > 65000)) {
            $fail("Cantidad «{$quantityRaw}» inválida: debe ser un número entero.");

            return;
        }
        if ($quantity === 0) {
            $report->warning($sheet, $r, 'Cantidad 0: no se registra nada.');

            return;
        }

        $date = null;
        if ($get('date') !== '') {
            $date = self::parseDate($get('date'));
            if ($date === null) {
                $fail("Fecha «{$get('date')}» no válida (usa AAAA-MM-DD o DD/MM/AAAA).");

                return;
            }
        }

        $semester = self::parseSemester($get('semester')) ?? SupportActivity::semesterFor($date);
        if ($get('semester') !== '' && self::parseSemester($get('semester')) === null) {
            $fail("Semestre «{$get('semester')}» no válido (1 o 2).");

            return;
        }

        $group = null;
        if ($get('group') !== '') {
            $group = $this->resolveGroup($get('group'), null, false, $report, $sheet, $r);
            if ($group === null) {
                $report->rowsFailed++;

                return;
            }
        }

        $student = null;
        if ($get('name') !== '' || $get('code') !== '') {
            if ($group === null) {
                $fail('Para registrar a un estudiante indica también su grupo.');

                return;
            }

            $student = $this->resolveStudent($get('name'), $get('code') ?: null, $group, false, $report, $sheet, $r);
            if ($student === null) {
                $report->rowsFailed++;

                return;
            }

            $quantity = 1;
            $enrolledIn = Enrollment::where('student_id', $student->id)->where('school_year_id', $this->schoolYear->id)->value('group_id');
            if ($enrolledIn !== null && $enrolledIn !== $group->id) {
                $report->warning($sheet, $r, "{$student->full_name} está matriculado(a) en ".Group::whereKey($enrolledIn)->value('code')." en {$this->schoolYear->name}; la actividad se registra en {$group->code}.");
            }
        }

        $description = $get('description') !== '' ? $get('description') : null;

        $duplicate = SupportActivity::query()
            ->where('school_year_id', $this->schoolYear->id)
            ->where('area', $area->value)
            ->where('type', $type->value)
            ->where('group_id', $group?->id)
            ->where('student_id', $student?->id)
            ->where('semester', $semester)
            ->where('quantity', $quantity)
            ->where('description', $description)
            ->when($date, fn ($q) => $q->whereDate('occurred_on', $date), fn ($q) => $q->whereNull('occurred_on'))
            ->exists();

        if ($duplicate) {
            $report->count('Actividades que ya existían (omitidas)');
            $report->rowsImported++;

            return;
        }

        $activity = new SupportActivity([
            'school_year_id' => $this->schoolYear->id,
            'area' => $area,
            'type' => $type,
            'group_id' => $group?->id,
            'student_id' => $student?->id,
            'occurred_on' => $date,
            'semester' => $semester,
            'quantity' => $quantity,
            'description' => $description,
            'author_id' => $this->user->id,
        ]);
        $this->stamp($activity, $r);
        $activity->save();

        $report->rowsImported++;
        $report->count($type->label().($type->isPerStudent() && $student === null ? ' (cifra agregada)' : ''), $quantity);
        $report->addPreview([
            'fila' => $r,
            'área' => $area->label(),
            'tipo' => $type->label(),
            'grupo' => $group->code ?? '—',
            'estudiante' => $student->full_name ?? '—',
            'cantidad' => $quantity,
            'semestre' => $semester ? ($semester === 1 ? 'I' : 'II') : '—',
        ]);
    }

    public static function parseDate(string $text): ?CarbonImmutable
    {
        foreach (['Y-m-d', 'd/m/Y', 'd-m-Y', 'd/m/y', 'Y/m/d'] as $format) {
            try {
                $date = CarbonImmutable::createFromFormat('!'.$format, substr(trim($text), 0, 10));
                if ($date !== null && $date->year > 2000) {
                    return $date;
                }
            } catch (Throwable) {
                // Siguiente formato.
            }
        }

        return null;
    }

    public static function parseSemester(string $text): ?int
    {
        $t = NameMatcher::normalize($text);

        return match (true) {
            $t === '' => null,
            in_array($t, ['1', 'i', 'primero', 'primer', 'i semestre', 'primer semestre', 's1'], true) => 1,
            in_array($t, ['2', 'ii', 'segundo', 'ii semestre', 'segundo semestre', 's2'], true) => 2,
            default => null,
        };
    }
}
