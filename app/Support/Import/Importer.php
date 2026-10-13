<?php

namespace App\Support\Import;

use App\Models\ImportBatch;
use App\Models\SchoolYear;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * Base de todos los importadores.
 *
 * La simulación y la importación real ejecutan exactamente el mismo código dentro de una
 * transacción: la simulación la revierte al final (no queda nada guardado) y la importación
 * la confirma. Así lo que el ADMIN ve en la simulación es, fila por fila, lo que se guardará.
 */
abstract class Importer
{
    /** @param array<string, mixed> $options */
    public function __construct(
        protected SchoolYear $schoolYear,
        protected User $user,
        protected array $options = [],
    ) {}

    /** @param list<SheetData> $sheets */
    abstract protected function process(array $sheets, ImportReport $report): void;

    protected ?ImportBatch $batch = null;

    protected string $fileName = '';

    /** Simula la importación: lee, valida y prueba a guardar, y deshace todo al final. */
    public function simulate(string $path, string $fileName): ImportReport
    {
        return $this->execute($path, $fileName, null);
    }

    /** Importa de verdad, marcando cada registro con el lote para poder revertirlo completo. */
    public function import(string $path, string $fileName, ImportBatch $batch): ImportReport
    {
        return $this->execute($path, $fileName, $batch);
    }

    private function execute(string $path, string $fileName, ?ImportBatch $batch): ImportReport
    {
        $this->batch = $batch;
        $this->fileName = $fileName;
        $report = new ImportReport;

        try {
            $sheets = SheetData::load($path);
        } catch (Throwable $e) {
            $report->error(null, null, 'No se pudo leer el archivo: '.$e->getMessage());

            return $report;
        }

        DB::beginTransaction();

        try {
            $this->process($sheets, $report);
        } catch (Throwable $e) {
            DB::rollBack();
            throw $e;
        }

        if ($batch !== null) {
            DB::commit();
        } else {
            DB::rollBack();
        }

        return $report;
    }

    /** Deja en el registro de dónde vino (lote, archivo y fila), para auditoría y reversión. */
    protected function stamp(Model $model, int $row): void
    {
        $model->forceFill([
            'import_batch_id' => $this->batch?->id,
            'source_file' => mb_substr($this->fileName, 0, 255),
            'source_row' => $row,
        ]);
    }

    /**
     * Encuentra la columna de cada concepto según el texto del encabezado (no su posición).
     *
     * @param  array<int, string>  $headerCells
     * @param  array<string, list<string>>  $aliases  concepto => encabezados normalizados aceptados
     * @return array{columns: array<string, int>, unknown: list<string>}
     */
    protected function mapHeaders(array $headerCells, array $aliases): array
    {
        $columns = [];
        $unknown = [];

        foreach ($headerCells as $col => $text) {
            $normalized = NameMatcher::normalize($text);
            $found = null;

            foreach ($aliases as $concept => $names) {
                if (in_array($normalized, $names, true)) {
                    $found = $concept;
                    break;
                }
            }

            if ($found !== null && ! isset($columns[$found])) {
                $columns[$found] = $col;
            } elseif ($found === null) {
                $unknown[] = $text;
            }
        }

        return ['columns' => $columns, 'unknown' => $unknown];
    }

    /**
     * Primera fila (en las primeras 30) que contiene alguno de los encabezados obligatorios.
     *
     * @param  list<string>  $required  encabezados normalizados
     */
    protected function findHeaderRow(SheetData $sheet, array $required): ?int
    {
        foreach ($sheet->rows as $r => $cols) {
            if ($r > 30) {
                return null;
            }

            foreach ($cols as $text) {
                if (in_array(NameMatcher::normalize($text), $required, true)) {
                    return $r;
                }
            }
        }

        return null;
    }

    protected static function yes(?string $value): bool
    {
        return in_array(NameMatcher::normalize((string) $value), ['si', 's', 'x', '1', 'true', 'verdadero', 'yes'], true);
    }
}
