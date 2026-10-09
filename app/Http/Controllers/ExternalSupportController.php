<?php

namespace App\Http\Controllers;

use App\Models\ExternalSupport;
use App\Models\Student;
use App\Support\Scope;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class ExternalSupportController extends Controller
{
    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'student_id' => ['required', 'integer', 'exists:students,id'],
            'provider' => ['required', 'string', 'max:255'],
            'specialty' => ['required', 'string', 'max:255'],
            'frequency' => ['nullable', 'string', 'max:255'],
            'contact' => ['nullable', 'string', 'max:255'],
            'notes' => ['nullable', 'string', 'max:5000'],
        ]);

        $student = Student::findOrFail($data['student_id']);
        $group = $student->currentGroup();
        abort_if($group === null, 422, 'El estudiante no tiene matrícula vigente.');

        if (! Scope::externalSupportsSee($request->user(), $group)) {
            abort(403);
        }

        ExternalSupport::create([
            ...$data,
            'author_id' => $request->user()->id,
        ]);

        return back();
    }

    public function destroy(Request $request, ExternalSupport $externalSupport): RedirectResponse
    {
        $group = $externalSupport->student->currentGroup();
        abort_if($group === null, 404);

        if (! Scope::externalSupportsSee($request->user(), $group)) {
            abort(403);
        }

        $externalSupport->delete();

        return back();
    }
}
