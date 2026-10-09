<?php

namespace App\Http\Controllers;

use App\Models\Grade;
use App\Models\Student;
use App\Models\Subject;
use App\Support\Scope;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class GradeController extends Controller
{
    public function update(Request $request): RedirectResponse
    {
        $min = config('seguimiento.nivel_minimo', 1);
        $max = config('seguimiento.nivel_maximo', 7);

        $data = $request->validate([
            'student_id' => ['required', 'integer', 'exists:students,id'],
            'subject_id' => ['required', 'integer', 'exists:subjects,id'],
            'school_year_id' => ['required', 'integer', 'exists:school_years,id'],
            'period' => ['required', 'integer', 'between:1,3'],
            'level' => ['nullable', 'integer', "between:{$min},{$max}"],
        ]);

        $student = Student::findOrFail($data['student_id']);
        $subject = Subject::findOrFail($data['subject_id']);
        $group = $student->currentGroup();
        abort_if($group === null, 422, 'El estudiante no tiene matrícula vigente.');

        if (! Scope::gradeWrite($request->user(), $group, $subject)) {
            abort(403);
        }

        Grade::updateOrCreate(
            [
                'student_id' => $data['student_id'],
                'subject_id' => $data['subject_id'],
                'school_year_id' => $data['school_year_id'],
                'period' => $data['period'],
            ],
            [
                'level' => $data['level'],
                'recorded_by' => $request->user()->id,
            ]
        );

        return back();
    }
}
