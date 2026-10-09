<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\GradeLevel;
use App\Models\Group;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

/** Administración · Grados (catálogo Preescolar–11.° que usan grupos y asignaturas). */
class GradeLevelController extends Controller
{
    public function index(): Response
    {
        $levels = GradeLevel::orderBy('value')->get();

        return Inertia::render('admin/grados', [
            'levels' => $levels->map(fn (GradeLevel $g) => [
                'id' => $g->id,
                'value' => $g->value,
                'name' => $g->name,
                'active' => $g->active,
                'groups_count' => Group::where('grade', $g->value)->count(),
            ])->values(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:40', 'unique:grade_levels,name'],
            'value' => ['required', 'integer', 'min:0', 'max:15', 'unique:grade_levels,value'],
            'active' => ['sometimes', 'boolean'],
        ]);

        GradeLevel::create($data);

        return back();
    }

    public function update(Request $request, GradeLevel $gradeLevel): RedirectResponse
    {
        // El value no se edita: grupos y grade_subject lo referencian como entero.
        $data = $request->validate([
            'name' => ['sometimes', 'string', 'max:40', Rule::unique('grade_levels', 'name')->ignore($gradeLevel->id)],
            'active' => ['sometimes', 'boolean'],
        ]);

        $gradeLevel->update($data);

        return back();
    }

    public function destroy(GradeLevel $gradeLevel): RedirectResponse
    {
        if (Group::where('grade', $gradeLevel->value)->exists()
            || DB::table('grade_subject')->where('grade', $gradeLevel->value)->exists()) {
            throw ValidationException::withMessages([
                'name' => 'No se puede eliminar: hay grupos o asignaturas que usan este grado. Desactívelo en su lugar.',
            ]);
        }

        $gradeLevel->delete();

        return back();
    }
}
