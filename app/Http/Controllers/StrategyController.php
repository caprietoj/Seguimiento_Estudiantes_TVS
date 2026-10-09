<?php

namespace App\Http\Controllers;

use App\Enums\StrategyType;
use App\Models\FollowUp;
use App\Models\Group;
use App\Models\Strategy;
use App\Models\Student;
use App\Support\Scope;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class StrategyController extends Controller
{
    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'type' => ['required', Rule::enum(StrategyType::class)],
            'student_id' => ['required_if:type,individual', 'nullable', 'integer', 'exists:students,id'],
            'group_id' => ['required_if:type,group', 'nullable', 'integer', 'exists:groups,id'],
            'follow_up_id' => ['required', 'integer', 'exists:follow_ups,id'],
            'body' => ['required', 'string', 'max:5000'],
            'responsible' => ['nullable', 'string', 'max:255'],
        ]);

        $type = StrategyType::from($data['type']);

        if ($type === StrategyType::Individual) {
            $student = Student::findOrFail($data['student_id']);
            $group = $student->currentGroup();
            abort_if($group === null, 422, 'El estudiante no tiene matrícula vigente.');

            if (! Scope::inScope($request->user(), $group)) {
                abort(403);
            }
        } else {
            $group = Group::findOrFail($data['group_id']);

            if (! Scope::canManageGroupStrategy($request->user(), $group)) {
                abort(403);
            }
        }

        FollowUp::findOrFail($data['follow_up_id']);

        Strategy::create([
            'type' => $type->value,
            'student_id' => $type === StrategyType::Individual ? $data['student_id'] : null,
            'group_id' => $type === StrategyType::Group ? $data['group_id'] : null,
            'follow_up_id' => $data['follow_up_id'],
            'body' => $data['body'],
            'responsible' => $data['responsible'] ?? null,
            'author_id' => $request->user()->id,
        ]);

        return back();
    }

    public function destroy(Request $request, Strategy $strategy): RedirectResponse
    {
        $group = $strategy->type === StrategyType::Group
            ? $strategy->group
            : $strategy->student?->currentGroup();

        abort_if($group === null, 404);

        $allowed = $strategy->type === StrategyType::Group
            ? Scope::canManageGroupStrategy($request->user(), $group)
            : Scope::canDelete($request->user(), $group, $strategy->author_id);

        if (! $allowed) {
            abort(403);
        }

        $strategy->delete();

        return back();
    }
}
