<?php

namespace App\Support\Import;

use App\Enums\SupportArea;
use App\Models\Contribution;
use App\Models\ExternalSupport;
use App\Models\FollowUp;
use App\Models\Student;

/**
 * Plantilla «Seguimiento del Dpto. de Apoyo (por área)»: lo que cada área (Psicología,
 * Fonoaudiología, Terapia ocupacional, Neuropsicología) registró de cada estudiante en cada
 * seguimiento. Es la versión plana del Excel «Seguimiento Dpto. de Apoyo» del colegio (una hoja
 * por curso con bloques por fecha) y de la hoja «EMPALME» del informe de gestión.
 *
 * Cada fila es un estudiante × área × seguimiento y se guarda como un aporte del área en la
 * ficha (Dpto. de apoyo → Psicología, Fonoaudiología…). Las tres partes del formato del colegio
 * (habilidades, acuerdos/estrategias y seguimiento a las estrategias) quedan en el mismo aporte,
 * rotuladas. Si la fila dice que el estudiante tiene profesional externo, se registra además
 * como apoyo externo.
 */
class SupportNotesImporter extends Importer
{
    use ResolvesRecords;

    /** Encabezados de la plantilla descargable, en orden. */
    public const TEMPLATE = [
        'Grupo', 'Estudiante', 'Código institucional', 'Periodo', 'Seguimiento', 'Fecha', 'Área',
        'Habilidades / observación', 'Acuerdos / estrategias', 'Seguimiento a las estrategias',
        'Profesional externo', '¿Cuál?',
    ];

    /** @var array<string, list<string>> */
    private const HEADERS = [
        'group' => ['grupo', 'curso'],
        'name' => ['estudiante', 'nombre', 'nombre completo', 'nombre del estudiante'],
        'code' => ['codigo institucional', 'codigo'],
        'period' => ['periodo'],
        'number' => ['seguimiento', 'numero de seguimiento'],
        'date' => ['fecha', 'fecha de la reunion'],
        'area' => ['area', 'especialidad'],
        'skills' => ['habilidades observacion', 'habilidades', 'habilidades mencionadas', 'observacion', 'observacion cierre de ano'],
        'agreements' => ['acuerdos estrategias', 'acuerdos', 'estrategias', 'acuerdo estrategia'],
        'review' => ['seguimiento a las estrategias', 'seguimiento estrategias', 'seguimiento a estrategias'],
        'external' => ['profesional externo', 'profesion externo', 'apoyo externo'],
        'external_which' => ['cual', 'especialidad externa', 'profesional'],
    ];

    /** @var array<string, FollowUp|null> */
    private array $followUps = [];

