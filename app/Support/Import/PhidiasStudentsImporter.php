<?php

namespace App\Support\Import;

use App\Models\Enrollment;
use App\Models\Group;
use App\Models\Student;

/**
 * Importa el listado de estudiantes que exporta Phidias ("exported.xls", una tabla HTML con
 * ~56 columnas). De todo lo que trae, solo se usa lo que la aplicación guarda:
 *
 *  - Código                     → código institucional (la llave para reconocer al estudiante).
 *  - Estudiante ("APELLIDOS, NOMBRES") o Nombre + Apellidos → nombre completo "Nombres Apellidos".
 *  - Sección ("Sexto 6A")       → grupo 6A del año escolar elegido (se crea si no existe,
 *                                 con el grado del texto y la sección del Continuo IB).
 *  - Estado                     → activo / inactivo.
 *  - URL de la foto             → foto del estudiante, si todavía no tiene (en segundo plano).
 *
 * Phidias es la fuente oficial de la matrícula: si el estudiante está en otro grupo ese año,
 * se cambia (la reversión lo devuelve).
 */
class PhidiasStudentsImporter extends Importer
{
    use ResolvesRecords;

    /** @var array<string, list<string>> */
    private const HEADERS = [
        'course' => ['seccion', 'curso', 'grado'],
        'student' => ['estudiante'],
        'first_names' => ['nombre', 'nombres'],
        'surname1' => ['apellido 1', 'primer apellido', 'apellidos'],
        'surname2' => ['apellido 2', 'segundo apellido'],
        'code' => ['codigo'],
        'status' => ['estado'],
        'photo' => ['url de la foto', 'foto'],
    ];

    /** Grado (valor del catálogo `grade_levels`) por la palabra con que Phidias nombra el curso. */
    private const GRADE_WORDS = [
        'prejardin' => 0, 'prekinder' => 0, 'pre kinder' => 0,
        'jardin' => 1, 'kinder' => 1,
        'transicion' => 2, 'preescolar' => 2,
        'primero' => 3, 'segundo' => 4, 'tercero' => 5, 'cuarto' => 6, 'quinto' => 7,
        'sexto' => 8, 'septimo' => 9, 'octavo' => 10, 'noveno' => 11, 'decimo' => 12,
        'undecimo' => 13, 'once' => 13, 'onceavo' => 13,
    ];

    /** Partículas que van en minúscula dentro de un nombre propio. */
    private const PARTICLES = ['de', 'del', 'la', 'las', 'los', 'y', 'e', 'da', 'van', 'von'];

    /** Hosts desde los que se aceptan fotos (evita que el archivo haga pedir URLs internas). */
    public const PHOTO_HOSTS = ['amazonaws.com', 'phidias.co', 'phidias.com'];

