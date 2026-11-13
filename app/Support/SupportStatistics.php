<?php

namespace App\Support;

use App\Enums\SupportActivityType;
use App\Enums\SupportArea;
use App\Models\GradeLevel;
use App\Models\Group;
use App\Models\SchoolYear;
use App\Models\SupportActivity;
use App\Models\User;
use Illuminate\Support\Collection;

/**
 * Estadística general del Departamento de Apoyo: reproduce la hoja «ESTADÍSTICA GRAL» del
 * informe de gestión (cinco tablas grado × área con su total) a partir de las actividades
 * registradas, más el resumen de las demás actividades y la lista de estudiantes en seguimiento.
 *
 * - «Estudiantes con seguimiento» cuenta estudiantes distintos por grupo y área; los registros
 *   agregados (sin estudiante) suman su cantidad.
 * - Los demás indicadores suman la cantidad de cada registro.
 * - Las actividades sin grupo cuentan en la fila «Todo el colegio» y en los totales.
 */
class SupportStatistics
{
    public const NO_GROUP = 'Todo el colegio (sin grupo)';

    /**
     * @param  int|null  $semester  1, 2 o null para todo el año.
     * @param  int|null  $sectionId  Limitar a los grupos de una sección.
     * @return array<string, mixed>
     */
    public static function build(User $user, SchoolYear $year, ?int $semester, ?int $sectionId): array
    {
        $groups = Scope::visibleGroups($user, $year->id)
            ->when($sectionId !== null, fn (Collection $c) => $c->where('section_id', $sectionId))
            ->values();

        $includeUngrouped = $sectionId === null && (Scope::isAdmin($user) || Scope::isPsicologia($user) || $user->emc_section_id === null);

        /** @var Collection<int, SupportActivity> $activities */
        $activities = SupportActivity::query()
            ->where('school_year_id', $year->id)
            ->when($semester !== null, fn ($q) => $q->where('semester', $semester))
            ->where(function ($q) use ($groups, $includeUngrouped) {
                $q->whereIn('group_id', $groups->pluck('id'));
                if ($includeUngrouped) {
                    $q->orWhereNull('group_id');
                }
            })
            ->with(['student:id,full_name', 'group:id,code'])
            ->get();

        $gradeLabels = GradeLevel::query()->pluck('name', 'value');
        $rowsMeta = $groups->map(fn (Group $g) => [
            'key' => (string) $g->id,
            'group_id' => $g->id,
            'label' => $g->code,
            'grade' => $gradeLabels[$g->grade] ?? GradeLevel::labelFor($g->grade),
            'section' => $g->section->name ?? '',
            'section_id' => $g->section_id,
        ])->values()->all();

        if ($includeUngrouped && $activities->whereNull('group_id')->whereIn('type', SupportActivityType::indicators())->isNotEmpty()) {
            $rowsMeta[] = ['key' => 'none', 'group_id' => null, 'label' => self::NO_GROUP, 'grade' => '', 'section' => '', 'section_id' => null];
        }

        // Columnas: Psicología, T.O. y Fonoaudiología siempre (como el informe); Neuropsicología si tiene datos.
        $areas = array_values(array_filter(
            SupportArea::clinical(),
            fn (SupportArea $a) => $a !== SupportArea::Neuro || $activities->where('area', SupportArea::Neuro)->isNotEmpty()
        ));

        $indicators = [];
        foreach (SupportActivityType::indicators() as $type) {
            $indicators[] = self::indicator($type, $activities->where('type', $type), $rowsMeta, $areas);
        }

        return [
            'areas' => array_map(fn (SupportArea $a) => ['value' => $a->value, 'label' => $a->label(), 'short' => $a->shortLabel()], $areas),
            'indicators' => $indicators,
            'others' => self::others($activities),
            'cases' => self::cases($activities),
            'activities_count' => $activities->count(),
            'unknown_semester' => $semester === null ? 0 : SupportActivity::where('school_year_id', $year->id)->whereNull('semester')->count(),
        ];
    }

