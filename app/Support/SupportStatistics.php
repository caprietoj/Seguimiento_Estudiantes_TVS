<?php

namespace App\Support;

use App\Enums\ContributionField;
use App\Models\Contribution;
use App\Models\Enrollment;
use App\Models\GradeLevel;
use App\Models\Group;
use App\Models\SchoolYear;
use App\Models\User;
use Illuminate\Support\Collection;

/**
 * Estadística general del Departamento de Apoyo, calculada con las notas que cada área
 * (Psicología, T.O., Fonoaudiología, Neuropsicología) registra por estudiante en la reunión
 * de grupo. Reproduce las cinco tablas de la hoja «ESTADÍSTICA GRAL» del informe de gestión:
 *
 * 1. Estudiantes con seguimiento: estudiantes distintos con la marca «en seguimiento».
 * 2. Familias atendidas: notas con la marca «se atendió a la familia».
 * 3. Reuniones de seguimiento: reuniones de grupo (seguimientos) en que el área registró al
 *    menos una nota de un estudiante del grupo.
 * 4. Reuniones PIAR: notas con la marca.
 * 5. Atención en crisis: notas con la marca.
 *
 * Cada nota cuenta en el grupo en que el estudiante estaba matriculado ese año.
 */
class SupportStatistics
{
    /** @var array<string, array{title: string, flag: string|null, per_student: bool}> */
    public const INDICATORS = [
        'students' => ['title' => 'Estudiantes con seguimiento por área', 'flag' => 'in_follow_up', 'per_student' => true],
        'families' => ['title' => 'Familias atendidas por área', 'flag' => 'family_attended', 'per_student' => false],
        'meetings' => ['title' => 'Reuniones de seguimiento por área', 'flag' => null, 'per_student' => false],
        'piar' => ['title' => 'Reuniones de PIAR', 'flag' => 'piar_meeting', 'per_student' => false],
        'crisis' => ['title' => 'Atención en crisis', 'flag' => 'crisis_attention', 'per_student' => false],
    ];

    /**
     * Periodos de cada semestre (calendario B: I = periodo 1; II = periodos 2 y 3).
     *
     * @return list<int>
     */
    public static function semesterPeriods(int $semester): array
    {
        return config("seguimiento.semestres.{$semester}", $semester === 1 ? [1] : [2, 3]);
    }

    /**
     * @param  string  $scope  'todo', 'p1'…'p3' (un periodo) o 's1'/'s2' (un semestre).
     * @return array<string, mixed>
     */
    public static function build(User $user, SchoolYear $year, string $scope, ?int $sectionId): array
    {
        $groups = Scope::visibleGroups($user, $year->id)
            ->when($sectionId !== null, fn (Collection $c) => $c->where('section_id', $sectionId))
            ->values();

        $periods = match (true) {
            preg_match('/^p([1-9])$/', $scope, $m) === 1 => [(int) $m[1]],
            preg_match('/^s([12])$/', $scope, $m) === 1 => self::semesterPeriods((int) $m[1]),
            default => null,
        };

        $followUps = $year->followUps()->when($periods !== null, fn ($q) => $q->whereIn('period', $periods))->get()->keyBy('id');

        // Grupo de cada estudiante en ese año (las notas cuentan en su grupo del año).
        $groupOf = Enrollment::query()
            ->where('school_year_id', $year->id)
            ->whereIn('group_id', $groups->pluck('id'))
            ->pluck('group_id', 'student_id');

        $clinical = ContributionField::clinical();

        /** @var Collection<int, Contribution> $notes */
        $notes = Contribution::query()
            ->whereIn('follow_up_id', $followUps->keys())
            ->whereIn('student_id', $groupOf->keys())
            ->whereIn('field', array_map(fn (ContributionField $f) => $f->value, $clinical))
            ->with('student:id,full_name')
            ->get(['id', 'student_id', 'follow_up_id', 'field', 'in_follow_up', 'family_attended', 'piar_meeting', 'crisis_attention'])
            ->each(fn (Contribution $c) => $c->setAttribute('group_id', $groupOf[$c->student_id] ?? null));

        // Columnas: Psicología, T.O. y Fonoaudiología siempre (como el informe); Neuropsicología si tiene notas.
        $areas = array_values(array_filter(
            $clinical,
            fn (ContributionField $f) => $f !== ContributionField::SupportNeuro || $notes->where('field', $f)->isNotEmpty()
        ));

        $gradeLabels = GradeLevel::query()->pluck('name', 'value');
        $rowsMeta = $groups->map(fn (Group $g) => [
            'key' => (string) $g->id,
            'group_id' => $g->id,
            'label' => $g->code,
            'grade' => $gradeLabels[$g->grade] ?? GradeLevel::labelFor($g->grade),
            'section' => $g->section->name ?? '',
            'section_id' => $g->section_id,
        ])->values()->all();

        $indicators = [];
        foreach (self::INDICATORS as $key => $def) {
            $indicators[] = self::indicator($key, $def, $notes, $rowsMeta, $areas);
        }

        return [
            'areas' => array_map(fn (ContributionField $a) => ['value' => $a->value, 'label' => $a->label(), 'short' => $a->shortLabel()], $areas),
            'indicators' => $indicators,
            'cases' => self::cases($notes),
            'notes_count' => $notes->count(),
        ];
    }

