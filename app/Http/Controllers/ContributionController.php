<?php

namespace App\Http\Controllers;

use App\Enums\ContributionField;
use App\Models\Contribution;
use App\Models\FollowUp;
use App\Models\Student;
use App\Support\Scope;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ContributionController extends Controller
{
    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'student_id' => ['required', 'integer', 'exists:students,id'],
            'follow_up_id' => ['required', 'integer', 'exists:follow_ups,id'],
            'field' => ['required', Rule::enum(ContributionField::class)],
            'body' => ['required', 'string', 'max:5000'],
        ]);

        $student = Student::findOrFail($data['student_id']);
        $group = $student->currentGroup();
        abort_if($group === null, 422, 'El estudiante no tiene matrícula vigente.');

        $field = ContributionField::from($data['field']);

        if (! Scope::writesField($request->user(), $group, $field)) {
            abort(403);
        }

        FollowUp::findOrFail($data['follow_up_id']);

        Contribution::create([
            'student_id' => $data['student_id'],
            'follow_up_id' => $data['follow_up_id'],
            'field' => $field->value,
            'body' => $data['body'],
            'author_id' => $request->user()->id,
        ]);

        return back();
    }

    public function destroy(Request $request, Contribution $contribution): RedirectResponse
    {
        $group = $contribution->student->currentGroup();
        abort_if($group === null, 404);

        if (! Scope::canDelete($request->user(), $group, $contribution->author_id)) {
            abort(403);
        }

        $contribution->delete();

        return back();
    }
}