    protected function process(array $sheets, ImportReport $report): void
    {
        $sheet = $sheets[0] ?? null;
        $headerRow = $sheet ? $this->findHeaderRow($sheet, self::HEADERS['code']) : null;

        if ($sheet === null || $headerRow === null) {
            $report->error(null, null, 'El archivo no tiene la columna «Código». Exporta el listado de estudiantes desde Phidias incluyendo Código, Estudiante y Sección.');

            return;
        }

        $columns = $this->mapHeaders($sheet->rows[$headerRow], self::HEADERS)['columns'];

        foreach (['course' => 'Sección', 'code' => 'Código'] as $concept => $label) {
            if (! isset($columns[$concept])) {
                $report->error($sheet->name, $headerRow, "Falta la columna «{$label}» en el archivo de Phidias.");

                return;
            }
        }

        if (! isset($columns['student']) && ! isset($columns['first_names'])) {
            $report->error($sheet->name, $headerRow, 'Falta la columna «Estudiante» (o «Nombre») en el archivo de Phidias.');

            return;
        }

        $downloadPhotos = (bool) ($this->options['download_photos'] ?? true);
        $photos = [];
        $seenCodes = [];
        /** @var array<int, list<int>> $studentsByGroup */
        $studentsByGroup = [];

        foreach ($sheet->rows as $r => $cells) {
            if ($r <= $headerRow) {
                continue;
            }

            $get = fn (string $concept): string => isset($columns[$concept]) ? trim($cells[$columns[$concept]] ?? '') : '';
            $report->rowsRead++;

            $code = $get('code');
            $name = $this->fullName($get('student'), $get('first_names'), $get('surname1'), $get('surname2'));

            if ($code === '' || $name === '') {
                $report->error($sheet->name, $r, 'Fila sin código o sin nombre de estudiante.');
                $report->rowsFailed++;

                continue;
            }

            if (isset($seenCodes[$code])) {
                $report->error($sheet->name, $r, "El código {$code} está repetido (ya aparece en la fila {$seenCodes[$code]}).");
                $report->rowsFailed++;

                continue;
            }
            $seenCodes[$code] = $r;

            [$grade, $groupCode] = $this->parseCourse($get('course'));

            if ($groupCode === null) {
                $report->error($sheet->name, $r, "No se entendió el curso «{$get('course')}» de {$name}.");
                $report->rowsFailed++;

                continue;
            }

            $active = $this->isActive($get('status'));
            $existing = Student::where('institutional_code', $code)->exists();

            if (! $active && ! $existing && $this->findByName($name) === null) {
                $report->warning($sheet->name, $r, "{$name} figura como «{$get('status')}» en Phidias y no está en la aplicación: no se crea.");
                $report->rowsImported++;
                $report->addPreview($this->previewRow($r, $code, $name, $groupCode, $active, 'Omitido (retirado)'));

                continue;
            }

            $group = $this->resolveGroup($groupCode, null, true, $report, $sheet->name, $r, $grade);

            if ($group === null) {
                $report->rowsFailed++;

                continue;
            }

            $previousGroupId = Enrollment::where('school_year_id', $this->schoolYear->id)
                ->whereHas('student', fn ($q) => $q->where('institutional_code', $code))
                ->value('group_id');

            $student = $this->resolveStudent($name, $code, $group, true, $report, $sheet->name, $r);

            if ($student === null) {
                $report->rowsFailed++;

                continue;
            }

            $isNew = $student->wasRecentlyCreated;

            if ($active && ! $this->ensureEnrollment($student, $group, true, $report, $sheet->name, $r)) {
                $report->rowsFailed++;

                continue;
            }

            if ($student->active !== $active) {
                $report->remember(['type' => 'student_active', 'id' => $student->id, 'previous' => $student->active ? 1 : 0]);
                $student->update(['active' => $active]);
                $report->count($active ? 'Estudiantes reactivados' : 'Estudiantes marcados inactivos');
                if (! $active) {
                    $report->warning($sheet->name, $r, "{$student->full_name} figura como «{$get('status')}» en Phidias: queda inactivo(a).");
                }
            }

            if ($active) {
                $studentsByGroup[$group->id][] = $student->id;
            }

            $photoUrl = $get('photo');
            if ($downloadPhotos && $student->photo_path === null && self::isAllowedPhotoUrl($photoUrl)) {
                $photos[$student->id] = $photoUrl;
            }

            $action = match (true) {
                $isNew => 'Nuevo',
                ! $active => 'Inactivo',
                $previousGroupId !== null && $previousGroupId !== $group->id => 'Cambio de grupo',
                $previousGroupId === null => 'Matriculado',
                default => 'Sin cambios',
            };

            $report->rowsImported++;
            $report->addPreview($this->previewRow($r, $code, $name, $group->code, $active, $action, $student->full_name));
        }

        if ($photos !== []) {
            $report->count('Fotos por traer de Phidias', count($photos));
            $report->photos = $photos;
        }

        $this->reportMissing($studentsByGroup, $report, $sheet->name);
    }

    protected function warnsOnNewStudents(): bool
    {
        return false;
    }

    /**
     * Estudiantes que la aplicación tiene matriculados en un grupo del archivo, pero que no
     * aparecen en el archivo (posibles retiros o cambios de curso en Phidias).
     *
     * @param  array<int, list<int>>  $studentsByGroup
     */
    private function reportMissing(array $studentsByGroup, ImportReport $report, string $sheet): void
    {
        foreach ($studentsByGroup as $groupId => $ids) {
            $group = Group::find($groupId);
            if ($group === null) {
                continue;
            }

            $missing = $group->students()->whereNotIn('students.id', $ids)->orderBy('full_name')->pluck('full_name');

            if ($missing->isNotEmpty()) {
                $report->warning($sheet, null, "En la aplicación hay {$missing->count()} estudiante(s) de {$group->code} que no vienen en el archivo de Phidias (no se modifican): ".$missing->implode(', ').'.');
            }
        }
    }

