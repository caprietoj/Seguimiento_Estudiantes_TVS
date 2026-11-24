<?php

namespace App\Support\Import;

use App\Models\Enrollment;
use App\Models\Grade;
use App\Models\Student;
use App\Models\Subject;
use Illuminate\Support\Facades\DB;

/**
 * Calificaciones exportadas de Phidias («data.csv»): una fila por estudiante × asignatura ×
 * trimestre × criterio/indicador (Year, Code, Lastname, Firstname, …, Course, Section, Period,
 * Academic Area, Subject, Indicator…, Value, Grade Name…).
 *
 * Para cada estudiante, asignatura y trimestre:
 *  - la nota es el promedio de los valores de sus criterios/indicadores, en la escala de la
 *    sección (Preescolar/Primaria 1–5, PAI 1–8, DP 1–7) y con el desempeño de Phidias
 *    (`Grade::performanceFor`, rangos en `seguimiento.phidias_escalas`);
 *  - la asignatura queda en `student_subjects` del año: la ficha muestra solo las asignaturas
 *    que el estudiante ve (en el DP cada estudiante elige ~9 de ~20).
 *
 * El estudiante se busca solo por su código institucional (columna Code). No se empareja por
 * nombre. Las áreas que no son asignaturas (Perfil IB, Reporte Cualitativo, Enfoque de
 * aprendizaje) se omiten. Reimportar actualiza las notas y no duplica nada.
 */
class PhidiasGradesImporter extends Importer
{
    /** Encabezados del CSV de Phidias. */
    public const HEADERS = [
        'Year', 'Code', 'Lastname', 'Firstname', 'Gender', 'Category', 'Course', 'Section', 'Period',
        'Academic Area', 'Subject', 'Indicator Number', 'Indicator Category', 'Indicator Name',
        'Value', 'Grade Name', 'Grade Text', 'Result', 'Student', 'Indicator',
    ];

    /** Áreas que Phidias exporta pero no son asignaturas con nota. */
    private const NOT_SUBJECTS = ['perfil ib', 'reporte cualitativo', 'enfoque de aprendizaje'];

    private const PERIODS = ['trimestre i' => 1, 'trimestre ii' => 2, 'trimestre iii' => 3, 'periodo 1' => 1, 'periodo 2' => 2, 'periodo 3' => 3];

    /**
     * El CSV de Phidias no pone comillas: el nombre del indicador puede traer comas y saltos de
     * línea. Se arma cada registro (empieza con el año «2025-2026,») y se toman las 13 primeras
     * columnas desde la izquierda, las 6 últimas desde la derecha y el resto es el indicador.
     */
    protected function loadSheets(string $path): array
    {
        $raw = (string) file_get_contents($path);
        $raw = (string) preg_replace('/^\xEF\xBB\xBF/', '', $raw);
        $firstLine = strtok($raw, "\r\n") ?: '';

        if (! str_starts_with(trim($firstLine), 'Year,') || ! str_contains($firstLine, ',Subject,')) {
            return SheetData::load($path);
        }

        $lines = preg_split('/\r\n|\n|\r/', $raw) ?: [];
        $records = [];
        $current = null;
        $startRow = 0;

        foreach ($lines as $i => $line) {
            if ($i === 0) {
                continue;
            }
            if (preg_match('/^\d{4}\s*[-–]\s*\d{4},/', $line)) {
                if ($current !== null) {
                    $records[$startRow] = $current;
                }
                $current = $line;
                $startRow = $i + 1;
            } elseif ($current !== null && trim($line) !== '') {
                $current .= ' '.trim($line);
            }
        }
        if ($current !== null) {
            $records[$startRow] = $current;
        }

        $rows = [1 => array_combine(range(1, count(self::HEADERS)), self::HEADERS)];
        foreach ($records as $row => $record) {
            $parts = explode(',', $record);
            if (count($parts) < 19) {
                $rows[$row] = [1 => '__malformed__', 2 => $record];

                continue;
            }
            $cells = [
                ...array_slice($parts, 0, 13),
                implode(',', array_slice($parts, 13, count($parts) - 19)),
                ...array_slice($parts, -6),
            ];
            $rows[$row] = array_combine(range(1, count($cells)), array_map('trim', $cells));
        }

        return [new SheetData('data', $rows, [])];
    }

