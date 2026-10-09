import { Button } from '@/components/ui/button';
import {
    DropdownMenu,
    DropdownMenuCheckboxItem,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuLabel,
    DropdownMenuSeparator,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import SeguimientoLayout from '@/layouts/seguimiento-layout';
import { Head, Link, router, useForm } from '@inertiajs/react';
import { ColumnDef, flexRender, getCoreRowModel, RowData, useReactTable } from '@tanstack/react-table';
import {
    AlertCircle,
    BookOpen,
    Calendar,
    Check,
    CheckCircle2,
    ChevronDown,
    ChevronUp,
    Columns3,
    ExternalLink,
    GraduationCap,
    HeartHandshake,
    Lightbulb,
    Maximize2,
    MessageSquare,
    Minimize2,
    Plus,
    RotateCcw,
    Search,
    Shield,
    Sparkles,
    Trash2,
    User,
    Users,
    X,
} from 'lucide-react';
import { useCallback, useEffect, useMemo, useRef, useState } from 'react';

declare module '@tanstack/react-table' {
    // eslint-disable-next-line @typescript-eslint/no-unused-vars
    interface ColumnMeta<TData extends RowData, TValue> {
        support?: boolean;
        label?: string;
    }
}

interface Column {
    key: string;
    label: string;
    type: 'notes' | 'asignaturas' | 'subject_attention' | 'estrategias' | 'responsables';
    support?: boolean;
}

interface Note {
    id: number;
    body: string;
    author_name: string;
    author_role: string;
    can_delete: boolean;
}

interface NotesCellData {
    notes: Note[];
    can_write: boolean;
}

interface AsignaturaItem {
    subject: string;
    level: number;
}

interface SubjectAttentionCellData {
    notes: Note[];
    grades_alert: AsignaturaItem[];
    available_subjects: string[];
    can_write: boolean;
}

interface EstrategiaItem {
    id: number;
    body: string;
    responsible: string | null;
    last_result: string;
    can_delete: boolean;
}

interface EstrategiasCellData {
    items: EstrategiaItem[];
    can_write: boolean;
}

interface StudentRow {
    id: number;
    full_name: string;
    institutional_code?: string | null;
    photo_url?: string | null;
    late_commitments: number;
    disciplinary_open: number;
    attention_subjects_count?: number;
    cells: Record<string, NotesCellData | AsignaturaItem[] | SubjectAttentionCellData | EstrategiasCellData | string[]>;
}

interface GroupStrategy {
    id: number;
    body: string;
    responsible: string | null;
    last_result: string;
    last_review_body: string | null;
    reviews_count: number;
    can_review: boolean;
    can_delete: boolean;
}

interface ReunionProps {
    schoolYear: { id: number; name: string };
    groups: { id: number; code: string; section: string }[];
    followUps: { id: number; label: string }[];
    group: { id: number; code: string; grade: number; section: string; director_name: string | null };
    followUp: { id: number; label: string; period: number };
    meeting: { held_on: string } | null;
    can: { manage_meeting_date: boolean; create_group_strategy: boolean };
    stats: { students: number; open_commitments: number; late_commitments: number };
    groupStrategies: GroupStrategy[];
    columns: Column[];
    hiddenAreas: string[];
    students: StudentRow[];
}

const VIS_KEY = 'reunion:column-visibility';
const SIZE_KEY = 'reunion:column-sizing';

const DEFAULT_SIZE: Record<Column['type'], number> = {
    notes: 250,
    asignaturas: 190,
    subject_attention: 290,
    estrategias: 260,
    responsables: 160,
};

function loadVisibility(): Record<string, boolean> {
    try {
        const raw = localStorage.getItem(VIS_KEY);
        const parsed: unknown = raw ? JSON.parse(raw) : null;
        if (parsed && typeof parsed === 'object' && !Array.isArray(parsed)) {
            const out: Record<string, boolean> = {};
            for (const [k, v] of Object.entries(parsed as Record<string, unknown>)) {
                if (typeof v === 'boolean') out[k] = v;
            }
            return out;
        }
    } catch {
        /* preferencias corruptas: ignorar */
    }
    return {};
}

function loadSizing(): Record<string, number> {
    try {
        const raw = localStorage.getItem(SIZE_KEY);
        const parsed: unknown = raw ? JSON.parse(raw) : null;
        if (parsed && typeof parsed === 'object' && !Array.isArray(parsed)) {
            const out: Record<string, number> = {};
            for (const [k, v] of Object.entries(parsed as Record<string, unknown>)) {
                if (typeof v === 'number' && Number.isFinite(v) && v > 0) out[k] = v;
            }
            return out;
        }
    } catch {
        /* preferencias corruptas: ignorar */
    }
    return {};
}

function navigate(groupId: number, followUpId: number) {
    router.get(`/reunion/${groupId}/${followUpId}`, {}, { preserveScroll: true });
}

function DeleteButton({ onConfirm }: { onConfirm: () => void }) {
    const [armed, setArmed] = useState(false);

    return (
        <button
            type="button"
            className={
                'inline-flex items-center gap-1 rounded px-1.5 py-0.5 text-[10.5px] transition ' +
                (armed ? 'bg-crit font-semibold text-white' : 'text-muted-foreground hover:bg-crit/10 hover:text-crit')
            }
            onClick={() => {
                if (!armed) {
                    setArmed(true);
                    return;
                }
                onConfirm();
                setArmed(false);
            }}
            onBlur={() => setArmed(false)}
            title={armed ? 'Haz clic de nuevo para confirmar eliminación' : 'Eliminar aporte'}
        >
            <Trash2 className="size-3" />
            {armed ? 'Confirmar' : ''}
        </button>
    );
}

function NoteItem({ note, field, onDelete }: { note: Note; field: string; onDelete: () => void }) {
    const isStrength = field === 'strength';
    const isImprovement = field === 'improvement';

    return (
        <div
            className={
                'relative flex flex-col gap-1 rounded-md border p-2 text-xs transition ' +
                (isStrength
                    ? 'border-emerald-200 bg-emerald-50/40 text-emerald-950'
                    : isImprovement
                      ? 'border-amber-200 bg-amber-50/40 text-amber-950'
                      : 'border-border/70 bg-card text-foreground')
            }
        >
            <p className="leading-relaxed whitespace-pre-wrap">{note.body}</p>
            <div className="text-muted-foreground mt-1 flex items-center justify-between gap-1.5 border-t border-black/5 pt-1 text-[10.5px]">
                <span className="truncate">
                    <strong className="text-foreground/85 font-semibold">{note.author_name}</strong>
                    {note.author_role && <span className="opacity-75"> · {note.author_role}</span>}
                </span>
                {note.can_delete && <DeleteButton onConfirm={onDelete} />}
            </div>
        </div>
    );
}

function AddNoteForm({ studentId, followUpId, field }: { studentId: number; followUpId: number; field: string }) {
    const [open, setOpen] = useState(false);
    const form = useForm({ student_id: studentId, follow_up_id: followUpId, field, body: '' });

    if (!open) {
        return (
            <button
                type="button"
                onClick={() => setOpen(true)}
                className="text-primary hover:border-primary/80 hover:bg-primary/5 border-primary/40 inline-flex items-center gap-1 self-start rounded-md border border-dashed px-2 py-1 text-[11px] font-semibold transition"
            >
                <Plus className="size-3" />
                Añadir aporte
            </button>
        );
    }

    return (
        <form
            className="border-primary/30 bg-primary/5 flex flex-col gap-1.5 rounded-md border p-2.5 shadow-sm"
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
                onKeyDown={(e) => {
                    if (e.key === 'Escape') setOpen(false);
                    if (e.key === 'Enter' && (e.ctrlKey || e.metaKey)) {
                        e.currentTarget.form?.requestSubmit();
                    }
                }}
                className="border-border bg-background focus:ring-primary min-h-18 w-full resize-y rounded-md border p-2 text-xs leading-relaxed focus:ring-1 focus:outline-none"
                placeholder="Escribe el aporte formativo o académico..."
            />
            <div className="flex items-center justify-between">
                <span className="text-muted-foreground text-[10px]">Ctrl+Enter para guardar</span>
                <div className="flex gap-1.5">
                    <Button type="button" size="sm" variant="ghost" className="h-7 px-2 text-xs" onClick={() => setOpen(false)}>
                        Cancelar
                    </Button>
                    <Button type="submit" size="sm" className="h-7 px-2.5 text-xs font-semibold" disabled={form.processing}>
                        Guardar
                    </Button>
                </div>
            </div>
        </form>
    );
}

function AddSubjectNoteForm({
    studentId,
    followUpId,
    availableSubjects,
    initialSubject = '',
    onDone,
}: {
    studentId: number;
    followUpId: number;
    availableSubjects: string[];
    initialSubject?: string;
    onDone: () => void;
}) {
    const isInitialKnown = initialSubject ? availableSubjects.includes(initialSubject) : false;
    const [selectedSubject, setSelectedSubject] = useState(isInitialKnown ? initialSubject : initialSubject ? '__custom__' : '');
    const [customSubject, setCustomSubject] = useState(isInitialKnown ? '' : initialSubject);
    const [bodyText, setBodyText] = useState('');
    const [submitting, setSubmitting] = useState(false);

    const activeSubjectName = selectedSubject === '__custom__' ? customSubject.trim() : selectedSubject.trim();

    const handleSubmit = (e: React.FormEvent) => {
        e.preventDefault();
        const noteTrimmed = bodyText.trim();
        if (!noteTrimmed) return;

        const finalBody = activeSubjectName ? `${activeSubjectName}: ${noteTrimmed}` : noteTrimmed;

        setSubmitting(true);
        router.post(
            '/aportes',
            {
                student_id: studentId,
                follow_up_id: followUpId,
                field: 'subject_attention',
                body: finalBody,
            },
            {
                preserveScroll: true,
                onSuccess: () => {
                    setSubmitting(false);
                    onDone();
                },
                onError: () => {
                    setSubmitting(false);
                },
            },
        );
    };

    return (
        <form
            className="border-primary/40 bg-card flex flex-col gap-2 rounded-lg border p-2.5 shadow-sm text-xs"
            onSubmit={handleSubmit}
        >
            <div className="flex flex-col gap-1">
                <label className="text-[10px] font-bold text-primary uppercase tracking-wider">
                    Asignatura
                </label>
                <select
                    className="border-border bg-background focus:ring-primary w-full rounded-md border px-2 py-1 text-xs focus:ring-1 focus:outline-none"
                    value={selectedSubject}
                    onChange={(e) => setSelectedSubject(e.target.value)}
                >
                    <option value="">Seleccionar asignatura de la lista...</option>
                    {availableSubjects.map((s) => (
                        <option key={s} value={s}>
                            {s}
                        </option>
                    ))}
                    <option value="__custom__">Escribir otra asignatura manualmente...</option>
                </select>

                {selectedSubject === '__custom__' && (
                    <input
                        autoFocus
                        type="text"
                        placeholder="Nombre de la asignatura (ej. Filosofía, Cine, ToK...)"
                        value={customSubject}
                        onChange={(e) => setCustomSubject(e.target.value)}
                        className="border-border bg-background focus:ring-primary mt-1 w-full rounded-md border px-2 py-1 text-xs focus:ring-1 focus:outline-none"
                    />
                )}
            </div>

            <div className="flex flex-col gap-1">
                <label className="text-[10px] font-bold text-muted-foreground uppercase tracking-wider">
                    Observación pedagógica
                </label>
                <textarea
                    required
                    value={bodyText}
                    onChange={(e) => setBodyText(e.target.value)}
                    onKeyDown={(e) => {
                        if (e.key === 'Escape') onDone();
                        if (e.key === 'Enter' && (e.ctrlKey || e.metaKey)) {
                            e.currentTarget.form?.requestSubmit();
                        }
                    }}
                    className="border-border bg-background focus:ring-primary min-h-16 w-full resize-y rounded-md border p-2 text-xs leading-relaxed focus:ring-1 focus:outline-none"
                    placeholder="Vacíos conceptuales, aspectos a afianzar o recomendaciones pedagógicas..."
                />
            </div>

            <div className="flex items-center justify-between pt-0.5">
                <span className="text-muted-foreground text-[10px]">Ctrl+Enter para guardar</span>
                <div className="flex gap-1.5">
                    <Button type="button" size="sm" variant="ghost" className="h-7 px-2 text-xs" onClick={onDone}>
                        Cancelar
                    </Button>
                    <Button type="submit" size="sm" className="h-7 px-2.5 text-xs font-semibold" disabled={submitting || !bodyText.trim()}>
                        Guardar
                    </Button>
                </div>
            </div>
        </form>
    );
}

function SubjectAttentionCard({
    note,
    gradesAlert,
    onDelete,
}: {
    note: Note;
    gradesAlert: AsignaturaItem[];
    onDelete: () => void;
}) {
    const match = note.body.match(/^([^:\n]{2,45}):\s*([\s\S]+)$/);
    const subjectName = match ? match[1].trim() : null;
    const observationText = match ? match[2].trim() : note.body;

    const matchedGrade = subjectName
        ? gradesAlert.find(
              (g) =>
                  g.subject.toLowerCase() === subjectName.toLowerCase() ||
                  g.subject.toLowerCase().includes(subjectName.toLowerCase()) ||
                  subjectName.toLowerCase().includes(g.subject.toLowerCase()),
          )
        : null;

    return (
        <div className="border-border/80 bg-card hover:border-primary/40 relative flex flex-col gap-1.5 rounded-lg border p-2 text-xs shadow-2xs transition">
            <div className="flex items-center justify-between gap-1.5">
                <div className="flex flex-wrap items-center gap-1.5">
                    {subjectName ? (
                        <span className="inline-flex items-center gap-1 rounded-md bg-primary/10 px-1.5 py-0.5 text-[10.5px] font-bold text-primary">
                            <BookOpen className="size-3" />
                            {subjectName}
                        </span>
                    ) : (
                        <span className="inline-flex items-center gap-1 rounded-md bg-muted px-1.5 py-0.5 text-[10.5px] font-semibold text-muted-foreground">
                            <GraduationCap className="size-3" />
                            General
                        </span>
                    )}

                    {matchedGrade && (
                        <span
                            className={
                                'inline-flex items-center gap-0.5 rounded px-1.5 py-0.2 text-[10px] font-bold ' +
                                (matchedGrade.level <= 2
                                    ? 'bg-red-100 text-red-700 border border-red-200'
                                    : 'bg-amber-100 text-amber-800 border border-amber-200')
                            }
                            title={`Nivel ${matchedGrade.level} en evaluación oficial`}
                        >
                            <span>Nivel {matchedGrade.level}</span>
                            <span className="text-[9px]">⚠️</span>
                        </span>
                    )}
                </div>

                {note.can_delete && <DeleteButton onConfirm={onDelete} />}
            </div>

            <p className="text-foreground/90 leading-relaxed whitespace-pre-wrap text-[11px]">
                {observationText}
            </p>

            <div className="text-muted-foreground mt-0.5 flex items-center justify-between border-t border-black/5 pt-1 text-[10px]">
                <span className="truncate">
                    <strong className="text-foreground/80 font-semibold">{note.author_name}</strong>
                    {note.author_role && <span className="opacity-75"> · {note.author_role}</span>}
                </span>
            </div>
        </div>
    );
}

function SubjectAttentionCell({
    studentId,
    followUpId,
    data,
}: {
    studentId: number;
    followUpId: number;
    data: SubjectAttentionCellData;
}) {
    const [adding, setAdding] = useState(false);
    const [preselectedSubject, setPreselectedSubject] = useState('');

    const notes = data.notes ?? [];
    const gradesAlert = data.grades_alert ?? [];
    const availableSubjects = data.available_subjects ?? [];

    const coveredNames = notes
        .map((n) => {
            const m = n.body.match(/^([^:\n]{2,45}):/);
            return m ? m[1].trim().toLowerCase() : '';
        })
        .filter(Boolean);

    const uncoveredAlerts = gradesAlert.filter(
        (g) => !coveredNames.some((c) => c.includes(g.subject.toLowerCase()) || g.subject.toLowerCase().includes(c)),
    );

    const openWithSubject = (subj: string) => {
        setPreselectedSubject(subj);
        setAdding(true);
    };

    return (
        <div className="flex flex-col gap-1.5">
            {notes.map((n) => (
                <SubjectAttentionCard
                    key={n.id}
                    note={n}
                    gradesAlert={gradesAlert}
                    onDelete={() => router.delete(`/aportes/${n.id}`, { preserveScroll: true })}
                />
            ))}

            {uncoveredAlerts.length > 0 && (
                <div className="rounded-md border border-amber-200 bg-amber-50/70 p-1.5 text-xs">
                    <span className="text-[10px] font-bold text-amber-900 block mb-1">
                        ⚠️ Calificaciones ≤ 3 sin observación:
                    </span>
                    <div className="flex flex-wrap gap-1">
                        {uncoveredAlerts.map((a) => (
                            <button
                                key={a.subject}
                                type="button"
                                onClick={() => openWithSubject(a.subject)}
                                className="inline-flex items-center gap-1 rounded bg-amber-100/90 hover:bg-amber-200 border border-amber-300 px-1.5 py-0.5 text-[10.5px] font-semibold text-amber-900 transition cursor-pointer"
                                title="Clic para registrar aporte para esta asignatura"
                            >
                                <span>{a.subject}</span>
                                <span
                                    className={
                                        'size-3.5 rounded text-[9px] text-white flex items-center justify-center font-bold ' +
                                        (a.level <= 2 ? 'bg-red-600' : 'bg-amber-600')
                                    }
                                >
                                    {a.level}
                                </span>
                                {data.can_write && <Plus className="size-2.5 opacity-70" />}
                            </button>
                        ))}
                    </div>
                </div>
            )}

            {notes.length === 0 && gradesAlert.length === 0 && !data.can_write && (
                <span className="text-muted-foreground/50 text-[11px] italic">Sin registros</span>
            )}

            {notes.length === 0 && gradesAlert.length === 0 && data.can_write && !adding && (
                <div className="flex items-center gap-1 text-[11px] text-emerald-700 font-medium pb-1">
                    <CheckCircle2 className="size-3 text-emerald-600 shrink-0" />
                    <span>Al día (sin alertas ≤ 3)</span>
                </div>
            )}

            {data.can_write && (
                <>
                    {adding ? (
                        <AddSubjectNoteForm
                            studentId={studentId}
                            followUpId={followUpId}
                            availableSubjects={availableSubjects}
                            initialSubject={preselectedSubject}
                            onDone={() => {
                                setAdding(false);
                                setPreselectedSubject('');
                            }}
                        />
                    ) : (
                        <button
                            type="button"
                            onClick={() => {
                                setPreselectedSubject('');
                                setAdding(true);
                            }}
                            className="text-primary hover:border-primary/80 hover:bg-primary/5 border-primary/40 inline-flex items-center gap-1 self-start rounded-md border border-dashed px-2 py-1 text-[11px] font-semibold transition cursor-pointer"
                        >
                            <Plus className="size-3" />
                            Añadir observación de asignatura
                        </button>
                    )}
                </>
            )}
        </div>
    );
}

function AddStrategyForm({
    studentId = null,
    groupId = null,
    followUpId,
    label,
}: {
    studentId?: number | null;
    groupId?: number | null;
    followUpId: number;
    label: string;
}) {
    const [open, setOpen] = useState(false);
    const form = useForm({
        type: studentId ? 'individual' : 'group',
        student_id: studentId,
        group_id: groupId,
        follow_up_id: followUpId,
        body: '',
        responsible: '',
    });

    if (!open) {
        return (
            <button
                type="button"
                onClick={() => setOpen(true)}
                className="text-primary hover:border-primary/80 hover:bg-primary/5 border-primary/40 inline-flex items-center gap-1 self-start rounded-md border border-dashed px-2 py-1 text-[11px] font-semibold transition"
            >
                <Plus className="size-3" />
                {label}
            </button>
        );
    }

    return (
        <form
            className="border-primary/30 bg-primary/5 flex flex-col gap-1.5 rounded-md border p-2.5 shadow-sm"
            onSubmit={(e) => {
                e.preventDefault();
                form.post('/estrategias', {
                    preserveScroll: true,
                    onSuccess: () => {
                        form.reset('body', 'responsible');
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
                onKeyDown={(e) => {
                    if (e.key === 'Escape') setOpen(false);
                    if (e.key === 'Enter' && (e.ctrlKey || e.metaKey)) {
                        e.currentTarget.form?.requestSubmit();
                    }
                }}
                className="border-border bg-background focus:ring-primary min-h-16 w-full resize-y rounded-md border p-2 text-xs focus:ring-1 focus:outline-none"
                placeholder="Descripción de la estrategia pedagógica..."
            />
            <input
                value={form.data.responsible}
                onChange={(e) => form.setData('responsible', e.target.value)}
                className="border-border bg-background focus:ring-primary w-full rounded-md border px-2 py-1 text-xs focus:ring-1 focus:outline-none"
                placeholder="Responsable(s) de seguimiento..."
            />
            <div className="flex justify-end gap-1.5 pt-1">
                <Button type="button" size="sm" variant="ghost" className="h-7 px-2 text-xs" onClick={() => setOpen(false)}>
                    Cancelar
                </Button>
                <Button type="submit" size="sm" className="h-7 px-2.5 text-xs font-semibold" disabled={form.processing}>
                    Guardar
                </Button>
            </div>
        </form>
    );
}

function AddReviewForm({ strategyId }: { strategyId: number }) {
    const [open, setOpen] = useState(false);
    const form = useForm({ body: '', result: 'partially' });

    if (!open) {
        return (
            <button
                type="button"
                onClick={() => setOpen(true)}
                className="text-primary inline-flex items-center gap-1 text-[11px] font-semibold hover:underline"
            >
                <Plus className="size-3" />
                Registrar seguimiento
            </button>
        );
    }

    return (
        <form
            className="border-border bg-background flex flex-col gap-1.5 rounded-md border p-2 text-xs shadow-sm"
            onSubmit={(e) => {
                e.preventDefault();
                form.post(`/estrategias/${strategyId}/seguimientos`, {
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
                className="border-border bg-background focus:ring-primary min-h-14 w-full resize-y rounded-md border p-1.5 text-xs focus:ring-1 focus:outline-none"
                placeholder="¿Cómo ha evolucionado la estrategia?"
            />
            <select
                value={form.data.result}
                onChange={(e) => form.setData('result', e.target.value)}
                className="border-border bg-background w-full rounded-md border px-2 py-1 text-xs"
            >
                <option value="works">Funciona</option>
                <option value="partially">Funciona parcialmente</option>
                <option value="does_not_work">No funciona</option>
            </select>
            <div className="flex justify-end gap-1.5 pt-1">
                <Button type="button" size="sm" variant="ghost" className="h-6 px-2 text-xs" onClick={() => setOpen(false)}>
                    Cancelar
                </Button>
                <Button type="submit" size="sm" className="h-6 px-2 text-xs" disabled={form.processing}>
                    Guardar
                </Button>
            </div>
        </form>
    );
}

function resultBadgeClass(result: string) {
    if (result === 'Funciona') return 'bg-emerald-100 text-emerald-800 border-emerald-300';
    if (result === 'Funciona parcialmente') return 'bg-amber-100 text-amber-800 border-amber-300';
    if (result === 'No funciona') return 'bg-red-100 text-red-800 border-red-300';
    return 'bg-muted text-muted-foreground border-border';
}

function StudentCell({ student, index }: { student: StudentRow; index: number }) {
    const initials = student.full_name
        .split(' ')
        .filter(Boolean)
        .slice(0, 2)
        .map((w) => w[0]?.toUpperCase())
        .join('');

    return (
        <div className="flex items-start gap-2.5">
            <span className="text-muted-foreground/60 w-5 shrink-0 pt-0.5 text-right font-mono text-[11px] font-semibold select-none">
                {String(index + 1).padStart(2, '0')}
            </span>

            <div className="bg-primary/10 border-primary/20 text-primary flex size-8 shrink-0 items-center justify-center overflow-hidden rounded-full border text-xs font-bold shadow-xs">
                {student.photo_url ? <img src={student.photo_url} alt={student.full_name} className="size-full object-cover" /> : initials}
            </div>

            <div className="min-w-0 flex-1">
                <div className="flex items-center gap-1.5">
                    <Link
                        href={`/estudiantes/${student.id}`}
                        className="text-primary group-hover/row:text-primary truncate text-xs leading-snug font-bold hover:underline"
                        title="Ver ficha integral del estudiante"
                    >
                        {student.full_name}
                    </Link>
                    <Link
                        href={`/estudiantes/${student.id}`}
                        target="_blank"
                        rel="noreferrer"
                        className="text-muted-foreground/40 hover:text-primary shrink-0 transition"
                        title="Abrir ficha en nueva pestaña"
                    >
                        <ExternalLink className="size-3" />
                    </Link>
                </div>

                <div className="mt-1 flex flex-wrap gap-1">
                    {student.late_commitments > 0 && (
                        <span className="bg-crit-soft text-crit border-crit/20 py-0.2 inline-flex items-center gap-0.5 rounded-full border px-1.5 text-[10px] font-semibold">
                            ⚠️ {student.late_commitments} vencido(s)
                        </span>
                    )}
                    {student.disciplinary_open > 0 && (
                        <span className="py-0.2 inline-flex items-center gap-0.5 rounded-full border border-amber-300 bg-amber-100 px-1.5 text-[10px] font-semibold text-amber-900">
                            ⚖️ Disciplinario
                        </span>
                    )}
                    {student.attention_subjects_count !== undefined && student.attention_subjects_count > 0 && (
                        <span className="py-0.2 inline-flex items-center gap-0.5 rounded-full border border-orange-200 bg-orange-50 px-1.5 text-[10px] font-medium text-orange-800">
                            📚 {student.attention_subjects_count} en atención
                        </span>
                    )}
                </div>
            </div>
        </div>
    );
}

function ContentCell({ col, student, followUpId }: { col: Column; student: StudentRow; followUpId: number }) {
    const cell = student.cells[col.key];

    if (col.type === 'notes') {
        const data = cell as NotesCellData;

        return (
            <div className="flex flex-col gap-1.5">
                {data.notes.map((n) => (
                    <NoteItem key={n.id} note={n} field={col.key} onDelete={() => router.delete(`/aportes/${n.id}`, { preserveScroll: true })} />
                ))}
                {data.notes.length === 0 && !data.can_write && <span className="text-muted-foreground/50 text-[11px] italic">Sin registros</span>}
                {data.can_write && <AddNoteForm studentId={student.id} followUpId={followUpId} field={col.key} />}
            </div>
        );
    }

    if (col.type === 'subject_attention') {
        const data = cell as SubjectAttentionCellData;
        return <SubjectAttentionCell studentId={student.id} followUpId={followUpId} data={data} />;
    }

    if (col.type === 'asignaturas') {
        const items = cell as AsignaturaItem[];

        return (
            <div className="flex flex-wrap gap-1">
                {items.length === 0 ? (
                    <span className="inline-flex items-center gap-1 rounded-full border border-emerald-200 bg-emerald-50 px-2 py-0.5 text-[10.5px] font-medium text-emerald-700">
                        <CheckCircle2 className="size-3" />
                        Al día (sin alertas ≤3)
                    </span>
                ) : (
                    items.map((item) => (
                        <span
                            key={item.subject}
                            className={
                                'inline-flex items-center gap-1 rounded-md border px-1.5 py-0.5 text-[11px] font-semibold ' +
                                (item.level <= 2 ? 'border-red-200 bg-red-50 text-red-700' : 'border-amber-200 bg-amber-50 text-amber-800')
                            }
                        >
                            <span>{item.subject}</span>
                            <span
                                className={
                                    'size-4 rounded text-center text-[10px] leading-4 font-bold ' +
                                    (item.level <= 2 ? 'bg-red-600 text-white' : 'bg-amber-600 text-white')
                                }
                            >
                                {item.level}
                            </span>
                        </span>
                    ))
                )}
            </div>
        );
    }

    if (col.type === 'estrategias') {
        const data = cell as EstrategiasCellData;

        return (
            <div className="flex flex-col gap-1.5">
                {data.items.map((item) => (
                    <div key={item.id} className="border-border/70 bg-card rounded-md border p-2 text-xs shadow-2xs">
                        <p className="text-foreground leading-snug font-medium">{item.body}</p>
                        <div className="mt-1.5 flex flex-wrap items-center justify-between gap-1 text-[10.5px]">
                            <span className={'py-0.2 rounded-full border px-1.5 text-[10px] font-semibold ' + resultBadgeClass(item.last_result)}>
                                {item.last_result}
                            </span>
                            {item.responsible && (
                                <span className="text-muted-foreground max-w-32 truncate" title={item.responsible}>
                                    Resp: {item.responsible}
                                </span>
                            )}
                            {item.can_delete && <DeleteButton onConfirm={() => router.delete(`/estrategias/${item.id}`, { preserveScroll: true })} />}
                        </div>
                    </div>
                ))}
                {data.items.length === 0 && !data.can_write && <span className="text-muted-foreground/50 text-[11px] italic">Sin estrategias</span>}
                {data.can_write && <AddStrategyForm studentId={student.id} followUpId={followUpId} label="Añadir estrategia" />}
            </div>
        );
    }

    const responsables = cell as string[];

    return (
        <div className="flex flex-wrap gap-1">
            {responsables.length === 0 ? (
                <span className="text-muted-foreground/50 text-[11px] italic">Sin asignar</span>
            ) : (
                responsables.map((r) => (
                    <span
                        key={r}
                        className="bg-muted text-muted-foreground border-border/60 inline-flex items-center gap-1 rounded-md border px-1.5 py-0.5 text-[11px] font-medium"
                    >
                        <User className="size-2.5 opacity-60" />
                        {r}
                    </span>
                ))
            )}
        </div>
    );
}

function getColumnIcon(key: string, type: string, support?: boolean) {
    if (key === 'strength') return <Sparkles className="size-3.5 text-emerald-600" />;
    if (key === 'improvement') return <AlertCircle className="size-3.5 text-amber-600" />;
    if (type === 'subject_attention' || type === 'asignaturas') return <GraduationCap className="text-primary size-3.5" />;
    if (type === 'estrategias') return <Lightbulb className="size-3.5 text-amber-600" />;
    if (type === 'responsables') return <Users className="text-muted-foreground size-3.5" />;
    if (support) return <HeartHandshake className="size-3.5 text-indigo-600" />;
    return <MessageSquare className="text-muted-foreground size-3.5" />;
}

export default function ReunionShow(props: ReunionProps) {
    const { schoolYear, groups, followUps, group, followUp, meeting, can, stats, groupStrategies, columns, hiddenAreas, students } = props;

    const meetingForm = useForm({ held_on: meeting?.held_on ?? '' });

    const [search, setSearch] = useState('');
    const [filterMode, setFilterMode] = useState<'all' | 'pending' | 'attention'>('all');
    const [columnVisibility, setColumnVisibility] = useState<Record<string, boolean>>(loadVisibility);
    const [columnSizing, setColumnSizing] = useState<Record<string, number>>(loadSizing);
    const [isExpanded, setIsExpanded] = useState(false);
    const [showGroupStrategies, setShowGroupStrategies] = useState(groupStrategies.length > 0);
    const [edges, setEdges] = useState({ left: false, right: false });
    const wrapRef = useRef<HTMLDivElement>(null);

    useEffect(() => {
        const t = setTimeout(() => localStorage.setItem(SIZE_KEY, JSON.stringify(columnSizing)), 400);
        return () => clearTimeout(t);
    }, [columnSizing]);

    useEffect(() => {
        localStorage.setItem(VIS_KEY, JSON.stringify(columnVisibility));
    }, [columnVisibility]);

    const updateEdges = useCallback(() => {
        const el = wrapRef.current;
        if (!el) return;
        setEdges({ left: el.scrollLeft > 1, right: el.scrollLeft + el.clientWidth < el.scrollWidth - 1 });
    }, []);

    useEffect(() => {
        updateEdges();
    }, [updateEdges, students, columnVisibility, columnSizing]);

    const resetLayout = () => {
        setColumnVisibility({});
        setColumnSizing({});
        localStorage.removeItem(VIS_KEY);
        localStorage.removeItem(SIZE_KEY);
    };

    const applyPreset = (preset: 'all' | 'teaching' | 'support' | 'compact') => {
        const newVis: Record<string, boolean> = {};
        columns.forEach((col) => {
            if (preset === 'all') {
                newVis[col.key] = true;
            } else if (preset === 'teaching') {
                newVis[col.key] = !col.support;
            } else if (preset === 'support') {
                newVis[col.key] = !!col.support || col.key === 'strength' || col.key === 'improvement';
            } else if (preset === 'compact') {
                newVis[col.key] = ['strength', 'improvement', 'subject_attention', 'asignaturas'].includes(col.key);
            }
        });
        setColumnVisibility(newVis);
    };

    const studentsWithPending = useMemo(() => students.filter((s) => s.late_commitments > 0 || s.disciplinary_open > 0), [students]);

    const studentsWithAttention = useMemo(() => students.filter((s) => (s.attention_subjects_count ?? 0) > 0), [students]);

    const displayStudents = useMemo(() => {
        const q = search.trim().toLowerCase();
        return students.filter((s) => {
            if (filterMode === 'pending' && s.late_commitments === 0 && s.disciplinary_open === 0) return false;
            if (filterMode === 'attention' && (s.attention_subjects_count ?? 0) === 0) return false;
            if (q && !s.full_name.toLowerCase().includes(q)) return false;
            return true;
        });
    }, [students, search, filterMode]);

    const tableColumns = useMemo<ColumnDef<StudentRow>[]>(() => {
        const studentCol: ColumnDef<StudentRow> = {
            id: 'student',
            accessorKey: 'full_name',
            size: 250,
            minSize: 210,
            enableResizing: false,
            header: () => 'Estudiante',
            cell: ({ row }) => <StudentCell student={row.original} index={row.index} />,
        };

        const contentCols: ColumnDef<StudentRow>[] = columns.map((c) => ({
            id: c.key,
            size: DEFAULT_SIZE[c.type],
            meta: { support: c.support, label: c.label },
            header: () => (
                <div className="flex items-center gap-1.5">
                    {getColumnIcon(c.key, c.type, c.support)}
                    <span className="truncate">{c.label}</span>
                </div>
            ),
            cell: ({ row }) => <ContentCell col={c} student={row.original} followUpId={followUp.id} />,
        }));

        return [studentCol, ...contentCols];
    }, [columns, followUp.id]);

    const table = useReactTable({
        data: displayStudents,
        columns: tableColumns,
        state: { columnVisibility, columnSizing },
        onColumnVisibilityChange: setColumnVisibility,
        onColumnSizingChange: setColumnSizing,
        columnResizeMode: 'onChange',
        getCoreRowModel: getCoreRowModel(),
        defaultColumn: { minSize: 120, maxSize: 540 },
    });

    const visibleCols = table.getVisibleLeafColumns();
    const visibleSupport = visibleCols.filter((c) => c.columnDef.meta?.support);
    const visibleNonSupport = visibleCols.filter((c) => c.id !== 'student' && !c.columnDef.meta?.support);
    const studentVisible = table.getColumn('student')?.getIsVisible() ?? true;

    return (
        <SeguimientoLayout>
            <Head title={`Reunión de grupo · ${group.code}`} />

            {/* ─── BARRA DE CONTEXTO Y SELECTORES ─── */}
            <div className="border-border/80 bg-card mb-4 rounded-xl border p-3 shadow-2xs">
                <div className="flex flex-wrap items-center justify-between gap-4">
                    <div className="flex flex-wrap items-center gap-3">
                        <div className="flex items-center gap-2">
                            <span className="bg-primary/10 text-primary flex size-9 items-center justify-center rounded-lg text-sm font-bold">
                                {group.code}
                            </span>
                            <div>
                                <h2 className="font-display text-primary text-lg leading-tight font-bold">Reunión de Seguimiento · {group.code}</h2>
                                <p className="text-muted-foreground text-xs">
                                    {group.section} · {group.director_name ? `Director: ${group.director_name}` : 'Sin director'} · Año{' '}
                                    {schoolYear.name}
                                </p>
                            </div>
                        </div>

                        <div className="bg-border/70 hidden h-6 w-px md:block" />

                        {/* Selectores rápidos */}
                        <div className="flex flex-wrap items-center gap-2">
                            <select
                                className="border-border bg-background focus:ring-primary rounded-md border px-2.5 py-1 text-xs font-semibold focus:ring-1 focus:outline-none"
                                value={group.id}
                                onChange={(e) => navigate(Number(e.target.value), followUp.id)}
                            >
                                {groups.map((g) => (
                                    <option key={g.id} value={g.id}>
                                        Grupo {g.code} ({g.section})
                                    </option>
                                ))}
                            </select>

                            <select
                                className="border-border bg-background focus:ring-primary rounded-md border px-2.5 py-1 text-xs font-semibold focus:ring-1 focus:outline-none"
                                value={followUp.id}
                                onChange={(e) => navigate(group.id, Number(e.target.value))}
                            >
                                {followUps.map((f) => (
                                    <option key={f.id} value={f.id}>
                                        {f.label}
                                    </option>
                                ))}
                            </select>

                            <div className="border-border bg-background flex items-center gap-1.5 rounded-md border px-2 py-0.5 text-xs">
                                <Calendar className="text-muted-foreground size-3" />
                                {can.manage_meeting_date ? (
                                    <input
                                        type="date"
                                        className="bg-transparent text-xs font-medium focus:outline-none"
                                        value={meetingForm.data.held_on}
                                        onChange={(e) => {
                                            meetingForm.setData('held_on', e.target.value);
                                            router.put(
                                                `/reunion/${group.id}/${followUp.id}/fecha`,
                                                { held_on: e.target.value },
                                                { preserveScroll: true },
                                            );
                                        }}
                                        title="Fecha oficial de la reunión de seguimiento"
                                    />
                                ) : (
                                    <span className="text-xs font-medium">{meeting?.held_on ?? 'Sin fecha'}</span>
                                )}
                            </div>
                        </div>
                    </div>

                    {/* Stat Badges */}
                    <div className="flex items-center gap-2">
                        <div className="border-border/80 bg-muted/40 flex items-center gap-2 rounded-lg border px-2.5 py-1 text-xs">
                            <Users className="text-primary size-3.5" />
                            <span>
                                <strong>{stats.students}</strong> <span className="text-muted-foreground text-[11px]">estudiantes</span>
                            </span>
                        </div>
                        <div className="border-border/80 bg-muted/40 flex items-center gap-2 rounded-lg border px-2.5 py-1 text-xs">
                            <Check className="size-3.5 text-emerald-600" />
                            <span>
                                <strong>{stats.open_commitments}</strong> <span className="text-muted-foreground text-[11px]">compromisos</span>
                            </span>
                        </div>
                        {stats.late_commitments > 0 && (
                            <div className="border-crit/30 bg-crit/10 text-crit flex items-center gap-1.5 rounded-lg border px-2.5 py-1 text-xs font-semibold">
                                <AlertCircle className="size-3.5" />
                                <span>{stats.late_commitments} vencidos</span>
                            </div>
                        )}
                    </div>
                </div>
            </div>

            {/* ─── ESTRATEGIAS GRUPALES (COLAPSABLES) ─── */}
            <div className="border-border/80 bg-card mb-3 overflow-hidden rounded-lg border">
                <button
                    type="button"
                    onClick={() => setShowGroupStrategies((v) => !v)}
                    className="hover:bg-muted/30 flex w-full items-center justify-between p-2.5 text-left text-xs font-semibold transition"
                >
                    <div className="flex items-center gap-2">
                        <Lightbulb className="size-4 text-amber-600" />
                        <span>Estrategias grupales para {group.code}</span>
                        <span className="bg-primary/10 text-primary py-0.2 rounded-full px-2 text-[10.5px]">{groupStrategies.length} activa(s)</span>
                    </div>
                    <div className="text-muted-foreground flex items-center gap-1 text-[11px]">
                        <span>{showGroupStrategies ? 'Ocultar' : 'Ver estrategias'}</span>
                        {showGroupStrategies ? <ChevronUp className="size-3.5" /> : <ChevronDown className="size-3.5" />}
                    </div>
                </button>

                {showGroupStrategies && (
                    <div className="border-border/60 bg-muted/20 border-t p-3">
                        <div className="grid grid-cols-[repeat(auto-fill,minmax(280px,1fr))] gap-2.5">
                            {groupStrategies.length === 0 && (
                                <p className="text-muted-foreground text-xs italic">Sin estrategias grupales configuradas en este grupo.</p>
                            )}
                            {groupStrategies.map((s) => (
                                <div key={s.id} className="border-border bg-card flex flex-col gap-1.5 rounded-md border p-2.5 shadow-2xs">
                                    <p className="text-xs leading-snug font-semibold">{s.body}</p>
                                    <div className="text-muted-foreground text-[11px]">Responsable: {s.responsible ?? 'Docentes del grupo'}</div>
                                    <div className="flex flex-wrap items-center gap-1.5">
                                        <span
                                            className={
                                                'py-0.2 rounded-full border px-1.5 text-[10.5px] font-semibold ' + resultBadgeClass(s.last_result)
                                            }
                                        >
                                            {s.last_result}
                                        </span>
                                        {s.reviews_count > 0 && (
                                            <span className="text-muted-foreground text-[11px]">{s.reviews_count} seguimiento(s)</span>
                                        )}
                                    </div>
                                    {s.last_review_body && (
                                        <p className="text-muted-foreground text-[11px] italic">&ldquo;{s.last_review_body}&rdquo;</p>
                                    )}
                                    <div className="border-border/40 flex items-center justify-between border-t pt-1">
                                        {s.can_review && <AddReviewForm strategyId={s.id} />}
                                        {s.can_delete && (
                                            <DeleteButton onConfirm={() => router.delete(`/estrategias/${s.id}`, { preserveScroll: true })} />
                                        )}
                                    </div>
                                </div>
                            ))}
                            {can.create_group_strategy && (
                                <AddStrategyForm groupId={group.id} followUpId={followUp.id} label="Añadir estrategia grupal" />
                            )}
                        </div>
                    </div>
                )}
            </div>

            {/* ─── FILTROS Y CONTROLES DE TABLA ─── */}
            <div className="mb-2.5 flex flex-wrap items-center justify-between gap-2.5">
                {/* Filtros rápidos de filas */}
                <div className="flex flex-wrap items-center gap-1.5">
                    <button
                        type="button"
                        onClick={() => setFilterMode('all')}
                        className={
                            'rounded-md px-2.5 py-1 text-xs font-semibold transition ' +
                            (filterMode === 'all'
                                ? 'bg-primary text-primary-foreground shadow-2xs'
                                : 'bg-muted/70 text-muted-foreground hover:bg-muted hover:text-foreground')
                        }
                    >
                        Todos ({students.length})
                    </button>
                    <button
                        type="button"
                        onClick={() => setFilterMode('pending')}
                        className={
                            'rounded-md px-2.5 py-1 text-xs font-semibold transition ' +
                            (filterMode === 'pending'
                                ? 'bg-crit text-white shadow-2xs'
                                : 'bg-muted/70 text-muted-foreground hover:bg-muted hover:text-foreground')
                        }
                    >
                        Con pendientes ({studentsWithPending.length})
                    </button>
                    <button
                        type="button"
                        onClick={() => setFilterMode('attention')}
                        className={
                            'rounded-md px-2.5 py-1 text-xs font-semibold transition ' +
                            (filterMode === 'attention'
                                ? 'bg-amber-600 text-white shadow-2xs'
                                : 'bg-muted/70 text-muted-foreground hover:bg-muted hover:text-foreground')
                        }
                    >
                        Con asignaturas ≤3 ({studentsWithAttention.length})
                    </button>

                    <div className="relative ml-2">
                        <Search className="text-muted-foreground pointer-events-none absolute top-1/2 left-2.5 size-3.5 -translate-y-1/2" />
                        <input
                            type="text"
                            value={search}
                            onChange={(e) => setSearch(e.target.value)}
                            placeholder="Buscar por nombre…"
                            className="border-border bg-background focus:ring-primary w-40 rounded-md border py-1 pr-6 pl-8 text-xs focus:ring-1 focus:outline-none md:w-52"
                        />
                        {search && (
                            <button
                                type="button"
                                onClick={() => setSearch('')}
                                className="text-muted-foreground hover:text-foreground absolute top-1/2 right-1.5 -translate-y-1/2"
                            >
                                <X className="size-3" />
                            </button>
                        )}
                    </div>
                </div>

                {/* Controles de vista de tabla */}
                <div className="flex flex-wrap items-center gap-2">
                    {hiddenAreas.length > 0 && (
                        <span className="text-muted-foreground hidden text-[11px] lg:inline" title={hiddenAreas.join(', ')}>
                            🔒 {hiddenAreas.length} área(s) restringida(s)
                        </span>
                    )}

                    {/* Menú de columnas y presets */}
                    <DropdownMenu>
                        <DropdownMenuTrigger asChild>
                            <Button type="button" size="sm" variant="outline" className="h-7 gap-1.5 text-xs">
                                <Columns3 className="size-3.5" />
                                Columnas ({visibleCols.length - (studentVisible ? 1 : 0)})
                            </Button>
                        </DropdownMenuTrigger>
                        <DropdownMenuContent align="end" className="max-h-88 w-56 overflow-y-auto">
                            <DropdownMenuLabel className="text-xs">Vistas rápidas</DropdownMenuLabel>
                            <DropdownMenuItem onClick={() => applyPreset('all')} className="text-xs">
                                Ver todas las columnas
                            </DropdownMenuItem>
                            <DropdownMenuItem onClick={() => applyPreset('teaching')} className="text-xs">
                                Solo seguimiento docente
                            </DropdownMenuItem>
                            <DropdownMenuItem onClick={() => applyPreset('support')} className="text-xs">
                                Solo Dpto. de Apoyo
                            </DropdownMenuItem>
                            <DropdownMenuItem onClick={() => applyPreset('compact')} className="text-xs">
                                Vista compacta
                            </DropdownMenuItem>
                            <DropdownMenuSeparator />
                            <DropdownMenuLabel className="text-xs">Columnas activas</DropdownMenuLabel>
                            {table
                                .getAllLeafColumns()
                                .filter((col) => col.id !== 'student')
                                .map((col) => (
                                    <DropdownMenuCheckboxItem
                                        key={col.id}
                                        checked={col.getIsVisible()}
                                        onCheckedChange={(v) => col.toggleVisibility(!!v)}
                                        className="text-xs"
                                    >
                                        {col.columnDef.meta?.label ?? col.id}
                                    </DropdownMenuCheckboxItem>
                                ))}
                            <DropdownMenuSeparator />
                            <DropdownMenuItem onClick={resetLayout} className="text-muted-foreground text-xs">
                                <RotateCcw className="mr-1.5 size-3" />
                                Restablecer anchos y columnas
                            </DropdownMenuItem>
                        </DropdownMenuContent>
                    </DropdownMenu>

                    {/* Botón de expandir / pantalla completa */}
                    <Button
                        type="button"
                        size="sm"
                        variant="outline"
                        className="h-7 px-2 text-xs"
                        onClick={() => setIsExpanded((v) => !v)}
                        title={isExpanded ? 'Volver a tamaño normal' : 'Maximizar altura de la tabla'}
                    >
                        {isExpanded ? <Minimize2 className="size-3.5" /> : <Maximize2 className="size-3.5" />}
                    </Button>
                </div>
            </div>

            {/* ─── TABLA PRINCIPAL DE REUNIÓN DE GRUPO ─── */}
            <div className="relative">
                <div
                    ref={wrapRef}
                    onScroll={updateEdges}
                    className={
                        'border-border bg-card overflow-auto rounded-xl border shadow-sm transition-all ' +
                        (isExpanded ? 'h-[calc(100vh-170px)]' : 'max-h-[72vh]')
                    }
                >
                    <table className="border-separate border-spacing-0 text-xs" style={{ tableLayout: 'fixed', width: table.getTotalSize() }}>
                        <colgroup>
                            {visibleCols.map((col) => (
                                <col key={col.id} style={{ width: col.getSize() }} />
                            ))}
                        </colgroup>

                        <thead className="sticky top-0 z-20 shadow-xs">
                            {/* Nivel 1 de encabezado: Agrupación visual */}
                            <tr>
                                {studentVisible && (
                                    <th className="bg-muted border-border border-primary/25 sticky left-0 z-30 border-r-2 border-b p-2 text-left shadow-[4px_0_10px_-4px_rgba(0,0,0,0.12)]">
                                        <span className="text-muted-foreground text-[10px] font-bold tracking-wider uppercase">
                                            Estudiantes ({displayStudents.length})
                                        </span>
                                    </th>
                                )}
                                {visibleNonSupport.length > 0 && (
                                    <th
                                        colSpan={visibleNonSupport.length}
                                        className="border-border bg-muted/80 text-muted-foreground border-b px-3 py-1 text-left text-[10px] font-bold tracking-wider uppercase"
                                    >
                                        Seguimiento Formativo & Académico
                                    </th>
                                )}
                                {visibleSupport.length > 0 && (
                                    <th
                                        colSpan={visibleSupport.length}
                                        className="border-t-brand-red border-border border-t-[3px] border-b bg-indigo-50/70 px-3 py-1 text-center text-[10.5px] font-bold tracking-wide text-indigo-950 uppercase"
                                    >
                                        <div className="flex items-center justify-center gap-1.5">
                                            <Shield className="size-3 text-indigo-600" />
                                            <span>Dpto. de Apoyo Integral · Confidencial</span>
                                        </div>
                                    </th>
                                )}
                            </tr>

                            {/* Nivel 2 de encabezado: Columnas individuales */}
                            {table.getHeaderGroups()[0] && (
                                <tr>
                                    {table
                                        .getHeaderGroups()[0]
                                        .headers.filter((header) => header.column.getIsVisible())
                                        .map((header) => {
                                            const isStudent = header.column.id === 'student';
                                            const support = header.column.columnDef.meta?.support;

                                            return (
                                                <th
                                                    key={header.id}
                                                    colSpan={header.colSpan}
                                                    className={
                                                        'border-border relative border-b p-2.5 text-left text-[11px] font-bold tracking-wide uppercase transition select-none ' +
                                                        (isStudent
                                                            ? 'bg-muted border-primary/25 text-primary sticky left-0 z-30 border-r-2 shadow-[4px_0_10px_-4px_rgba(0,0,0,0.12)]'
                                                            : support
                                                              ? 'bg-indigo-50/90 text-indigo-900 hover:bg-indigo-100/80'
                                                              : 'bg-muted/90 text-foreground/90 hover:bg-muted')
                                                    }
                                                >
                                                    {flexRender(header.column.columnDef.header, header.getContext())}
                                                    {header.column.getCanResize() && (
                                                        <div
                                                            onPointerDown={header.getResizeHandler()}
                                                            onDoubleClick={() => header.column.resetSize()}
                                                            title="Arrastrar para redimensionar"
                                                            className="bg-border/80 hover:bg-primary absolute top-0 right-0 h-full w-1.5 -translate-x-1/2 cursor-col-resize touch-none transition"
                                                        />
                                                    )}
                                                </th>
                                            );
                                        })}
                                </tr>
                            )}
                        </thead>

                        <tbody>
                            {displayStudents.length === 0 && (
                                <tr>
                                    <td
                                        colSpan={visibleCols.length}
                                        className="text-muted-foreground border-border bg-card border-b p-10 text-center text-sm italic"
                                    >
                                        {students.length === 0
                                            ? 'No hay estudiantes matriculados en este grupo.'
                                            : 'Ningún estudiante coincide con el filtro o término de búsqueda.'}
                                    </td>
                                </tr>
                            )}

                            {table.getRowModel().rows.map((row) => (
                                <tr key={row.original.id} className="group/row transition-colors">
                                    {row.getVisibleCells().map((cell) => {
                                        const isStudent = cell.column.id === 'student';
                                        const support = cell.column.columnDef.meta?.support;
                                        const zebra = row.index % 2 === 1;

                                        return (
                                            <td
                                                key={cell.id}
                                                className={
                                                    'border-border border-b p-2 align-top text-xs transition ' +
                                                    (isStudent
                                                        ? 'border-primary/25 sticky left-0 z-10 border-r-2 shadow-[4px_0_10px_-4px_rgba(0,0,0,0.12)] ' +
                                                          (zebra ? 'bg-muted/90' : 'bg-card') +
                                                          ' group-hover/row:bg-primary/5'
                                                        : support
                                                          ? 'bg-indigo-50/20 group-hover/row:bg-indigo-50/50'
                                                          : (zebra ? 'bg-muted/30' : 'bg-card') + ' group-hover/row:bg-primary/5')
                                                }
                                            >
                                                {flexRender(cell.column.columnDef.cell, cell.getContext())}
                                            </td>
                                        );
                                    })}
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </div>

                {/* Sombras de desbordamiento horizontal */}
                {edges.left && (
                    <div className="pointer-events-none absolute inset-y-0 left-0 w-8 rounded-l-xl bg-gradient-to-r from-black/10 to-transparent"></div>
                )}
                {edges.right && (
                    <div className="pointer-events-none absolute inset-y-0 right-0 w-8 rounded-r-xl bg-gradient-to-l from-black/10 to-transparent"></div>
                )}
            </div>
        </SeguimientoLayout>
    );
}
