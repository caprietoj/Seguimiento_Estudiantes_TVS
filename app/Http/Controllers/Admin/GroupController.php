<?php

namespace App\Http\Controllers\Admin;

use App\Enums\RoleName;
use App\Http\Controllers\Controller;
use App\Models\GradeLevel;
use App\Models\Group;
use App\Models\SchoolYear;
use App\Models\Section;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class GroupController extends Controller
{
    public function index(Request $request): Response
    {
        $schoolYears = SchoolYear::orderByDesc('starts_on')->get();
        $requestedYearId = $request->integer('anio');
        $activeYearId = SchoolYear::where('is_active', true)->value('id');
        $selectedYearId = $requestedYearId !== 0 ? $requestedYearId : ($activeYearId ?? $schoolYears->first()?->id);

        $groups = Group::with(['section', 'director'])
            ->withCount('enrollments')
            ->where('school_year_id', $selectedYearId)
            ->orderBy('code')
            ->get();

        $allGradeLabels = GradeLevel::options();

        return Inertia::render('admin/grupos', [
            'schoolYears' => $schoolYears->map(fn (SchoolYear $y) => ['id' => $y->id, 'name' => $y->name])->values(),
            'selectedYearId' => $selectedYearId,
            'sections' => Section::orderBy('name')->get(['id', 'name']),
            'directors' => User::role(RoleName::Profesor->value)->orderBy('name')->get(['id', 'name']),
            'gradeLevels' => collect(GradeLevel::activeOptions())->map(fn (string $name, int $value) => ['value' => $value, 'label' => $name])->values(),
            'groups' => $groups->map(fn (Group $g) => [
                'id' => $g->id,
                'code' => $g->code,
                'grade' => $g->grade,
                'grade_label' => $allGradeLabels[$g->grade] ?? ($g->grade.'.°'),
                'section_id' => $g->section_id,
                'section_name' => $g->section->name,
                'director_id' => $g->director_id,
                'director_name' => $g->director?->name,
                'students_count' => $g->enrollments_count,
            ])->values(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'code' => ['required', 'string', 'max:50'],
            'grade' => ['required', 'integer', Rule::in(array_keys(GradeLevel::activeOptions()))],
            'section_id' => ['required', 'integer', 'exists:sections,id'],
            'school_year_id' => ['required', 'integer', 'exists:school_years,id'],
            'director_id' => ['nullable', 'integer', 'exists:users,id'],
        ]);

        Group::create($data);

        return back();
    }

    public function update(Request $request, Group $group): RedirectResponse
    {
        abort_if($group->schoolYear->is_read_only, 403, 'Este año escolar es de solo lectura.');

        $data = $request->validate([
            'code' => ['required', 'string', 'max:50'],
            // El grado propio del grupo sigue siendo válido aunque esté desactivado.
            'grade' => ['required', 'integer', Rule::in([...array_keys(GradeLevel::activeOptions()), $group->grade])],
            'section_id' => ['required', 'integer', 'exists:sections,id'],
            'director_id' => ['nullable', 'integer', 'exists:users,id'],
        ]);

        $group->update($data);

        return back();
    }
}
