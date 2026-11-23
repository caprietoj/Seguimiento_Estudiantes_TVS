import { useEffect, useRef, useState } from 'react';

export interface ChartSeries {
    key: string;
    label: string;
    color: string;
}

export interface ChartCategory {
    label: string;
    sublabel?: string;
    values: Record<string, number>;
}

interface Props {
    series: ChartSeries[];
    categories: ChartCategory[];
    height?: number;
    title: string;
}

const PAD = { top: 12, right: 8, bottom: 34, left: 32 };

function niceMax(max: number): number {
    if (max <= 5) return Math.max(1, max);
    const step = Math.pow(10, Math.floor(Math.log10(max)));
    for (const m of [1, 2, 2.5, 5, 10]) {
        if (m * step >= max) return m * step;
    }
    return 10 * step;
}

function ticks(max: number): number[] {
    const count = max <= 5 ? max : 4;
    return Array.from({ length: count + 1 }, (_, i) => Math.round((max / count) * i * 10) / 10);
}

/**
 * Barras agrupadas (un grupo por curso, una barra por área), dibujadas en SVG sin
 * dependencias. Barras finas con extremo redondeado, 2 px de separación entre barras,
 * rejilla tenue y tooltip al pasar el mouse por cada curso.
 */
export function GroupedBarChart({ series, categories, height = 240, title }: Props) {
    const wrap = useRef<HTMLDivElement>(null);
    const [width, setWidth] = useState(640);
    const [hover, setHover] = useState<number | null>(null);

    useEffect(() => {
        const el = wrap.current;
        if (!el) return;
        const ro = new ResizeObserver(([entry]) => setWidth(Math.max(280, entry.contentRect.width)));
        ro.observe(el);
        return () => ro.disconnect();
    }, []);

    const max = niceMax(Math.max(0, ...categories.flatMap((c) => series.map((s) => c.values[s.key] ?? 0))));
    const innerW = width - PAD.left - PAD.right;
    const innerH = height - PAD.top - PAD.bottom;
    const slot = categories.length ? innerW / categories.length : innerW;
    const gap = 2;
    const groupW = Math.min(slot * 0.78, series.length * 18);
    const barW = Math.max(2, (groupW - gap * (series.length - 1)) / series.length);
    const y = (v: number) => PAD.top + innerH - (v / max) * innerH;
    const rotate = slot < 34;

    return (
        <div ref={wrap} className="relative w-full">
            <svg width={width} height={height} role="img" aria-label={title} className="block">
                {ticks(max).map((t) => (
                    <g key={t}>
                        <line
                            x1={PAD.left}
                            x2={width - PAD.right}
                            y1={y(t)}
                            y2={y(t)}
                            stroke="currentColor"
                            className="text-border"
                            strokeWidth={1}
                        />
                        <text x={PAD.left - 6} y={y(t)} dy="0.32em" textAnchor="end" className="fill-muted-foreground text-[10px]">
                            {t}
                        </text>
                    </g>
                ))}

                {categories.map((cat, ci) => {
                    const x0 = PAD.left + ci * slot + (slot - groupW) / 2;
                    return (
                        <g key={cat.label + ci}>
                            {hover === ci && <rect x={PAD.left + ci * slot} y={PAD.top} width={slot} height={innerH} className="fill-muted" />}
                            {series.map((s, si) => {
                                const v = cat.values[s.key] ?? 0;
                                if (v <= 0) return null;
                                const h = Math.max(1, (v / max) * innerH);
                                const x = x0 + si * (barW + gap);
                                const r = Math.min(4, barW / 2, h);
                                const top = PAD.top + innerH - h;
                                // Solo el extremo de datos va redondeado; la base queda recta sobre el eje.
                                const d = `M${x},${top + h} L${x},${top + r} Q${x},${top} ${x + r},${top} L${x + barW - r},${top} Q${x + barW},${top} ${x + barW},${top + r} L${x + barW},${top + h} Z`;
                                return <path key={s.key} d={d} fill={s.color} />;
                            })}
                            <text
                                x={PAD.left + ci * slot + slot / 2}
                                y={height - PAD.bottom + 14}
                                textAnchor={rotate ? 'end' : 'middle'}
                                transform={rotate ? `rotate(-40 ${PAD.left + ci * slot + slot / 2} ${height - PAD.bottom + 14})` : undefined}
                                className="fill-muted-foreground text-[10px]"
                            >
                                {cat.label}
                            </text>
                            <rect
                                x={PAD.left + ci * slot}
                                y={PAD.top}
                                width={slot}
                                height={innerH + 20}
                                fill="transparent"
                                onMouseEnter={() => setHover(ci)}
                                onMouseLeave={() => setHover(null)}
                            />
                        </g>
                    );
                })}
                <line
                    x1={PAD.left}
                    x2={width - PAD.right}
                    y1={y(0)}
                    y2={y(0)}
                    stroke="currentColor"
                    className="text-muted-foreground"
                    strokeWidth={1}
                />
            </svg>

            {hover !== null && categories[hover] && (
                <div
                    className="bg-popover border-border pointer-events-none absolute z-10 min-w-36 rounded-md border px-2.5 py-2 text-xs shadow-md"
                    style={{
                        left: Math.min(width - 160, Math.max(0, PAD.left + hover * slot + slot / 2 - 72)),
                        top: 4,
                    }}
                >
                    <div className="mb-1 font-semibold">
                        {categories[hover].label}
                        {categories[hover].sublabel ? (
                            <span className="text-muted-foreground font-normal"> · {categories[hover].sublabel}</span>
                        ) : null}
                    </div>
                    {series.map((s) => (
                        <div key={s.key} className="flex items-center justify-between gap-3">
                            <span className="flex items-center gap-1.5">
                                <span className="inline-block size-2 rounded-sm" style={{ background: s.color }} />
                                {s.label}
                            </span>
                            <span className="font-mono">{categories[hover].values[s.key] ?? 0}</span>
                        </div>
                    ))}
                </div>
            )}
        </div>
    );
}

export function ChartLegend({ series }: { series: ChartSeries[] }) {
    return (
        <div className="text-muted-foreground flex flex-wrap gap-3 text-xs">
            {series.map((s) => (
                <span key={s.key} className="flex items-center gap-1.5">
                    <span className="inline-block size-2.5 rounded-sm" style={{ background: s.color }} />
                    {s.label}
                </span>
            ))}
        </div>
    );
}
