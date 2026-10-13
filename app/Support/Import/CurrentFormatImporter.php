<?php

namespace App\Support\Import;

use App\Enums\ContributionField;
use App\Enums\StrategyType;
use App\Models\Contribution;
use App\Models\FollowUp;
use App\Models\Group;
use App\Models\GroupMeeting;
use App\Models\Strategy;
use App\Models\User;
use Carbon\CarbonImmutable;
use Throwable;

/**
 * Importa el Excel "Seguimiento y acuerdos" tal como lo usa hoy el colegio: una hoja por
 * reunión, con el grupo en el título, el periodo/seguimiento/fecha/director en la cabecera y
 * una fila por estudiante. Cada celda se reparte en la estructura nueva de la aplicación:
 *
 *  - Fortalezas, Aspectos por mejorar, Asignaturas para tener en cuenta, Observación y las
 *    cinco áreas del Dpto. de apoyo → un aporte (Contribution) de ese campo en ese seguimiento.
 *  - Estrategias + Responsables → una estrategia individual activa.
 *  - Fecha → fecha de la reunión de grupo de ese seguimiento.
 *  - Director(a) de grupo → se asigna al grupo si todavía no tiene.
 *
 * Opciones: `group_code` (forzar el grupo si el título no lo trae) y `create_students`.
 */
class CurrentFormatImporter extends Importer
{
    use ResolvesRecords;

    /** @var array<string, list<string>> */
    private const HEADERS = [
        'student' => ['estudiante', 'estudiantes', 'nombre', 'nombre del estudiante', 'nombre estudiante', 'alumno', 'nombre completo'],
        'code' => ['codigo', 'codigo institucional'],
        'strength' => ['fortalezas avances', 'fortalezas', 'avances', 'fortalezas y avances'],
        'improvement' => ['aspectos por mejorar', 'aspectos de mejora', 'aspectos a mejorar', 'por mejorar'],
        'subject_attention' => ['asignaturas para tener en cuenta', 'asignaturas a tener en cuenta', 'asignaturas'],
        'strategy' => ['estrategias', 'estrategia'],
        'responsible' => ['responsables', 'responsable'],
        'support_psychology' => ['psicologia'],
        'support_speech' => ['fonoaudiologia'],
        'support_ot' => ['terapia ocupacional', 't o', 'to'],
        'support_neuro' => ['neuropsicologia'],
        'support_coordination' => ['coordinacion'],
        'observation' => ['observacion', 'observaciones'],
    ];

    /** @var array<string, ContributionField> */
    private const FIELDS = [
        'strength' => ContributionField::Strength,
        'improvement' => ContributionField::Improvement,
        'subject_attention' => ContributionField::SubjectAttention,
        'support_psychology' => ContributionField::SupportPsychology,
        'support_speech' => ContributionField::SupportSpeech,
        'support_ot' => ContributionField::SupportOt,
        'support_neuro' => ContributionField::SupportNeuro,
        'support_coordination' => ContributionField::SupportCoordination,
        'observation' => ContributionField::Observation,
    ];

    protected function process(array $sheets, ImportReport $report): void
    {
        $processed = 0;

        foreach ($sheets as $sheet) {
            if ($sheet->rows === []) {
                continue;
            }

            $headerRow = $this->findHeaderRow($sheet, self::HEADERS['student']);

            if ($headerRow === null) {
                $report->warning($sheet->name, null, 'La hoja no tiene la columna «Estudiante»; se omite.');

                continue;
            }

            $processed++;
            $this->processSheet($sheet, $headerRow, $report);
        }

        if ($processed === 0) {
            $report->error(null, null, 'Ninguna hoja tiene el formato «Seguimiento y acuerdos» (falta la columna «Estudiante»).');
        }
    }

    private function processSheet(SheetData $sheet, int $headerRow, ImportReport $report): void
    {
        $name = $sheet->name;

        // --- Cabecera: grupo, periodo, seguimiento, fecha y director ---
        $groupCode = trim((string) ($this->options['group_code'] ?? '')) ?: $this->groupCodeFromTitle($sheet);

        if ($groupCode === null) {
            $report->error($name, 1, 'No se encontró el grupo en el título («SEGUIMIENTO Y ACUERDOS – 10A»). Indícalo en el campo «Grupo».');
            $this->failAllRows($sheet, $headerRow, $report);

            return;
        }

        $group = $this->resolveGroup($groupCode, null, true, $report, $name, 1);

        if ($group === null) {
            $this->failAllRows($sheet, $headerRow, $report);

            return;
        }

        [$period, $number] = $this->followUpNumbers($sheet);

        if ($period === null || $number === null) {
            $report->error($name, 3, 'No se pudo leer el periodo o el número de seguimiento de la cabecera («Periodo:» / «Seguimiento:»).');
            $this->failAllRows($sheet, $headerRow, $report);

            return;
        }

        $followUp = $this->resolveFollowUp($period, $number, $report, $name);
        $this->applyMeetingDate($sheet, $group, $followUp, $report);
        $this->applyDirector($sheet, $group, $report);

        // --- Columnas, por el texto del encabezado ---
        $mapped = $this->mapHeaders($sheet->rows[$headerRow], self::HEADERS);
        $columns = $mapped['columns'];

        foreach ($mapped['unknown'] as $header) {
            $report->warning($name, $headerRow, "La columna «{$header}» no corresponde a ningún campo de la aplicación; no se importa.");
        }

        // --- Filas de estudiantes, hasta el siguiente título de sección ---
        $endRow = $sheet->lastRow();
        foreach (array_keys($sheet->bannerRows) as $banner) {
            if ($banner > $headerRow && $banner <= $endRow) {
                $endRow = $banner - 1;
            }
        }

        for ($r = $headerRow + 1; $r <= $endRow; $r++) {
            $this->processRow($sheet, $r, $columns, $group, $followUp, $report);
        }

        $leftover = array_filter(array_keys($sheet->rows), fn (int $r) => $r > $endRow + 1);
        if ($leftover !== []) {
            $report->warning($name, $endRow + 1, 'Hay contenido debajo de la tabla de estudiantes (desde la fila '.($endRow + 1).') que no tiene el formato de la tabla; no se importa.');
        }
    }

