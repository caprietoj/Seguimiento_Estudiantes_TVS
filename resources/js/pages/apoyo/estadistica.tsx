import { AREA_COLORS, ApoyoNav } from '@/components/apoyo/apoyo-nav';
import { ChartLegend, GroupedBarChart, type ChartSeries } from '@/components/apoyo/grouped-bar-chart';
import SeguimientoLayout from '@/layouts/seguimiento-layout';
import { Head, Link, router } from '@inertiajs/react';
import { useMemo, useState } from 'react';

interface Area {
    value: string;
    label: string;
    short: string;
}

interface IndicatorRow {
    key: string;
    group_id: number | null;
    label: string;
    grade: string;
    section: string;
    values: Record<string, number>;
    total: number;
}

interface Indicator {
    type: string;
    title: string;
    per_student: boolean;
    rows: IndicatorRow[];
    sections: { section: string; values: Record<string, number>; total: number }[];
    totals: Record<string, number>;
    total: number;
}

interface Props {
    years: { id: number; name: string }[];
    sections: { id: number; name: string }[];
    filters: { anio: number; semestre: string; seccion: string };
    yearName: string;
    canWrite: boolean;
    stats: {
        areas: Area[];
        indicators: Indicator[];
        others: { type: string; label: string; values: Record<string, number>; total: number }[];
        cases: { student_id: number; name: string; group: string; areas: string[] }[];
        activities_count: number;
        unknown_semester: number;
    };
}

const OTHER_AREAS: { value: string; label: string }[] = [
    { value: 'psychology', label: 'Psicología' },
    { value: 'ot', label: 'T.O.' },
    { value: 'speech', label: 'Fono' },
    { value: 'neuro', label: 'Neuro' },
    { value: 'team', label: 'Equipo' },
];

function query(filters: Props['filters'], change: Partial<Props['filters']>) {
    const f = { ...filters, ...change };
    const q: Record<string, string> = { anio: String(f.anio) };
    if (f.semestre !== 'todo') q.semestre = f.semestre;
    if (f.seccion !== 'todas') q.seccion = f.seccion;
    return q;
}

export default function Estadistica({ years, sections, filters, yearName, canWrite, stats }: Props) {
    const series: ChartSeries[] = stats.areas.map((a) => ({ key: a.value, label: a.label, color: AREA_COLORS[a.value] ?? '#888' }));
    const go = (change: Partial<Props['filters']>) => router.get('/apoyo/estadistica', query(filters, change), { preserveScroll: true });
    const excelHref = '/apoyo/estadistica/excel?' + new URLSearchParams(query(filters, {})).toString();
    const periodLabel = filters.semestre === 'todo' ? 'año completo' : filters.semestre === '1' ? 'I semestre' : 'II semestre';

    return (
        <SeguimientoLayout>
            <Head title="Estadística general · Dpto. de Apoyo" />
            <ApoyoNav yearId={filters.anio} />

            <div className="mb-4 flex flex-wrap items-end justify-between gap-3">
                <div>
                    <h2 className="font-display text-primary text-2xl">Estadística general</h2>
                    <p className="text-muted-foreground text-sm">
                        Departamento de Apoyo · {yearName} · {periodLabel}. Se calcula con las actividades registradas.
                    </p>
                </div>

                <div className="flex flex-wrap items-end gap-2">
                    <label className="text-xs">
                        <span className="text-muted-foreground mb-0.5 block">Año escolar</span>
                        <select
                            className="border-input bg-background h-9 rounded-md border px-2 text-sm"
                            value={filters.anio}
                            onChange={(e) => go({ anio: Number(e.target.value) })}
                        >
                            {years.map((y) => (
                                <option key={y.id} value={y.id}>
                                    {y.name}
                                </option>
                            ))}
                        </select>
                    </label>
                    <label className="text-xs">
                        <span className="text-muted-foreground mb-0.5 block">Periodo</span>
                        <select
                            className="border-input bg-background h-9 rounded-md border px-2 text-sm"
                            value={filters.semestre}
                            onChange={(e) => go({ semestre: e.target.value })}
                        >
                            <option value="todo">Año completo</option>
                            <option value="1">I semestre (jul–dic)</option>
                            <option value="2">II semestre (ene–jun)</option>
                        </select>
                    </label>
                    <label className="text-xs">
                        <span className="text-muted-foreground mb-0.5 block">Sección</span>
                        <select
                            className="border-input bg-background h-9 rounded-md border px-2 text-sm"
                            value={filters.seccion}
                            onChange={(e) => go({ seccion: e.target.value })}
                        >
                            <option value="todas">Todas</option>
                            {sections.map((s) => (
                                <option key={s.id} value={s.id}>
                                    {s.name}
                                </option>
                            ))}
                        </select>
                    </label>
                    <a
                        href={excelHref}
                        className="bg-primary text-primary-foreground h-9 rounded-md px-3 py-2 text-sm font-semibold hover:opacity-90"
                    >
                        Descargar Excel
                    </a>
                </div>
            </div>

            {stats.unknown_semester > 0 && (
                <p className="bg-warn-soft text-warn mb-4 rounded-md px-3 py-2 text-sm">
                    {stats.unknown_semester} actividad(es) de este año no tienen fecha ni semestre, así que no se cuentan al filtrar por semestre.
                </p>
            )}

            {stats.activities_count === 0 ? (
                <div className="border-border bg-card rounded-lg border p-8 text-center">
                    <p className="font-semibold">Todavía no hay actividades registradas con estos filtros.</p>
                    <p className="text-muted-foreground mt-1 text-sm">
                        Regístralas en{' '}
                        <Link href={`/apoyo/actividades?anio=${filters.anio}`} className="text-primary underline">
                            Registro de actividades
                        </Link>
                        {canWrite ? ' o impórtalas en Administración · Importar («Actividades del Dpto. de Apoyo»).' : '.'}
                    </p>
                </div>
            ) : (
                <>
                    <div className="mb-5 grid grid-cols-2 gap-2 md:grid-cols-5">
                        {stats.indicators.map((ind, i) => (
                            <a
                                key={ind.type}
                                href={`#ind-${ind.type}`}
                                className="border-border bg-card hover:border-primary rounded-lg border px-3 py-2"
                            >
                                <div className="font-display text-primary text-2xl">{ind.total}</div>
                                <div className="text-muted-foreground text-xs leading-tight">
                                    {i + 1}. {ind.title}
                                </div>
                            </a>
                        ))}
                    </div>

                    {stats.indicators.map((ind, i) => (
                        <IndicatorBlock key={ind.type} n={i + 1} indicator={ind} areas={stats.areas} series={series} />
                    ))}

                    <OthersTable others={stats.others} />
                    <CasesList cases={stats.cases} />
                </>
            )}
        </SeguimientoLayout>
    );
}

