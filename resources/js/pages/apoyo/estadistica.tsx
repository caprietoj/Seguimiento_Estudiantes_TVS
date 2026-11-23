import { AREA_COLORS } from '@/components/apoyo/area-colors';
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
    scopes: { value: string; label: string }[];
    filters: { anio: number; periodo: string; seccion: string };
    yearName: string;
    stats: {
        areas: Area[];
        indicators: Indicator[];
        cases: { student_id: number; name: string; group: string; areas: string[] }[];
        notes_count: number;
    };
}

function query(filters: Props['filters'], change: Partial<Props['filters']>) {
    const f = { ...filters, ...change };
    const q: Record<string, string> = { anio: String(f.anio) };
    if (f.periodo !== 'todo') q.periodo = f.periodo;
    if (f.seccion !== 'todas') q.seccion = f.seccion;
    return q;
}

export default function Estadistica({ years, sections, scopes, filters, yearName, stats }: Props) {
    const series: ChartSeries[] = stats.areas.map((a) => ({ key: a.value, label: a.label, color: AREA_COLORS[a.value] ?? '#888' }));
    const go = (change: Partial<Props['filters']>) => router.get('/apoyo/estadistica', query(filters, change), { preserveScroll: true });
    const excelHref = '/apoyo/estadistica/excel?' + new URLSearchParams(query(filters, {})).toString();
    const periodLabel = (scopes.find((x) => x.value === filters.periodo)?.label ?? 'Año completo').toLowerCase();

    return (
        <SeguimientoLayout>
            <Head title="Estadística general · Dpto. de Apoyo" />

            <div className="mb-4 flex flex-wrap items-end justify-between gap-3">
                <div>
                    <h2 className="font-display text-primary text-2xl">Estadística general</h2>
                    <p className="text-muted-foreground text-sm">
                        Departamento de Apoyo · {yearName} · {periodLabel}. Se calcula con las notas que cada área registra por estudiante en la
                        reunión de grupo.
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
                            value={filters.periodo}
                            onChange={(e) => go({ periodo: e.target.value })}
                        >
                            {scopes.map((x) => (
                                <option key={x.value} value={x.value}>
                                    {x.label}
                                </option>
                            ))}
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

            {stats.notes_count === 0 ? (
                <div className="border-border bg-card rounded-lg border p-8 text-center">
                    <p className="font-semibold">Todavía no hay notas del Dpto. de Apoyo con estos filtros.</p>
                    <p className="text-muted-foreground mt-1 text-sm">
                        Las cifras salen de lo que Psicología, Fonoaudiología, T.O. y Neuropsicología registran por estudiante en{' '}
                        <Link href="/reunion" className="text-primary underline">
                            Reunión de grupo
                        </Link>{' '}
                        con sus marcas: en seguimiento, familia atendida, reunión PIAR y atención en crisis.
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

                    <CasesList cases={stats.cases} />
                </>
            )}
        </SeguimientoLayout>
    );
}

const HOW: Record<string, string> = {
    students: 'Estudiantes distintos con la marca «En seguimiento» en alguna nota del área.',
    families: 'Notas del área con la marca «Se atendió a la familia».',
    meetings: 'Reuniones de grupo (seguimientos) en que el área registró al menos una nota de un estudiante del grupo.',
    piar: 'Notas del área con la marca «Reunión PIAR».',
    crisis: 'Notas del área con la marca «Atención en crisis».',
};

function IndicatorBlock({ n, indicator, areas, series }: { n: number; indicator: Indicator; areas: Area[]; series: ChartSeries[] }) {
    const [showSections, setShowSections] = useState(false);
    // La fila «Todo el colegio» no va en la gráfica (no es un curso), sí en la tabla.
    const categories = indicator.rows.filter((r) => r.group_id !== null).map((r) => ({ label: r.label, sublabel: r.grade, values: r.values }));
    const hasData = indicator.total > 0;

    return (
        <section id={`ind-${indicator.type}`} className="border-border bg-card mb-5 scroll-mt-4 rounded-lg border p-4">
            <div className="mb-3 flex flex-wrap items-baseline justify-between gap-2">
                <div>
                    <h3 className="text-primary text-base font-semibold">
                        {n}. {indicator.title}
                    </h3>
                    <p className="text-muted-foreground text-xs">{HOW[indicator.type]}</p>
                </div>
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

                    <div className="min-w-0 self-start">
                        <div className="mb-2">
                            <ChartLegend series={series} />
                        </div>
                        <GroupedBarChart
                            title={indicator.title}
                            series={series}
                            categories={categories}
                            // Fija junto a su tabla (sin acompañar el scroll), con una altura parecida a la de la tabla.
                            height={Math.max(260, Math.min(560, indicator.rows.length * 29 + 40))}
                        />
                    </div>
                </div>
            )}
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
