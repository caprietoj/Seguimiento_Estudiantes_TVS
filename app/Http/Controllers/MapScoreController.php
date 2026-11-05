<?php

namespace App\Http\Controllers;

use App\Enums\MapPerformanceLevel;
use App\Models\MapScore;
use App\Models\Setting;
use App\Models\Student;
use App\Support\Scope;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class MapScoreController extends Controller
{
    public function update(Request $request): RedirectResponse
    {
        $maxPeriods = (int) Setting::get('map_periodos', 2);
        $validAreas = array_column(Setting::mapAreas(false), 'key');

        $data = $request->validate([
            'student_id' => ['required', 'integer', 'exists:students,id'],
            'school_year_id' => ['required', 'integer', 'exists:school_years,id'],
            'subject' => ['required', 'string', Rule::in($validAreas)],
            'period' => ['required', 'integer', "between:1,{$maxPeriods}"],
            'rit_score' => ['nullable', 'integer', 'between:100,350'],
            'percentile' => ['nullable', 'integer', 'between:1,99'],
            'performance_level' => ['nullable', Rule::enum(MapPerformanceLevel::class)],
        ]);

        $student = Student::findOrFail($data['student_id']);
        $group = $student->currentGroup();
        abort_if($group === null, 422, 'El estudiante no tiene matrícula vigente.');

        if (! Scope::mapScoreWrite($request->user(), $group)) {
            abort(403);
        }

        // Si se especificó percentil pero no nivel, se calcula automáticamente según normas NWEA
        $performanceLevel = $data['performance_level'] ?? null;
        if (! $performanceLevel && isset($data['percentile'])) {
            $performanceLevel = MapPerformanceLevel::fromPercentile((int) $data['percentile'])?->value;
        }

        // Si todos los valores vienen vacíos, eliminar el registro si existía
        if (empty($data['rit_score']) && empty($data['percentile']) && empty($performanceLevel)) {
            MapScore::where([
                'student_id' => $data['student_id'],
                'school_year_id' => $data['school_year_id'],
                'subject' => $data['subject'],
                'period' => $data['period'],
            ])->delete();

            return back();
        }

        MapScore::updateOrCreate(
            [
                'student_id' => $data['student_id'],
                'school_year_id' => $data['school_year_id'],
                'subject' => $data['subject'],
                'period' => $data['period'],
            ],
            [
                'rit_score' => $data['rit_score'] ?? null,
                'percentile' => $data['percentile'] ?? null,
                'performance_level' => $performanceLevel,
                'recorded_by' => $request->user()->id,
            ]
        );

        return back();
    }
}
