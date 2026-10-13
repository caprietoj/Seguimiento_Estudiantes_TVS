import { AdminNav } from '@/components/admin-nav';
import { Button } from '@/components/ui/button';
import SeguimientoLayout from '@/layouts/seguimiento-layout';
import { Head, Link, router, useForm } from '@inertiajs/react';
import { useMemo, useState } from 'react';

interface Teacher {
    id: number;
    name: string;
    email: string;
}

interface Assignment {
    id: number;
    teacher: string;
    email: string;
    subject: string;
}

interface GroupRow {
    id: number;
    code: string;
    students_count: number;
    director_id: number | null;
    director_name: string | null;
    assignments: Assignment[];
}

interface Props {
    schoolYears: { id: number; name: string }[];
    selectedYearId: number;
    teachers: Teacher[];
    subjects: { id: number; name: string }[];
    teachersWithoutGroup: Teacher[];
    groups: GroupRow[];
}

const selectClass = 'border-border bg-background rounded-md border p-2 text-sm';

export default function AdminAsignaciones({ schoolYears, selectedYearId, teachers, subjects, teachersWithoutGroup, groups }: Props) {
    const [open, setOpen] = useState(false);
    const [filter, setFilter] = useState('');
    const form = useForm<{ user_id: string; group_id: string; subject_ids: number[] }>({
        user_id: '',
        group_id: '',
        subject_ids: [],
    });

    const visibleGroups = useMemo(() => {
        const q = filter.trim().toLowerCase();
        if (q === '') return groups;
        return groups.filter(
            (g) =>
                g.code.toLowerCase().includes(q) ||
                (g.director_name ?? '').toLowerCase().includes(q) ||
                g.assignments.some((a) => a.teacher.toLowerCase().includes(q) || a.email.toLowerCase().includes(q)),
        );
    }, [filter, groups]);

    const toggleSubject = (id: number) =>
        form.setData(
            'subject_ids',
            form.data.subject_ids.includes(id) ? form.data.subject_ids.filter((s) => s !== id) : [...form.data.subject_ids, id],
        );

    return (
        <SeguimientoLayout>
            <Head title="Administración · Asignaciones" />
            <AdminNav />

            <div className="mb-2 flex flex-wrap items-center justify-between gap-3">
                <div className="flex items-end gap-2">
                    <h2 className="font-display text-primary text-xl">Asignaciones de profesores</h2>
                    <select
                        className="border-border bg-background rounded-md border px-2 py-1.5 text-sm"
                        value={selectedYearId}
                        onChange={(e) => router.get('/admin/asignaciones', { anio: e.target.value })}
                    >
                        {schoolYears.map((y) => (
                            <option key={y.id} value={y.id}>
                                {y.name}
                            </option>
                        ))}
                    </select>
                </div>
                <div className="flex gap-2">
                    <Button size="sm" variant="outline" asChild>
                        <Link href="/admin/importar">Importar desde Excel</Link>
                    </Button>
                    <Button size="sm" onClick={() => setOpen(!open)}>
                        {open ? 'Cancelar' : '+ Asignar profesor'}
                    </Button>
                </div>
            </div>
            <p className="text-muted-foreground mb-4 text-sm">
                Cada profesor solo ve los estudiantes de los grupos donde dicta una asignatura o es director(a) de grupo.
            </p>

            {teachersWithoutGroup.length > 0 && (
                <div className="border-warn bg-warn-soft mb-4 rounded-lg border p-3 text-sm">
                    <p className="font-semibold">
                        {teachersWithoutGroup.length === 1 ? '1 profesor no tiene' : `${teachersWithoutGroup.length} profesores no tienen`} grupo en
                        este año escolar y no verán ningún estudiante:
                    </p>
                    <p className="text-muted-foreground mt-1">{teachersWithoutGroup.map((t) => t.name).join(' · ')}</p>
                </div>
            )}

            {open && (
                <form
                    className="border-border bg-accent mb-4 flex flex-col gap-3 rounded-lg border p-3"
                    onSubmit={(e) => {
                        e.preventDefault();
                        form.post('/admin/asignaciones', {
                            preserveScroll: true,
                            onSuccess: () => form.setData('subject_ids', []),
                        });
                    }}
                >
                    <div className="flex flex-wrap items-end gap-3">
                        <div className="flex min-w-64 flex-col gap-1">
                            <label className="text-muted-foreground text-xs">Profesor(a)</label>
                            <select
                                required
                                value={form.data.user_id}
                                onChange={(e) => form.setData('user_id', e.target.value)}
                                className={selectClass}
                            >
                                <option value="">Elegir…</option>
                                {teachers.map((t) => (
                                    <option key={t.id} value={t.id}>
                                        {t.name} — {t.email}
                                    </option>
                                ))}
                            </select>
                        </div>
                        <div className="flex flex-col gap-1">
                            <label className="text-muted-foreground text-xs">Grupo</label>
                            <select
                                required
                                value={form.data.group_id}
                                onChange={(e) => form.setData('group_id', e.target.value)}
                                className={selectClass}
                            >
                                <option value="">Elegir…</option>
                                {groups.map((g) => (
                                    <option key={g.id} value={g.id}>
                                        {g.code}
                                    </option>
                                ))}
                            </select>
                        </div>
                    </div>
                    <div>
                        <p className="text-muted-foreground mb-1 text-xs">Asignaturas que dicta en ese grupo</p>
                        <div className="flex flex-wrap gap-2">
                            {subjects.map((s) => (
                                <label
                                    key={s.id}
                                    className={
                                        'cursor-pointer rounded-full border px-3 py-1 text-xs ' +
                                        (form.data.subject_ids.includes(s.id)
                                            ? 'border-primary bg-primary text-primary-foreground'
                                            : 'border-border bg-background')
                                    }
                                >
                                    <input
                                        type="checkbox"
                                        className="sr-only"
                                        checked={form.data.subject_ids.includes(s.id)}
                                        onChange={() => toggleSubject(s.id)}
                                    />
                                    {s.name}
                                </label>
                            ))}
                        </div>
                    </div>
                    {Object.keys(form.errors).length > 0 && <div className="text-crit text-sm">{Object.values(form.errors).join(' · ')}</div>}
                    <div>
                        <Button type="submit" size="sm" disabled={form.processing || form.data.subject_ids.length === 0}>
                            Guardar asignación
                        </Button>
                    </div>
                </form>
            )}

            <input
                value={filter}
                onChange={(e) => setFilter(e.target.value)}
                placeholder="Buscar por grupo, profesor o correo…"
                className="border-border bg-background mb-3 w-full max-w-sm rounded-md border p-2 text-sm"
            />

            <div className="border-border bg-card overflow-x-auto rounded-lg border">
                <table className="w-full text-sm">
                    <thead>
                        <tr className="bg-muted text-muted-foreground text-left text-[11px] uppercase">
                            <th className="p-2">Grupo</th>
                            <th className="p-2">Estudiantes</th>
                            <th className="p-2">Director(a) de grupo</th>
                            <th className="p-2">Profesores y asignaturas</th>
                        </tr>
                    </thead>
                    <tbody>
                        {visibleGroups.map((g) => (
                            <tr key={g.id} className="border-border border-t align-top">
                                <td className="p-2 font-semibold">{g.code}</td>
                                <td className="p-2">{g.students_count}</td>
                                <td className="p-2">
                                    <select
                                        key={`${g.id}-${g.director_id ?? ''}`}
                                        defaultValue={g.director_id ?? ''}
                                        className="border-border bg-background max-w-56 rounded-md border px-2 py-1 text-xs"
                                        onChange={(e) =>
                                            router.put(
                                                `/admin/asignaciones/grupo/${g.id}/director`,
                                                { director_id: e.target.value || null },
                                                { preserveScroll: true },
                                            )
                                        }
                                    >
                                        <option value="">Sin asignar</option>
                                        {teachers.map((t) => (
                                            <option key={t.id} value={t.id}>
                                                {t.name}
                                            </option>
                                        ))}
                                    </select>
                                </td>
                                <td className="p-2">
                                    <div className="flex flex-wrap gap-1.5">
                                        {g.assignments.map((a) => (
                                            <span
                                                key={a.id}
                                                className="border-border bg-background inline-flex items-center gap-1 rounded-full border py-0.5 pr-1 pl-2.5 text-xs"
                                                title={a.email}
                                            >
                                                <span className="font-medium">{a.teacher}</span>
                                                <span className="text-muted-foreground">· {a.subject}</span>
                                                <button
                                                    type="button"
                                                    aria-label={`Quitar ${a.subject} a ${a.teacher}`}
                                                    className="text-muted-foreground hover:text-crit rounded-full px-1"
                                                    onClick={() => {
                                                        if (confirm(`¿Quitar a ${a.teacher} de ${a.subject} en ${g.code}?`)) {
                                                            router.delete(`/admin/asignaciones/${a.id}`, { preserveScroll: true });
                                                        }
                                                    }}
                                                >
                                                    ×
                                                </button>
                                            </span>
                                        ))}
                                        {g.assignments.length === 0 && (
                                            <span className="text-muted-foreground text-xs italic">Sin profesores asignados</span>
                                        )}
                                    </div>
                                </td>
                            </tr>
                        ))}
                        {visibleGroups.length === 0 && (
                            <tr>
                                <td colSpan={4} className="text-muted-foreground p-3 text-center text-xs italic">
                                    {groups.length === 0 ? 'Sin grupos en este año escolar.' : 'Ningún grupo coincide con la búsqueda.'}
                                </td>
                            </tr>
                        )}
                    </tbody>
                </table>
            </div>
        </SeguimientoLayout>
    );
}