    protected function process(array $sheets, ImportReport $report): void
    {
        $aliases = [
            'year' => ['year', 'ano'], 'code' => ['code', 'codigo'], 'lastname' => ['lastname', 'apellidos'],
            'firstname' => ['firstname', 'nombres'], 'category' => ['category'], 'course' => ['course', 'curso'],
            'section' => ['section', 'seccion', 'grupo'], 'period' => ['period', 'periodo'], 'area' => ['academic area', 'area'],
            'subject' => ['subject', 'asignatura'], 'value' => ['value', 'valor'],
        ];

        $sheet = null;
        $columns = [];
        foreach ($sheets as $candidate) {
            $headerRow = $this->findHeaderRow($candidate, ['subject']);
            if ($headerRow !== null) {
                $columns = $this->mapHeaders($candidate->rows[$headerRow], $aliases)['columns'];
                $sheet = $candidate;
                break;
            }
        }

        $missing = array_diff(['year', 'code', 'category', 'course', 'period', 'area', 'subject', 'value'], array_keys($columns));
        if ($sheet === null || $missing !== []) {
            $report->error(null, null, 'No es la exportación de calificaciones de Phidias: faltan las columnas Year, Code, Category, Course, Period, Academic Area, Subject y Value.');

            return;
        }

        // 1) Agrupar: código × asignatura × trimestre → valores.
        $groups = [];
        $names = [];
        $yearsInFile = [];
        $skippedAreas = [];

        foreach ($sheet->rows as $r => $cells) {
            if ($r === 1) {
                continue;
            }
            if (($cells[1] ?? '') === '__malformed__') {
                $report->error($sheet->name, $r, 'Fila incompleta: no se pudo leer.');

                continue;
            }

            $get = fn (string $k): string => isset($columns[$k]) ? trim($cells[$columns[$k]] ?? '') : '';
            $area = NameMatcher::normalize($get('area'));
            if (in_array($area, self::NOT_SUBJECTS, true)) {
                $skippedAreas[$get('area')] = true;

                continue;
            }

            $period = self::PERIODS[NameMatcher::normalize($get('period'))] ?? null;
            if ($period === null) {
                $report->error($sheet->name, $r, "Periodo «{$get('period')}» no reconocido (se espera Trimestre I, II o III).");

                continue;
            }

            $code = $get('code');
            $subject = self::subjectName($get('subject'));
            if ($code === '' || $subject === '') {
                continue;
            }

            $yearsInFile[str_replace([' ', '–'], ['', '-'], $get('year'))] = true;
            $key = $code.'|'.NameMatcher::normalize($subject).'|'.$period;
            $groups[$key] ??= [
                'code' => $code, 'subject' => $subject, 'period' => $period, 'values' => [],
                'scale' => $this->scaleFor($get('category'), $get('course')), 'section' => $get('section'), 'row' => $r,
            ];
            if ($get('value') !== '' && is_numeric($get('value'))) {
                $groups[$key]['values'][] = (float) $get('value');
            }
            $names[$code] = trim($get('firstname').' '.$get('lastname'));
        }

        $report->rowsRead = count($groups);

        // El archivo debe ser del año que se eligió al importar.
        $wanted = str_replace([' ', '–'], ['', '-'], $this->schoolYear->name);
        $others = array_diff(array_keys($yearsInFile), [$wanted]);
        if ($others !== []) {
            $report->error(null, null, 'El archivo es de '.implode(', ', array_keys($yearsInFile))." pero se eligió el año {$this->schoolYear->name}. Elige el año correcto.");

            return;
        }

        if ($skippedAreas !== []) {
            $report->warning(null, null, 'No son asignaturas y no se importan: '.implode(', ', array_keys($skippedAreas)).'.');
        }

        // 2) Estudiantes por código (sin emparejar por nombre: datos sensibles).
        $students = Student::whereIn('institutional_code', array_keys($names))->get()->keyBy('institutional_code');
        $enrolled = Enrollment::where('school_year_id', $this->schoolYear->id)->pluck('group_id', 'student_id');
        foreach (array_diff(array_keys($names), $students->keys()->map(fn ($c) => (string) $c)->all()) as $code) {
            $report->error(null, null, "No hay ningún estudiante con el código {$code} ({$names[$code]}): sus calificaciones no se importan. Impórtalo primero desde Phidias.");
        }
        foreach ($students as $student) {
            if (! $enrolled->has($student->id)) {
                $report->warning(null, null, "{$student->full_name} ({$student->institutional_code}) no tiene matrícula en {$this->schoolYear->name}; sus notas se importan igual.");
            }
        }

        // 3) Asignaturas del catálogo (por nombre sin tildes ni mayúsculas; se crean si faltan).
        $catalog = Subject::all()->keyBy(fn (Subject $s) => NameMatcher::normalize($s->name));
        $subjectFor = function (string $name) use (&$catalog, $report): Subject {
            $key = NameMatcher::normalize($name);
            if (! $catalog->has($key)) {
                $catalog->put($key, Subject::create(['name' => $name, 'active' => true]));
                $report->count('Asignaturas nuevas en el catálogo');
            }

            return $catalog->get($key);
        };

        // 4) Notas y asignaturas de cada estudiante.
        $linked = [];
        foreach ($groups as $g) {
            /** @var Student|null $student */
            $student = $students->get($g['code']);
            if ($student === null) {
                $report->rowsFailed++;

                continue;
            }

            $subject = $subjectFor($g['subject']);

            $pivotKey = $student->id.'|'.$subject->id;
            if (! isset($linked[$pivotKey])) {
                $linked[$pivotKey] = true;
                $exists = DB::table('student_subjects')->where(['student_id' => $student->id, 'subject_id' => $subject->id, 'school_year_id' => $this->schoolYear->id])->exists();
                if (! $exists) {
                    DB::table('student_subjects')->insert([
                        'student_id' => $student->id, 'subject_id' => $subject->id, 'school_year_id' => $this->schoolYear->id,
                        'import_batch_id' => $this->batch?->id, 'created_at' => now(), 'updated_at' => now(),
                    ]);
                    $report->count('Asignaturas asignadas a estudiantes');
                }
            }

            if ($g['values'] === []) {
                $report->rowsImported++;
                $report->count('Asignaturas sin nota en el trimestre');

                continue;
            }

            $score = array_sum($g['values']) / count($g['values']);
            $grade = Grade::firstOrNew([
                'student_id' => $student->id,
                'subject_id' => $subject->id,
                'school_year_id' => $this->schoolYear->id,
                'period' => $g['period'],
            ]);
            $isNew = ! $grade->exists;
            $grade->fill([
                'score' => round($score, 2),
                'scale_max' => $g['scale'],
                'performance' => Grade::performanceFor($score, $g['scale']),
                'level' => null,
                'recorded_by' => $this->user->id,
            ]);
            if ($isNew || $grade->isDirty()) {
                $this->stamp($grade, $g['row']);
                $grade->save();
                $report->count($isNew ? 'Calificaciones nuevas' : 'Calificaciones actualizadas');
            } else {
                $report->count('Calificaciones iguales (sin cambios)');
            }

            $report->rowsImported++;
            $report->addPreview([
                'estudiante' => $student->full_name,
                'grupo' => $g['section'],
                'asignatura' => $subject->name,
                'trimestre' => $g['period'],
                'nota' => $grade->display(),
                'desempeño' => $grade->performance,
                'criterios' => count($g['values']),
            ]);
        }
    }

