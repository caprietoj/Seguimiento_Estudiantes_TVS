<?php

namespace App\Support\Import;

/**
 * Resultado de una simulación o importación. Se guarda tal cual en
 * `import_batches.error_report` y es lo que muestra la pantalla de importación.
 */
class ImportReport
{
    public int $rowsRead = 0;

    public int $rowsImported = 0;

    public int $rowsFailed = 0;

    /** @var array<string, int> Conteo de lo que se crea, por concepto ("Aportes", "Estrategias"…). */
    public array $created = [];

    /** @var list<array{sheet: ?string, row: ?int, message: string}> */
    public array $errors = [];

    /** @var list<array{sheet: ?string, row: ?int, message: string}> */
    public array $warnings = [];

    /** @var array<int, string> Fotos por descargar después de importar: id del estudiante => URL. */
    public array $photos = [];

    /** @var list<array<string, string|int|null>> Vista previa de lo que entra (máx. 200 filas). */
    public array $preview = [];

    /**
     * Cambios que no quedan marcados con `import_batch_id` y que la reversión debe deshacer.
     *
     * @var list<array{type: string, id: int, previous?: int|null}>
     */
    public array $undo = [];

    public function error(?string $sheet, ?int $row, string $message): void
    {
        $this->errors[] = ['sheet' => $sheet, 'row' => $row, 'message' => $message];
    }

    public function warning(?string $sheet, ?int $row, string $message): void
    {
        $this->warnings[] = ['sheet' => $sheet, 'row' => $row, 'message' => $message];
    }

    public function count(string $label, int $n = 1): void
    {
        $this->created[$label] = ($this->created[$label] ?? 0) + $n;
    }

    /** @param array<string, string|int|null> $row */
    public function addPreview(array $row): void
    {
        if (count($this->preview) < 200) {
            $this->preview[] = $row;
        }
    }

    /** @param array{type: string, id: int, previous?: int|null} $change */
    public function remember(array $change): void
    {
        $this->undo[] = $change;
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'rows_read' => $this->rowsRead,
            'rows_imported' => $this->rowsImported,
            'rows_failed' => $this->rowsFailed,
            'created' => $this->created,
            'errors' => array_slice($this->errors, 0, 500),
            'errors_total' => count($this->errors),
            'warnings' => array_slice($this->warnings, 0, 500),
            'warnings_total' => count($this->warnings),
            'preview' => $this->preview,
            'undo' => $this->undo,
            'photos' => $this->photos,
        ];
    }
}
