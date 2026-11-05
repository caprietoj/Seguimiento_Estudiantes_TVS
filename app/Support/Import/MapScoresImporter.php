<?php

namespace App\Support\Import;

use App\Enums\MapPerformanceLevel;
use App\Models\MapScore;
use App\Models\Setting;

/**
 * Plantilla "Calificaciones MAP Growth (NWEA)": importa los resultados de pruebas estandarizadas MAP.
 */
class MapScoresImporter extends Importer
{
    use ResolvesRecords;

    /** Encabezados de la plantilla descargable, en orden. */
    public const TEMPLATE = ['Código institucional', 'Nombre completo', 'Grupo', 'Área evaluada', 'Periodo', 'Puntaje RIT', 'Percentil', 'Nivel de desempeño'];

    /** @var array<string, list<string>> */
    private const HEADERS = [
        'code' => ['codigo institucional', 'codigo', 'documento', 'id'],
        'name' => ['nombre completo', 'nombre', 'estudiante', 'nombres y apellidos', 'apellidos y nombres'],
        'group' => ['grupo', 'curso'],
        'subject' => ['area evaluada', 'area', 'asignatura', 'materia', 'subject', 'test'],
        'period' => ['periodo', 'period', 'corte', 'term', 'aplicacion'],
        'rit' => ['puntaje rit', 'rit', 'rit score', 'score'],
        'percentile' => ['percentil', 'percentile', '%ile', 'pct'],
        'level' => ['nivel de desempeño', 'nivel', 'quintil', 'desempeño', 'performance level'],
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

        foreach (['name', 'group', 'subject', 'period'] as $req) {
            if (! isset($columns[$req])) {
                $report->error($sheet->name, $headerRow, "Falta la columna requerida «{$req}».");

                return;
            }
        }

        $maxPeriods = (int) Setting::get('map_periodos', 2);

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
            $ritRaw = isset($columns['rit']) ? trim($cells[$columns['rit']] ?? '') : '';
            $percentileRaw = isset($columns['percentile']) ? trim($cells[$columns['percentile']] ?? '') : '';
            $levelRaw = isset($columns['level']) ? trim($cells[$columns['level']] ?? '') : '';

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

            // Identificar área MAP
            $subject = $this->resolveMapSubject($subjectRaw);
            if ($subject === null) {
                $configuredNames = implode(', ', array_column(Setting::mapAreas(true), 'name'));
                $report->error($sheet->name, $r, "Área evaluada «{$subjectRaw}» no reconocida. Áreas configuradas: {$configuredNames}.");
                $report->rowsFailed++;

                continue;
            }

            $period = (int) preg_replace('/\D/', '', $periodRaw);
            if ($period < 1 || $period > $maxPeriods) {
                $report->error($sheet->name, $r, "Periodo «{$periodRaw}» inválido. Debe ser 1 o 2.");
                $report->rowsFailed++;

                continue;
            }

            $rit = $ritRaw !== '' ? (int) $ritRaw : null;
            if ($rit !== null && ($rit < 100 || $rit > 350)) {
                $report->error($sheet->name, $r, "Puntaje RIT «{$ritRaw}» fuera de rango (100–350).");
                $report->rowsFailed++;

                continue;
            }

            $percentile = $percentileRaw !== '' ? (int) $percentileRaw : null;
            if ($percentile !== null && ($percentile < 1 || $percentile > 99)) {
                $report->error($sheet->name, $r, "Percentil «{$percentileRaw}» fuera de rango (1–99).");
                $report->rowsFailed++;

                continue;
            }

            $level = $this->resolveMapLevel($levelRaw, $percentile);

            if ($rit === null && $percentile === null && $level === null) {
                $report->warning($sheet->name, $r, "La fila no contiene puntajes válidos para {$student->full_name}.");
                $report->rowsFailed++;

                continue;
            }

            MapScore::updateOrCreate(
                [
                    'student_id' => $student->id,
                    'school_year_id' => $this->schoolYear->id,
                    'subject' => $subject,
                    'period' => $period,
                ],
                [
                    'rit_score' => $rit,
                    'percentile' => $percentile,
                    'performance_level' => $level,
                    'recorded_by' => $this->user->id,
                    'import_batch_id' => $this->batch?->id,
                    'source_file' => $this->fileName,
                    'source_row' => $r,
                ]
            );

            $report->rowsImported++;
            $report->count('Calificaciones MAP registradas');
            $report->addPreview([
                'Estudiante' => $student->full_name,
                'Grupo' => $group->code,
                'Área' => $this->getAreaLabel($subject),
                'Periodo' => "Periodo {$period}",
                'RIT' => $rit ?? '–',
                'Percentil' => $percentile !== null ? "P{$percentile}" : '–',
                'Nivel' => $level?->label() ?? '–',
            ]);
        }
    }

    private function resolveMapSubject(string $raw): ?string
    {
        $norm = NameMatcher::normalize($raw);
        $areas = Setting::mapAreas(false);

        // 1. Coincidencia directa con clave o nombre de las áreas configuradas
        foreach ($areas as $area) {
            if ($norm === NameMatcher::normalize($area['key']) || $norm === NameMatcher::normalize($area['name'])) {
                return $area['key'];
            }
        }

        // 2. Coincidencia semántica con áreas estándar NWEA
        foreach ($areas as $area) {
            $key = $area['key'];
            $name = $area['name'];
            if (($key === 'math' || str_contains($name, 'Matemát') || str_contains($name, 'Math'))
                && (str_contains($norm, 'matemat') || str_contains($norm, 'math'))) {
                return $key;
            }
            if (($key === 'reading' || str_contains($name, 'Lectur') || str_contains($name, 'Read'))
                && (str_contains($norm, 'lectur') || str_contains($norm, 'read') || str_contains($norm, 'reading'))) {
                return $key;
            }
            if (($key === 'language' || str_contains($name, 'Lengua') || str_contains($name, 'Language'))
                && (str_contains($norm, 'lengua') || str_contains($norm, 'languag') || str_contains($norm, 'uso'))) {
                return $key;
            }
            if (($key === 'science' || str_contains($name, 'Cienc') || str_contains($name, 'Scien'))
                && (str_contains($norm, 'cienc') || str_contains($norm, 'scien'))) {
                return $key;
            }
        }

        // 3. Coincidencia parcial si el nombre contiene el término o viceversa
        foreach ($areas as $area) {
            $areaNorm = NameMatcher::normalize($area['name']);
            if (str_contains($areaNorm, $norm) || str_contains($norm, $areaNorm)) {
                return $area['key'];
            }
        }

        return null;
    }

    private function getAreaLabel(string $key): string
    {
        $areas = Setting::mapAreas(false);
        foreach ($areas as $area) {
            if ($area['key'] === $key) {
                return $area['name'];
            }
        }

        return ucfirst($key);
    }

    private function resolveMapLevel(string $raw, ?int $percentile): ?MapPerformanceLevel
    {
        $norm = NameMatcher::normalize($raw);

        if ($norm !== '') {
            if (str_contains($norm, 'alto') && ! str_contains($norm, 'promedio')) {
                return MapPerformanceLevel::High;
            }
            if (str_contains($norm, 'promedio alto') || str_contains($norm, 'high avg') || str_contains($norm, 'high average')) {
                return MapPerformanceLevel::HighAverage;
            }
            if ($norm === 'promedio' || $norm === 'avg' || $norm === 'average') {
                return MapPerformanceLevel::Average;
            }
            if (str_contains($norm, 'promedio bajo') || str_contains($norm, 'low avg') || str_contains($norm, 'low average')) {
                return MapPerformanceLevel::LowAverage;
            }
            if (str_contains($norm, 'bajo') || $norm === 'low') {
                return MapPerformanceLevel::Low;
            }

            $try = MapPerformanceLevel::tryFrom($raw);
            if ($try !== null) {
                return $try;
            }
        }

        // Si no vino nivel explícito pero hay percentil, deducirlo con cortes
        if ($percentile !== null) {
            $corteBajo = (int) Setting::get('map_corte_bajo', 21);
            $cortePromBajo = (int) Setting::get('map_corte_promedio_bajo', 40);
            $corteProm = (int) Setting::get('map_corte_promedio', 60);
            $cortePromAlto = (int) Setting::get('map_corte_promedio_alto', 80);

            return match (true) {
                $percentile < $corteBajo => MapPerformanceLevel::Low,
                $percentile <= $cortePromBajo => MapPerformanceLevel::LowAverage,
                $percentile <= $corteProm => MapPerformanceLevel::Average,
                $percentile <= $cortePromAlto => MapPerformanceLevel::HighAverage,
                default => MapPerformanceLevel::High,
            };
        }

        return null;
    }
}
