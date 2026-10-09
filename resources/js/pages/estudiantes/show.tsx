import { Button } from '@/components/ui/button';
import SeguimientoLayout from '@/layouts/seguimiento-layout';
import { Head, Link, router, useForm } from '@inertiajs/react';
import { useState } from 'react';

interface Note {
    id: number;
    body: string;
    author_name: string;
    follow_up_label: string;
    can_delete: boolean;
}

interface StrategyReview {
    id: number;
    body: string;
    result: string;
    reviewed_on: string;
    author_name: string;
}

interface IndividualStrategy {
    id: number;
    body: string;
    responsible: string | null;
    since: string;
    last_result: string;
    can_delete: boolean;
    can_review: boolean;
    reviews: StrategyReview[];
}

interface GroupStrategy {
    id: number;
    body: string;
    responsible: string | null;
    last_result: string;
}

interface CommitmentItem {
    id: number;
    body: string;
    responsible: string | null;
    due_on: string | null;
    status: string;
    status_label: string;
    is_late: boolean;
    can_manage: boolean;
    can_delete: boolean;
}

interface ExternalSupportItem {
    id: number;
    provider: string;
    specialty: string;
    frequency: string | null;
    contact: string | null;
    notes: string | null;
}

interface PerformanceRow {
    subject_id: number;
    subject: string;
    levels: Record<number, number | null>;
    can_write: boolean;
}

interface CommitteeItem {
    id: number;
    decision: string;
    decided_on: string;
    notes: string | null;
    author_name: string;
}

interface DisciplinaryItem {
    id: number;
    type: string;
    description: string;
    action_taken: string | null;
    occurred_on: string;
    status: string;
    status_label: string;
}

interface FichaProps {
    student: { id: number; full_name: string; photo_url: string | null };
    group: { id: number; code: string; grade: number; grade_label: string; section: string; director_name: string | null };
    schoolYear: string;
    schoolYears: { id: number; name: string }[];
    selectedYearId: number;
    nav: { index: number | null; total: number; prev_id: number | null; next_id: number | null };
    can: { change_photo: boolean };
    strengths: Note[];
    canWriteStrengths: boolean;
    improvements: Note[];
    canWriteImprovements: boolean;
    subjectAttentions?: Note[];
    canWriteSubjectAttentions?: boolean;
    individualStrategies: IndividualStrategy[];
    canCreateStrategy: boolean;
    groupStrategies: GroupStrategy[];
    commitments: { school: CommitmentItem[]; family: CommitmentItem[]; can_create: boolean };
    responsibles: string[];
    internalSupports: { field: string; label: string; notes: Note[]; can_write: boolean }[];
    externalSupports: { items: ExternalSupportItem[]; can_write: boolean } | null;
    performance: PerformanceRow[];
    committee: { items: CommitteeItem[]; can_write: boolean };
    disciplinary: { items: DisciplinaryItem[]; can_write: boolean } | null;
    stats: { late_commitments: number; open_commitments: number; attention_subjects: number };
    followUpOptions?: { id: number; label: string }[];
    defaultFollowUpId: number | null;
}

function resultBadgeClass(result: string) {
    if (result === 'Funciona') return 'bg-good-soft text-good';
    if (result === 'Funciona parcialmente') return 'bg-warn-soft text-warn';
    if (result === 'No funciona') return 'bg-crit-soft text-crit';
    return 'bg-muted text-muted-foreground';
}

function lvlClass(level: number | null | undefined) {
    if (!level) return 'bg-transparent text-muted-foreground';
    if (level <= 2) return 'bg-crit-soft text-crit';
    if (level === 3) return 'bg-warn-soft text-warn';
    if (level === 4) return 'bg-muted text-foreground';

    return 'bg-good-soft text-good';
}

function DeleteButton({ onConfirm }: { onConfirm: () => void }) {
    const [armed, setArmed] = useState(false);

    return (
        <button
            type="button"
            className={'ml-auto shrink-0 text-[11px] ' + (armed ? 'text-crit font-bold' : 'text-muted-foreground hover:text-crit')}
            onClick={() => {
                if (!armed) {
                    setArmed(true);
                    return;
                }
                onConfirm();
                setArmed(false);
            }}
            onBlur={() => setArmed(false)}
        >
            {armed ? '¿Eliminar? Confirmar' : 'Eliminar'}
        </button>
    );
}

function Section({ id, title, badge, children }: { id: string; title: string; badge?: string; children: React.ReactNode }) {
    return (
        <section id={id} className="border-border bg-card scroll-mt-20 rounded-lg border p-4">
            <header className="mb-2.5 flex flex-wrap items-center gap-2">
                <h3 className="text-primary mr-auto text-[17px] font-semibold">{title}</h3>
                {badge && <span className="bg-muted text-muted-foreground rounded-full px-2 py-0.5 text-[11px] font-semibold">{badge}</span>}
            </header>
            {children}
        </section>
    );
}

function LockedSection({ id, title, who }: { id: string; title: string; who: string }) {
    return (
        <section id={id} className="border-border bg-muted/40 scroll-mt-20 rounded-lg border border-dashed p-4">
            <header className="mb-1 flex items-center gap-2">
                <h3 className="text-muted-foreground text-[17px] font-semibold">{title}</h3>
                <span className="bg-muted text-muted-foreground rounded-full px-2 py-0.5 text-[11px] font-semibold">Restringido</span>
            </header>
            <p className="text-muted-foreground text-sm">Visible solo para {who}.</p>
        </section>
    );
}

