<?php

namespace App\Http\Controllers\Admin;

use App\Enums\AuditAction;
use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\FollowUp;
use App\Models\SchoolYear;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class FollowUpController extends Controller
{
    public function index(Request $request): Response
    {
        $schoolYears = SchoolYear::orderByDesc('starts_on')->get();
        $requestedYearId = $request->integer('anio');
        $activeYearId = SchoolYear::where('is_active', true)->value('id');
        $firstYear = $schoolYears->first();
        $selectedYearId = $requestedYearId !== 0 ? $requestedYearId : ($activeYearId ?? ($firstYear instanceof SchoolYear ? $firstYear->id : 0));

        $selectedYear = $selectedYearId > 0 ? SchoolYear::find($selectedYearId) : null;

        /** @var Collection<int, FollowUp> $followUps */
        $followUps = $selectedYearId > 0
            ? FollowUp::where('school_year_id', $selectedYearId)
                ->withCount(['groupMeetings', 'contributions', 'strategies'])
                ->orderBy('period')
                ->orderBy('number')
                ->get()
            : FollowUp::query()->whereRaw('1 = 0')->get();

        $distinctPeriods = $followUps->pluck('period')->unique()->count();
        $totalMeetings = (int) $followUps->sum('group_meetings_count');
        $totalContributions = (int) $followUps->sum('contributions_count');

        return Inertia::render('admin/seguimientos', [
            'schoolYears' => $schoolYears->map(fn (SchoolYear $y) => ['id' => $y->id, 'name' => $y->name])->values(),
            'selectedYearId' => $selectedYearId,
            'selectedYearIsReadOnly' => $selectedYear instanceof SchoolYear ? (bool) $selectedYear->is_read_only : false,
            'followUps' => $followUps->map(fn (FollowUp $f) => [
                'id' => $f->id,
                'period' => $f->period,
                'number' => $f->number,
                'label' => $f->label(),
                'short_label' => $f->shortLabel(),
                'meetings_count' => $f->group_meetings_count,
                'contributions_count' => $f->contributions_count,
                'strategies_count' => $f->strategies_count,
                'can_delete' => $f->group_meetings_count === 0 && $f->contributions_count === 0 && $f->strategies_count === 0,
            ])->values(),
            'stats' => [
                'periods' => $distinctPeriods,
                'follow_ups' => $followUps->count(),
                'meetings' => $totalMeetings,
                'contributions' => $totalContributions,
            ],
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'school_year_id' => ['required', 'integer', 'exists:school_years,id'],
            'period' => ['required', 'integer', 'min:1', 'max:10'],
            'number' => ['required', 'integer', 'min:1', 'max:10'],
        ]);

        $schoolYear = SchoolYear::findOrFail($data['school_year_id']);
        abort_if($schoolYear->is_read_only, 403, 'Este año escolar es de solo lectura.');

        $exists = FollowUp::where('school_year_id', $schoolYear->id)
            ->where('period', $data['period'])
            ->where('number', $data['number'])
            ->exists();

        if ($exists) {
            throw ValidationException::withMessages([
                'number' => "El seguimiento (Periodo {$data['period']}, Seguimiento {$data['number']}) ya existe en este año escolar.",
            ]);
        }

        DB::transaction(function () use ($data, $request) {
            $followUp = FollowUp::create($data);

            AuditLog::create([
                'user_id' => $request->user()?->id,
                'action' => AuditAction::Create,
                'auditable_type' => FollowUp::class,
                'auditable_id' => $followUp->id,
                'changes' => $data,
                'ip' => $request->ip(),
                'user_agent' => $request->userAgent(),
                'created_at' => now(),
            ]);
        });

        return back();
    }

    public function update(Request $request, FollowUp $followUp): RedirectResponse
    {
        abort_if($followUp->schoolYear->is_read_only, 403, 'Este año escolar es de solo lectura.');

        $data = $request->validate([
            'period' => ['required', 'integer', 'min:1', 'max:10'],
            'number' => ['required', 'integer', 'min:1', 'max:10'],
        ]);

        $exists = FollowUp::where('school_year_id', $followUp->school_year_id)
            ->where('period', $data['period'])
            ->where('number', $data['number'])
            ->where('id', '!=', $followUp->id)
            ->exists();

        if ($exists) {
            throw ValidationException::withMessages([
                'number' => "Ya existe otro seguimiento con Periodo {$data['period']} y Seguimiento {$data['number']}.",
            ]);
        }

        DB::transaction(function () use ($followUp, $data, $request) {
            $old = ['period' => $followUp->period, 'number' => $followUp->number];
            $followUp->update($data);

            AuditLog::create([
                'user_id' => $request->user()?->id,
                'action' => AuditAction::Update,
                'auditable_type' => FollowUp::class,
                'auditable_id' => $followUp->id,
                'changes' => ['from' => $old, 'to' => $data],
                'ip' => $request->ip(),
                'user_agent' => $request->userAgent(),
                'created_at' => now(),
            ]);
        });

        return back();
    }

    public function destroy(Request $request, FollowUp $followUp): RedirectResponse
    {
        abort_if($followUp->schoolYear->is_read_only, 403, 'Este año escolar es de solo lectura.');

        $inUse = $followUp->contributions()->exists()
            || $followUp->strategies()->exists()
            || $followUp->groupMeetings()->exists();

        if ($inUse) {
            throw ValidationException::withMessages([
                'error' => 'No se puede eliminar este seguimiento porque ya tiene aportes, estrategias o reuniones registradas.',
            ]);
        }

        DB::transaction(function () use ($followUp, $request) {
            AuditLog::create([
                'user_id' => $request->user()?->id,
                'action' => AuditAction::Delete,
                'auditable_type' => FollowUp::class,
                'auditable_id' => $followUp->id,
                'changes' => ['period' => $followUp->period, 'number' => $followUp->number],
                'ip' => $request->ip(),
                'user_agent' => $request->userAgent(),
                'created_at' => now(),
            ]);

            $followUp->delete();
        });

        return back();
    }

    public function generateDefaults(SchoolYear $schoolYear): RedirectResponse
    {
        abort_if($schoolYear->is_read_only, 403, 'Este año escolar es de solo lectura.');

        $periods = (int) config('seguimiento.periodos', 3);
        $perPeriod = (int) config('seguimiento.seguimientos_por_periodo', 2);

        DB::transaction(function () use ($schoolYear, $periods, $perPeriod) {
            for ($p = 1; $p <= $periods; $p++) {
                for ($n = 1; $n <= $perPeriod; $n++) {
                    FollowUp::firstOrCreate([
                        'school_year_id' => $schoolYear->id,
                        'period' => $p,
                        'number' => $n,
                    ]);
                }
            }
        });

        return back();
    }
}
