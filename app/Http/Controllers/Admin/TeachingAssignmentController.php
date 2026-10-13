<?php

namespace App\Http\Controllers\Admin;

use App\Enums\AuditAction;
use App\Enums\RoleName;
use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Group;
use App\Models\SchoolYear;
use App\Models\Subject;
use App\Models\TeachingAssignment;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Administración · Asignaciones: qué profesor dicta qué asignatura en qué grupo, y quién dirige
 * cada grupo. Un profesor solo ve los grupos donde tiene asignación o es director(a)
 * (App\Support\Scope::inScope), así que esta pantalla es la que define qué ve cada docente.
 */
class TeachingAssignmentController extends Controller
{
    public function index(Request $request): Response
    {
        $schoolYears = SchoolYear::orderByDesc('starts_on')->get();
        $activeYearId = SchoolYear::where('is_active', true)->value('id');
        $yearId = $request->integer('anio') ?: ($activeYearId ?? $schoolYears->first()->id ?? 0);

        $groups = Group::with(['director', 'teachingAssignments' => fn ($q) => $q->with(['user', 'subject'])])
            ->withCount('enrollments')
            ->where('school_year_id', $yearId)
            ->orderBy('grade')
            ->orderBy('code')
            ->get();

        $teachers = User::withRoleName(RoleName::Profesor)->where('active', true)->orderBy('name')->get(['id', 'name', 'email']);

        $withGroup = TeachingAssignment::where('school_year_id', $yearId)->pluck('user_id')
            ->merge($groups->pluck('director_id')->filter())
            ->unique();

        return Inertia::render('admin/asignaciones', [
            'schoolYears' => $schoolYears->map(fn (SchoolYear $y) => ['id' => $y->id, 'name' => $y->name])->values(),
            'selectedYearId' => $yearId,
            'teachers' => $teachers->map(fn (User $u) => ['id' => $u->id, 'name' => $u->name, 'email' => $u->email])->values(),
            'subjects' => Subject::active()->orderBy('name')->get(['id', 'name']),
            'teachersWithoutGroup' => $teachers->reject(fn (User $u) => $withGroup->contains($u->id))
                ->map(fn (User $u) => ['id' => $u->id, 'name' => $u->name, 'email' => $u->email])->values(),
            'groups' => $groups->map(fn (Group $g) => [
                'id' => $g->id,
                'code' => $g->code,
                'students_count' => $g->enrollments_count,
                'director_id' => $g->director_id,
                'director_name' => $g->director?->name,
                'assignments' => $g->teachingAssignments
                    ->sortBy(fn (TeachingAssignment $a) => $a->user->name.$a->subject->name)
                    ->map(fn (TeachingAssignment $a) => [
                        'id' => $a->id,
                        'teacher' => $a->user->name,
                        'email' => $a->user->email,
                        'subject' => $a->subject->name,
                    ])->values(),
            ])->values(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'user_id' => ['required', 'integer', 'exists:users,id'],
            'group_id' => ['required', 'integer', 'exists:groups,id'],
            'subject_ids' => ['required', 'array', 'min:1'],
            'subject_ids.*' => ['integer', 'exists:subjects,id'],
        ], [], ['user_id' => 'profesor', 'group_id' => 'grupo', 'subject_ids' => 'asignaturas']);

        $group = Group::findOrFail($data['group_id']);
        abort_if($group->schoolYear->is_read_only, 403, 'Este año escolar es de solo lectura.');

        foreach ($data['subject_ids'] as $subjectId) {
            $assignment = TeachingAssignment::firstOrCreate([
                'user_id' => $data['user_id'],
                'group_id' => $group->id,
                'subject_id' => $subjectId,
                'school_year_id' => $group->school_year_id,
            ]);

            if ($assignment->wasRecentlyCreated) {
                $this->audit($request, AuditAction::Create, $assignment);
            }
        }

        return back();
    }

    public function destroy(Request $request, TeachingAssignment $assignment): RedirectResponse
    {
        abort_if($assignment->schoolYear->is_read_only, 403, 'Este año escolar es de solo lectura.');

        $this->audit($request, AuditAction::Delete, $assignment);
        $assignment->delete();

        return back();
    }

    public function director(Request $request, Group $group): RedirectResponse
    {
        abort_if($group->schoolYear->is_read_only, 403, 'Este año escolar es de solo lectura.');

        $data = $request->validate([
            'director_id' => ['nullable', 'integer', Rule::exists('users', 'id')],
        ]);

        $previous = $group->director_id;
        $group->update(['director_id' => $data['director_id'] ?? null]);

        AuditLog::create([
            'user_id' => $request->user()?->id,
            'action' => AuditAction::Update,
            'auditable_type' => Group::class,
            'auditable_id' => $group->id,
            'changes' => ['director_id' => ['antes' => $previous, 'ahora' => $group->director_id]],
            'ip' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'created_at' => now(),
        ]);

        return back();
    }

    private function audit(Request $request, AuditAction $action, TeachingAssignment $assignment): void
    {
        AuditLog::create([
            'user_id' => $request->user()?->id,
            'action' => $action,
            'auditable_type' => TeachingAssignment::class,
            'auditable_id' => $assignment->id,
            'changes' => $assignment->only(['user_id', 'group_id', 'subject_id', 'school_year_id']),
            'ip' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'created_at' => now(),
        ]);
    }
}
