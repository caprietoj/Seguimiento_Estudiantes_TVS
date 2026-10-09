<?php

namespace App\Http\Controllers;

use App\Enums\ContributionField;
use App\Enums\RoleName;
use App\Models\Commitment;
use App\Models\Contribution;
use App\Models\DisciplinaryCase;
use App\Models\Group;
use App\Models\SchoolYear;
use App\Models\Strategy;
use App\Models\Subject;
use App\Models\User;
use App\Support\Scope;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class ReunionController extends Controller
{
    /**
     * Vista "Reunión de grupo" (sección 6.1): el director consolida la información del
     * grupo y, durante la reunión, cada participante agrega sus aportes al mismo tiempo.
     */
    public function show(Request $request, ?Group $group = null, ?int $followUpId = null): Response
    {
        $user = $request->user();
        $schoolYear = SchoolYear::where('is_active', true)->firstOrFail();

        $visibleGroups = Scope::visibleGroups($user, $schoolYear->id);

        if ($group === null) {
            $group = $visibleGroups->first();
        }

        if ($group === null) {
            return Inertia::render('reunion/sin-grupos');
        }

        Gate::authorize('view', $group);

        $followUps = $schoolYear->followUps()->orderBy('period')->orderBy('number')->get();
        $followUp = $followUpId
            ? $followUps->firstWhere('id', $followUpId)
            : $followUps->first();
        abort_if($followUp === null, 404);

        $meeting = $group->groupMeetings()->where('follow_up_id', $followUp->id)->first();

        $students = $group->students()
            ->orderBy('full_name')
            ->get()
            ->load(['grades' => fn ($q) => $q->where('school_year_id', $schoolYear->id)->where('period', $followUp->period)->with('subject')]);

        $studentIds = $students->pluck('id');

        $groupSubjects = Subject::active()
            ->forGrade($group->grade)
            ->orderBy('name')
            ->pluck('name')
            ->values()
            ->all();

        $contributions = Contribution::query()
            ->whereIn('student_id', $studentIds)
            ->where('follow_up_id', $followUp->id)
            ->with('author')
            ->get()
            ->groupBy(fn ($c) => $c->student_id.'|'.$c->field->value);

        $individualStrategies = Strategy::query()
            ->whereIn('student_id', $studentIds)
            ->where('type', 'individual')
            ->with(['reviews' => fn ($q) => $q->latest('reviewed_on')])
            ->get()
            ->groupBy('student_id');

        $openCommitments = Commitment::query()->whereIn('student_id', $studentIds)->open()->get();
        $lateByStudent = $openCommitments->filter(fn ($c) => $c->isLate())->groupBy('student_id');
        $disciplinaryOpenByStudent = Scope::disciplinarySee($user, $group)
            ? DisciplinaryCase::query()->whereIn('student_id', $studentIds)->where('status', 'open')->get()->groupBy('student_id')
            : collect();

        $areas = collect(ContributionField::supportAreas());
        $visibleAreas = $areas->filter(fn (ContributionField $f) => Scope::seesField($user, $group, $f))->values();
        $hiddenAreas = $areas->reject(fn (ContributionField $f) => $visibleAreas->contains($f))->values();

        $columns = collect([
            ['key' => 'strength', 'label' => 'Fortalezas / avances', 'type' => 'notes'],
            ['key' => 'improvement', 'label' => 'Aspectos de mejora', 'type' => 'notes'],
            ['key' => 'subject_attention', 'label' => 'Asignaturas para tener en cuenta', 'type' => 'subject_attention'],
            ['key' => 'estrategias', 'label' => 'Estrategias individuales', 'type' => 'estrategias'],
            ['key' => 'responsables', 'label' => 'Responsables', 'type' => 'responsables'],
        ])
            ->concat($visibleAreas->map(fn (ContributionField $f) => ['key' => $f->value, 'label' => $f->label(), 'type' => 'notes', 'support' => true]))
            ->push(['key' => 'observation', 'label' => 'Observación', 'type' => 'notes'])
            ->values();

        $roleLabel = function (User $author) use ($group) {
            if ($author->id === $group->director_id) {
                return 'Director(a) de grupo';
            }
            $role = $author->getRoleNames()->first();

            return $role ? RoleName::from($role)->label() : '';
        };

        $studentRows = $students->map(function ($student) use ($contributions, $individualStrategies, $lateByStudent, $disciplinaryOpenByStudent, $columns, $user, $group, $roleLabel, $groupSubjects) {
            $cells = [];
            foreach ($columns as $col) {
                if ($col['type'] === 'notes') {
                    $field = ContributionField::from($col['key']);
                    $list = ($contributions->get($student->id.'|'.$col['key']) ?? collect())->map(fn (Contribution $c) => [
                        'id' => $c->id,
                        'body' => $c->body,
                        'author_name' => $c->author->name,
                        'author_role' => $roleLabel($c->author),
                        'can_delete' => Scope::canDelete($user, $group, $c->author_id),
                    ])->values();
                    $cells[$col['key']] = [
                        'notes' => $list,
                        'can_write' => Scope::writesField($user, $group, $field),
                    ];
                } elseif ($col['type'] === 'subject_attention') {
                    $threshold = (int) config('seguimiento.nivel_atencion', 3);
                    $field = ContributionField::SubjectAttention;
                    $notes = ($contributions->get($student->id.'|'.$field->value) ?? collect())->map(fn (Contribution $c) => [
                        'id' => $c->id,
                        'body' => $c->body,
                        'author_name' => $c->author->name,
                        'author_role' => $roleLabel($c->author),
                        'can_delete' => Scope::canDelete($user, $group, $c->author_id),
                    ])->values();

                    $alertGrades = $student->grades
                        ->filter(fn ($g) => $g->level !== null && $g->level <= $threshold)
                        ->map(fn ($g) => ['subject' => $g->subject->name, 'level' => $g->level])
                        ->values();

                    $cells[$col['key']] = [
                        'notes' => $notes,
                        'grades_alert' => $alertGrades,
                        'available_subjects' => $groupSubjects,
                        'can_write' => Scope::writesField($user, $group, $field),
                    ];
                } elseif ($col['type'] === 'asignaturas') {
                    $threshold = (int) config('seguimiento.nivel_atencion', 3);
                    $cells[$col['key']] = $student->grades
                        ->filter(fn ($g) => $g->level !== null && $g->level <= $threshold)
                        ->map(fn ($g) => ['subject' => $g->subject->name, 'level' => $g->level])
                        ->values();
                } elseif ($col['type'] === 'estrategias') {
                    $list = ($individualStrategies->get($student->id) ?? collect())->map(fn (Strategy $s) => [
                        'id' => $s->id,
                        'body' => $s->body,
                        'responsible' => $s->responsible,
                        'last_result' => $s->reviews->first()?->result?->label() ?? 'Sin evaluar',
                        'can_delete' => Scope::canDelete($user, $group, $s->author_id),
                    ])->values();
                    $cells[$col['key']] = [
                        'items' => $list,
                        'can_write' => Scope::inScope($user, $group),
                    ];
                } elseif ($col['type'] === 'responsables') {
                    $cells[$col['key']] = $student->responsibles();
                }
            }

            $threshold = (int) config('seguimiento.nivel_atencion', 3);
            $attentionCount = $student->grades
                ->filter(fn ($g) => $g->level !== null && $g->level <= $threshold)
                ->count();

            return [
                'id' => $student->id,
                'full_name' => $student->full_name,
                'institutional_code' => $student->institutional_code,
                'photo_url' => $student->photo_path ? route('fotos.show', $student) : null,
                'late_commitments' => ($lateByStudent->get($student->id) ?? collect())->count(),
                'disciplinary_open' => ($disciplinaryOpenByStudent->get($student->id) ?? collect())->count(),
                'attention_subjects_count' => $attentionCount,
                'cells' => $cells,
            ];
        })->values();

        return Inertia::render('reunion/show', [
            'schoolYear' => ['id' => $schoolYear->id, 'name' => $schoolYear->name],
            'groups' => $visibleGroups->map(fn (Group $g) => ['id' => $g->id, 'code' => $g->code, 'section' => $g->section->name])->values(),
            'followUps' => $followUps->map(fn ($f) => ['id' => $f->id, 'label' => $f->label()])->values(),
            'group' => [
                'id' => $group->id,
                'code' => $group->code,
                'grade' => $group->grade,
                'section' => $group->section->name,
                'director_name' => $group->director?->name,
            ],
            'followUp' => ['id' => $followUp->id, 'label' => $followUp->label(), 'period' => $followUp->period],
            'meeting' => $meeting ? ['held_on' => $meeting->held_on?->toDateString()] : null,
            'can' => [
                'manage_meeting_date' => Scope::canManageGroupStrategy($user, $group),
                'create_group_strategy' => Scope::canManageGroupStrategy($user, $group),
            ],
            'stats' => [
                'students' => $students->count(),
                'open_commitments' => $openCommitments->count(),
                'late_commitments' => $lateByStudent->flatten(1)->count(),
            ],
            'groupStrategies' => Strategy::where('group_id', $group->id)->where('type', 'group')
                ->with(['reviews' => fn ($q) => $q->latest('reviewed_on')])
                ->get()
                ->map(fn (Strategy $s) => [
                    'id' => $s->id,
                    'body' => $s->body,
                    'responsible' => $s->responsible,
                    'last_result' => $s->reviews->first()?->result?->label() ?? 'Sin evaluar',
                    'last_review_body' => $s->reviews->first()?->body,
                    'reviews_count' => $s->reviews->count(),
                    'can_review' => Scope::inScope($user, $group),
                    'can_delete' => Scope::canManageGroupStrategy($user, $group),
                ])->values(),
            'columns' => $columns,
            'hiddenAreas' => $hiddenAreas->map(fn (ContributionField $f) => $f->label())->values(),
            'students' => $studentRows,
        ]);
    }
}