    /** @param array<string, int> $columns */
    private function processRow(SheetData $sheet, int $r, array $columns, Group $group, FollowUp $followUp, ImportReport $report): void
    {
        $cells = $sheet->rows[$r] ?? [];

        if ($cells === []) {
            return;
        }

        $report->rowsRead++;
        $studentName = isset($columns['student']) ? ($cells[$columns['student']] ?? '') : '';

        if (trim($studentName) === '') {
            $report->error($sheet->name, $r, 'Fila con información pero sin nombre de estudiante.');
            $report->rowsFailed++;

            return;
        }

        $code = isset($columns['code']) ? ($cells[$columns['code']] ?? null) : null;
        $student = $this->resolveStudent($studentName, $code, $group, (bool) ($this->options['create_students'] ?? true), $report, $sheet->name, $r);

        if ($student === null || ! $this->ensureEnrollment($student, $group, false, $report, $sheet->name, $r)) {
            $report->rowsFailed++;

            return;
        }

        $added = 0;
        $hasContent = false;

        foreach (self::FIELDS as $concept => $field) {
            $body = isset($columns[$concept]) ? trim($cells[$columns[$concept]] ?? '') : '';

            if ($body === '') {
                continue;
            }

            $hasContent = true;

            $exists = Contribution::where('student_id', $student->id)
                ->where('follow_up_id', $followUp->id)
                ->where('field', $field->value)
                ->where('body', $body)
                ->exists();

            if ($exists) {
                $report->count('Aportes que ya existían (omitidos)');

                continue;
            }

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

        $strategyText = isset($columns['strategy']) ? trim($cells[$columns['strategy']] ?? '') : '';
        $responsible = isset($columns['responsible']) ? trim($cells[$columns['responsible']] ?? '') : '';

        if ($strategyText !== '') {
            $exists = Strategy::where('student_id', $student->id)
                ->where('follow_up_id', $followUp->id)
                ->where('body', $strategyText)
                ->exists();

            if ($exists) {
                $report->count('Estrategias que ya existían (omitidas)');
            } else {
                $strategy = new Strategy([
                    'type' => StrategyType::Individual,
                    'student_id' => $student->id,
                    'follow_up_id' => $followUp->id,
                    'body' => $strategyText,
                    'responsible' => $responsible !== '' ? mb_substr($responsible, 0, 255) : null,
                    'author_id' => $this->user->id,
                    'is_active' => true,
                ]);
                $this->stamp($strategy, $r);
                $strategy->save();
                $report->count('Estrategias individuales');
                $added++;
            }
        } elseif ($responsible !== '') {
            $report->warning($sheet->name, $r, "Hay responsables («{$responsible}») sin estrategia; no se importan.");
        }

        if (! $hasContent && $strategyText === '') {
            $report->warning($sheet->name, $r, "{$student->full_name}: la fila no trae información para importar.");
        }

        $report->rowsImported++;
        $report->addPreview([
            'hoja' => $sheet->name,
            'fila' => $r,
            'en_el_archivo' => $studentName,
            'estudiante' => $student->full_name,
            'grupo' => $group->code,
            'seguimiento' => $followUp->shortLabel(),
            'registros' => $added,
        ]);
    }

    private function groupCodeFromTitle(SheetData $sheet): ?string
    {
        foreach ($sheet->rows as $r => $cols) {
            if ($r > 4) {
                break;
            }

            foreach ($cols as $text) {
                if (str_contains(NameMatcher::normalize($text), 'seguimiento y acuerdos')) {
                    $parts = preg_split('/\s[–—-]\s?|\s?[–—-]\s/u', $text) ?: [];
                    $last = trim((string) end($parts));

                    return count($parts) > 1 && $last !== '' ? $last : null;
                }
            }
        }

        return null;
    }

    /** @return array{0: int|null, 1: int|null} */
    private function followUpNumbers(SheetData $sheet): array
    {
        $period = self::parseNumber($sheet->valueNextTo(['periodo']));
        $number = self::parseNumber($sheet->valueNextTo(['seguimiento']));

        // Respaldo: el nombre de la hoja ("mitad periodo I", "final periodo II").
        $sheetName = NameMatcher::normalize($sheet->name);
        if ($period === null && preg_match('/periodo\s+([ivx\d]+)/', $sheetName, $m)) {
            $period = self::parseNumber($m[1]);
        }
        if ($number === null) {
            $number = match (true) {
                str_contains($sheetName, 'mitad') => 1,
                str_contains($sheetName, 'final') || str_contains($sheetName, 'cierre') || str_contains($sheetName, 'fin ') => 2,
                default => null,
            };
        }

        return [$period, $number];
    }

    public static function parseNumber(?string $text): ?int
    {
        if ($text === null) {
            return null;
        }

        $text = NameMatcher::normalize($text);

        if (preg_match('/\d+/', $text, $m)) {
            return (int) $m[0];
        }

        $words = ['primero' => 1, 'primer' => 1, 'segundo' => 2, 'tercero' => 3, 'tercer' => 3, 'cuarto' => 4];
        foreach ($words as $word => $n) {
            if (str_contains($text, $word)) {
                return $n;
            }
        }

        if (preg_match('/\b(iv|v?i{1,3}|v)\b/', $text, $m)) {
            return ['i' => 1, 'ii' => 2, 'iii' => 3, 'iv' => 4, 'v' => 5, 'vi' => 6, 'vii' => 7, 'viii' => 8][$m[1]];
        }

        return null;
    }

    private function resolveFollowUp(int $period, int $number, ImportReport $report, string $sheet): FollowUp
    {
        $followUp = FollowUp::where('school_year_id', $this->schoolYear->id)
            ->where('period', $period)
            ->where('number', $number)
            ->first();

        if ($followUp !== null) {
            return $followUp;
        }

        $followUp = FollowUp::create(['school_year_id' => $this->schoolYear->id, 'period' => $period, 'number' => $number]);
        $report->remember(['type' => 'follow_up_created', 'id' => $followUp->id]);
        $report->warning($sheet, 3, "Se creará el seguimiento P{$period}·S{$number} en {$this->schoolYear->name}.");

        return $followUp;
    }

    private function applyMeetingDate(SheetData $sheet, Group $group, FollowUp $followUp, ImportReport $report): void
    {
        $rawDate = $sheet->valueNextTo(['fecha']);
        $date = null;

        if ($rawDate !== null) {
            foreach (['Y-m-d', 'd/m/Y', 'd-m-Y', 'd/m/y', 'Y/m/d'] as $format) {
                try {
                    $date = CarbonImmutable::createFromFormat('!'.$format, substr($rawDate, 0, 10));
                    if ($date !== null) {
                        break;
                    }
                } catch (Throwable) {
                    $date = null;
                }
            }

            if ($date === null) {
                $report->warning($sheet->name, 3, "No se entendió la fecha «{$rawDate}»; la reunión queda sin fecha.");
            }
        }

        $meeting = GroupMeeting::where('group_id', $group->id)->where('follow_up_id', $followUp->id)->first();

        if ($meeting === null) {
            $meeting = GroupMeeting::create(['group_id' => $group->id, 'follow_up_id' => $followUp->id, 'held_on' => $date]);
            $report->remember(['type' => 'meeting_created', 'id' => $meeting->id]);
            $report->count('Reuniones de grupo');
        } elseif ($meeting->held_on === null && $date !== null) {
            $meeting->update(['held_on' => $date]);
            $report->remember(['type' => 'meeting_date_set', 'id' => $meeting->id]);
        }
    }

    private function applyDirector(SheetData $sheet, Group $group, ImportReport $report): void
    {
        $directorName = $sheet->valueNextTo(['director a de grupo', 'director de grupo', 'directora de grupo', 'director a', 'director']);

        if ($directorName === null) {
            return;
        }

        $result = NameMatcher::best($directorName, User::where('active', true)->get(), fn (User $u) => $u->name);
        /** @var User|null $user */
        $user = $result['score'] >= NameMatcher::AUTO && ! $result['ambiguous'] ? $result['match'] : null;

        if ($user === null) {
            $report->warning($sheet->name, 3, "El director(a) «{$directorName}» no coincide con ningún usuario; asígnalo en Administración · Asignaciones.");

            return;
        }

        if ($group->director_id === null) {
            $report->remember(['type' => 'director_set', 'id' => $group->id, 'previous' => null]);
            $group->update(['director_id' => $user->id]);
            $report->warning($sheet->name, 3, "{$user->name} queda como director(a) de {$group->code}.");
        } elseif ($group->director_id !== $user->id) {
            $current = User::whereKey($group->director_id)->value('name');
            $report->warning($sheet->name, 3, "El archivo dice que el director(a) de {$group->code} es {$user->name}, pero en la aplicación es {$current}; no se cambia.");
        }
    }

    private function failAllRows(SheetData $sheet, int $headerRow, ImportReport $report): void
    {
        $n = count(array_filter(array_keys($sheet->rows), fn (int $r) => $r > $headerRow));
        $report->rowsRead += $n;
        $report->rowsFailed += $n;
    }
}
