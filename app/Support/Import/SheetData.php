<?php

namespace App\Support\Import;

use Carbon\CarbonImmutable;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Shared\Date as ExcelDate;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

/**
 * Una hoja del archivo, ya convertida a texto plano: `rows[fila][columna]` (ambos desde 1).
 * Las celdas vacías no aparecen. Las fechas de Excel se guardan como `Y-m-d`.
 */
class SheetData
{
    /**
     * @param  array<int, array<int, string>>  $rows
     * @param  array<int, true>  $bannerRows  Filas con una celda combinada a lo ancho (títulos de sección).
     */
    public function __construct(
        public readonly string $name,
        public readonly array $rows,
        public readonly array $bannerRows,
    ) {}

    /** @return list<SheetData> */
    public static function load(string $path): array
    {
        $reader = IOFactory::createReaderForFile($path);
        $spreadsheet = $reader->load($path);

        $sheets = [];
        foreach ($spreadsheet->getWorksheetIterator() as $worksheet) {
            $sheets[] = self::fromWorksheet($worksheet);
        }

        return $sheets;
    }

    private static function fromWorksheet(Worksheet $sheet): self
    {
        $rows = [];

        foreach ($sheet->getRowIterator() as $row) {
            $cells = $row->getCellIterator();
            $cells->setIterateOnlyExistingCells(true);

            foreach ($cells as $cell) {
                $value = $cell->getCalculatedValue();

                if ($value === null || $value === '') {
                    continue;
                }

                if (is_numeric($value) && ExcelDate::isDateTime($cell)) {
                    $text = CarbonImmutable::instance(ExcelDate::excelToDateTimeObject((float) $value))->format('Y-m-d');
                } elseif (is_bool($value)) {
                    $text = $value ? 'Sí' : 'No';
                } else {
                    $text = trim(str_replace(["\r\n", "\r"], "\n", (string) $value));
                }

                if ($text !== '') {
                    $rows[$row->getRowIndex()][Coordinate::columnIndexFromString($cell->getColumn())] = $text;
                }
            }
        }

        $bannerRows = [];
        foreach ($sheet->getMergeCells() as $range) {
            [[$c1, $r1], [$c2]] = Coordinate::rangeBoundaries($range);
            if ($c1 === 1 && ($c2 - $c1) >= 3) {
                $bannerRows[(int) $r1] = true;
            }
        }

        ksort($rows);

        return new self($sheet->getTitle(), $rows, $bannerRows);
    }

    public function cell(int $row, int $col): ?string
    {
        return $this->rows[$row][$col] ?? null;
    }

    public function lastRow(): int
    {
        return $this->rows === [] ? 0 : (int) array_key_last($this->rows);
    }

    /**
     * Busca un rótulo ("Periodo:", "Fecha:") en las primeras filas y devuelve el primer valor
     * no vacío a su derecha. Si lo siguiente a la derecha es otro rótulo (termina en «:»), el
     * valor está vacío y devuelve null.
     *
     * @param  list<string>  $labels  Rótulos normalizados aceptados.
     */
    public function valueNextTo(array $labels, int $maxRow = 12): ?string
    {
        foreach ($this->rows as $r => $cols) {
            if ($r > $maxRow) {
                break;
            }

            foreach ($cols as $c => $text) {
                $label = rtrim(NameMatcher::normalize($text), ' ');
                if (in_array($label, $labels, true)) {
                    foreach ($cols as $c2 => $text2) {
                        if ($c2 > $c) {
                            return str_ends_with(rtrim($text2), ':') ? null : $text2;
                        }
                    }

                    return null;
                }
            }
        }

        return null;
    }
}
