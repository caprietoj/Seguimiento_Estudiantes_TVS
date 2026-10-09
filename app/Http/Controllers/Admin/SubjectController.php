<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\GradeLevel;
use App\Models\Subject;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class SubjectController extends Controller
{
    public function index(): Response
    {
        $subjects = Subject::orderBy('name')->get();

        return Inertia::render('admin/asignaturas', [
            'gradeLevels' => collect(GradeLevel::options())->map(fn (string $name, int $value) => ['value' => $value, 'label' => $name])->values(),
            'subjects' => $subjects->map(fn (Subject $s) => [
                'id' => $s->id,
                'name' => $s->name,
                'active' => $s->active,
                'grades' => $s->gradeLevels(),
            ])->values(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'grades' => ['array'],
            'grades.*' => ['integer'],
        ]);

        $subject = Subject::create(['name' => $data['name'], 'active' => true]);
        $subject->syncGradeLevels($data['grades'] ?? []);

        return back();
    }

    public function update(Request $request, Subject $subject): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'active' => ['boolean'],
            'grades' => ['array'],
            'grades.*' => ['integer'],
        ]);

        $subject->update(['name' => $data['name'], 'active' => $data['active'] ?? $subject->active]);
        $subject->syncGradeLevels($data['grades'] ?? []);

        return back();
    }
}