    /**
     * @param  array{title: string, flag: string|null, per_student: bool}  $def
     * @param  Collection<int, Contribution>  $notes
     * @param  list<array<string, mixed>>  $rowsMeta
     * @param  list<ContributionField>  $areas
     * @return array<string, mixed>
     */
    private static function indicator(string $key, array $def, Collection $notes, array $rowsMeta, array $areas): array
    {
        $value = function (Collection $subset) use ($key, $def): int {
            return match (true) {
                $key === 'meetings' => $subset->map(fn (Contribution $c) => $c->getAttribute('group_id').'|'.$c->follow_up_id)->unique()->count(),
                $def['per_student'] => $subset->where($def['flag'], true)->pluck('student_id')->unique()->count(),
                default => $subset->where($def['flag'], true)->count(),
            };
        };

        $rows = [];
        foreach ($rowsMeta as $meta) {
            $inRow = $notes->where('group_id', $meta['group_id']);
            $values = [];
            foreach ($areas as $area) {
                $values[$area->value] = $value($inRow->where('field', $area));
            }
            $rows[] = [...$meta, 'values' => $values, 'total' => array_sum($values)];
        }

        $totals = [];
        foreach ($areas as $area) {
            $totals[$area->value] = $value($notes->where('field', $area));
        }

        $sections = [];
        foreach (collect($rowsMeta)->groupBy('section_id') as $metas) {
            $subset = $notes->whereIn('group_id', $metas->pluck('group_id'));
            $values = [];
            foreach ($areas as $area) {
                $values[$area->value] = $value($subset->where('field', $area));
            }
            $sections[] = ['section' => $metas->first()['section'], 'values' => $values, 'total' => array_sum($values)];
        }

        return [
            'type' => $key,
            'title' => $def['title'],
            'per_student' => $def['per_student'],
            'rows' => $rows,
            'sections' => $sections,
            'totals' => $totals,
            // En «estudiantes» el total cuenta a cada estudiante una vez aunque lo vean varias áreas.
            'total' => $def['per_student'] ? $value($notes->whereIn('field', $areas)) : array_sum($totals),
        ];
    }

    /**
     * Estudiantes con la marca «en seguimiento» y sus áreas, para ver quiénes están detrás de la cifra.
     *
     * @param  Collection<int, Contribution>  $notes
     * @return list<array{student_id: int, name: string, group: string, areas: list<string>}>
     */
    private static function cases(Collection $notes): array
    {
        $codes = Group::whereIn('id', $notes->pluck('group_id')->unique())->pluck('code', 'id');

        return $notes
            ->where('in_follow_up', true)
            ->groupBy('student_id')
            ->map(fn (Collection $items) => [
                'student_id' => (int) $items->first()->student_id,
                'name' => (string) $items->first()->student?->full_name,
                'group' => (string) ($codes[$items->first()->getAttribute('group_id')] ?? ''),
                'areas' => $items->map(fn (Contribution $c) => $c->field->shortLabel())->unique()->values()->all(),
            ])
            ->sort(fn (array $a, array $b) => strnatcasecmp($a['group'], $b['group']) ?: strcasecmp($a['name'], $b['name']))
            ->values()
            ->all();
    }
}
