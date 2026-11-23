<?php

namespace App\Support;

use App\Enums\ContributionField;
use App\Models\GradeLevel;
use App\Models\Group;
use App\Models\ImportBatch;
use App\Models\SchoolYear;
use App\Models\User;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

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

        $followUpIds = $year->followUps()->when($periods !== null, fn ($q) => $q->whereIn('period', $periods))->pluck('id');
        $clinical = ContributionField::clinical();
        $fields = array_map(fn (ContributionField $f) => $f->value, $clinical);

        // Notas del Dpto. de ese año con el grupo del estudiante en ese año. Los conteos los hace
        // la base de datos (una fila por grupo × área): con miles de notas, recorrerlas en PHP
        // por cada grupo, área e indicador tomaba segundos.
        $base = fn () => DB::table('contributions as c')
            ->join('enrollments as e', function ($j) use ($year) {
                $j->on('e.student_id', '=', 'c.student_id')->where('e.school_year_id', '=', $year->id);
            })
            ->whereNull('c.deleted_at')
            ->whereIn('c.follow_up_id', $followUpIds)
            ->whereIn('c.field', $fields)
            ->whereIn('e.group_id', $groups->pluck('id'));

        $flag = fn (string $col) => "SUM(CASE WHEN c.{$col} = 1 THEN 1 ELSE 0 END)";
        $agg = $base()
            ->groupBy('e.group_id', 'c.field')
            ->selectRaw('e.group_id, c.field, COUNT(*) as notes'
                .', COUNT(DISTINCT CASE WHEN c.in_follow_up = 1 THEN c.student_id END) as students'
                .', '.$flag('family_attended').' as families'
                .', COUNT(DISTINCT c.follow_up_id) as meetings'
                .', '.$flag('piar_meeting').' as piar'
                .', '.$flag('crisis_attention').' as crisis')
            ->get()
            ->keyBy(fn ($r) => $r->group_id.'|'.$r->field);

        // Columnas: Psicología, T.O. y Fonoaudiología siempre (como el informe); Neuropsicología si tiene notas.
        $areas = array_values(array_filter(
            $clinical,
            fn (ContributionField $f) => $f !== ContributionField::SupportNeuro || $agg->contains(fn ($r) => $r->field === $f->value)
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

        // Un estudiante está en un solo grupo por año, así que los estudiantes distintos de un área
        // se pueden sumar por grupo; solo el total entre áreas necesita su propia cuenta.
        $distinctStudents = (int) $base()->where('c.in_follow_up', true)->distinct()->count('c.student_id');

        $indicators = [];
        foreach (self::INDICATORS as $key => $def) {
            $indicators[] = self::indicator($key, $def, $agg, $rowsMeta, $areas, $distinctStudents);
        }

        return [
            'areas' => array_map(fn (ContributionField $a) => ['value' => $a->value, 'label' => $a->label(), 'short' => $a->shortLabel()], $areas),
            'indicators' => $indicators,
            'cases' => self::cases($base()),
            'notes_count' => (int) $agg->sum('notes'),
            'sources' => self::sources($base(), $areas),
        ];
    }

    /**
     * @param  array{title: string, flag: string|null, per_student: bool}  $def
     * @param  Collection<string, \stdClass>  $agg  Conteos por «grupo|área».
     * @param  list<array<string, mixed>>  $rowsMeta
     * @param  list<ContributionField>  $areas
     * @return array<string, mixed>
     */
    private static function indicator(string $key, array $def, Collection $agg, array $rowsMeta, array $areas, int $distinctStudents): array
    {
        $cell = fn (int $groupId, ContributionField $area): int => (int) ($agg->get($groupId.'|'.$area->value)->{$key} ?? 0);

        $rows = [];
        $totals = array_fill_keys(array_map(fn (ContributionField $a) => $a->value, $areas), 0);
        $bySection = [];

        foreach ($rowsMeta as $meta) {
            $values = [];
            foreach ($areas as $area) {
                $values[$area->value] = $cell($meta['group_id'], $area);
                $totals[$area->value] += $values[$area->value];
            }
            $rows[] = [...$meta, 'values' => $values, 'total' => array_sum($values)];

            $sec = $bySection[$meta['section_id']] ??= ['section' => $meta['section'], 'values' => array_fill_keys(array_keys($values), 0), 'total' => 0];
            foreach ($values as $k => $v) {
                $sec['values'][$k] += $v;
            }
            $sec['total'] += array_sum($values);
            $bySection[$meta['section_id']] = $sec;
        }

        return [
            'type' => $key,
            'title' => $def['title'],
            'per_student' => $def['per_student'],
            'rows' => $rows,
            'sections' => array_values($bySection),
            'totals' => $totals,
            // En «estudiantes» el total cuenta a cada estudiante una vez aunque lo vean varias áreas.
            'total' => $def['per_student'] ? $distinctStudents : array_sum($totals),
        ];
    }

    /**
     * De dónde salen las notas que se cuentan: cada importación (archivo, plantilla, fecha y
     * quién) o «registradas en la reunión / ficha». Así se puede verificar que las cifras
     * corresponden a datos reales y revertir una importación equivocada.
     *
     * @param  list<ContributionField>  $areas
     * @return list<array<string, mixed>>
     */
    private static function sources(Builder $query, array $areas): array
    {
        $rows = $query->groupBy('c.import_batch_id', 'c.field')
            ->selectRaw('c.import_batch_id, c.field, COUNT(*) as notes'
                .', SUM(CASE WHEN c.in_follow_up = 1 OR c.family_attended = 1 OR c.piar_meeting = 1 OR c.crisis_attention = 1 THEN 1 ELSE 0 END) as marked')
            ->get();

        $batches = ImportBatch::with('user:id,name')->whereIn('id', $rows->pluck('import_batch_id')->filter()->unique())->get()->keyBy('id');

        return $rows->groupBy(fn ($r) => (string) ($r->import_batch_id ?? 'manual'))
            ->map(function (Collection $items, string $key) use ($batches, $areas) {
                $batch = $key === 'manual' ? null : $batches->get((int) $key);
                $values = [];
                foreach ($areas as $area) {
                    $values[$area->value] = (int) ($items->firstWhere('field', $area->value)->notes ?? 0);
                }

                return [
                    'key' => $key,
                    'label' => $key === 'manual'
                        ? 'Registradas en la reunión de grupo o la ficha'
                        : ($batch !== null ? "Importación «{$batch->file_name}»" : "Importación #{$key} (ya no está en el historial)"),
                    'detail' => $batch !== null
                        ? $batch->template_type->label().' · '.$batch->created_at?->format('d/m/Y H:i').' · '.($batch->user->name ?? '')
                        : null,
                    'values' => $values,
                    'total' => (int) $items->sum('notes'),
                    'marked' => (int) $items->sum('marked'),
                ];
            })
            ->sortBy(fn (array $s) => $s['key'] === 'manual' ? 0 : 1)
            ->values()
            ->all();
    }

    /**
     * Estudiantes con la marca «en seguimiento» y sus áreas, para ver quiénes están detrás de la cifra.
     *
     * @return list<array{student_id: int, name: string, group: string, areas: list<string>}>
     */
    private static function cases(Builder $query): array
    {
        $rows = $query->where('c.in_follow_up', true)
            ->join('students as s', 's.id', '=', 'c.student_id')
            ->join('groups as g', 'g.id', '=', 'e.group_id')
            ->distinct()
            ->get(['c.student_id', 'c.field', 's.full_name', 'g.code']);

        return $rows->groupBy('student_id')
            ->map(fn (Collection $items) => [
                'student_id' => (int) $items->first()->student_id,
                'name' => (string) $items->first()->full_name,
                'group' => (string) $items->first()->code,
                'areas' => $items->map(fn ($r) => ContributionField::from($r->field)->shortLabel())->unique()->values()->all(),
            ])
            ->sort(fn (array $a, array $b) => strnatcasecmp($a['group'], $b['group']) ?: strcasecmp($a['name'], $b['name']))
            ->values()
            ->all();
    }
}
