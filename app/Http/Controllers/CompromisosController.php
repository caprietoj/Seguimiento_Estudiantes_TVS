<?php

namespace App\Http\Controllers;

use App\Models\Commitment;
use App\Models\Enrollment;
use App\Models\Group;
use App\Models\SchoolYear;
use App\Support\Scope;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class CompromisosController extends Controller
{
    /**
     * Vista "Compromisos" (sección 6.4): todos los compromisos de los estudiantes
     * visibles, con filtros por grupo, estado y tipo.
     */
    public function index(Request $request): Response
    {
        $user = $request->user();
        $schoolYear = SchoolYear::where('is_active', true)->firstOrFail();
        $visibleGroups = Scope::visibleGroups($user, $schoolYear->id);

        $groupFilter = $request->string('grupo', 'todos')->toString();
        $estadoFilter = $request->string('estado', 'abiertos')->toString();
        $tipoFilter = $request->string('tipo', 'todos')->toString();

        $filterGroups = $groupFilter !== 'todos'
            ? $visibleGroups->filter(fn (Group $g) => (string) $g->id === $groupFilter)->values()
            : $visibleGroups;

        $studentGroupMap = Enrollment::query()
            ->where('school_year_id', $schoolYear->id)
            ->whereIn('group_id', $filterGroups->pluck('id'))
            ->get()
            ->keyBy('student_id');

        $list = Commitment::query()
            ->whereIn('student_id', $studentGroupMap->keys())
            ->with('student')
            ->get();

        $counts = [
            'vencidos' => $list->filter->isLate()->count(),
            'pendientes' => $list->where('status', 'pending')->count(),
            'en_proceso' => $list->where('status', 'in_progress')->count(),
            'cumplidos' => $list->where('status', 'done')->count(),
        ];

        if ($tipoFilter !== 'todos') {
            $list = $list->where('type', $tipoFilter);
        }

        $list = match ($estadoFilter) {
            'abiertos' => $list->filter->isOpen(),
            'vencidos' => $list->filter->isLate(),
            'todos' => $list,
            default => $list->where('status', $estadoFilter),
        };

        $groupsById = $visibleGroups->keyBy('id');

        $items = $list->sortBy('due_on')->values()->map(function (Commitment $c) use ($studentGroupMap, $groupsById, $user) {
            $enrollment = $studentGroupMap->get($c->student_id);
            $group = $enrollment ? $groupsById->get($enrollment->group_id) : null;

            return [
                'id' => $c->id,
                'student_id' => $c->student_id,
                'student_name' => $c->student->full_name,
                'group_code' => $group?->code,
                'type' => $c->type->value,
                'type_label' => $c->type->label(),
                'body' => $c->body,
                'responsible' => $c->responsible,
                'due_on' => $c->due_on?->toDateString(),
                'status' => $c->status->value,
                'status_label' => $c->status->label(),
                'is_late' => $c->isLate(),
                'can_manage' => $group !== null && Scope::canDelete($user, $group, $c->author_id),
            ];
        });

        return Inertia::render('compromisos/index', [
            'groups' => $visibleGroups->map(fn (Group $g) => ['id' => $g->id, 'code' => $g->code])->values(),
            'filters' => ['grupo' => $groupFilter, 'estado' => $estadoFilter, 'tipo' => $tipoFilter],
            'counts' => $counts,
            'items' => $items,
        ]);
    }
}
