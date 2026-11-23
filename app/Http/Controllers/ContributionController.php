<?php

namespace App\Http\Controllers;

use App\Enums\ContributionField;
use App\Enums\SupportFlag;
use App\Models\Contribution;
use App\Models\FollowUp;
use App\Models\Group;
use App\Models\Student;
use App\Support\Scope;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class ContributionController extends Controller
{
    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'student_id' => ['required', 'integer', 'exists:students,id'],
            'follow_up_id' => ['required', 'integer', 'exists:follow_ups,id'],
            'field' => ['required', Rule::enum(ContributionField::class)],
        ]);

        $student = Student::findOrFail($data['student_id']);
        $followUp = FollowUp::findOrFail($data['follow_up_id']);
        $group = $this->groupFor($student, $followUp);
        abort_if($group === null, 422, 'El estudiante no tiene matrícula en ese año escolar.');

        $field = ContributionField::from($data['field']);

        if (! Scope::writesField($request->user(), $group, $field)) {
            abort(403);
        }

        Contribution::create([
            'student_id' => $data['student_id'],
            'follow_up_id' => $data['follow_up_id'],
            'field' => $field->value,
            ...$this->content($request, $field),
            'author_id' => $request->user()->id,
        ]);

        return back();
    }

    /** Corregir una nota del Dpto. de Apoyo (textos y marcas): quien la escribió o quien puede borrarla. */
    public function update(Request $request, Contribution $contribution): RedirectResponse
    {
        $group = $this->groupFor($contribution->student, $contribution->followUp);
        abort_if($group === null, 404);
        abort_unless($contribution->field->isClinical(), 404);

        if (! Scope::canDelete($request->user(), $group, $contribution->author_id)) {
            abort(403);
        }

        $contribution->update($this->content($request, $contribution->field));

        return back();
    }

    public function destroy(Request $request, Contribution $contribution): RedirectResponse
    {
        $group = $this->groupFor($contribution->student, $contribution->followUp);
        abort_if($group === null, 404);

        if (! Scope::canDelete($request->user(), $group, $contribution->author_id)) {
            abort(403);
        }

        $contribution->delete();

        return back();
    }

    /**
     * Grupo del estudiante en el año del seguimiento (no en el año activo): así se puede
     * registrar y corregir en reuniones de años anteriores.
     */
    private function groupFor(Student $student, FollowUp $followUp): ?Group
    {
        return $student->enrollmentFor($followUp->schoolYear)?->group;
    }

    /**
     * Texto del aporte. En las áreas del Dpto. de Apoyo: habilidades, acuerdos, seguimiento a
     * las estrategias y las marcas de la estadística; basta con un texto o una marca.
     *
     * @return array<string, mixed>
     */
    private function content(Request $request, ContributionField $field): array
    {
        if (! $field->isClinical()) {
            return $request->validate(['body' => ['required', 'string', 'max:5000']]);
        }

        $rules = [
            'body' => ['nullable', 'string', 'max:5000'],
            'agreements' => ['nullable', 'string', 'max:5000'],
            'strategy_follow_up' => ['nullable', 'string', 'max:5000'],
        ];
        foreach (SupportFlag::columns() as $flag) {
            $rules[$flag] = ['sometimes', 'boolean'];
        }
        $data = $request->validate($rules);

        $out = [
            'body' => trim((string) ($data['body'] ?? '')),
            'agreements' => trim((string) ($data['agreements'] ?? '')) ?: null,
            'strategy_follow_up' => trim((string) ($data['strategy_follow_up'] ?? '')) ?: null,
        ];
        foreach (SupportFlag::columns() as $flag) {
            $out[$flag] = (bool) ($data[$flag] ?? false);
        }

        $hasText = $out['body'] !== '' || $out['agreements'] !== null || $out['strategy_follow_up'] !== null;
        $hasFlag = in_array(true, array_intersect_key($out, array_flip(SupportFlag::columns())), true);

        if (! $hasText && ! $hasFlag) {
            throw ValidationException::withMessages(['body' => 'Escribe al menos uno de los textos o marca una casilla.']);
        }

        return $out;
    }
}
