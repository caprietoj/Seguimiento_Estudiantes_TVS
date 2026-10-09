<?php

namespace App\Http\Controllers;

use App\Enums\ContributionField;
use App\Models\Commitment;
use App\Models\CommitteeDecision;
use App\Models\Contribution;
use App\Models\DisciplinaryCase;
use App\Models\ExternalSupport;
use App\Models\GradeLevel;
use App\Models\SchoolYear;
use App\Models\Strategy;
use App\Models\Student;
use App\Models\Subject;
use App\Support\Scope;
use Barryvdh\DomPDF\Facade\Pdf;
use Dompdf\Dompdf;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response as HttpResponse;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;

class StudentController extends Controller
{
    /**
     * Pestaña "Ficha del estudiante" del menú superior: sin un estudiante elegido todavía,
     * entra al primero (por nombre) del primer grupo visible para el usuario.
     */
    public function index(Request $request): RedirectResponse
    {
        $user = $request->user();
        $schoolYear = SchoolYear::where('is_active', true)->firstOrFail();
        $group = Scope::visibleGroups($user, $schoolYear->id)->first();
        abort_if($group === null, 404, 'Tu usuario todavía no tiene grupos asignados.');

        $student = $group->students()->orderBy('full_name')->first();
        abort_if($student === null, 404, 'El grupo no tiene estudiantes matriculados.');

        return redirect()->route('estudiantes.show', $student);
    }

    /**
     * Ficha del estudiante (sección 6.2): se alimenta durante todo el año escolar. Cada
     * sección no permitida para el rol se muestra como un bloque "Restringido".
     */
    public function show(Request $request, Student $student): Response
    {
        Gate::authorize('view', $student);

        $data = $this->buildFicha($request, $student);

        if ($data === null) {
            $schoolYears = SchoolYear::orderByDesc('starts_on')->get();
            $activeYearId = SchoolYear::where('is_active', true)->value('id');

            return Inertia::render('estudiantes/sin-matricula', [
                'student' => ['id' => $student->id, 'full_name' => $student->full_name],
                'schoolYears' => $schoolYears->map(fn (SchoolYear $y) => ['id' => $y->id, 'name' => $y->name])->values(),
                'selectedYearId' => $activeYearId ?? $schoolYears->first()?->id,
            ]);
        }

        return Inertia::render('estudiantes/show', $data);
    }