function NoteList({ notes, onDelete }: { notes: Note[]; onDelete: (id: number) => void }) {
    if (notes.length === 0) {
        return <p className="text-muted-foreground text-xs italic">Sin registros.</p>;
    }

    return (
        <div className="flex flex-col gap-1.5">
            {notes.map((n) => (
                <div key={n.id} className="bg-muted/60 rounded-md p-2">
                    <p className="text-sm whitespace-pre-wrap">{n.body}</p>
                    <div className="text-muted-foreground mt-1 flex items-center gap-2 text-[11px]">
                        <span className="bg-accent text-primary rounded px-1.5 py-0.5 font-mono">{n.follow_up_label}</span>
                        <span>{n.author_name}</span>
                        {n.can_delete && <DeleteButton onConfirm={() => onDelete(n.id)} />}
                    </div>
                </div>
            ))}
        </div>
    );
}

function AddNoteForm({ studentId, field, followUpId, label }: { studentId: number; field: string; followUpId: number; label: string }) {
    const [open, setOpen] = useState(false);
    const form = useForm({ student_id: studentId, follow_up_id: followUpId, field, body: '' });

    if (!open) {
        return (
            <button
                type="button"
                onClick={() => setOpen(true)}
                className="border-border text-primary hover:border-primary hover:bg-accent mt-2 self-start rounded-md border border-dashed px-2 py-1 text-xs font-semibold"
            >
                + {label}
            </button>
        );
    }

    return (
        <form
            className="bg-accent mt-2 flex flex-col gap-1.5 rounded-md p-2"
            onSubmit={(e) => {
                e.preventDefault();
                form.post('/aportes', {
                    preserveScroll: true,
                    onSuccess: () => {
                        form.reset();
                        setOpen(false);
                    },
                });
            }}
        >
            <textarea
                autoFocus
                required
                value={form.data.body}
                onChange={(e) => form.setData('body', e.target.value)}
                className="border-border bg-background min-h-16 w-full resize-y rounded-md border p-2 text-sm"
            />
            <div className="flex gap-1.5">
                <Button type="submit" size="sm" disabled={form.processing}>
                    Guardar
                </Button>
                <Button type="button" size="sm" variant="ghost" onClick={() => setOpen(false)}>
                    Cancelar
                </Button>
            </div>
        </form>
    );
}