    /**
     * @param  Collection<int, SupportActivity>  $items
     * @param  list<array{key: string, group_id: int|null, label: string, grade: string, section: string, section_id: int|null}>  $rowsMeta
     * @param  list<SupportArea>  $areas
     * @return array<string, mixed>
     */
    private static function indicator(SupportActivityType $type, Collection $items, array $rowsMeta, array $areas): array
    {
        $value = function (Collection $subset) use ($type): int {
            if (! $type->isPerStudent()) {
                return (int) $subset->sum('quantity');
            }

            return $subset->whereNotNull('student_id')->pluck('student_id')->unique()->count()
                + (int) $subset->whereNull('student_id')->sum('quantity');
        };

        $rows = [];
        foreach ($rowsMeta as $meta) {
            $inRow = $items->where('group_id', $meta['group_id']);
            $values = [];
            foreach ($areas as $area) {
                $values[$area->value] = $value($inRow->where('area', $area));
            }
            $rows[] = [...$meta, 'values' => $values, 'total' => array_sum($values)];
        }

        $totals = [];
        foreach ($areas as $area) {
            $totals[$area->value] = $value($items->where('area', $area));
        }

        // Subtotales por sección (PEP, PAI, DP), como los informes por sección.
        $sections = [];
        foreach (collect($rowsMeta)->whereNotNull('section_id')->groupBy('section_id') as $sectionId => $metas) {
            $ids = $metas->pluck('group_id');
            $subset = $items->whereIn('group_id', $ids);
            $values = [];
            foreach ($areas as $area) {
                $values[$area->value] = $value($subset->where('area', $area));
            }
            $sections[] = ['section' => $metas->first()['section'], 'values' => $values, 'total' => array_sum($values)];
        }

        return [
            'type' => $type->value,
            'title' => $type->indicatorTitle(),
            'per_student' => $type->isPerStudent(),
            'rows' => $rows,
            'sections' => $sections,
            'totals' => $totals,
            'total' => $type->isPerStudent()
                ? $items->whereNotNull('student_id')->whereIn('area', $areas)->pluck('student_id')->unique()->count() + (int) $items->whereNull('student_id')->whereIn('area', $areas)->sum('quantity')
                : array_sum($totals),
        ];
    }

    /**
     * Resumen de las actividades que no son indicadores (admisiones, comités, talleres…), por área.
     *
     * @param  Collection<int, SupportActivity>  $activities
     * @return list<array{type: string, label: string, values: array<string, int>, total: int}>
     */
    private static function others(Collection $activities): array
    {
        $out = [];

        foreach (SupportActivityType::cases() as $type) {
            if ($type->isIndicator()) {
                continue;
            }

            $subset = $activities->where('type', $type);
            if ($subset->isEmpty()) {
                continue;
            }

            $values = [];
            foreach (SupportArea::cases() as $area) {
                $values[$area->value] = (int) $subset->where('area', $area)->sum('quantity');
            }

            $out[] = ['type' => $type->value, 'label' => $type->label(), 'values' => $values, 'total' => array_sum($values)];
        }

        return $out;
    }

    /**
     * Estudiantes en seguimiento con sus áreas, para ver quiénes están detrás de las cifras.
     *
     * @param  Collection<int, SupportActivity>  $activities
     * @return list<array{student_id: int, name: string, group: string, areas: list<string>}>
     */
    private static function cases(Collection $activities): array
    {
        return $activities
            ->where('type', SupportActivityType::StudentCase)
            ->whereNotNull('student_id')
            ->groupBy('student_id')
            ->map(fn (Collection $items) => [
                'student_id' => (int) $items->first()->student_id,
                'name' => (string) $items->first()->student?->full_name,
                'group' => (string) ($items->first()->group->code ?? ''),
                'areas' => $items->map(fn (SupportActivity $a) => $a->area->shortLabel())->unique()->values()->all(),
            ])
            ->sort(fn (array $a, array $b) => strnatcasecmp($a['group'], $b['group']) ?: strcasecmp($a['name'], $b['name']))
            ->values()
            ->all();
    }
}
