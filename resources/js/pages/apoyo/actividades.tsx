import { ApoyoNav } from '@/components/apoyo/apoyo-nav';
import SeguimientoLayout from '@/layouts/seguimiento-layout';
import { Head, router, useForm } from '@inertiajs/react';
import { useState, type FormEvent } from 'react';

interface Option {
    value: string;
    label: string;
}

interface Activity {
    id: number;
    area: string;
    area_label: string;
    type: string;
    type_label: string;
    group_id: number | null;
    group_code: string | null;
    student_id: number | null;
    student_name: string | null;
    occurred_on: string | null;
    semester: number | null;
    quantity: number;
    description: string | null;
    author: string | null;
}

interface Props {
    years: { id: number; name: string }[];
    yearName: string;
    filters: { anio: number; area: string; tipo: string; grupo: number | string; semestre: string; q: string };
    areas: Option[];
    types: (Option & { per_student: boolean })[];
    groups: { id: number; code: string }[];
    studentsByGroup: Record<string, { id: number; name: string }[]>;
    items: Activity[];
    total: number;
    limit: number;
    canWrite: boolean;
}

const selectCls = 'border-input bg-background h-9 rounded-md border px-2 text-sm';

export default function Actividades(props: Props) {
    const { years, yearName, filters, areas, types, groups, items, total, limit, canWrite } = props;
    const [editing, setEditing] = useState<Activity | null>(null);
    const [search, setSearch] = useState(filters.q);

    const go = (change: Partial<Props['filters']>) => {
        const f = { ...filters, ...change };
        const q: Record<string, string> = { anio: String(f.anio) };
        if (f.area !== 'todas') q.area = f.area;
        if (f.tipo !== 'todos') q.tipo = f.tipo;
        if (f.grupo !== 'todos') q.grupo = String(f.grupo);
        if (f.semestre !== 'todo') q.semestre = f.semestre;
        if (f.q) q.q = f.q;
        router.get('/apoyo/actividades', q, { preserveScroll: true, preserveState: true });
    };

    return (
        <SeguimientoLayout>
            <Head title="Registro de actividades · Dpto. de Apoyo" />
            <ApoyoNav yearId={filters.anio} />

            <div className="mb-4 flex flex-wrap items-end justify-between gap-3">
                <div>
                    <h2 className="font-display text-primary text-2xl">Registro de actividades</h2>
                    <p className="text-muted-foreground text-sm">
                        {yearName}. Estudiantes en seguimiento, familias atendidas, reuniones, PIAR, crisis y demás acciones del departamento.
                    </p>
                </div>
                <label className="text-xs">
                    <span className="text-muted-foreground mb-0.5 block">Año escolar</span>
                    <select className={selectCls} value={filters.anio} onChange={(e) => go({ anio: Number(e.target.value), grupo: 'todos' })}>
                        {years.map((y) => (
                            <option key={y.id} value={y.id}>
                                {y.name}
                            </option>
                        ))}
                    </select>
                </label>
            </div>

            {canWrite && <ActivityForm key={editing?.id ?? 'new'} {...props} editing={editing} onDone={() => setEditing(null)} />}

            <div className="mb-3 flex flex-wrap items-end gap-2">
                <select className={selectCls} value={filters.area} onChange={(e) => go({ area: e.target.value })} aria-label="Área">
                    <option value="todas">Todas las áreas</option>
                    {areas.map((a) => (
                        <option key={a.value} value={a.value}>
                            {a.label}
                        </option>
                    ))}
                </select>
                <select className={selectCls} value={filters.tipo} onChange={(e) => go({ tipo: e.target.value })} aria-label="Tipo">
                    <option value="todos">Todos los tipos</option>
                    {types.map((t) => (
                        <option key={t.value} value={t.value}>
                            {t.label}
                        </option>
                    ))}
                </select>
                <select className={selectCls} value={String(filters.grupo)} onChange={(e) => go({ grupo: e.target.value })} aria-label="Grupo">
                    <option value="todos">Todos los grupos</option>
                    {groups.map((g) => (
                        <option key={g.id} value={g.id}>
                            {g.code}
                        </option>
                    ))}
                </select>
                <select className={selectCls} value={filters.semestre} onChange={(e) => go({ semestre: e.target.value })} aria-label="Semestre">
                    <option value="todo">Año completo</option>
                    <option value="1">I semestre</option>
                    <option value="2">II semestre</option>
                </select>
                <form
                    onSubmit={(e) => {
                        e.preventDefault();
                        go({ q: search.trim() });
                    }}
                >
                    <input
                        className={selectCls + ' w-56'}
                        placeholder="Buscar estudiante o descripción"
                        value={search}
                        onChange={(e) => setSearch(e.target.value)}
                    />
                </form>
                <span className="text-muted-foreground ml-auto text-xs">
                    {total > limit ? `Mostrando ${limit} de ${total}` : `${total} registro(s)`}
                </span>
            </div>

            <div className="border-border bg-card overflow-x-auto rounded-lg border">
                <table className="w-full text-sm">
                    <thead>
                        <tr className="border-border text-muted-foreground border-b text-left text-xs">
                            <th className="px-3 py-2 font-semibold">Fecha</th>
                            <th className="px-2 py-2 font-semibold">Área</th>
                            <th className="px-2 py-2 font-semibold">Actividad</th>
                            <th className="px-2 py-2 font-semibold">Grupo</th>
                            <th className="px-2 py-2 font-semibold">Estudiante</th>
                            <th className="px-2 py-2 text-right font-semibold">Cant.</th>
                            <th className="px-2 py-2 font-semibold">Descripción</th>
                            <th className="px-2 py-2 font-semibold">Registró</th>
                            {canWrite && <th className="px-3 py-2" />}
                        </tr>
                    </thead>
                    <tbody>
                        {items.length === 0 && (
                            <tr>
                                <td colSpan={9} className="text-muted-foreground px-3 py-6 text-center">
                                    No hay actividades con estos filtros.
                                </td>
                            </tr>
                        )}
                        {items.map((a) => (
                            <tr key={a.id} className={'border-border/60 border-b align-top ' + (editing?.id === a.id ? 'bg-accent' : '')}>
                                <td className="px-3 py-1.5 whitespace-nowrap">
                                    {a.occurred_on ?? '—'}
                                    {a.semester && <span className="text-muted-foreground ml-1 text-xs">{a.semester === 1 ? 'I' : 'II'} sem.</span>}
                                </td>
                                <td className="px-2 py-1.5 whitespace-nowrap">{a.area_label}</td>
                                <td className="px-2 py-1.5">{a.type_label}</td>
                                <td className="px-2 py-1.5 font-semibold">
                                    {a.group_code ?? <span className="text-muted-foreground font-normal">Todo el colegio</span>}
                                </td>
                                <td className="px-2 py-1.5">{a.student_name ?? '—'}</td>
                                <td className="px-2 py-1.5 text-right font-mono">{a.quantity}</td>
                                <td className="text-muted-foreground max-w-xs px-2 py-1.5 text-xs">
                                    <span className="line-clamp-2" title={a.description ?? ''}>
                                        {a.description}
                                    </span>
                                </td>
                                <td className="text-muted-foreground px-2 py-1.5 text-xs whitespace-nowrap">{a.author}</td>
                                {canWrite && (
                                    <td className="px-3 py-1.5 text-right whitespace-nowrap">
                                        <button
                                            type="button"
                                            className="text-primary mr-3 text-xs font-semibold"
                                            onClick={() => {
                                                setEditing(a);
                                                window.scrollTo({ top: 0, behavior: 'smooth' });
                                            }}
                                        >
                                            Editar
                                        </button>
                                        <button
                                            type="button"
                                            className="text-crit text-xs font-semibold"
                                            onClick={() => {
                                                if (confirm(`¿Eliminar «${a.type_label}»${a.student_name ? ` de ${a.student_name}` : ''}?`)) {
                                                    router.delete(`/apoyo/actividades/${a.id}`, { preserveScroll: true });
                                                }
                                            }}
                                        >
                                            Eliminar
                                        </button>
                                    </td>
                                )}
                            </tr>
                        ))}
                    </tbody>
                </table>
            </div>
        </SeguimientoLayout>
    );
}