export default function FichaEstudiante(props: FichaProps) {
    const {
        student,
        group,
        schoolYear,
        schoolYears,
        selectedYearId,
        nav,
        can,
        strengths,
        canWriteStrengths,
        improvements,
        canWriteImprovements,
        subjectAttentions = [],
        canWriteSubjectAttentions = false,
        individualStrategies,
        canCreateStrategy,
        groupStrategies,
        commitments,
        responsibles,
        internalSupports,
        externalSupports,
        performance,
        committee,
        disciplinary,
        stats,
        followUpOptions = [],
        defaultFollowUpId,
    } = props;

    const [currentFollowUpId, setCurrentFollowUpId] = useState<number | null>(defaultFollowUpId);
    const activeFollowUpId = currentFollowUpId ?? defaultFollowUpId;

    const photoForm = useForm<{ photo: File | null }>({ photo: null });
    const strategyForm = useForm({
        type: 'individual',
        student_id: student.id,
        group_id: null,
        follow_up_id: activeFollowUpId,
        body: '',
        responsible: '',
    });
    const [strategyOpen, setStrategyOpen] = useState(false);
    const supportForm = useForm({ student_id: student.id, provider: '', specialty: '', frequency: '', contact: '', notes: '' });
    const [supportOpen, setSupportOpen] = useState(false);
    const committeeForm = useForm({
        student_id: student.id,
        decided_on: new Date().toISOString().slice(0, 10),
        decision: 'continue_follow_up',
        notes: '',
    });
    const [committeeOpen, setCommitteeOpen] = useState(false);
    const disciplinaryForm = useForm({
        student_id: student.id,
        occurred_on: new Date().toISOString().slice(0, 10),
        type: 'type_i',
        description: '',
        action_taken: '',
    });
    const [disciplinaryOpen, setDisciplinaryOpen] = useState(false);

    const jumpTargets = [
        ['f-fort', 'Fortalezas'],
        ['f-mej', 'Aspectos de mejora'],
        ['f-ei', 'Estrategias individuales'],
        ['f-eg', 'Estrategias grupales'],
        ['f-comp', 'Compromisos'],
        ['f-resp', 'Responsables'],
        ['f-ai', 'Apoyos internos'],
        ['f-ae', 'Apoyos externos'],
        ['f-des', 'Desempeño académico'],
        ['f-com', 'Comité evaluador'],
        ['f-dis', 'Proceso disciplinario'],
    ];

    function commitmentRows(type: 'school' | 'family') {
        const list = commitments[type];
        if (list.length === 0) {
            return <p className="text-muted-foreground text-xs italic">Sin compromisos.</p>;
        }

        return (
            <div className="flex flex-col gap-2">
                {list.map((c) => (
                    <div key={c.id} className="border-border rounded-md border p-2">
                        <p className="text-sm font-semibold">{c.body}</p>
                        <div className="text-muted-foreground mt-1 flex flex-wrap items-center gap-2 text-xs">
                            <span>{c.responsible}</span>
                            <span className={c.is_late ? 'text-crit font-semibold' : ''}>
                                {c.due_on}
                                {c.is_late ? ' · vencido' : ''}
                            </span>
                            {c.can_delete && <DeleteButton onConfirm={() => router.delete(`/compromisos/${c.id}`, { preserveScroll: true })} />}
                        </div>
                        {c.can_manage ? (
                            <select
                                className="border-border bg-background mt-1 rounded-md border px-2 py-1 text-xs"
                                defaultValue={c.status}
                                onChange={(e) => router.patch(`/compromisos/${c.id}/estado`, { status: e.target.value }, { preserveScroll: true })}
                            >
                                <option value="pending">Pendiente</option>
                                <option value="in_progress">En proceso</option>
                                <option value="done">Cumplido</option>
                                <option value="closed">Cerrado</option>
                            </select>
                        ) : (
                            <span className="bg-muted mt-1 inline-block rounded-full px-2 py-0.5 text-[11px] font-semibold">{c.status_label}</span>
                        )}
                    </div>
                ))}
            </div>
        );
    }

    return (
        <SeguimientoLayout>
            <Head title={`Ficha · ${student.full_name}`} />

            <div className="mb-4 flex flex-wrap items-end gap-4">
                <div className="flex flex-col gap-1">
                    <label className="text-muted-foreground text-[11px] font-semibold tracking-wide uppercase">Estudiante</label>
                    <div className="flex items-center gap-1">
                        <Button
                            variant="ghost"
                            size="sm"
                            disabled={!nav.prev_id}
                            onClick={() => nav.prev_id && router.get(`/estudiantes/${nav.prev_id}`)}
                        >
                            ←
                        </Button>
                        <span className="font-mono text-sm">
                            {nav.index} de {nav.total}
                        </span>
                        <Button
                            variant="ghost"
                            size="sm"
                            disabled={!nav.next_id}
                            onClick={() => nav.next_id && router.get(`/estudiantes/${nav.next_id}`)}
                        >
                            →
                        </Button>
                    </div>
                </div>
                <div className="flex flex-col gap-1">
                    <label className="text-muted-foreground text-[11px] font-semibold tracking-wide uppercase">Año escolar</label>
                    <select
                        className="border-border bg-background rounded-md border px-2 py-1.5 text-sm"
                        value={selectedYearId}
                        onChange={(e) => router.get(`/estudiantes/${student.id}`, { anio: e.target.value })}
                    >
                        {schoolYears.map((y) => (
                            <option key={y.id} value={y.id}>
                                {y.name}
                            </option>
                        ))}
                    </select>
                </div>
                {followUpOptions.length > 0 && (
                    <div className="flex flex-col gap-1">
                        <label className="text-muted-foreground text-[11px] font-semibold tracking-wide uppercase">Periodo y seguimiento</label>
                        <select
                            className="border-border bg-background rounded-md border px-2 py-1.5 text-sm"
                            value={currentFollowUpId ?? ''}
                            onChange={(e) => {
                                const val = Number(e.target.value);
                                setCurrentFollowUpId(val);
                                strategyForm.setData('follow_up_id', val);
                            }}
                        >
                            {followUpOptions.map((f) => (
                                <option key={f.id} value={f.id}>
                                    {f.label}
                                </option>
                            ))}
                        </select>
                    </div>
                )}
                <a
                    href={`/estudiantes/${student.id}/pdf${selectedYearId ? `?anio=${selectedYearId}` : ''}`}
                    target="_blank"
                    rel="noreferrer"
                    className="border-primary text-primary hover:bg-accent ml-auto inline-flex items-center gap-1.5 rounded-md border px-3 py-1.5 text-sm font-semibold transition"
                >
                    <svg className="size-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path
                            strokeLinecap="round"
                            strokeLinejoin="round"
                            strokeWidth={2}
                            d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"
                        />
                    </svg>
                    Exportar a PDF
                </a>
            </div>

            <div className="grid grid-cols-1 items-start gap-4 pb-10 md:grid-cols-[280px_minmax(0,1fr)]">
                <aside className="border-border bg-card sticky top-4 flex flex-col gap-3 rounded-lg border p-4">
                    <div className="flex flex-col items-center gap-2">
                        <div className="bg-primary text-primary-foreground flex h-32 w-32 items-center justify-center overflow-hidden rounded-xl text-3xl font-bold">
                            {student.photo_url ? (
                                <img src={student.photo_url} alt={student.full_name} className="h-full w-full object-cover" />
                            ) : (
                                student.full_name
                                    .split(' ')
                                    .slice(0, 2)
                                    .map((w) => w[0])
                                    .join('')
                            )}
                        </div>
                        {can.change_photo && (
                            <label className="text-primary cursor-pointer text-xs font-semibold hover:underline">
                                {student.photo_url ? 'Cambiar foto' : 'Subir foto'}
                                <input
                                    type="file"
                                    accept="image/*"
                                    className="hidden"
                                    onChange={(e) => {
                                        const file = e.target.files?.[0];
                                        if (!file) return;
                                        photoForm.setData('photo', file);
                                        photoForm.post(`/estudiantes/${student.id}/foto`, { preserveScroll: true, forceFormData: true });
                                    }}
                                />
                            </label>
                        )}
                    </div>
                    <h2 className="text-center text-xl font-semibold">{student.full_name}</h2>
                    <dl className="grid grid-cols-[auto_1fr] gap-x-3 gap-y-1 text-sm">
                        <dt className="text-muted-foreground">Grado</dt>
                        <dd className="font-semibold">
                            {group.grade_label} · {group.code}
                        </dd>
                        <dt className="text-muted-foreground">Sección</dt>
                        <dd className="font-semibold">{group.section}</dd>
                        <dt className="text-muted-foreground">Director(a)</dt>
                        <dd className="font-semibold">{group.director_name ?? 'Sin asignar'}</dd>
                        <dt className="text-muted-foreground">Año escolar</dt>
                        <dd className="font-semibold">{schoolYear}</dd>
                    </dl>
                    <div className="flex flex-wrap gap-1">
                        {stats.open_commitments > 0 && (
                            <span className="bg-warn-soft text-warn rounded-full px-2 py-0.5 text-[11px] font-semibold">
                                {stats.open_commitments} compromiso(s) abierto(s)
                            </span>
                        )}
                        {stats.late_commitments > 0 && (
                            <span className="bg-crit-soft text-crit rounded-full px-2 py-0.5 text-[11px] font-semibold">
                                {stats.late_commitments} vencido(s)
                            </span>
                        )}
                        {stats.attention_subjects > 0 && (
                            <span className="bg-warn-soft text-warn rounded-full px-2 py-0.5 text-[11px] font-semibold">
                                {stats.attention_subjects} asignatura(s) en nivel ≤ 3
                            </span>
                        )}
                    </div>
                    <nav className="border-border flex flex-col border-t pt-2">
                        {jumpTargets.map(([id, label]) => (
                            <a
                                key={id}
                                href={`#${id}`}
                                className="text-muted-foreground hover:bg-muted hover:text-foreground rounded px-1.5 py-1 text-sm"
                            >
                                {label}
                            </a>
                        ))}
                    </nav>
                    <Link href="/reunion" className="text-muted-foreground text-xs hover:underline">
                        ← Volver a la reunión de grupo
                    </Link>
                </aside>

                <div className="flex flex-col gap-3.5">
                    <Section id="f-fort" title="Fortalezas">
                        <NoteList notes={strengths} onDelete={(id) => router.delete(`/aportes/${id}`, { preserveScroll: true })} />
                        {canWriteStrengths && activeFollowUpId && (
                            <AddNoteForm studentId={student.id} field="strength" followUpId={activeFollowUpId} label="Agregar" />
                        )}
                    </Section>

                    <Section id="f-mej" title="Aspectos de mejora">
                        <NoteList notes={improvements} onDelete={(id) => router.delete(`/aportes/${id}`, { preserveScroll: true })} />
                        {canWriteImprovements && activeFollowUpId && (
                            <AddNoteForm studentId={student.id} field="improvement" followUpId={activeFollowUpId} label="Agregar" />
                        )}
                    </Section>

                    <Section id="f-ei" title="Estrategias individuales y su seguimiento">
                        <div className="flex flex-col gap-2">
                            {individualStrategies.length === 0 && (
                                <p className="text-muted-foreground text-xs italic">Sin estrategias individuales.</p>
                            )}
                            {individualStrategies.map((s) => (
                                <div key={s.id} className="border-border rounded-md border p-2.5">
                                    <p className="font-semibold">{s.body}</p>
                                    <div className="text-muted-foreground mt-1 flex flex-wrap items-center gap-2 text-xs">
                                        <span className={'rounded-full px-2 py-0.5 font-semibold ' + resultBadgeClass(s.last_result)}>
                                            {s.last_result}
                                        </span>
                                        <span>
                                            Responsable: {s.responsible ?? '—'} · desde {s.since}
                                        </span>
                                        {s.can_delete && (
                                            <DeleteButton onConfirm={() => router.delete(`/estrategias/${s.id}`, { preserveScroll: true })} />
                                        )}
                                    </div>
                                    {s.reviews.length > 0 && (
                                        <div className="border-border mt-2 flex flex-col gap-1 border-l-2 pl-3">
                                            {s.reviews.map((r) => (
                                                <div key={r.id} className="text-xs">
                                                    <span className="text-muted-foreground font-mono">{r.reviewed_on}</span>{' '}
                                                    <span className={'rounded-full px-1.5 py-0.5 font-semibold ' + resultBadgeClass(r.result)}>
                                                        {r.result}
                                                    </span>
                                                    <p>{r.body}</p>
                                                    <span className="text-muted-foreground">{r.author_name}</span>
                                                </div>
                                            ))}
                                        </div>
                                    )}
                                </div>
                            ))}
                            {canCreateStrategy && defaultFollowUpId && (
                                <>
                                    {!strategyOpen ? (
                                        <button
                                            type="button"
                                            onClick={() => setStrategyOpen(true)}
                                            className="border-border text-primary hover:border-primary hover:bg-accent self-start rounded-md border border-dashed px-2 py-1 text-xs font-semibold"
                                        >
                                            + Agregar estrategia individual
                                        </button>
                                    ) : (
                                        <form
                                            className="bg-accent flex flex-col gap-1.5 rounded-md p-2"
                                            onSubmit={(e) => {
                                                e.preventDefault();
                                                strategyForm.post('/estrategias', {
                                                    preserveScroll: true,
                                                    onSuccess: () => {
                                                        strategyForm.reset('body', 'responsible');
                                                        setStrategyOpen(false);
                                                    },
                                                });
                                            }}
                                        >
                                            <textarea
                                                required
                                                value={strategyForm.data.body}
                                                onChange={(e) => strategyForm.setData('body', e.target.value)}
                                                placeholder="Estrategia"
                                                className="border-border bg-background min-h-16 w-full resize-y rounded-md border p-2 text-sm"
                                            />
                                            <input
                                                value={strategyForm.data.responsible}
                                                onChange={(e) => strategyForm.setData('responsible', e.target.value)}
                                                placeholder="Responsable(s)"
                                                className="border-border bg-background w-full rounded-md border p-2 text-sm"
                                            />
                                            <div className="flex gap-1.5">
                                                <Button type="submit" size="sm" disabled={strategyForm.processing}>
                                                    Guardar
                                                </Button>
                                                <Button type="button" size="sm" variant="ghost" onClick={() => setStrategyOpen(false)}>
                                                    Cancelar
                                                </Button>
                                            </div>
                                        </form>
                                    )}
                                </>
                            )}
                        </div>
                    </Section>

                    <Section id="f-eg" title="Estrategias grupales" badge={`Del grupo ${group.code}`}>
                        <p className="text-muted-foreground mb-2 text-xs">Se crean y se les hace seguimiento en la vista Reunión de grupo.</p>
                        <div className="flex flex-col gap-2">
                            {groupStrategies.length === 0 && <p className="text-muted-foreground text-xs italic">Sin estrategias grupales.</p>}
                            {groupStrategies.map((s) => (
                                <div key={s.id} className="border-border rounded-md border p-2.5">
                                    <p className="font-semibold">{s.body}</p>
                                    <div className="text-muted-foreground mt-1 flex items-center gap-2 text-xs">
                                        <span className={'rounded-full px-2 py-0.5 font-semibold ' + resultBadgeClass(s.last_result)}>
                                            {s.last_result}
                                        </span>
                                        <span>Responsable: {s.responsible ?? '—'}</span>
                                    </div>
                                </div>
                            ))}
                        </div>
                    </Section>

                    <Section id="f-comp" title="Compromisos">
                        <div className="grid grid-cols-1 gap-4 md:grid-cols-2">
                            <div>
                                <h4 className="text-muted-foreground mb-2 text-xs font-semibold tracking-wide uppercase">Del colegio</h4>
                                {commitmentRows('school')}
                            </div>
                            <div>
                                <h4 className="text-muted-foreground mb-2 text-xs font-semibold tracking-wide uppercase">De la familia</h4>
                                {commitmentRows('family')}
                            </div>
                        </div>
                        <p className="text-muted-foreground mt-2 text-xs">
                            La familia no entra a la aplicación: sus compromisos los registra el colegio después de la reunión o la cita.
                        </p>
                        {commitments.can_create && <NewCommitmentForm studentId={student.id} />}
                    </Section>

                    <Section id="f-resp" title="Responsables">
                        <p className="text-muted-foreground mb-2 text-xs">Se arma a partir de las estrategias y los compromisos.</p>
                        <div className="flex flex-wrap gap-1">
                            {responsibles.length === 0 && <span className="text-muted-foreground text-xs">—</span>}
                            {responsibles.map((r) => (
                                <span key={r} className="bg-muted rounded-full px-2 py-0.5 text-[11.5px] font-semibold">
                                    {r}
                                </span>
                            ))}
                        </div>
                    </Section>

                    {internalSupports.length > 0 ? (
                        <Section id="f-ai" title="Apoyos internos (Dpto. de apoyo)" badge="Confidencial">
                            <div className="grid grid-cols-1 gap-4 md:grid-cols-2">
                                {internalSupports.map((area) => (
                                    <div key={area.field}>
                                        <h4 className="text-muted-foreground mb-2 text-xs font-semibold tracking-wide uppercase">{area.label}</h4>
                                        <NoteList notes={area.notes} onDelete={(id) => router.delete(`/aportes/${id}`, { preserveScroll: true })} />
                                        {area.can_write && activeFollowUpId && (
                                            <AddNoteForm studentId={student.id} field={area.field} followUpId={activeFollowUpId} label="Agregar" />
                                        )}
                                    </div>
                                ))}
                            </div>
                        </Section>
                    ) : (
                        <LockedSection id="f-ai" title="Apoyos internos (Dpto. de apoyo)" who="Psicología y Coordinación de la sección" />
                    )}

                    {externalSupports ? (
                        <Section id="f-ae" title="Apoyos externos" badge="Confidencial">
                            <div className="grid grid-cols-1 gap-3 md:grid-cols-2">
                                {externalSupports.items.map((x) => (
                                    <div key={x.id} className="border-border rounded-md border p-2.5">
                                        <p className="font-semibold">{x.provider}</p>
                                        <p className="text-muted-foreground text-xs">
                                            {x.specialty} · {x.frequency ?? '—'} · {x.contact ?? ''}
                                        </p>
                                        {x.notes && <p className="mt-1 text-sm">{x.notes}</p>}
                                        <DeleteButton onConfirm={() => router.delete(`/apoyos-externos/${x.id}`, { preserveScroll: true })} />
                                    </div>
                                ))}
                                {externalSupports.items.length === 0 && (
                                    <p className="text-muted-foreground text-xs italic">Sin apoyos externos registrados.</p>
                                )}
                            </div>
                            {externalSupports.can_write && (
                                <>
                                    {!supportOpen ? (
                                        <button
                                            type="button"
                                            onClick={() => setSupportOpen(true)}
                                            className="border-border text-primary hover:border-primary hover:bg-accent mt-2 self-start rounded-md border border-dashed px-2 py-1 text-xs font-semibold"
                                        >
                                            + Agregar apoyo externo
                                        </button>
                                    ) : (
                                        <form
                                            className="bg-accent mt-2 flex flex-col gap-1.5 rounded-md p-2"
                                            onSubmit={(e) => {
                                                e.preventDefault();
                                                supportForm.post('/apoyos-externos', {
                                                    preserveScroll: true,
                                                    onSuccess: () => {
                                                        supportForm.reset();
                                                        setSupportOpen(false);
                                                    },
                                                });
                                            }}
                                        >
                                            <input
                                                required
                                                placeholder="Profesional o institución"
                                                value={supportForm.data.provider}
                                                onChange={(e) => supportForm.setData('provider', e.target.value)}
                                                className="border-border bg-background rounded-md border p-2 text-sm"
                                            />
                                            <input
                                                required
                                                placeholder="Especialidad"
                                                value={supportForm.data.specialty}
                                                onChange={(e) => supportForm.setData('specialty', e.target.value)}
                                                className="border-border bg-background rounded-md border p-2 text-sm"
                                            />
                                            <input
                                                placeholder="Frecuencia"
                                                value={supportForm.data.frequency}
                                                onChange={(e) => supportForm.setData('frequency', e.target.value)}
                                                className="border-border bg-background rounded-md border p-2 text-sm"
                                            />
                                            <input
                                                placeholder="Contacto"
                                                value={supportForm.data.contact}
                                                onChange={(e) => supportForm.setData('contact', e.target.value)}
                                                className="border-border bg-background rounded-md border p-2 text-sm"
                                            />
                                            <textarea
                                                placeholder="Observación"
                                                value={supportForm.data.notes}
                                                onChange={(e) => supportForm.setData('notes', e.target.value)}
                                                className="border-border bg-background min-h-14 w-full resize-y rounded-md border p-2 text-sm"
                                            />
                                            <div className="flex gap-1.5">
                                                <Button type="submit" size="sm" disabled={supportForm.processing}>
                                                    Guardar
                                                </Button>
                                                <Button type="button" size="sm" variant="ghost" onClick={() => setSupportOpen(false)}>
                                                    Cancelar
                                                </Button>
                                            </div>
                                        </form>
                                    )}
                                </>
                            )}
                        </Section>
                    ) : (
                        <LockedSection id="f-ae" title="Apoyos externos" who="Psicología y Coordinación de la sección" />
                    )}

                    <Section id="f-des" title="Desempeño académico" badge="Escala IB 1–7">
                        <div className="overflow-x-auto">
                            <table className="w-full text-sm">
                                <thead>
                                    <tr className="text-muted-foreground text-left text-[11px] uppercase">
                                        <th className="pb-1">Asignatura</th>
                                        <th className="pb-1 text-center">Periodo 1</th>
                                        <th className="pb-1 text-center">Periodo 2</th>
                                        <th className="pb-1 text-center">Periodo 3</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    {performance.map((row) => (
                                        <tr key={row.subject_id} className="border-border border-t">
                                            <td className="py-1.5">{row.subject}</td>
                                            {[1, 2, 3].map((p) => (
                                                <td key={p} className="py-1.5 text-center">
                                                    {row.can_write ? (
                                                        <select
                                                            defaultValue={row.levels[p] ?? ''}
                                                            className={'w-14 rounded px-1 py-0.5 text-center font-mono ' + lvlClass(row.levels[p])}
                                                            onChange={(e) =>
                                                                router.put(
                                                                    '/desempeno',
                                                                    {
                                                                        student_id: student.id,
                                                                        subject_id: row.subject_id,
                                                                        school_year_id: selectedYearId,
                                                                        period: p,
                                                                        level: e.target.value || null,
                                                                    },
                                                                    { preserveScroll: true },
                                                                )
                                                            }
                                                        >
                                                            <option value="">–</option>
                                                            {[1, 2, 3, 4, 5, 6, 7].map((n) => (
                                                                <option key={n} value={n}>
                                                                    {n}
                                                                </option>
                                                            ))}
                                                        </select>
                                                    ) : (
                                                        <span className={'inline-block w-8 rounded px-1 py-0.5 font-mono ' + lvlClass(row.levels[p])}>
                                                            {row.levels[p] ?? '–'}
                                                        </span>
                                                    )}
                                                </td>
                                            ))}
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        </div>

                        {subjectAttentions.length > 0 && (
                            <div className="mt-4 border-t border-border pt-3">
                                <h4 className="mb-2 text-xs font-semibold tracking-wide text-primary uppercase">
                                    Asignaturas para tener en cuenta · Observaciones pedagógicas
                                </h4>
                                <NoteList
                                    notes={subjectAttentions}
                                    onDelete={(id) => router.delete(`/aportes/${id}`, { preserveScroll: true })}
                                />
                            </div>
                        )}

                        {canWriteSubjectAttentions && activeFollowUpId && (
                            <div className="mt-2">
                                <AddNoteForm
                                    studentId={student.id}
                                    field="subject_attention"
                                    followUpId={activeFollowUpId}
                                    label="Añadir observación de asignatura"
                                />
                            </div>
                        )}
                    </Section>

                    <Section id="f-com" title="Comité evaluador">
                        <div className="flex flex-col gap-2">
                            {committee.items.length === 0 && <p className="text-muted-foreground text-xs italic">Sin decisiones registradas.</p>}
                            {committee.items.map((c) => (
                                <div key={c.id} className="border-border rounded-md border p-2">
                                    <p className="font-semibold">{c.decision}</p>
                                    <p className="text-muted-foreground text-xs">
                                        {c.decided_on} · {c.author_name}
                                        {c.notes ? ' · ' + c.notes : ''}
                                    </p>
                                </div>
                            ))}
                        </div>
                        {committee.can_write && (
                            <>
                                {!committeeOpen ? (
                                    <button
                                        type="button"
                                        onClick={() => setCommitteeOpen(true)}
                                        className="border-border text-primary hover:border-primary hover:bg-accent mt-2 self-start rounded-md border border-dashed px-2 py-1 text-xs font-semibold"
                                    >
                                        + Registrar decisión
                                    </button>
                                ) : (
                                    <form
                                        className="bg-accent mt-2 flex flex-col gap-1.5 rounded-md p-2"
                                        onSubmit={(e) => {
                                            e.preventDefault();
                                            committeeForm.post('/comite', {
                                                preserveScroll: true,
                                                onSuccess: () => {
                                                    committeeForm.reset('notes');
                                                    setCommitteeOpen(false);
                                                },
                                            });
                                        }}
                                    >
                                        <input
                                            type="date"
                                            required
                                            value={committeeForm.data.decided_on}
                                            onChange={(e) => committeeForm.setData('decided_on', e.target.value)}
                                            className="border-border bg-background rounded-md border p-2 text-sm"
                                        />
                                        <select
                                            value={committeeForm.data.decision}
                                            onChange={(e) => committeeForm.setData('decision', e.target.value)}
                                            className="border-border bg-background rounded-md border p-2 text-sm"
                                        >
                                            <option value="continue_follow_up">Continúa seguimiento</option>
                                            <option value="improvement_plan">Plan de mejoramiento</option>
                                            <option value="referral_support">Remisión a Dpto. de apoyo</option>
                                            <option value="family_meeting">Citación a la familia</option>
                                            <option value="no_news">Sin novedad</option>
                                        </select>
                                        <textarea
                                            placeholder="Observaciones"
                                            value={committeeForm.data.notes}
                                            onChange={(e) => committeeForm.setData('notes', e.target.value)}
                                            className="border-border bg-background min-h-14 w-full resize-y rounded-md border p-2 text-sm"
                                        />
                                        <div className="flex gap-1.5">
                                            <Button type="submit" size="sm" disabled={committeeForm.processing}>
                                                Guardar
                                            </Button>
                                            <Button type="button" size="sm" variant="ghost" onClick={() => setCommitteeOpen(false)}>
                                                Cancelar
                                            </Button>
                                        </div>
                                    </form>
                                )}
                            </>
                        )}
                    </Section>

                    {disciplinary ? (
                        <Section id="f-dis" title="Proceso disciplinario" badge="Restringido">
                            <p className="text-muted-foreground mb-2 text-xs">
                                Situaciones Tipo I, II y III según el Manual de Convivencia (Ley 1620 de 2013).
                            </p>
                            <div className="flex flex-col gap-2">
                                {disciplinary.items.length === 0 && (
                                    <p className="text-muted-foreground text-xs italic">Sin situaciones registradas.</p>
                                )}
                                {disciplinary.items.map((d) => (
                                    <div key={d.id} className="border-border rounded-md border p-2">
                                        <p className="font-semibold">
                                            {d.type} · {d.description}
                                        </p>
                                        <p className="text-muted-foreground text-xs">
                                            {d.occurred_on} · Acción: {d.action_taken ?? '—'}
                                        </p>
                                        {disciplinary.can_write ? (
                                            <select
                                                defaultValue={d.status}
                                                className="border-border bg-background mt-1 rounded-md border px-2 py-1 text-xs"
                                                onChange={(e) =>
                                                    router.patch(`/disciplina/${d.id}/estado`, { status: e.target.value }, { preserveScroll: true })
                                                }
                                            >
                                                <option value="open">Abierto</option>
                                                <option value="closed">Cerrado</option>
                                            </select>
                                        ) : (
                                            <span className="bg-muted mt-1 inline-block rounded-full px-2 py-0.5 text-[11px] font-semibold">
                                                {d.status_label}
                                            </span>
                                        )}
                                    </div>
                                ))}
                            </div>
                            {disciplinary.can_write && (
                                <>
                                    {!disciplinaryOpen ? (
                                        <button
                                            type="button"
                                            onClick={() => setDisciplinaryOpen(true)}
                                            className="border-border text-primary hover:border-primary hover:bg-accent mt-2 self-start rounded-md border border-dashed px-2 py-1 text-xs font-semibold"
                                        >
                                            + Registrar situación
                                        </button>
                                    ) : (
                                        <form
                                            className="bg-accent mt-2 flex flex-col gap-1.5 rounded-md p-2"
                                            onSubmit={(e) => {
                                                e.preventDefault();
                                                disciplinaryForm.post('/disciplina', {
                                                    preserveScroll: true,
                                                    onSuccess: () => {
                                                        disciplinaryForm.reset('description', 'action_taken');
                                                        setDisciplinaryOpen(false);
                                                    },
                                                });
                                            }}
                                        >
                                            <input
                                                type="date"
                                                required
                                                value={disciplinaryForm.data.occurred_on}
                                                onChange={(e) => disciplinaryForm.setData('occurred_on', e.target.value)}
                                                className="border-border bg-background rounded-md border p-2 text-sm"
                                            />
                                            <select
                                                value={disciplinaryForm.data.type}
                                                onChange={(e) => disciplinaryForm.setData('type', e.target.value)}
                                                className="border-border bg-background rounded-md border p-2 text-sm"
                                            >
                                                <option value="type_i">Tipo I</option>
                                                <option value="type_ii">Tipo II</option>
                                                <option value="type_iii">Tipo III</option>
                                            </select>
                                            <textarea
                                                required
                                                placeholder="Descripción"
                                                value={disciplinaryForm.data.description}
                                                onChange={(e) => disciplinaryForm.setData('description', e.target.value)}
                                                className="border-border bg-background min-h-14 w-full resize-y rounded-md border p-2 text-sm"
                                            />
                                            <textarea
                                                placeholder="Acción / debido proceso"
                                                value={disciplinaryForm.data.action_taken}
                                                onChange={(e) => disciplinaryForm.setData('action_taken', e.target.value)}
                                                className="border-border bg-background min-h-14 w-full resize-y rounded-md border p-2 text-sm"
                                            />
                                            <div className="flex gap-1.5">
                                                <Button type="submit" size="sm" disabled={disciplinaryForm.processing}>
                                                    Guardar
                                                </Button>
                                                <Button type="button" size="sm" variant="ghost" onClick={() => setDisciplinaryOpen(false)}>
                                                    Cancelar
                                                </Button>
                                            </div>
                                        </form>
                                    )}
                                </>
                            )}
                        </Section>
                    ) : (
                        <LockedSection id="f-dis" title="Proceso disciplinario" who="el director de grupo, Coordinación y Psicología" />
                    )}
                </div>
            </div>
        </SeguimientoLayout>
    );
}

function NewCommitmentForm({ studentId }: { studentId: number }) {
    const [open, setOpen] = useState(false);
    const [type, setType] = useState<'school' | 'family'>('school');
    const form = useForm({ student_id: studentId, type: 'school', body: '', responsible: '', due_on: '' });

    if (!open) {
        return (
            <button
                type="button"
                onClick={() => setOpen(true)}
                className="border-border text-primary hover:border-primary hover:bg-accent mt-3 self-start rounded-md border border-dashed px-2 py-1 text-xs font-semibold"
            >
                + Agregar compromiso
            </button>
        );
    }

    return (
        <form
            className="bg-accent mt-3 flex flex-col gap-1.5 rounded-md p-2"
            onSubmit={(e) => {
                e.preventDefault();
                form.post('/compromisos', {
                    preserveScroll: true,
                    onSuccess: () => {
                        form.reset();
                        setOpen(false);
                    },
                });
            }}
        >
            <div className="flex gap-2">
                <select
                    value={type}
                    onChange={(e) => {
                        const v = e.target.value as 'school' | 'family';
                        setType(v);
                        form.setData('type', v);
                    }}
                    className="border-border bg-background rounded-md border p-2 text-sm"
                >
                    <option value="school">Del colegio</option>
                    <option value="family">De la familia</option>
                </select>
                <input
                    type="date"
                    required
                    value={form.data.due_on}
                    onChange={(e) => form.setData('due_on', e.target.value)}
                    className="border-border bg-background rounded-md border p-2 text-sm"
                />
            </div>
            <textarea
                required
                placeholder="Compromiso"
                value={form.data.body}
                onChange={(e) => form.setData('body', e.target.value)}
                className="border-border bg-background min-h-14 w-full resize-y rounded-md border p-2 text-sm"
            />
            <input
                placeholder="Responsable"
                value={form.data.responsible}
                onChange={(e) => form.setData('responsible', e.target.value)}
                className="border-border bg-background rounded-md border p-2 text-sm"
            />
            <div className="flex gap-1.5">
                <Button type="submit" size="sm" disabled={form.processing}>
                    Guardar
                </Button>
                <Button type="button" size="sm" variant="ghost" onClick={() => setOpen(false)}>
                    Cancelar
                </Button>
            </div>
        </form>
    );
}