    protected function process(array $sheets, ImportReport $report): void
    {
        $processed = 0;

        foreach ($sheets as $sheet) {
            $headerRow = $this->findHeaderRow($sheet, self::HEADERS['area']);

            if ($headerRow === null) {
                continue;
            }

            $columns = $this->mapHeaders($sheet->rows[$headerRow], self::HEADERS)['columns'];
            $missing = array_diff(['group', 'name', 'period', 'number', 'area'], array_keys($columns));

            if ($missing !== []) {
                $labels = ['group' => 'Grupo', 'name' => 'Estudiante', 'period' => 'Periodo', 'number' => 'Seguimiento', 'area' => 'Área'];
                $report->error($sheet->name, $headerRow, 'Faltan columnas: '.implode(', ', array_map(fn ($c) => "«{$labels[$c]}»", $missing)).'. Usa la plantilla descargable.');

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
            $report->error(null, null, 'No se encontró la cabecera de la plantilla (columnas «Grupo», «Estudiante», «Periodo», «Seguimiento», «Área»…). Usa la plantilla descargable.');
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
        $name = $get('name');

        if ($name === '' && $get('code') === '') {
            $report->error($sheet, $r, 'Fila sin estudiante.');
            $report->rowsFailed++;

            return;
        }

        $area = SupportArea::fromText($get('area'));
        $field = $area?->contributionField();

        if ($field === null) {
            $report->error($sheet, $r, "Área «{$get('area')}» no reconocida. Usa Psicología, Fonoaudiología, Terapia ocupacional o Neuropsicología.");
            $report->rowsFailed++;

            return;
        }

        $followUp = $this->followUp($get('period'), $get('number'));

        if ($followUp === null) {
            $report->error($sheet, $r, "No existe el seguimiento «Periodo {$get('period')} · Seguimiento {$get('number')}» en {$this->schoolYear->name}. Revisa Administración · Periodo y seguimiento.");
            $report->rowsFailed++;

            return;
        }

        $group = $this->resolveGroup($get('group'), null, true, $report, $sheet, $r);
        $code = $get('code') !== '' ? $get('code') : null;
        $student = $group ? $this->resolveStudent($name, $code, $group, (bool) ($this->options['create_students'] ?? false), $report, $sheet, $r) : null;

        if ($group === null || $student === null || ! $this->ensureEnrollment($student, $group, false, $report, $sheet, $r)) {
            $report->rowsFailed++;

            return;
        }

        $body = self::composeBody($get('skills'), $get('agreements'), $get('review'));
        $added = 0;

        if ($body !== '') {
            $existing = Contribution::withTrashed()
                ->where('student_id', $student->id)
                ->where('follow_up_id', $followUp->id)
                ->where('field', $field->value)
                ->where('body', $body)
                ->first();

            if ($existing?->trashed()) {
                $existing->restore();
                $report->count('Aportes restaurados');
                $added++;
            } elseif ($existing !== null) {
                $report->count('Aportes que ya existían (omitidos)');
            } else {
                $contribution = new Contribution([
                    'student_id' => $student->id,
                    'follow_up_id' => $followUp->id,
                    'field' => $field,
                    'body' => $body,
                    'author_id' => $this->user->id,
                ]);
                $this->stamp($contribution, $r);
                $contribution->save();
                $report->count('Aportes · '.$field->label());
                $added++;
            }
        }

        $added += $this->external($student, $area, $followUp, $get('external'), $get('external_which'), $report, $r);

        if ($body === '' && $added === 0) {
            $report->warning($sheet, $r, "{$student->full_name}: la fila no trae información para importar.");
        }

        $report->rowsImported++;
        $report->addPreview([
            'hoja' => $sheet,
            'fila' => $r,
            'estudiante' => $student->full_name,
            'grupo' => $group->code,
            'seguimiento' => $followUp->shortLabel(),
            'área' => $area->label(),
            'registros' => $added,
        ]);
    }

    /** Une las tres partes del formato del colegio en un solo texto, rotulando las que no son la primera. */
    public static function composeBody(string $skills, string $agreements, string $review): string
    {
        $parts = [];

        if ($skills !== '') {
            $parts[] = $skills;
        }
        if ($agreements !== '') {
            $parts[] = 'Acuerdos / estrategias: '.$agreements;
        }
        if ($review !== '') {
            $parts[] = 'Seguimiento a las estrategias: '.$review;
        }

        return implode("\n\n", $parts);
    }

    private function followUp(string $period, string $number): ?FollowUp
    {
        $p = CurrentFormatImporter::parseNumber($period);
        $n = CurrentFormatImporter::parseNumber($number);

        if ($p === null || $n === null) {
            return null;
        }

        return $this->followUps["{$p}-{$n}"] ??= FollowUp::where('school_year_id', $this->schoolYear->id)
            ->where('period', $p)
            ->where('number', $n)
            ->first();
    }

    private function external(Student $student, SupportArea $area, FollowUp $followUp, string $flag, string $which, ImportReport $report, int $row): int
    {
        if (! self::yes($flag)) {
            return 0;
        }

        $specialty = $which !== '' ? mb_substr(preg_replace('/\s+/', ' ', $which) ?? $which, 0, 255) : 'Sin especificar';

        $exists = ExternalSupport::withTrashed()
            ->where('student_id', $student->id)
            ->where('specialty', $specialty)
            ->exists();

        if ($exists) {
            $report->count('Apoyos externos que ya existían (omitidos)');

            return 0;
        }

        $support = new ExternalSupport([
            'student_id' => $student->id,
            'provider' => 'Profesional externo',
            'specialty' => $specialty,
            'notes' => "Reportado por {$area->label()} en {$followUp->label()} ({$this->schoolYear->name}).",
            'author_id' => $this->user->id,
        ]);
        $this->stamp($support, $row);
        $support->save();
        $report->count('Apoyos externos');

        return 1;
    }
}
