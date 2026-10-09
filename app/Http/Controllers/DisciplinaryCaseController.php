<?php

namespace App\Http\Controllers;

use App\Enums\DisciplinaryCaseStatus;
use App\Enums\DisciplinaryCaseType;
use App\Models\DisciplinaryCase;
use App\Models\Student;
use App\Support\Scope;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class DisciplinaryCaseController extends Controller
{
    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'student_id' => ['required', 'integer', 'exists:students,id'],
            'occurred_on' => ['required', 'date'],
            'type' => ['required', Rule::enum(DisciplinaryCaseType::class)],
            'description' => ['required', 'string', 'max:5000'],
            'action_taken' => ['nullable', 'string', 'max:5000'],
        ]);

        $student = Student::findOrFail($data['student_id']);
        $group = $student->currentGroup();
        abort_if($group === null, 422, 'El estudiante no tiene matrícula vigente.');

        if (! Scope::disciplinaryWrite($request->user(), $group)) {
            abort(403);
        }

        DisciplinaryCase::create([
            ...$data,
            'status' => DisciplinaryCaseStatus::Open->value,
            'author_id' => $request->user()->id,
        ]);

        return back();
    }

    public function updateStatus(Request $request, DisciplinaryCase $disciplinaryCase): RedirectResponse
    {
        $data = $request->validate([
            'status' => ['required', Rule::enum(DisciplinaryCaseStatus::class)],
        ]);

        $group = $disciplinaryCase->student->currentGroup();
        abort_if($group === null, 404);

        if (! Scope::disciplinaryWrite($request->user(), $group)) {
            abort(403);
        }

        $disciplinaryCase->update(['status' => $data['status']]);

        return back();
    }
}