function IndicatorBlock({ n, indicator, areas, series }: { n: number; indicator: Indicator; areas: Area[]; series: ChartSeries[] }) {
    const [showSections, setShowSections] = useState(false);
    // La fila «Todo el colegio» no va en la gráfica (no es un curso), sí en la tabla.
    const categories = indicator.rows.filter((r) => r.group_id !== null).map((r) => ({ label: r.label, sublabel: r.grade, values: r.values }));
    const hasData = indicator.total > 0;

    return (
        <section id={`ind-${indicator.type}`} className="border-border bg-card mb-5 scroll-mt-4 rounded-lg border p-4">
            <div className="mb-3 flex flex-wrap items-baseline justify-between gap-2">
                <h3 className="text-primary text-base font-semibold">
                    {n}. {indicator.title}
                </h3>
                <div className="text-muted-foreground text-xs">
                    Total: <span className="text-foreground font-mono font-semibold">{indicator.total}</span>
                    {indicator.per_student ? ' estudiantes distintos' : ''}
                </div>
            </div>

            {!hasData ? (
                <p className="text-muted-foreground text-sm">Sin registros para este indicador.</p>
            ) : (
                <div className="grid gap-4 xl:grid-cols-[minmax(0,420px)_1fr]">
                    <div className="overflow-x-auto">
                        <table className="w-full text-sm">
                            <thead>
                                <tr className="border-border text-muted-foreground border-b text-left text-xs">
                                    <th className="py-1.5 pr-2 font-semibold">Grupo</th>
                                    {areas.map((a) => (
                                        <th key={a.value} className="px-1.5 py-1.5 text-right font-semibold">
                                            {a.short}
                                        </th>
                                    ))}
                                    <th className="py-1.5 pl-1.5 text-right font-semibold">Total</th>
                                </tr>
                            </thead>
                            <tbody>
                                {indicator.rows.map((r) => (
                                    <tr key={r.key} className="border-border/60 border-b">
                                        <td className="py-1 pr-2">
                                            <span className="font-semibold">{r.label}</span>
                                            {r.grade && <span className="text-muted-foreground ml-1.5 text-xs">{r.grade}</span>}
                                        </td>
                                        {areas.map((a) => (
                                            <td
                                                key={a.value}
                                                className={
                                                    'px-1.5 py-1 text-right font-mono ' + (r.values[a.value] ? '' : 'text-muted-foreground/50')
                                                }
                                            >
                                                {r.values[a.value]}
                                            </td>
                                        ))}
                                        <td className="py-1 pl-1.5 text-right font-mono font-semibold">{r.total}</td>
                                    </tr>
                                ))}
                            </tbody>
                            <tfoot>
                                <tr className="border-foreground/30 border-t-2 font-semibold">
                                    <td className="py-1.5 pr-2">Total</td>
                                    {areas.map((a) => (
                                        <td key={a.value} className="px-1.5 py-1.5 text-right font-mono">
                                            {indicator.totals[a.value]}
                                        </td>
                                    ))}
                                    <td className="py-1.5 pl-1.5 text-right font-mono">{indicator.total}</td>
                                </tr>
                                {showSections &&
                                    indicator.sections.map((s) => (
                                        <tr key={s.section} className="text-muted-foreground text-xs">
                                            <td className="py-1 pr-2 italic">{s.section}</td>
                                            {areas.map((a) => (
                                                <td key={a.value} className="px-1.5 py-1 text-right font-mono">
                                                    {s.values[a.value]}
                                                </td>
                                            ))}
                                            <td className="py-1 pl-1.5 text-right font-mono">{s.total}</td>
                                        </tr>
                                    ))}
                            </tfoot>
                        </table>
                        {indicator.sections.length > 1 && (
                            <button type="button" className="text-primary mt-1.5 text-xs underline" onClick={() => setShowSections((v) => !v)}>
                                {showSections ? 'Ocultar' : 'Ver'} totales por sección
                            </button>
                        )}
                        {indicator.per_student && (
                            <p className="text-muted-foreground mt-1.5 text-xs">
                                El total cuenta a cada estudiante una vez aunque lo acompañen varias áreas.
                            </p>
                        )}
                    </div>

                    <div className="min-w-0 xl:sticky xl:top-4 xl:self-start">
                        <div className="mb-2">
                            <ChartLegend series={series} />
                        </div>
                        <GroupedBarChart
                            title={indicator.title}
                            series={series}
                            categories={categories}
                            height={Math.max(260, Math.min(520, indicator.rows.length * 22 + 40))}
                        />
                    </div>
                </div>
            )}
        </section>
    );
}