function ActivityForm({ filters, areas, types, groups, studentsByGroup, editing, onDone }: Props & { editing: Activity | null; onDone: () => void }) {
    const form = useForm({
        school_year_id: filters.anio,
        area: editing?.area ?? 'psychology',
        type: editing?.type ?? 'student_case',
        group_id: editing?.group_id ? String(editing.group_id) : '',
        student_id: editing?.student_id ? String(editing.student_id) : '',
        occurred_on: editing?.occurred_on ?? new Date().toISOString().slice(0, 10),
        semester: editing?.semester ? String(editing.semester) : '',
        quantity: editing?.quantity ?? 1,
        description: editing?.description ?? '',
    });

    const type = types.find((t) => t.value === form.data.type);
    const students = form.data.group_id ? (studentsByGroup[form.data.group_id] ?? []) : [];
    const fixedOne = Boolean(type?.per_student && form.data.student_id);

    const submit = (e: FormEvent) => {
        e.preventDefault();
        form.transform((d) => ({
            ...d,
            group_id: d.group_id ? Number(d.group_id) : null,
            student_id: d.student_id ? Number(d.student_id) : null,
            semester: d.semester ? Number(d.semester) : null,
            occurred_on: d.occurred_on || null,
            quantity: fixedOne ? 1 : Number(d.quantity),
        }));
        const options = {
            preserveScroll: true,
            onSuccess: () => {
                if (editing) {
                    onDone();
                } else {
                    form.reset('student_id', 'description', 'quantity');
                }
            },
        };
        if (editing) {
            form.put(`/apoyo/actividades/${editing.id}`, options);
        } else {
            form.post('/apoyo/actividades', options);
        }
    };

    const err = (k: keyof typeof form.errors) => (form.errors[k] ? <p className="text-crit mt-0.5 text-xs">{form.errors[k]}</p> : null);

    return (
        <form onSubmit={submit} className="border-border bg-card mb-5 rounded-lg border p-4">
            <h3 className="text-primary mb-3 text-sm font-semibold">{editing ? 'Editar actividad' : 'Nueva actividad'}</h3>
            <div className="grid gap-3 sm:grid-cols-2 lg:grid-cols-6">
                <label className="text-xs">
                    <span className="text-muted-foreground mb-0.5 block">Área</span>
                    <select className={selectCls + ' w-full'} value={form.data.area} onChange={(e) => form.setData('area', e.target.value)}>
                        {areas.map((a) => (
                            <option key={a.value} value={a.value}>
                                {a.label}
                            </option>
                        ))}
                    </select>
                    {err('area')}
                </label>
                <label className="text-xs lg:col-span-2">
                    <span className="text-muted-foreground mb-0.5 block">Actividad</span>
                    <select className={selectCls + ' w-full'} value={form.data.type} onChange={(e) => form.setData('type', e.target.value)}>
                        {types.map((t) => (
                            <option key={t.value} value={t.value}>
                                {t.label}
                            </option>
                        ))}
                    </select>
                    {err('type')}
                </label>
                <label className="text-xs">
                    <span className="text-muted-foreground mb-0.5 block">Grupo</span>
                    <select
                        className={selectCls + ' w-full'}
                        value={form.data.group_id}
                        onChange={(e) => form.setData((d) => ({ ...d, group_id: e.target.value, student_id: '' }))}
                    >
                        <option value="">Todo el colegio (sin grupo)</option>
                        {groups.map((g) => (
                            <option key={g.id} value={g.id}>
                                {g.code}
                            </option>
                        ))}
                    </select>
                    {err('group_id')}
                </label>
                <label className="text-xs lg:col-span-2">
                    <span className="text-muted-foreground mb-0.5 block">Estudiante {type?.per_student ? '' : '(opcional)'}</span>
                    <select
                        className={selectCls + ' w-full'}
                        value={form.data.student_id}
                        disabled={!form.data.group_id}
                        onChange={(e) => form.setData('student_id', e.target.value)}
                    >
                        <option value="">
                            {form.data.group_id ? (type?.per_student ? 'Sin nombre: usar la cantidad' : '—') : 'Elige primero el grupo'}
                        </option>
                        {students.map((s) => (
                            <option key={s.id} value={s.id}>
                                {s.name}
                            </option>
                        ))}
                    </select>
                    {err('student_id')}
                </label>
                <label className="text-xs">
                    <span className="text-muted-foreground mb-0.5 block">Fecha</span>
                    <input
                        type="date"
                        className={selectCls + ' w-full'}
                        value={form.data.occurred_on}
                        onChange={(e) => form.setData('occurred_on', e.target.value)}
                    />
                    {err('occurred_on')}
                </label>
                <label className="text-xs">
                    <span className="text-muted-foreground mb-0.5 block">Semestre</span>
                    <select className={selectCls + ' w-full'} value={form.data.semester} onChange={(e) => form.setData('semester', e.target.value)}>
                        <option value="">Según la fecha</option>
                        <option value="1">I (jul–dic)</option>
                        <option value="2">II (ene–jun)</option>
                    </select>
                </label>
                <label className="text-xs">
                    <span className="text-muted-foreground mb-0.5 block">Cantidad</span>
                    <input
                        type="number"
                        min={1}
                        max={1000}
                        className={selectCls + ' w-full'}
                        value={fixedOne ? 1 : form.data.quantity}
                        disabled={fixedOne}
                        onChange={(e) => form.setData('quantity', Number(e.target.value))}
                    />
                    {err('quantity')}
                </label>
                <label className="text-xs sm:col-span-2 lg:col-span-3">
                    <span className="text-muted-foreground mb-0.5 block">Descripción (opcional)</span>
                    <input
                        className={selectCls + ' w-full'}
                        value={form.data.description}
                        onChange={(e) => form.setData('description', e.target.value)}
                    />
                    {err('description')}
                </label>
                <div className="flex items-end gap-2">
                    <button
                        type="submit"
                        disabled={form.processing}
                        className="bg-primary text-primary-foreground h-9 rounded-md px-4 text-sm font-semibold disabled:opacity-60"
                    >
                        {editing ? 'Guardar' : 'Registrar'}
                    </button>
                    {editing && (
                        <button type="button" className="text-muted-foreground h-9 px-2 text-sm" onClick={onDone}>
                            Cancelar
                        </button>
                    )}
                </div>
            </div>
            {form.recentlySuccessful && !editing && <p className="text-good mt-2 text-xs">Registrada.</p>}
        </form>
    );
}
