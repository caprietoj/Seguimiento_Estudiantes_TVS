import SeguimientoLayout from '@/layouts/seguimiento-layout';
import { Head, Link, router } from '@inertiajs/react';

interface CommitmentRow {
    id: number;
    student_id: number;
    student_name: string;
    group_code: string | null;
    type: string;
    type_label: string;
    body: string;
    responsible: string | null;
    due_on: string | null;
    status: string;
    status_label: string;
    is_late: boolean;
    can_manage: boolean;
}

interface Props {
    groups: { id: number; code: string }[];
    filters: { grupo: string; estado: string; tipo: string };
    counts: { vencidos: number; pendientes: number; en_proceso: number; cumplidos: number };
    items: CommitmentRow[];
}

function navigate(filters: Record<string, string>) {
    router.get('/compromisos', filters, { preserveState: true });
}

export default function CompromisosIndex({ groups, filters, counts, items }: Props) {
    return (
        <SeguimientoLayout>
            <Head title="Compromisos" />

            <div className="mb-4 flex flex-wrap items-end justify-between gap-3">
                <div>
                    <h2 className="font-display text-primary text-2xl">Compromisos</h2>
                    <p className="text-muted-foreground text-sm">Del colegio y de la familia, en todos los grupos que puedes ver.</p>
                </div>
                <div className="flex gap-2">
                    <div className={'min-w-20 rounded-lg border px-3 py-1.5 ' + (counts.vencidos ? 'border-crit' : 'border-border bg-card')}>
                        <div className={'font-mono text-lg ' + (counts.vencidos ? 'text-crit' : '')}>{counts.vencidos}</div>
                        <div className="text-muted-foreground text-[11px]">Vencidos</div>
                    </div>
                    <div className="border-border bg-card min-w-20 rounded-lg border px-3 py-1.5">
                        <div className="font-mono text-lg">{counts.pendientes}</div>
                        <div className="text-muted-foreground text-[11px]">Pendientes</div>
                    </div>
                    <div className="border-border bg-card min-w-20 rounded-lg border px-3 py-1.5">
                        <div className="font-mono text-lg">{counts.en_proceso}</div>
                        <div className="text-muted-foreground text-[11px]">En proceso</div>
                    </div>
                    <div className="border-border bg-card min-w-20 rounded-lg border px-3 py-1.5">
                        <div className="font-mono text-lg">{counts.cumplidos}</div>
                        <div className="text-muted-foreground text-[11px]">Cumplidos</div>
                    </div>
                </div>
            </div>

            <div className="mb-3 flex flex-wrap items-end gap-4">
                <div className="flex flex-col gap-1">
                    <label className="text-muted-foreground text-[11px] font-semibold tracking-wide uppercase">Grupo</label>
                    <select
                        className="border-border bg-background rounded-md border px-2 py-1.5 text-sm"
                        value={filters.grupo}
                        onChange={(e) => navigate({ ...filters, grupo: e.target.value })}
                    >
                        <option value="todos">Todos</option>
                        {groups.map((g) => (
                            <option key={g.id} value={g.id}>
                                {g.code}
                            </option>
                        ))}
                    </select>
                </div>
                <div className="flex flex-col gap-1">
                    <label className="text-muted-foreground text-[11px] font-semibold tracking-wide uppercase">Estado</label>
                    <select
                        className="border-border bg-background rounded-md border px-2 py-1.5 text-sm"
                        value={filters.estado}
                        onChange={(e) => navigate({ ...filters, estado: e.target.value })}
                    >
                        <option value="abiertos">Abiertos</option>
                        <option value="vencidos">Vencidos</option>
                        <option value="todos">Todos</option>
                        <option value="pending">Pendiente</option>
                        <option value="in_progress">En proceso</option>
                        <option value="done">Cumplido</option>
                        <option value="closed">Cerrado</option>
                    </select>
                </div>
                <div className="flex flex-col gap-1">
                    <label className="text-muted-foreground text-[11px] font-semibold tracking-wide uppercase">De quién</label>
                    <select
                        className="border-border bg-background rounded-md border px-2 py-1.5 text-sm"
                        value={filters.tipo}
                        onChange={(e) => navigate({ ...filters, tipo: e.target.value })}
                    >
                        <option value="todos">Colegio y familia</option>
                        <option value="school">Colegio</option>
                        <option value="family">Familia</option>
                    </select>
                </div>
            </div>

            <div className="border-border bg-card overflow-x-auto rounded-lg border">
                <table className="w-full min-w-[760px] text-sm">
                    <thead>
                        <tr className="bg-muted text-muted-foreground text-left text-[11px] uppercase">
                            <th className="p-2">Estudiante</th>
                            <th className="p-2">Grupo</th>
                            <th className="p-2">De quién</th>
                            <th className="p-2">Compromiso</th>
                            <th className="p-2">Responsable</th>
                            <th className="p-2">Fecha límite</th>
                            <th className="p-2">Estado</th>
                        </tr>
                    </thead>
                    <tbody>
                        {items.length === 0 && (
                            <tr>
                                <td colSpan={7} className="text-muted-foreground p-3 text-center text-xs italic">
                                    No hay compromisos con estos filtros.
                                </td>
                            </tr>
                        )}
                        {items.map((c) => (
                            <tr key={c.id} className={'border-border border-t ' + (c.is_late ? 'shadow-[inset_3px_0_0_var(--crit)]' : '')}>
                                <td className="p-2">
                                    <Link href={`/estudiantes/${c.student_id}`} className="text-primary font-semibold hover:underline">
                                        {c.student_name}
                                    </Link>
                                </td>
                                <td className="p-2">{c.group_code}</td>
                                <td className="p-2">
                                    <span
                                        className={
                                            'rounded-full px-2 py-0.5 text-[11px] font-semibold ' +
                                            (c.type === 'family' ? 'bg-accent text-primary' : 'bg-muted')
                                        }
                                    >
                                        {c.type_label}
                                    </span>
                                </td>
                                <td className="p-2">{c.body}</td>
                                <td className="p-2">{c.responsible}</td>
                                <td className={'p-2 font-mono ' + (c.is_late ? 'text-crit font-semibold' : '')}>{c.due_on}</td>
                                <td className="p-2">
                                    {c.can_manage ? (
                                        <select
                                            defaultValue={c.status}
                                            className="border-border bg-background rounded-md border px-2 py-1 text-xs"
                                            onChange={(e) =>
                                                router.patch(`/compromisos/${c.id}/estado`, { status: e.target.value }, { preserveScroll: true })
                                            }
                                        >
                                            <option value="pending">Pendiente</option>
                                            <option value="in_progress">En proceso</option>
                                            <option value="done">Cumplido</option>
                                            <option value="closed">Cerrado</option>
                                        </select>
                                    ) : (
                                        <span className="bg-muted rounded-full px-2 py-0.5 text-[11px] font-semibold">{c.status_label}</span>
                                    )}
                                </td>
                            </tr>
                        ))}
                    </tbody>
                </table>
            </div>
        </SeguimientoLayout>
    );
}
