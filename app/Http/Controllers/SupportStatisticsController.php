<?php

namespace App\Http\Controllers;

use App\Models\SchoolYear;
use App\Models\Section;
use App\Support\Scope;
use App\Support\SupportStatistics;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Chart\Chart;
use PhpOffice\PhpSpreadsheet\Chart\DataSeries;
use PhpOffice\PhpSpreadsheet\Chart\DataSeriesValues;
use PhpOffice\PhpSpreadsheet\Chart\Legend;
use PhpOffice\PhpSpreadsheet\Chart\PlotArea;
use PhpOffice\PhpSpreadsheet\Chart\Title;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\PageSetup;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Dpto. de Apoyo · Estadística general: la hoja «ESTADÍSTICA GRAL» del informe de gestión,
 * calculada con las notas de las áreas en la reunión de grupo, con descarga en Excel.
 */
class SupportStatisticsController extends Controller
{
    /** Filtro de tiempo: año completo, semestre (I = P1, II = P2 y P3) o un periodo. */
    private const SCOPES = [
        'todo' => 'Año completo',
        's1' => 'I semestre',
        's2' => 'II semestre',
        'p1' => 'Periodo 1',
        'p2' => 'Periodo 2',
        'p3' => 'Periodo 3',
    ];

    /** Los mismos colores por área que la página (validados para daltonismo). */
    private const AREA_COLORS = ['support_psychology' => '2A78D6', 'support_ot' => 'EB6834', 'support_speech' => '1BAF7A', 'support_neuro' => 'EDA100'];

    public function index(Request $request): Response
    {
        [$year, $scope, $sectionId] = $this->filters($request);

        return Inertia::render('apoyo/estadistica', [
            ...$this->filterProps($year, $scope, $sectionId),
            'stats' => SupportStatistics::build($request->user(), $year, $scope, $sectionId),
        ]);
    }

    public function excel(Request $request): StreamedResponse
    {
        [$year, $scope, $sectionId] = $this->filters($request);
        $stats = SupportStatistics::build($request->user(), $year, $scope, $sectionId);
        $period = self::SCOPES[$scope];
        $section = $sectionId ? Section::whereKey($sectionId)->value('name') : null;

        $book = new Spreadsheet;
        $sheet = $book->getActiveSheet();
        $sheet->setTitle('ESTADÍSTICA GRAL');
        $sheet->setCellValue('A1', "ESTADÍSTICA GENERAL · DEPARTAMENTO DE APOYO · {$year->name} · {$period}".($section ? " · {$section}" : ''));
        $sheet->getStyle('A1')->getFont()->setBold(true)->setSize(13);

        $areas = $stats['areas'];
        $lastCol = $this->col(count($areas) + 2);
        $row = 3;

        foreach ($stats['indicators'] as $i => $indicator) {
            $sheet->setCellValue("A{$row}", ($i + 1).'. '.$indicator['title']);
            $sheet->mergeCells("A{$row}:{$lastCol}{$row}");
            $sheet->getStyle("A{$row}")->getFont()->setBold(true);
            $row++;

            $header = ['GRADO / GRUPO', ...array_map(fn ($a) => mb_strtoupper($a['label']), $areas), 'TOTAL'];
            $sheet->fromArray($header, null, "A{$row}");
            $this->headerStyle($sheet, "A{$row}:{$lastCol}{$row}");
            $headerRow = $row;
            $row++;
            $firstData = $row;

            $lastData = $firstData - 1;
            foreach ($indicator['rows'] as $r) {
                $sheet->fromArray([$r['label'], ...array_map(fn ($a) => $r['values'][$a['value']], $areas), $r['total']], null, "A{$row}", true);
                // La gráfica es por curso: la fila «Todo el colegio» (sin grupo) queda fuera.
                if ($r['group_id'] !== null) {
                    $lastData = $row;
                }
                $row++;
            }

            $sheet->fromArray(['TOTAL', ...array_map(fn ($a) => $indicator['totals'][$a['value']], $areas), $indicator['total']], null, "A{$row}", true);
            $sheet->getStyle("A{$row}:{$lastCol}{$row}")->getFont()->setBold(true);
            $row++;

            foreach ($indicator['sections'] as $s) {
                $sheet->fromArray(['  '.$s['section'], ...array_map(fn ($a) => $s['values'][$a['value']], $areas), $s['total']], null, "A{$row}", true);
                $sheet->getStyle("A{$row}:{$lastCol}{$row}")->getFont()->setItalic(true);
                $row++;
            }

            if ($lastData >= $firstData) {
                $this->addChart($sheet, $indicator['title'], $areas, $headerRow, $firstData, $lastData, $this->col(count($areas) + 4), $headerRow - 1, ($i + 1).'. '.mb_strtoupper($indicator['title']));
            }

            $row = max($row + 2, $headerRow + 22);
        }

        $sheet->getPageSetup()->setOrientation(PageSetup::ORIENTATION_LANDSCAPE)->setFitToWidth(1)->setFitToHeight(0);
        $sheet->getColumnDimension('A')->setWidth(30);
        foreach (range(2, count($areas) + 2) as $c) {
            $sheet->getColumnDimension($this->col($c))->setWidth(20);
        }

        $this->casesSheet($book, $stats);
        $book->setActiveSheetIndex(0);

        $fileName = 'Estadistica general Dpto de Apoyo - '.$year->name.' - '.$period.'.xlsx';

        return response()->streamDownload(function () use ($book) {
            $writer = new Xlsx($book);
            $writer->setIncludeCharts(true);
            $writer->save('php://output');
        }, $fileName, ['Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet']);
    }