function OthersTable({ others }: { others: Props['stats']['others'] }) {
    if (others.length === 0) return null;
    const used = OTHER_AREAS.filter((a) => others.some((o) => o.values[a.value] > 0));

    return (
        <section className="border-border bg-card mb-5 rounded-lg border p-4">
            <h3 className="text-primary mb-3 text-base font-semibold">Otras actividades del departamento</h3>
            <div className="overflow-x-auto">
                <table className="w-full max-w-3xl text-sm">
                    <thead>
                        <tr className="border-border text-muted-foreground border-b text-left text-xs">
                            <th className="py-1.5 pr-2 font-semibold">Actividad</th>
                            {used.map((a) => (
                                <th key={a.value} className="px-2 py-1.5 text-right font-semibold">
                                    {a.label}
                                </th>
                            ))}
                            <th className="py-1.5 pl-2 text-right font-semibold">Total</th>
                        </tr>
                    </thead>
                    <tbody>
                        {others.map((o) => (
                            <tr key={o.type} className="border-border/60 border-b">
                                <td className="py-1 pr-2">{o.label}</td>
                                {used.map((a) => (
                                    <td
                                        key={a.value}
                                        className={'px-2 py-1 text-right font-mono ' + (o.values[a.value] ? '' : 'text-muted-foreground/50')}
                                    >
                                        {o.values[a.value]}
                                    </td>
                                ))}
                                <td className="py-1 pl-2 text-right font-mono font-semibold">{o.total}</td>
                            </tr>
                        ))}
                    </tbody>
                </table>
            </div>
        </section>
    );
}

function CasesList({ cases }: { cases: Props['stats']['cases'] }) {
    const [open, setOpen] = useState(false);
    const [q, setQ] = useState('');
    const filtered = useMemo(() => {
        const t = q.trim().toLowerCase();
        return t ? cases.filter((c) => c.name.toLowerCase().includes(t) || c.group.toLowerCase().includes(t)) : cases;
    }, [cases, q]);

    if (cases.length === 0) return null;

    return (
        <section className="border-border bg-card rounded-lg border p-4">
            <div className="flex flex-wrap items-center justify-between gap-2">
                <h3 className="text-primary text-base font-semibold">Estudiantes en seguimiento ({cases.length})</h3>
                <button type="button" className="text-primary text-sm underline" onClick={() => setOpen((v) => !v)}>
                    {open ? 'Ocultar lista' : 'Ver lista'}
                </button>
            </div>
            {open && (
                <>
                    <input
                        className="border-input bg-background my-3 h-9 w-full max-w-sm rounded-md border px-2 text-sm"
                        placeholder="Buscar por nombre o grupo"
                        value={q}
                        onChange={(e) => setQ(e.target.value)}
                    />
                    <div className="grid gap-x-6 sm:grid-cols-2 lg:grid-cols-3">
                        {filtered.map((c) => (
                            <div key={c.student_id} className="border-border/60 flex items-baseline justify-between gap-2 border-b py-1 text-sm">
                                <Link href={`/estudiantes/${c.student_id}`} className="hover:text-primary truncate">
                                    <span className="text-muted-foreground mr-1.5 font-mono text-xs">{c.group}</span>
                                    {c.name}
                                </Link>
                                <span className="text-muted-foreground shrink-0 text-xs">{c.areas.join(' · ')}</span>
                            </div>
                        ))}
                    </div>
                </>
            )}
        </section>
    );
}
