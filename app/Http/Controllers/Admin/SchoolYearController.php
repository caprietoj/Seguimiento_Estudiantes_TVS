<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\FollowUp;
use App\Models\SchoolYear;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Administración · Años escolares (sección 6.5). Crear un año genera automáticamente sus
 * seguimientos (config('seguimiento.periodos') x config('seguimiento.seguimientos_por_periodo')).
 */
class SchoolYearController extends Controller
{
    public function index(): Response
    {
        $years = SchoolYear::withCount(['groups', 'followUps'])->orderByDesc('starts_on')->get();

        return Inertia::render('admin/anios', [
            'years' => $years->map(fn (SchoolYear $y) => [
                'id' => $y->id,
                'name' => $y->name,
                'starts_on' => $y->starts_on->toDateString(),
                'ends_on' => $y->ends_on->toDateString(),
                'is_active' => $y->is_active,
                'is_read_only' => $y->is_read_only,
                'groups_count' => $y->groups_count,
                'follow_ups_count' => $y->follow_ups_count,
            ])->values(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'starts_on' => ['required', 'date'],
            'ends_on' => ['required', 'date', 'after:starts_on'],
        ]);

        DB::transaction(function () use ($data) {
            $year = SchoolYear::create([
                ...$data,
                'is_active' => false,
                'is_read_only' => false,
            ]);

            $periods = config('seguimiento.periodos', 3);
            $perPeriod = config('seguimiento.seguimientos_por_periodo', 2);

            for ($p = 1; $p <= $periods; $p++) {
                for ($n = 1; $n <= $perPeriod; $n++) {
                    FollowUp::create(['school_year_id' => $year->id, 'period' => $p, 'number' => $n]);
                }
            }
        });

        return back();
    }

    public function update(Request $request, SchoolYear $schoolYear): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'starts_on' => ['required', 'date'],
            'ends_on' => ['required', 'date', 'after:starts_on'],
            'is_read_only' => ['boolean'],
        ]);

        $schoolYear->update($data);

        return back();
    }

    /** Activa este año (lo hace el único is_active=true) sin tocar los demás datos. */
    public function activate(SchoolYear $schoolYear): RedirectResponse
    {
        DB::transaction(function () use ($schoolYear) {
            SchoolYear::where('is_active', true)->update(['is_active' => false]);
            $schoolYear->update(['is_active' => true]);
        });

        return back();
    }
}