    /** @return array{0: SchoolYear, 1: string, 2: int|null} */
    private function filters(Request $request): array
    {
        $year = SchoolYear::find($request->integer('anio')) ?? SchoolYear::where('is_active', true)->first() ?? SchoolYear::orderByDesc('starts_on')->firstOrFail();
        $scope = array_key_exists((string) $request->query('periodo'), self::SCOPES) ? (string) $request->query('periodo') : 'todo';
        $sectionId = $request->integer('seccion') > 0 ? $request->integer('seccion') : null;

        // EMC con sección asignada ve solo su sección.
        $user = $request->user();
        if ($user->emc_section_id !== null && ! Scope::isAdmin($user) && ! Scope::isPsicologia($user)) {
            $sectionId = $user->emc_section_id;
        }

        return [$year, $scope, $sectionId];
    }

    /** @return array<string, mixed> */
    private function filterProps(SchoolYear $year, string $scope, ?int $sectionId): array
    {
        return [
            'years' => SchoolYear::orderByDesc('starts_on')->get(['id', 'name'])->map(fn (SchoolYear $y) => ['id' => $y->id, 'name' => $y->name])->values(),
            'sections' => Section::orderBy('id')->get(['id', 'name'])->map(fn (Section $s) => ['id' => $s->id, 'name' => $s->name])->values(),
            'scopes' => collect(self::SCOPES)->map(fn (string $label, string $value) => ['value' => $value, 'label' => $label])->values(),
            'filters' => [
                'anio' => $year->id,
                'periodo' => $scope,
                'seccion' => $sectionId === null ? 'todas' : (string) $sectionId,
            ],
            'yearName' => $year->name,
        ];
    }

    /** @param list<array{value: string, label: string, short: string}> $areas */
    private function addChart(Worksheet $sheet, string $title, array $areas, int $headerRow, int $first, int $last, string $anchorCol, int $anchorRow, string $chartTitle): void
    {
        $name = "'".str_replace("'", "''", $sheet->getTitle())."'";
        $labels = [];
        $values = [];

        foreach ($areas as $i => $area) {
            $c = $this->col($i + 2);
            $labels[] = new DataSeriesValues(DataSeriesValues::DATASERIES_TYPE_STRING, "{$name}!\${$c}\${$headerRow}", null, 1);
            $values[] = new DataSeriesValues(DataSeriesValues::DATASERIES_TYPE_NUMBER, "{$name}!\${$c}\${$first}:\${$c}\${$last}", null, $last - $first + 1, [], null, self::AREA_COLORS[$area['value']] ?? null);
        }

        $categories = [new DataSeriesValues(DataSeriesValues::DATASERIES_TYPE_STRING, "{$name}!\$A\${$first}:\$A\${$last}", null, $last - $first + 1)];

        $series = new DataSeries(DataSeries::TYPE_BARCHART, DataSeries::GROUPING_CLUSTERED, range(0, count($values) - 1), $labels, $categories, $values);
        $series->setPlotDirection(DataSeries::DIRECTION_COL);

        $chart = new Chart('chart_'.md5($title), new Title($chartTitle), new Legend(Legend::POSITION_BOTTOM, null, false), new PlotArea(null, [$series]));
        $chart->setTopLeftPosition($anchorCol.max(1, $anchorRow));
        $chart->setBottomRightPosition($this->col((int) $this->colIndex($anchorCol) + 9).(max(1, $anchorRow) + 20));
        $sheet->addChart($chart);
    }

    /** @param array<string, mixed> $stats */
    private function casesSheet(Spreadsheet $book, array $stats): void
    {
        $sheet = $book->createSheet();
        $sheet->setTitle('Estudiantes en seguimiento');
        $sheet->fromArray(['GRUPO', 'ESTUDIANTE', 'ÁREAS'], null, 'A1');
        $this->headerStyle($sheet, 'A1:C1');

        $row = 2;
        foreach ($stats['cases'] as $case) {
            $sheet->fromArray([$case['group'], $case['name'], implode(', ', $case['areas'])], null, "A{$row}");
            $row++;
        }

        $sheet->getColumnDimension('A')->setWidth(12);
        $sheet->getColumnDimension('B')->setWidth(40);
        $sheet->getColumnDimension('C')->setWidth(36);
    }

    private function headerStyle(Worksheet $sheet, string $range): void
    {
        $sheet->getStyle($range)->getFont()->setBold(true)->getColor()->setRGB('FFFFFF');
        $sheet->getStyle($range)->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('364E76');
    }

    private function col(int $index): string
    {
        return Coordinate::stringFromColumnIndex($index);
    }

    private function colIndex(string $col): int
    {
        return Coordinate::columnIndexFromString($col);
    }
}
