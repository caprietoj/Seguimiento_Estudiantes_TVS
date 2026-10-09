<?php

namespace App\Http\Controllers;

use App\Enums\CommitmentStatus;
use App\Enums\CommitmentType;
use App\Models\Commitment;
use App\Models\Student;
use App\Support\Scope;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class CommitmentController extends Controller
{
    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'student_id' => ['required', 'integer', 'exists:students,id'],
            'type' => ['required', Rule::enum(CommitmentType::class)],
            'body' => ['required', 'string', 'max:5000'],
            'responsible' => ['nullable', 'string', 'max:255'],
            'due_on' => ['required', 'date'],
        ]);

        $student = Student::findOrFail($data['student_id']);
        $group = $student->currentGroup();
        abort_if($group === null, 422, 'El estudiante no tiene matrícula vigente.');

        if (! Scope::inScope($request->user(), $group)) {
            abort(403);
        }

        Commitment::create([
            'student_id' => $data['student_id'],
            'type' => $data['type'],
            'body' => $data['body'],
            'responsible' => $data['responsible'] ?? null,
            'due_on' => $data['due_on'],
            'status' => CommitmentStatus::Pending->value,
            'author_id' => $request->user()->id,
        ]);

        return back();
    }

    public function updateStatus(Request $request, Commitment $commitment): RedirectResponse
    {
        $data = $request->validate([
            'status' => ['required', Rule::enum(CommitmentStatus::class)],
        ]);

        $group = $commitment->student->currentGroup();
        abort_if($group === null, 404);

        if (! Scope::canDelete($request->user(), $group, $commitment->author_id)) {
            abort(403);
        }

        $commitment->update(['status' => $data['status']]);

        return back();
    }

    public function destroy(Request $request, Commitment $commitment): RedirectResponse
    {
        $group = $commitment->student->currentGroup();
        abort_if($group === null, 404);

        if (! Scope::canDelete($request->user(), $group, $commitment->author_id)) {
            abort(403);
        }

        $commitment->delete();

        return back();
    }
}
