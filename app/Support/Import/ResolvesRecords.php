<?php

namespace App\Support\Import;

use App\Models\Enrollment;
use App\Models\GradeLevel;
use App\Models\Group;
use App\Models\Section;
use App\Models\Student;
use Illuminate\Support\Collection;

/**
 * Búsqueda tolerante de grupos y estudiantes a partir del texto del Excel.
 *
 * @phpstan-require-extends Importer
 */
trait ResolvesRecords
{
    /** @var Collection<int, Student>|null */
    private ?Collection $allStudents = null;

    /** @var array<int, Group> */
    private array $groupCache = [];

    public static function normalizeGroupCode(string $code): string
    {
        // "6°A", "6º A" y "6ª A" son el mismo 6A (los símbolos de ordinal no son una letra).
        $code = NameMatcher::normalize(str_replace(['°', 'º', 'ª'], '', $code));
        $code = (string) preg_replace('/\b(grado|grupo|curso)\b/', '', $code);

        return mb_strtoupper(str_replace(' ', '', $code));
    }

    /**
     * Grupo del año escolar por su código ("10A", "10 A", "10°A" son el mismo). Si no existe y
     * se permite, lo crea deduciendo el grado del número y la sección de la columna "Sección"
     * o de otro grupo del mismo grado.
     */
    protected function resolveGroup(string $rawCode, ?string $sectionName, bool $allowCreate, ImportReport $report, ?string $sheet, ?int $row, ?int $grade = null): ?Group
    {
        $wanted = self::normalizeGroupCode($rawCode);

        if ($wanted === '') {
            $report->error($sheet, $row, 'No se indicó el grupo.');

            return null;
        }

        foreach ($this->groupCache as $group) {
            if (self::normalizeGroupCode($group->code) === $wanted) {
                return $group;
            }
        }

        $group = Group::where('school_year_id', $this->schoolYear->id)->get()
            ->first(fn (Group $g) => self::normalizeGroupCode($g->code) === $wanted);

        if ($group !== null) {
            return $this->groupCache[$group->id] = $group;
        }

        if (! $allowCreate) {
            $report->error($sheet, $row, "El grupo «{$rawCode}» no existe en el año {$this->schoolYear->name}. Créalo en Administración · Grupos o importa primero los estudiantes con su grupo.");

            return null;
        }

        if ($grade === null && preg_match('/^(\d{1,2})/', $wanted, $m)) {
            $grade = (int) $m[1];
        }

        if ($grade === null || ! GradeLevel::where('value', $grade)->exists()) {
            $report->error($sheet, $row, "No se pudo deducir el grado del grupo «{$rawCode}» para crearlo.");

            return null;
        }

        $section = null;

        if ($sectionName !== null && trim($sectionName) !== '') {
            $section = Section::all()->first(fn (Section $s) => NameMatcher::normalize($s->name) === NameMatcher::normalize($sectionName));
            if ($section === null) {
                $report->error($sheet, $row, "La sección «{$sectionName}» no existe. Revisa Administración · Secciones.");

                return null;
            }
        } else {
            $section = self::sectionForGrade($grade);
        }

        if ($section === null) {
            $report->error($sheet, $row, "No se pudo saber a qué sección pertenece el grupo «{$rawCode}». Crea primero un grupo de ese grado en Administración · Grupos, o indica la sección.");

            return null;
        }

        $group = Group::create([
            'code' => $wanted,
            'grade' => $grade,
            'section_id' => $section->id,
            'school_year_id' => $this->schoolYear->id,
        ]);

        $report->count('Grupos nuevos');
        $report->remember(['type' => 'group_created', 'id' => $group->id]);
        $report->warning($sheet, $row, "Se creará el grupo {$group->code} ({$section->name}) en {$this->schoolYear->name}.");

        return $this->groupCache[$group->id] = $group;
    }

    /**
     * Sección de un grado: la de otro grupo del mismo grado (cualquier año) o, si no hay, la
     * sección cuyo nombre declara el rango ("PAI (5.°–9.°)", "PEP (Preescolar–4.°)").
     */
    public static function sectionForGrade(int $grade): ?Section
    {
        $sectionId = Group::where('grade', $grade)->orderByDesc('id')->value('section_id');

        if ($sectionId !== null) {
            return Section::find($sectionId);
        }

        foreach (Section::all() as $section) {
            $name = NameMatcher::normalize($section->name);
            preg_match_all('/\d{1,2}/', $name, $m);
            $numbers = array_map('intval', $m[0]);

            if (str_contains($name, 'preescolar') || str_contains($name, 'transicion') || str_contains($name, 'kinder')) {
                array_unshift($numbers, 0);
            }

            if (count($numbers) >= 2 && $grade >= min($numbers) && $grade <= max($numbers)) {
                return $section;
            }
        }

        return null;
    }

