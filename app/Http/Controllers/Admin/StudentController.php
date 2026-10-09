<?php

namespace App\Http\Controllers\Admin;

use App\Enums\AuditAction;
use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Enrollment;
use App\Models\Group;
use App\Models\SchoolYear;
use App\Models\Student;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class StudentController extends Controller
{
    public function index(Request $request): Response
    {
        $schoolYears = SchoolYear::orderByDesc('starts_on')->get();
        $requestedYearId = $request->integer('anio');
        $activeYearId = SchoolYear::where('is_active', true)->value('id');
        $firstYear = $schoolYears->first();
        $selectedYearId = $requestedYearId !== 0 ? $requestedYearId : ($activeYearId ?? ($firstYear instanceof SchoolYear ? $firstYear->id : 0));

        /** @var Collection<int, Group> $groups */
        $groups = $selectedYearId > 0
            ? Group::where('school_year_id', $selectedYearId)->orderBy('code')->get()
            : Group::query()->whereRaw('1 = 0')->get();

        $search = trim((string) $request->input('buscar', ''));
        $filterGroup = $request->input('grupo');
        $filterStatus = $request->input('estado', 'todos');

        $query = Student::query();

        if ($search !== '') {
            $query->where(function ($q) use ($search) {
                $q->where('full_name', 'like', "%{$search}%")
                    ->orWhere('institutional_code', 'like', "%{$search}%");
            });
        }

        if ($filterStatus === 'activos') {
            $query->where('active', true);
        } elseif ($filterStatus === 'inactivos') {
            $query->where('active', false);
        }

        if ($filterGroup === 'sin-grupo' && $selectedYearId > 0) {
            $query->whereDoesntHave('enrollments', fn ($q) => $q->where('school_year_id', $selectedYearId));
        } elseif (is_numeric($filterGroup) && (int) $filterGroup > 0 && $selectedYearId > 0) {
            $query->whereHas('enrollments', fn ($q) => $q->where('school_year_id', $selectedYearId)->where('group_id', (int) $filterGroup));
        }

        /** @var Collection<int, Student> $students */
        $students = $query
            ->with(['enrollments' => fn ($q) => $q->where('school_year_id', $selectedYearId)->with('group')])
            ->orderBy('full_name')
            ->get();

        $totalStudents = Student::count();
        $activeStudents = Student::where('active', true)->count();
        $enrolledInSelectedYear = $selectedYearId > 0
            ? Enrollment::where('school_year_id', $selectedYearId)->count()
            : 0;

        return Inertia::render('admin/estudiantes', [
            'schoolYears' => $schoolYears->map(fn (SchoolYear $y) => ['id' => $y->id, 'name' => $y->name])->values(),
            'selectedYearId' => $selectedYearId,
            'groups' => $groups->map(fn (Group $g) => ['id' => $g->id, 'code' => $g->code])->values(),
            'students' => $students->map(function (Student $s) {
                /** @var ?Enrollment $enrollment */
                $enrollment = $s->enrollments->first();

                return [
                    'id' => $s->id,
                    'institutional_code' => $s->institutional_code,
                    'full_name' => $s->full_name,
                    'active' => (bool) $s->active,
                    'photo_url' => $s->photo_path ? route('fotos.show', $s) : null,
                    'group_id' => $enrollment?->group_id,
                    'group_code' => $enrollment?->group?->code,
                ];
            })->values(),
            'filters' => [
                'buscar' => $search,
                'grupo' => is_string($filterGroup) ? $filterGroup : '',
                'estado' => is_string($filterStatus) ? $filterStatus : 'todos',
            ],
            'stats' => [
                'total' => $totalStudents,
                'activos' => $activeStudents,
                'matriculados' => $enrolledInSelectedYear,
            ],
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'full_name' => ['required', 'string', 'max:255'],
            'institutional_code' => ['nullable', 'string', 'max:50', 'unique:students,institutional_code'],
            'active' => ['boolean'],
            'school_year_id' => ['nullable', 'integer', 'exists:school_years,id'],
            'group_id' => ['nullable', 'integer', 'exists:groups,id'],
        ]);

        DB::transaction(function () use ($data, $request) {
            $student = Student::create([
                'full_name' => $data['full_name'],
                'institutional_code' => ! empty($data['institutional_code']) ? $data['institutional_code'] : null,
                'active' => $data['active'] ?? true,
            ]);

            if (! empty($data['group_id']) && ! empty($data['school_year_id'])) {
                $group = Group::where('id', $data['group_id'])
                    ->where('school_year_id', $data['school_year_id'])
                    ->firstOrFail();

                Enrollment::create([
                    'student_id' => $student->id,
                    'group_id' => $group->id,
                    'school_year_id' => (int) $data['school_year_id'],
                ]);
            }

            AuditLog::create([
                'user_id' => $request->user()?->id,
                'action' => AuditAction::Create,
                'auditable_type' => Student::class,
                'auditable_id' => $student->id,
                'student_id' => $student->id,
                'changes' => [
                    'full_name' => $student->full_name,
                    'institutional_code' => $student->institutional_code,
                    'active' => $student->active,
                    'group_id' => $data['group_id'] ?? null,
                ],
                'ip' => $request->ip(),
                'user_agent' => $request->userAgent(),
                'created_at' => now(),
            ]);
        });

        return back();
    }

    public function update(Request $request, Student $student): RedirectResponse
    {
        $data = $request->validate([
            'full_name' => ['sometimes', 'required', 'string', 'max:255'],
            'institutional_code' => [
                'sometimes',
                'nullable',
                'string',
                'max:50',
                Rule::unique('students', 'institutional_code')->ignore($student->id),
            ],
            'active' => ['sometimes', 'boolean'],
            'school_year_id' => ['nullable', 'integer', 'exists:school_years,id'],
            'group_id' => ['nullable'],
        ]);

        DB::transaction(function () use ($student, $data, $request) {
            $updateData = [];
            if (isset($data['full_name'])) {
                $updateData['full_name'] = $data['full_name'];
            }
            if (array_key_exists('institutional_code', $data)) {
                $updateData['institutional_code'] = ! empty($data['institutional_code']) ? $data['institutional_code'] : null;
            }
            if (isset($data['active'])) {
                $updateData['active'] = (bool) $data['active'];
            }

            if (! empty($updateData)) {
                $student->update($updateData);
            }

            if ($request->has('group_id') && ! empty($data['school_year_id'])) {
                $groupId = $data['group_id'];
                $yearId = (int) $data['school_year_id'];

                if (! empty($groupId)) {
                    $group = Group::where('id', $groupId)
                        ->where('school_year_id', $yearId)
                        ->firstOrFail();

                    Enrollment::updateOrCreate(
                        ['student_id' => $student->id, 'school_year_id' => $yearId],
                        ['group_id' => $group->id]
                    );
                } else {
                    Enrollment::where('student_id', $student->id)
                        ->where('school_year_id', $yearId)
                        ->delete();
                }
            }

            AuditLog::create([
                'user_id' => $request->user()?->id,
                'action' => AuditAction::Update,
                'auditable_type' => Student::class,
                'auditable_id' => $student->id,
                'student_id' => $student->id,
                'changes' => $data,
                'ip' => $request->ip(),
                'user_agent' => $request->userAgent(),
                'created_at' => now(),
            ]);
        });

        return back();
    }

    public function destroy(Request $request, Student $student): RedirectResponse
    {
        DB::transaction(function () use ($student, $request) {
            AuditLog::create([
                'user_id' => $request->user()?->id,
                'action' => AuditAction::Delete,
                'auditable_type' => Student::class,
                'auditable_id' => $student->id,
                'student_id' => $student->id,
                'changes' => [
                    'full_name' => $student->full_name,
                    'institutional_code' => $student->institutional_code,
                ],
                'ip' => $request->ip(),
                'user_agent' => $request->userAgent(),
                'created_at' => now(),
            ]);

            $student->delete();
        });

        return back();
    }
}
