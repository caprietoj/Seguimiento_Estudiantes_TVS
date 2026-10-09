<?php

namespace App\Http\Controllers;

use App\Enums\CommitteeDecision as CommitteeDecisionEnum;
use App\Models\CommitteeDecision;
use App\Models\Student;
use App\Support\Scope;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class CommitteeDecisionController extends Controller
{
    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'student_id' => ['required', 'integer', 'exists:students,id'],
            'decided_on' => ['required', 'date'],
            'decision' => ['required', Rule::enum(CommitteeDecisionEnum::class)],
            'notes' => ['nullable', 'string', 'max:5000'],
        ]);

        $student = Student::findOrFail($data['student_id']);
        $group = $student->currentGroup();
        abort_if($group === null, 422, 'El estudiante no tiene matrícula vigente.');

        if (! Scope::committeeWrite($request->user(), $group)) {
            abort(403);
        }

        CommitteeDecision::create([
            ...$data,
            'author_id' => $request->user()->id,
        ]);

        return back();
    }
}