    /** Nombre de asignatura legible: «MATEMÁTICAS» → «Matemáticas»; respeta los que ya traen mayúsculas y minúsculas. */
    public static function subjectName(string $raw): string
    {
        $name = trim((string) preg_replace('/\s+/', ' ', $raw));
        // Siglas («CAS», «TIC») y nombres que ya traen mayúsculas y minúsculas se dejan igual.
        if ($name === '' || mb_strlen($name) <= 4 || mb_strtoupper($name) !== $name) {
            return $name;
        }

        $small = ['de', 'del', 'la', 'las', 'los', 'y', 'e', 'para', 'a', 'en', 'and'];
        $words = explode(' ', mb_strtolower($name));
        foreach ($words as $i => $w) {
            $words[$i] = ($i > 0 && in_array($w, $small, true)) ? $w : mb_convert_case($w, MB_CASE_TITLE);
        }

        return implode(' ', $words);
    }

    /** Escala de la sección: Preescolar/Primaria 1–5, Bachillerato PAI 1–8, Décimo y Undécimo (DP) 1–7. */
    private function scaleFor(string $category, string $course): int
    {
        if (NameMatcher::normalize($category) !== 'bachillerato') {
            return 5;
        }

        return in_array(NameMatcher::normalize($course), config('seguimiento.phidias_cursos_dp', ['decimo', 'undecimo']), true) ? 7 : 8;
    }
}