    private function findByName(string $name): ?Student
    {
        $result = NameMatcher::best($name, Student::all(), fn (Student $s) => $s->full_name);

        return $result['score'] >= NameMatcher::AUTO && ! $result['ambiguous'] && $result['match'] instanceof Student ? $result['match'] : null;
    }

    /** @return array<string, string|int|null> */
    private function previewRow(int $row, string $code, string $name, string $group, bool $active, string $action, ?string $inApp = null): array
    {
        return [
            'fila' => $row,
            'codigo' => $code,
            'en_el_archivo' => $name,
            'estudiante' => $inApp ?? '—',
            'grupo' => $group,
            'estado' => $active ? 'Activo' : 'Inactivo',
            'accion' => $action,
        ];
    }

    /**
     * "Sexto 6A" → [6, "6A"]; "Prekinder PKA" → [0, "PKA"]; "10A" → [10, "10A"].
     *
     * @return array{0: int|null, 1: string|null}
     */
    public static function parseCourse(string $course): array
    {
        $normalized = NameMatcher::normalize($course);

        if ($normalized === '') {
            return [null, null];
        }

        $tokens = explode(' ', $normalized);
        $code = mb_strtoupper((string) array_pop($tokens));
        $label = implode(' ', $tokens);

        // Las palabras más largas primero: "undecimo" contiene "decimo".
        $words = self::GRADE_WORDS;
        uksort($words, fn (string $x, string $y) => strlen($y) <=> strlen($x));

        $grade = null;
        foreach ($words as $word => $value) {
            if ($label !== '' && str_contains($label, $word)) {
                $grade = $value;
                break;
            }
        }

        if ($grade === null && preg_match('/^(\d{1,2})/', $code, $m)) {
            $grade = (int) $m[1];
        }

        return [$grade, $code !== '' ? $code : null];
    }

    /** "ANAYA RUIZ, NATALIA" → "Natalia Anaya Ruiz". */
    public function fullName(string $student, string $firstNames, string $surname1, string $surname2): string
    {
        if (str_contains($student, ',')) {
            [$surnames, $names] = array_map('trim', explode(',', $student, 2));
            $raw = trim("{$names} {$surnames}");
        } elseif ($firstNames !== '') {
            $surnames = $surname1;
            if ($surname2 !== '' && ! str_contains(NameMatcher::normalize($surname1), NameMatcher::normalize($surname2))) {
                $surnames .= ' '.$surname2;
            }
            $raw = trim("{$firstNames} {$surnames}");
        } else {
            $raw = $student;
        }

        $raw = trim((string) preg_replace('/\s+/u', ' ', $raw));
        $words = explode(' ', mb_strtolower($raw));

        foreach ($words as $i => $word) {
            $words[$i] = $i > 0 && in_array($word, self::PARTICLES, true) ? $word : mb_convert_case($word, MB_CASE_TITLE);
        }

        return implode(' ', $words);
    }

    private function isActive(string $status): bool
    {
        $status = NameMatcher::normalize($status);

        // Si Phidias no trae el estado, se asume activo (es un listado de matriculados).
        // "nuevo"/"nueva" son estudiantes nuevos del año (activos), no retirados.
        return $status === '' || in_array($status, ['activo', 'activa', 'matriculado', 'matriculada', 'nuevo', 'nueva'], true);
    }

    public static function isAllowedPhotoUrl(string $url): bool
    {
        if (! str_starts_with($url, 'https://')) {
            return false;
        }

        $host = (string) parse_url($url, PHP_URL_HOST);

        foreach (self::PHOTO_HOSTS as $allowed) {
            if ($host === $allowed || str_ends_with($host, '.'.$allowed)) {
                return true;
            }
        }

        return false;
    }
}
