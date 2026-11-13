<?php

namespace App\Http\Controllers;

use App\Enums\AuditAction;
use App\Enums\SupportActivityType;
use App\Enums\SupportArea;
use App\Models\AuditLog;
use App\Models\Enrollment;
use App\Models\Group;
use App\Models\SchoolYear;
use App\Models\SupportActivity;
use App\Support\Scope;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Dpto. de Apoyo · Registro de actividades: lo que alimenta la estadística general (casos en
 * seguimiento, familias atendidas, reuniones, PIAR, crisis, admisiones, comités…).
 */
class SupportActivityController extends Controller
{
    private const LIMIT = 400;

    public function index(Request $request): Response
    {
        $user = $request->user();
        $year = SchoolYear::find($request->integer('anio')) ?? SchoolYear::where('is_active', true)->first() ?? SchoolYear::orderByDesc('starts_on')->firstOrFail();
        $groups = Scope::visibleGroups($user, $year->id);

        $filters = [
            'anio' => $year->id,
            'area' => SupportArea::tryFrom((string) $request->query('area'))->value ?? 'todas',
            'tipo' => SupportActivityType::tryFrom((string) $request->query('tipo'))->value ?? 'todos',
            'grupo' => $groups->firstWhere('id', $request->integer('grupo'))->id ?? 'todos',
            'semestre' => in_array($request->query('semestre'), ['1', '2'], true) ? (string) $request->query('semestre') : 'todo',
            'q' => trim((string) $request->query('q', '')),
        ];

        $query = SupportActivity::query()
            ->where('school_year_id', $year->id)
            ->where(fn ($q) => $q->whereIn('group_id', $groups->pluck('id'))->orWhereNull('group_id'))
            ->when($filters['area'] !== 'todas', fn ($q) => $q->where('area', $filters['area']))
            ->when($filters['tipo'] !== 'todos', fn ($q) => $q->where('type', $filters['tipo']))
            ->when($filters['grupo'] !== 'todos', fn ($q) => $q->where('group_id', $filters['grupo']))
            ->when($filters['semestre'] !== 'todo', fn ($q) => $q->where('semester', (int) $filters['semestre']))
            ->when($filters['q'] !== '', fn ($q) => $q->where(fn ($w) => $w
                ->where('description', 'like', '%'.$filters['q'].'%')
                ->orWhereHas('student', fn ($s) => $s->where('full_name', 'like', '%'.$filters['q'].'%'))));

        $total = (clone $query)->count();
        $items = $query->with(['student:id,full_name', 'group:id,code', 'author:id,name'])
            ->orderByDesc('occurred_on')
            ->orderByDesc('id')
            ->limit(self::LIMIT)
            ->get();

        $students = Enrollment::query()
            ->where('school_year_id', $year->id)
            ->whereIn('group_id', $groups->pluck('id'))
            ->with('student:id,full_name')
            ->get()
            ->filter(fn (Enrollment $e) => $e->student !== null)
            ->groupBy('group_id')
            ->map(fn ($list) => $list->map(fn (Enrollment $e) => ['id' => $e->student_id, 'name' => $e->student->full_name])->sortBy('name')->values());

        return Inertia::render('apoyo/actividades', [
            'years' => SchoolYear::orderByDesc('starts_on')->get(['id', 'name'])->map(fn (SchoolYear $y) => ['id' => $y->id, 'name' => $y->name])->values(),
            'yearName' => $year->name,
            'filters' => $filters,
            'areas' => array_map(fn (SupportArea $a) => ['value' => $a->value, 'label' => $a->label()], SupportArea::cases()),
            'types' => array_map(fn (SupportActivityType $t) => ['value' => $t->value, 'label' => $t->label(), 'per_student' => $t->isPerStudent()], SupportActivityType::cases()),
            'groups' => $groups->map(fn (Group $g) => ['id' => $g->id, 'code' => $g->code])->values(),
            'studentsByGroup' => $students,
            'total' => $total,
            'limit' => self::LIMIT,
            'canWrite' => $user->can('apoyo-registrar'),
            'items' => $items->map(fn (SupportActivity $a) => [
                'id' => $a->id,
                'area' => $a->area->value,
                'area_label' => $a->area->label(),
                'type' => $a->type->value,
                'type_label' => $a->type->label(),
                'group_id' => $a->group_id,
                'group_code' => $a->group?->code,
                'student_id' => $a->student_id,
                'student_name' => $a->student?->full_name,
                'occurred_on' => $a->occurred_on?->toDateString(),
                'semester' => $a->semester,
                'quantity' => $a->quantity,
                'description' => $a->description,
                'author' => $a->import_batch_id !== null ? 'Importado' : $a->author?->name,
            ])->values(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validated($request);
        $activity = SupportActivity::create([...$data, 'author_id' => $request->user()->id]);
        $this->audit($request, AuditAction::Create, $activity);

        return back();
    }

    public function update(Request $request, SupportActivity $activity): RedirectResponse
    {
        $this->authorizeGroup($request, $activity);
        $activity->update($this->validated($request));
        $this->audit($request, AuditAction::Update, $activity);

        return back();
    }

    public function destroy(Request $request, SupportActivity $activity): RedirectResponse
    {
        $this->authorizeGroup($request, $activity);
        $this->audit($request, AuditAction::Delete, $activity);
        $activity->delete();

        return back();
    }

    /** @return array<string, mixed> */
    private function validated(Request $request): array
    {
        $data = $request->validate([
            'school_year_id' => ['required', 'integer', 'exists:school_years,id'],
            'area' => ['required', Rule::enum(SupportArea::class)],
            'type' => ['required', Rule::enum(SupportActivityType::class)],
            'group_id' => ['nullable', 'integer', 'exists:groups,id'],
            'student_id' => ['nullable', 'integer', 'exists:students,id'],
            'occurred_on' => ['nullable', 'date'],
            'semester' => ['nullable', 'integer', 'in:1,2'],
            'quantity' => ['required', 'integer', 'min:1', 'max:1000'],
            'description' => ['nullable', 'string', 'max:5000'],
        ], [], [
            'area' => 'área',
            'type' => 'tipo de actividad',
            'group_id' => 'grupo',
            'student_id' => 'estudiante',
            'occurred_on' => 'fecha',
            'quantity' => 'cantidad',
            'description' => 'descripción',
        ]);

        $yearId = (int) $data['school_year_id'];

        if (! empty($data['student_id']) && empty($data['group_id'])) {
            $data['group_id'] = Enrollment::where('student_id', $data['student_id'])->where('school_year_id', $yearId)->value('group_id');
        }

        if (! empty($data['group_id'])) {
            $group = Group::findOrFail($data['group_id']);
            if ($group->school_year_id !== $yearId) {
                throw ValidationException::withMessages(['group_id' => 'El grupo no pertenece al año escolar elegido.']);
            }
            abort_unless(Scope::inScope($request->user(), $group), 403);
        }

        $type = SupportActivityType::from($data['type']);
        if ($type->isPerStudent() && ! empty($data['student_id'])) {
            $data['quantity'] = 1;
        }
        if (! empty($data['student_id']) && empty($data['group_id'])) {
            throw ValidationException::withMessages(['group_id' => 'Indica el grupo del estudiante.']);
        }

        $date = ! empty($data['occurred_on']) ? CarbonImmutable::parse($data['occurred_on']) : null;
        $data['semester'] = $data['semester'] ?? SupportActivity::semesterFor($date);
        $data['description'] = isset($data['description']) && trim($data['description']) !== '' ? trim($data['description']) : null;

        return $data;
    }

    private function authorizeGroup(Request $request, SupportActivity $activity): void
    {
        if ($activity->group_id !== null) {
            abort_unless(Scope::inScope($request->user(), $activity->group()->firstOrFail()), 403);
        }
    }

    private function audit(Request $request, AuditAction $action, SupportActivity $activity): void
    {
        AuditLog::create([
            'user_id' => $request->user()?->id,
            'action' => $action,
            'auditable_type' => SupportActivity::class,
            'auditable_id' => $activity->id,
            'changes' => $activity->only(['area', 'type', 'group_id', 'student_id', 'occurred_on', 'semester', 'quantity']),
            'ip' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'created_at' => now(),
        ]);
    }
}