    public function exportPdf(Request $request, Student $student): HttpResponse
    {
        Gate::authorize('view', $student);

        $data = $this->buildFicha($request, $student);
        abort_if($data === null, 404);

        $data['generatedOn'] = now()->locale('es')->translatedFormat('d \d\e F \d\e Y, H:i');
        $data['exportedBy'] = $request->user()?->name;

        // DomPDF no puede (ni debe) fetchear la ruta autenticada de la foto: se incrusta en base64.
        if ($student->photo_path && Storage::disk('local')->exists($student->photo_path)) {
            $data['photoBase64'] = 'data:image/jpeg;base64,'.base64_encode(Storage::disk('local')->get($student->photo_path));
        }

        $pdf = Pdf::loadView('pdf.ficha', $data)->setPaper('letter', 'portrait');

        /** @var Dompdf $domPdf */
        $domPdf = $pdf->getDomPDF();
        $domPdf->render();

        $canvas = $domPdf->getCanvas();
        $fontMetrics = $domPdf->getFontMetrics();
        /** @var string $font */
        $font = $fontMetrics->getFont('DejaVu Sans', 'normal');

        $canvas->page_text(38, 756, 'The Victoria School · Ficha Integral de Seguimiento · Confidencial', $font, 7.5, [0.4, 0.45, 0.52]);
        $canvas->page_text(500, 756, 'Página {PAGE_NUM} de {PAGE_COUNT}', $font, 7.5, [0.4, 0.45, 0.52]);

        return new HttpResponse((string) $domPdf->output(), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="ficha-'.$student->id.'.pdf"',
        ]);
    }

    /** @return array<string, mixed>|null */
    private function buildFicha(Request $request, Student $student): ?array
    {
        $user = $request->user();

        $schoolYears = SchoolYear::orderByDesc('starts_on')->get();
        $activeYear = $schoolYears->firstWhere('is_active', true) ?? $schoolYears->first();
        abort_if($activeYear === null, 404);

        $selectedYearId = $request->integer('anio') ?: $activeYear->id;
        $selectedYear = $schoolYears->firstWhere('id', $selectedYearId) ?? $activeYear;

        $enrollment = $student->enrollmentFor($selectedYear);
        $group = $enrollment?->group;

        if ($group === null) {
            return null;
        }

        $siblings = $group->students()->orderBy('full_name')->get();
        $idx = $siblings->search(fn (Student $s) => $s->id === $student->id);

        $followUps = $selectedYear->followUps()->orderBy('period')->orderBy('number')->get();
        $followUpIds = $followUps->pluck('id');

        $contributions = Contribution::query()
            ->where('student_id', $student->id)
            ->whereIn('follow_up_id', $followUpIds)
            ->with(['author', 'followUp'])
            ->get()
            ->groupBy(fn (Contribution $c) => $c->field->value);

        $noteItem = fn (Contribution $c) => [
            'id' => $c->id,
            'body' => $c->body,
            'author_name' => $c->author->name,
            'follow_up_label' => $c->followUp->shortLabel(),
            'can_delete' => Scope::canDelete($user, $group, $c->author_id),
        ];

        $notesFor = fn (string $field) => ($contributions->get($field) ?? collect())
            ->sortByDesc('created_at')->map($noteItem)->values();

        $individualStrategies = Strategy::where('type', 'individual')->where('student_id', $student->id)
            ->whereIn('follow_up_id', $followUpIds)
            ->with(['reviews.author', 'followUp'])
            ->get()
            ->map(fn (Strategy $s) => [
                'id' => $s->id,
                'body' => $s->body,
                'responsible' => $s->responsible,
                'since' => $s->followUp->shortLabel(),
                'last_result' => $s->reviews->last()?->result?->label() ?? 'Sin evaluar',
                'can_delete' => Scope::canDelete($user, $group, $s->author_id),
                'can_review' => Scope::inScope($user, $group),
                'reviews' => $s->reviews->map(fn ($r) => [
                    'id' => $r->id,
                    'body' => $r->body,
                    'result' => $r->result->label(),
                    'reviewed_on' => $r->reviewed_on->toDateString(),
                    'author_name' => $r->author->name,
                ])->values(),
            ])->values();

        $groupStrategies = Strategy::where('type', 'group')->where('group_id', $group->id)
            ->with('reviews')
            ->get()
            ->map(fn (Strategy $s) => [
                'id' => $s->id,
                'body' => $s->body,
                'responsible' => $s->responsible,
                'last_result' => $s->reviews->last()?->result?->label() ?? 'Sin evaluar',
            ])->values();

        $commitmentItem = fn (Commitment $c) => [
            'id' => $c->id,
            'body' => $c->body,
            'responsible' => $c->responsible,
            'due_on' => $c->due_on?->toDateString(),
            'status' => $c->status->value,
            'status_label' => $c->status->label(),
            'is_late' => $c->isLate(),
            'can_manage' => Scope::canDelete($user, $group, $c->author_id),
            'can_delete' => Scope::canDelete($user, $group, $c->author_id),
        ];
        $allCommitments = $student->commitments()->orderBy('due_on')->get();
        $commitments = [
            'school' => $allCommitments->where('type', 'school')->map($commitmentItem)->values(),
            'family' => $allCommitments->where('type', 'family')->map($commitmentItem)->values(),
            'can_create' => Scope::inScope($user, $group),
        ];

        $areas = collect(ContributionField::supportAreas());
        $internalSupports = $areas->filter(fn (ContributionField $f) => Scope::seesField($user, $group, $f))
            ->map(fn (ContributionField $f) => [
                'field' => $f->value,
                'label' => $f->label(),
                'notes' => $notesFor($f->value),
                'can_write' => Scope::writesField($user, $group, $f),
            ])->values();

        $externalSupports = null;
        if (Scope::externalSupportsSee($user, $group)) {
            $externalSupports = [
                'items' => $student->externalSupports()->get()->map(fn (ExternalSupport $x) => [
                    'id' => $x->id,
                    'provider' => $x->provider,
                    'specialty' => $x->specialty,
                    'frequency' => $x->frequency,
                    'contact' => $x->contact,
                    'notes' => $x->notes,
                ])->values(),
                'can_write' => true,
            ];
        }

        $subjects = Subject::active()->forGrade($group->grade)->orderBy('name')->get();
        $gradesByKey = $student->grades()->where('school_year_id', $selectedYear->id)->get()
            ->keyBy(fn ($g) => $g->subject_id.'|'.$g->period);
        $performance = $subjects->map(function (Subject $subject) use ($gradesByKey, $user, $group) {
            $levels = [];
            for ($p = 1; $p <= 3; $p++) {
                $grade = $gradesByKey->get($subject->id.'|'.$p);
                $levels[$p] = $grade?->level;
            }

            return [
                'subject_id' => $subject->id,
                'subject' => $subject->name,
                'levels' => $levels,
                'can_write' => Scope::gradeWrite($user, $group, $subject),
            ];
        })->values();

        // El comité evaluador lo ve todo el que ve al estudiante (sección 4: "Ve" para profesor/director).
        $committee = [
            'items' => CommitteeDecision::where('student_id', $student->id)->with('author')->orderByDesc('decided_on')->get()
                ->map(fn (CommitteeDecision $c) => [
                    'id' => $c->id,
                    'decision' => $c->decision->label(),
                    'decided_on' => $c->decided_on->toDateString(),
                    'notes' => $c->notes,
                    'author_name' => $c->author->name,
                ])->values(),
            'can_write' => Scope::committeeWrite($user, $group),
        ];

        $disciplinary = null;
        if (Scope::disciplinarySee($user, $group)) {
            $disciplinary = [
                'items' => DisciplinaryCase::where('student_id', $student->id)->orderByDesc('occurred_on')->get()
                    ->map(fn (DisciplinaryCase $d) => [
                        'id' => $d->id,
                        'type' => $d->type->label(),
                        'description' => $d->description,
                        'action_taken' => $d->action_taken,
                        'occurred_on' => $d->occurred_on->toDateString(),
                        'status' => $d->status->value,
                        'status_label' => $d->status->label(),
                    ])->values(),
                'can_write' => Scope::disciplinaryWrite($user, $group),
            ];
        }

        $lateCount = $allCommitments->filter(fn (Commitment $c) => $c->isLate())->count();
        $openCount = $allCommitments->filter(fn (Commitment $c) => $c->isOpen())->count();
        $attentionCount = $subjects->filter(function (Subject $s) use ($gradesByKey) {
            $g = $gradesByKey->get($s->id.'|1');

            return $g && $g->level !== null && $g->level <= config('seguimiento.nivel_atencion', 3);
        })->count();

        return [
            'student' => [
                'id' => $student->id,
                'institutional_code' => $student->institutional_code,
                'full_name' => $student->full_name,
                'photo_url' => $student->photo_path ? route('fotos.show', $student) : null,
            ],
            'group' => [
                'id' => $group->id,
                'code' => $group->code,
                'grade' => $group->grade,
                'grade_label' => GradeLevel::labelFor($group->grade),
                'section' => $group->section->name,
                'director_name' => $group->director?->name,
            ],
            'schoolYear' => $selectedYear->name,
            'schoolYears' => $schoolYears->map(fn (SchoolYear $y) => ['id' => $y->id, 'name' => $y->name])->values(),
            'selectedYearId' => $selectedYear->id,
            'nav' => [
                'index' => $idx === false ? null : $idx + 1,
                'total' => $siblings->count(),
                'prev_id' => $idx !== false && $idx > 0 ? $siblings[$idx - 1]->id : null,
                'next_id' => $idx !== false && $idx < $siblings->count() - 1 ? $siblings[$idx + 1]->id : null,
            ],
            'can' => [
                'change_photo' => Scope::photoWrite($user, $group),
            ],
            'strengths' => $notesFor('strength'),
            'canWriteStrengths' => Scope::writesField($user, $group, ContributionField::Strength),
            'improvements' => $notesFor('improvement'),
            'canWriteImprovements' => Scope::writesField($user, $group, ContributionField::Improvement),
            'subjectAttentions' => $notesFor(ContributionField::SubjectAttention->value),
            'canWriteSubjectAttentions' => Scope::writesField($user, $group, ContributionField::SubjectAttention),
            'individualStrategies' => $individualStrategies,
            'canCreateStrategy' => Scope::inScope($user, $group),
            'groupStrategies' => $groupStrategies,
            'commitments' => $commitments,
            'responsibles' => $student->responsibles(),
            'internalSupports' => $internalSupports,
            'externalSupports' => $externalSupports,
            'performance' => $performance,
            'committee' => $committee,
            'disciplinary' => $disciplinary,
            'stats' => [
                'late_commitments' => $lateCount,
                'open_commitments' => $openCount,
                'attention_subjects' => $attentionCount,
            ],
            'followUpOptions' => $followUps->map(fn ($f) => ['id' => $f->id, 'label' => $f->label()])->values(),
            'defaultFollowUpId' => $followUps->last()?->id,
        ];
    }
}