    /**
     * Estudiante por código institucional o por nombre: primero entre los matriculados en el
     * grupo ese año, después en todo el colegio. Si no aparece y se permite, lo crea.
     */
    protected function resolveStudent(string $name, ?string $code, Group $group, bool $allowCreate, ImportReport $report, string $sheet, int $row): ?Student
    {
        $code = $code !== null && trim($code) !== '' ? trim($code) : null;

        if ($code !== null) {
            $byCode = Student::where('institutional_code', $code)->first();
            if ($byCode !== null) {
                if (NameMatcher::similarity($name, $byCode->full_name) < 70) {
                    $report->warning($sheet, $row, "El código {$code} pertenece a «{$byCode->full_name}», pero el archivo dice «{$name}». Se usó el código.");
                }

                return $byCode;
            }
        }

        $inGroup = $group->students()->get();
        $result = NameMatcher::best($name, $inGroup, fn (Student $s) => $s->full_name);

        if ($result['match'] === null || $result['score'] < NameMatcher::AUTO) {
            $this->allStudents ??= Student::all();
            $result = NameMatcher::best($name, $this->allStudents, fn (Student $s) => $s->full_name);
        }

        if ($result['ambiguous']) {
            /** @var Student $a */
            $a = $result['match'];
            /** @var Student $b */
            $b = $result['runner_up'];
            $report->error($sheet, $row, "«{$name}» se parece por igual a «{$a->full_name}» y «{$b->full_name}». Escribe el nombre completo o el código institucional.");

            return null;
        }

        if ($result['match'] !== null && $result['score'] >= NameMatcher::AUTO) {
            /** @var Student $student */
            $student = $result['match'];
            if ($code !== null && $student->institutional_code !== null && $student->institutional_code !== $code) {
                $report->error($sheet, $row, "«{$name}» (código {$code}) se parece a «{$student->full_name}», que tiene otro código ({$student->institutional_code}). Revisa si es la misma persona.");

                return null;
            }
            if ($result['score'] < 100) {
                $report->warning($sheet, $row, "«{$name}» se emparejó con «{$student->full_name}» ({$result['score']} % de similitud).");
            }
            if ($code !== null && $student->institutional_code === null) {
                $student->update(['institutional_code' => $code]);
                $report->remember(['type' => 'student_code_set', 'id' => $student->id]);
            }

            return $student;
        }

        if (! $allowCreate) {
            $hint = $result['match'] instanceof Student ? " El más parecido es «{$result['match']->full_name}» ({$result['score']} %)." : '';
            $report->error($sheet, $row, "No se encontró al estudiante «{$name}».{$hint}");

            return null;
        }

        $student = new Student(['full_name' => $this->cleanName($name), 'institutional_code' => $code, 'active' => true]);
        $this->stamp($student, $row);
        $student->save();
        $this->allStudents?->push($student);
        $report->count('Estudiantes nuevos');

        if (! $this->warnsOnNewStudents()) {
            return $student;
        }

        $hint = $result['match'] instanceof Student && $result['score'] >= 60 ? " (el más parecido es «{$result['match']->full_name}», {$result['score']} %)" : '';
        $report->warning($sheet, $row, "«{$student->full_name}» no existe: se creará como estudiante nuevo{$hint}.");

        return $student;
    }

    /**
     * Garantiza la matrícula del estudiante en el grupo para el año. Si ya está en otro grupo ese
     * año: con $move lo cambia (y la reversión lo devuelve), sin $move lo reporta como error.
     */
    protected function ensureEnrollment(Student $student, Group $group, bool $move, ImportReport $report, string $sheet, int $row): bool
    {
        /** @var Enrollment|null $enrollment */
        $enrollment = Enrollment::where('student_id', $student->id)
            ->where('school_year_id', $this->schoolYear->id)
            ->first();

        if ($enrollment === null) {
            $enrollment = new Enrollment([
                'student_id' => $student->id,
                'group_id' => $group->id,
                'school_year_id' => $this->schoolYear->id,
            ]);
            $this->stamp($enrollment, $row);
            $enrollment->save();
            $report->count('Matrículas nuevas');

            return true;
        }

        if ($enrollment->group_id === $group->id) {
            return true;
        }

        $currentCode = Group::whereKey($enrollment->group_id)->value('code');

        if (! $move) {
            $report->error($sheet, $row, "{$student->full_name} está matriculado(a) en {$currentCode} en {$this->schoolYear->name}, no en {$group->code}.");

            return false;
        }

        $report->remember(['type' => 'enrollment_moved', 'id' => $enrollment->id, 'previous' => $enrollment->group_id]);
        $enrollment->update(['group_id' => $group->id]);
        $report->warning($sheet, $row, "{$student->full_name} se cambia de {$currentCode} a {$group->code}.");
        $report->count('Cambios de grupo');

        return true;
    }

    /** Si cada estudiante nuevo genera un aviso (en Phidias lo normal es que haya nuevos). */
    protected function warnsOnNewStudents(): bool
    {
        return true;
    }

    private function cleanName(string $name): string
    {
        $name = trim((string) preg_replace('/\s+/', ' ', $name));

        // Los nombres escritos todo en mayúsculas se pasan a formato título.
        return $name === mb_strtoupper($name) ? mb_convert_case(mb_strtolower($name), MB_CASE_TITLE) : $name;
    }
}
