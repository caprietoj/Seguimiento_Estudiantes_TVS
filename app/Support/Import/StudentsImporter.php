<?php

namespace App\Support\Import;

/**
 * Plantilla "Estudiantes y matrículas": crea los estudiantes que no existen y los matricula en
 * su grupo del año escolar elegido. Si el grupo no existe, lo crea (con la columna «Sección»).
 * Si el estudiante ya está en otro grupo ese año, lo cambia (la reversión lo devuelve).
 */
class StudentsImporter extends Importer
{
    use ResolvesRecords;

    /** Encabezados de la plantilla descargable, en orden. */
    public const TEMPLATE = ['Código institucional', 'Nombre completo', 'Grupo', 'Sección', 'Activo'];

    /** @var array<string, list<string>> */
    private const HEADERS = [
        'code' => ['codigo institucional', 'codigo', 'documento', 'id'],
        'name' => ['nombre completo', 'nombre', 'estudiante', 'nombres y apellidos', 'apellidos y nombres'],
        'group' => ['grupo', 'curso'],
        'section' => ['seccion'],
        'active' => ['activo', 'activa', 'estado'],
    ];

    protected function process(array $sheets, ImportReport $report): void
    {
        $sheet = $sheets[0] ?? null;
        $headerRow = $sheet ? $this->findHeaderRow($sheet, self::HEADERS['name']) : null;

        if ($sheet === null || $headerRow === null) {
            $report->error(null, null, 'No se encontró la columna «Nombre completo». Usa la plantilla descargable.');

            return;
        }

        $mapped = $this->mapHeaders($sheet->rows[$headerRow], self::HEADERS);
        $columns = $mapped['columns'];

        if (! isset($columns['group'])) {
            $report->error($sheet->name, $headerRow, 'Falta la columna «Grupo».');

            return;
        }

        $seen = [];

        foreach ($sheet->rows as $r => $cells) {
            if ($r <= $headerRow) {
                continue;
            }

            $report->rowsRead++;
            $name = trim($cells[$columns['name']] ?? '');
            $groupCode = trim($cells[$columns['group']] ?? '');
            $code = isset($columns['code']) ? ($cells[$columns['code']] ?? null) : null;

            if ($name === '') {
                $report->error($sheet->name, $r, 'Falta el nombre del estudiante.');
                $report->rowsFailed++;

                continue;
            }

            $key = $code !== null && trim($code) !== '' ? 'c:'.trim($code) : 'n:'.NameMatcher::key($name);
            if (isset($seen[$key])) {
                $report->error($sheet->name, $r, "«{$name}» está repetido (ya aparece en la fila {$seen[$key]}).");
                $report->rowsFailed++;

                continue;
            }
            $seen[$key] = $r;

            $group = $this->resolveGroup($groupCode, isset($columns['section']) ? ($cells[$columns['section']] ?? null) : null, true, $report, $sheet->name, $r);

            if ($group === null) {
                $report->rowsFailed++;

                continue;
            }

            $student = $this->resolveStudent($name, $code, $group, true, $report, $sheet->name, $r);

            if ($student === null || ! $this->ensureEnrollment($student, $group, true, $report, $sheet->name, $r)) {
                $report->rowsFailed++;

                continue;
            }

            if (isset($columns['active']) && isset($cells[$columns['active']])) {
                $active = self::yes($cells[$columns['active']]) || NameMatcher::normalize($cells[$columns['active']]) === 'activo';
                if ($student->active !== $active && ! $student->wasRecentlyCreated) {
                    $report->warning($sheet->name, $r, "{$student->full_name} figura como ".($active ? 'activo' : 'inactivo').' en el archivo; el estado de estudiantes existentes no se cambia desde la importación.');
                } elseif ($student->active !== $active) {
                    $student->update(['active' => $active]);
                }
            }

            $report->rowsImported++;
            $report->addPreview([
                'fila' => $r,
                'en_el_archivo' => $name,
                'estudiante' => $student->full_name,
                'codigo' => $student->institutional_code,
                'grupo' => $group->code,
            ]);
        }
    }
}
