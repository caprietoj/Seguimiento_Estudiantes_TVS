import { AdminNav } from '@/components/admin-nav';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import SeguimientoLayout from '@/layouts/seguimiento-layout';
import { Head, useForm } from '@inertiajs/react';
import { Plus, RotateCcw, Trash2 } from 'lucide-react';

interface MapArea {
    key: string;
    name: string;
    active: boolean;
}

interface GradeConfig {
    ib_escala_min: number;
    ib_escala_max: number;
    ib_nivel_atencion: number;
    ib_periodos: number;
    map_periodos: number;
    map_corte_bajo: number;
    map_corte_promedio_bajo: number;
    map_corte_promedio: number;
    map_corte_promedio_alto: number;
    map_areas: MapArea[];
}

interface Props {
    config: GradeConfig;
    flash?: { success?: string | null; error?: string | null };
}

const DEFAULT_MAP_AREAS: MapArea[] = [
    { key: 'math', name: 'Matemáticas (Math)', active: true },
    { key: 'reading', name: 'Lectura (Reading)', active: true },
    { key: 'language', name: 'Uso del Lenguaje (Language Usage)', active: true },
    { key: 'science', name: 'Ciencias (Science)', active: true },
];

export default function AdminCalificaciones({ config, flash }: Props) {
    const form = useForm({
        ib_escala_min: config.ib_escala_min,
        ib_escala_max: config.ib_escala_max,
        ib_nivel_atencion: config.ib_nivel_atencion,
        ib_periodos: config.ib_periodos,
        map_periodos: config.map_periodos,
        map_corte_bajo: config.map_corte_bajo,
        map_corte_promedio_bajo: config.map_corte_promedio_bajo,
        map_corte_promedio: config.map_corte_promedio,
        map_corte_promedio_alto: config.map_corte_promedio_alto,
        map_areas: config.map_areas ?? DEFAULT_MAP_AREAS,
    });

    const submit = (e: React.FormEvent) => {
        e.preventDefault();
        form.put('/admin/calificaciones', {
            preserveScroll: true,
        });
    };

    const updateArea = (index: number, field: keyof MapArea, value: string | boolean) => {
        const next = [...form.data.map_areas];
        next[index] = { ...next[index], [field]: value };
        form.setData('map_areas', next);
    };

    const addArea = () => {
        const baseKey = `area_${form.data.map_areas.length + 1}`;
        form.setData('map_areas', [
            ...form.data.map_areas,
            { key: baseKey, name: 'Nueva Área', active: true },
        ]);
    };

    const removeArea = (index: number) => {
        if (form.data.map_areas.length <= 1) return;
        form.setData(
            'map_areas',
            form.data.map_areas.filter((_, i) => i !== index),
        );
    };

    const resetDefaultAreas = () => {
        form.setData('map_areas', DEFAULT_MAP_AREAS);
    };

    return (
        <SeguimientoLayout>
            <Head title="Administración · Configuración de calificaciones" />

            <AdminNav />

            <div className="mb-6">
                <h1 className="text-xl font-bold tracking-tight">Configuración de calificaciones</h1>
                <p className="text-muted-foreground text-sm">
                    Ajusta los parámetros para la escala de Desempeño Académico (IB) y las evaluaciones estandarizadas MAP Growth (NWEA).
                </p>
            </div>

            {flash?.success && (
                <div className="mb-4 rounded-md border border-emerald-200 bg-emerald-50 p-3 text-sm text-emerald-800 dark:border-emerald-900 dark:bg-emerald-950/60 dark:text-emerald-200">
                    {flash.success}
                </div>
            )}

            <form onSubmit={submit} className="space-y-6 max-w-4xl">
                {/* ── Bloque 1: Desempeño Académico Regular (IB) ── */}
                <div className="rounded-lg border border-border bg-card p-5 shadow-xs">
                    <div className="mb-4 border-b border-border pb-3">
                        <h2 className="text-base font-semibold text-foreground">Desempeño académico · Escala IB</h2>
                        <p className="text-xs text-muted-foreground">
                            Configuración de la escala numérica de calificaciones por periodo y límites de alerta temprana.
                        </p>
                    </div>

                    <div className="grid grid-cols-1 gap-4 sm:grid-cols-2 md:grid-cols-4">
                        <div className="space-y-1.5">
                            <Label htmlFor="ib_escala_min">Nivel mínimo</Label>
                            <Input
                                id="ib_escala_min"
                                type="number"
                                min={1}
                                max={10}
                                value={form.data.ib_escala_min}
                                onChange={(e) => form.setData('ib_escala_min', parseInt(e.target.value) || 1)}
                            />
                            {form.errors.ib_escala_min && <p className="text-xs text-destructive">{form.errors.ib_escala_min}</p>}
                            <p className="text-[11px] text-muted-foreground">Normalmente 1</p>
                        </div>

                        <div className="space-y-1.5">
                            <Label htmlFor="ib_escala_max">Nivel máximo</Label>
                            <Input
                                id="ib_escala_max"
                                type="number"
                                min={form.data.ib_escala_min + 1}
                                max={100}
                                value={form.data.ib_escala_max}
                                onChange={(e) => form.setData('ib_escala_max', parseInt(e.target.value) || 7)}
                            />
                            {form.errors.ib_escala_max && <p className="text-xs text-destructive">{form.errors.ib_escala_max}</p>}
                            <p className="text-[11px] text-muted-foreground">Normalmente 7 (escala IB)</p>
                        </div>

                        <div className="space-y-1.5">
                            <Label htmlFor="ib_nivel_atencion">Umbral de atención</Label>
                            <Input
                                id="ib_nivel_atencion"
                                type="number"
                                min={form.data.ib_escala_min}
                                max={form.data.ib_escala_max}
                                value={form.data.ib_nivel_atencion}
                                onChange={(e) => form.setData('ib_nivel_atencion', parseInt(e.target.value) || 3)}
                            />
                            {form.errors.ib_nivel_atencion && <p className="text-xs text-destructive">{form.errors.ib_nivel_atencion}</p>}
                            <p className="text-[11px] text-muted-foreground">Nivel ≤ a este marca alerta</p>
                        </div>

                        <div className="space-y-1.5">
                            <Label htmlFor="ib_periodos">Periodos por año</Label>
                            <Input
                                id="ib_periodos"
                                type="number"
                                min={1}
                                max={6}
                                value={form.data.ib_periodos}
                                onChange={(e) => form.setData('ib_periodos', parseInt(e.target.value) || 3)}
                            />
                            {form.errors.ib_periodos && <p className="text-xs text-destructive">{form.errors.ib_periodos}</p>}
                            <p className="text-[11px] text-muted-foreground">Típicamente 3 periodos</p>
                        </div>
                    </div>
                </div>

                {/* ── Bloque 2: Pruebas estandarizadas MAP Growth (NWEA) ── */}
                <div className="rounded-lg border border-border bg-card p-5 shadow-xs space-y-6">
                    <div className="border-b border-border pb-3">
                        <h2 className="text-base font-semibold text-foreground">Pruebas estandarizadas MAP Growth (NWEA)</h2>
                        <p className="text-xs text-muted-foreground">
                            Áreas evaluadas, frecuencia de aplicación y percentiles normativos para la asignación de quintiles.
                        </p>
                    </div>

                    {/* 2.1 Áreas evaluadas */}
                    <div>
                        <div className="mb-3 flex flex-wrap items-center justify-between gap-2">
                            <div>
                                <h3 className="text-xs font-semibold uppercase tracking-wide text-primary">
                                    Áreas evaluadas de MAP Growth
                                </h3>
                                <p className="text-[11px] text-muted-foreground">
                                    Configura las materias o dimensiones evaluadas en la prueba MAP (ej. Matemáticas, Lectura, Ciencias).
                                </p>
                            </div>
                            <div className="flex items-center gap-2">
                                <Button
                                    type="button"
                                    variant="outline"
                                    size="sm"
                                    className="h-7 text-xs"
                                    onClick={resetDefaultAreas}
                                    title="Restaurar a las 4 áreas estándar de NWEA"
                                >
                                    <RotateCcw className="mr-1.5 h-3.5 w-3.5" />
                                    Restaurar estándar NWEA
                                </Button>
                                <Button
                                    type="button"
                                    size="sm"
                                    className="h-7 text-xs"
                                    onClick={addArea}
                                >
                                    <Plus className="mr-1.5 h-3.5 w-3.5" />
                                    Agregar área
                                </Button>
                            </div>
                        </div>

                        {form.errors.map_areas && (
                            <p className="mb-2 text-xs text-destructive">{form.errors.map_areas}</p>
                        )}

                        <div className="overflow-x-auto rounded-md border border-border">
                            <table className="w-full text-sm">
                                <thead>
                                    <tr className="border-b border-border bg-muted/50 text-[11px] uppercase tracking-wider text-muted-foreground text-left">
                                        <th className="py-2 px-3 text-center w-16">Activa</th>
                                        <th className="py-2 px-3">Nombre legible / Etiqueta</th>
                                        <th className="py-2 px-3 w-48">Identificador (Clave)</th>
                                        <th className="py-2 px-3 text-center w-16">Acción</th>
                                    </tr>
                                </thead>
                                <tbody className="divide-y divide-border">
                                    {form.data.map_areas.map((area, idx) => (
                                        <tr key={idx} className={area.active ? 'bg-card' : 'bg-muted/20 opacity-70'}>
                                            <td className="py-2 px-3 text-center align-middle">
                                                <input
                                                    type="checkbox"
                                                    checked={area.active}
                                                    onChange={(e) => updateArea(idx, 'active', e.target.checked)}
                                                    className="h-4 w-4 rounded border-border text-primary focus:ring-primary cursor-pointer"
                                                    title={area.active ? 'Área activa en la ficha' : 'Área inactiva (no se mostrará en la ficha)'}
                                                />
                                            </td>
                                            <td className="py-2 px-3 align-middle">
                                                <Input
                                                    value={area.name}
                                                    onChange={(e) => updateArea(idx, 'name', e.target.value)}
                                                    placeholder="Ej: Matemáticas (Math)"
                                                    className="h-8 text-xs"
                                                />
                                            </td>
                                            <td className="py-2 px-3 align-middle">
                                                <Input
                                                    value={area.key}
                                                    onChange={(e) =>
                                                        updateArea(
                                                            idx,
                                                            'key',
                                                            e.target.value.toLowerCase().replace(/[^a-z0-9_-]/g, ''),
                                                        )
                                                    }
                                                    placeholder="ej: math"
                                                    className="h-8 text-xs font-mono"
                                                />
                                            </td>
                                            <td className="py-2 px-3 text-center align-middle">
                                                <Button
                                                    type="button"
                                                    variant="ghost"
                                                    size="icon"
                                                    className="h-7 w-7 text-muted-foreground hover:text-destructive hover:bg-destructive/10"
                                                    onClick={() => removeArea(idx)}
                                                    disabled={form.data.map_areas.length <= 1}
                                                    title="Eliminar área"
                                                >
                                                    <Trash2 className="h-3.5 w-3.5" />
                                                </Button>
                                            </td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        </div>
                        <p className="mt-1.5 text-[11px] text-muted-foreground">
                            * El identificador se usa internamente para la vinculación de registros y la importación masiva. Solo letras minúsculas, números y guiones.
                        </p>
                    </div>

                    {/* 2.2 Frecuencia anual */}
                    <div className="pt-2 border-t border-border">
                        <div className="max-w-xs space-y-1.5">
                            <Label htmlFor="map_periodos">Evaluaciones al año (Periodos)</Label>
                            <Input
                                id="map_periodos"
                                type="number"
                                min={1}
                                max={4}
                                value={form.data.map_periodos}
                                onChange={(e) => form.setData('map_periodos', parseInt(e.target.value) || 2)}
                            />
                            {form.errors.map_periodos && <p className="text-xs text-destructive">{form.errors.map_periodos}</p>}
                            <p className="text-[11px] text-muted-foreground">Actualmente configurado en 2 veces al año</p>
                        </div>
                    </div>

                    {/* 2.3 Cortes normativos */}
                    <div className="pt-2 border-t border-border">
                        <h3 className="mb-2 text-xs font-semibold uppercase tracking-wide text-primary">Cortes normativos de percentiles (1–99)</h3>
                        <div className="grid grid-cols-1 gap-4 sm:grid-cols-2 md:grid-cols-4">
                            <div className="space-y-1.5">
                                <Label htmlFor="map_corte_bajo">Corte Bajo (&lt;)</Label>
                                <Input
                                    id="map_corte_bajo"
                                    type="number"
                                    min={1}
                                    max={96}
                                    value={form.data.map_corte_bajo}
                                    onChange={(e) => form.setData('map_corte_bajo', parseInt(e.target.value) || 21)}
                                />
                                {form.errors.map_corte_bajo && <p className="text-xs text-destructive">{form.errors.map_corte_bajo}</p>}
                                <p className="text-[11px] text-muted-foreground">&lt; {form.data.map_corte_bajo}% = Bajo</p>
                            </div>

                            <div className="space-y-1.5">
                                <Label htmlFor="map_corte_promedio_bajo">Corte Promedio bajo (≤)</Label>
                                <Input
                                    id="map_corte_promedio_bajo"
                                    type="number"
                                    min={form.data.map_corte_bajo + 1}
                                    max={97}
                                    value={form.data.map_corte_promedio_bajo}
                                    onChange={(e) => form.setData('map_corte_promedio_bajo', parseInt(e.target.value) || 40)}
                                />
                                {form.errors.map_corte_promedio_bajo && <p className="text-xs text-destructive">{form.errors.map_corte_promedio_bajo}</p>}
                                <p className="text-[11px] text-muted-foreground">{form.data.map_corte_bajo}–{form.data.map_corte_promedio_bajo}% = Prom. bajo</p>
                            </div>

                            <div className="space-y-1.5">
                                <Label htmlFor="map_corte_promedio">Corte Promedio (≤)</Label>
                                <Input
                                    id="map_corte_promedio"
                                    type="number"
                                    min={form.data.map_corte_promedio_bajo + 1}
                                    max={98}
                                    value={form.data.map_corte_promedio}
                                    onChange={(e) => form.setData('map_corte_promedio', parseInt(e.target.value) || 60)}
                                />
                                {form.errors.map_corte_promedio && <p className="text-xs text-destructive">{form.errors.map_corte_promedio}</p>}
                                <p className="text-[11px] text-muted-foreground">{form.data.map_corte_promedio_bajo + 1}–{form.data.map_corte_promedio}% = Promedio</p>
                            </div>

                            <div className="space-y-1.5">
                                <Label htmlFor="map_corte_promedio_alto">Corte Promedio alto (≤)</Label>
                                <Input
                                    id="map_corte_promedio_alto"
                                    type="number"
                                    min={form.data.map_corte_promedio + 1}
                                    max={99}
                                    value={form.data.map_corte_promedio_alto}
                                    onChange={(e) => form.setData('map_corte_promedio_alto', parseInt(e.target.value) || 80)}
                                />
                                {form.errors.map_corte_promedio_alto && <p className="text-xs text-destructive">{form.errors.map_corte_promedio_alto}</p>}
                                <p className="text-[11px] text-muted-foreground">&gt; {form.data.map_corte_promedio_alto}% = Alto</p>
                            </div>
                        </div>

                        {/* Previsualización visual de los quintiles */}
                        <div className="mt-4 rounded-md border border-border/80 bg-muted/40 p-3">
                            <span className="text-[11px] font-semibold text-foreground uppercase tracking-wider block mb-2">Escala resultante de quintiles:</span>
                            <div className="flex flex-wrap gap-2 text-xs">
                                <span className="inline-flex items-center gap-1.5 rounded border border-rose-200 bg-rose-50 px-2 py-1 text-rose-800 dark:border-rose-900 dark:bg-rose-950/60 dark:text-rose-200">
                                    <span className="font-semibold">Bajo:</span> &lt; {form.data.map_corte_bajo}%
                                </span>
                                <span className="inline-flex items-center gap-1.5 rounded border border-orange-200 bg-orange-50 px-2 py-1 text-orange-800 dark:border-orange-900 dark:bg-orange-950/60 dark:text-orange-200">
                                    <span className="font-semibold">Promedio bajo:</span> {form.data.map_corte_bajo}–{form.data.map_corte_promedio_bajo}%
                                </span>
                                <span className="inline-flex items-center gap-1.5 rounded border border-amber-200 bg-amber-50 px-2 py-1 text-amber-800 dark:border-amber-900 dark:bg-amber-950/60 dark:text-amber-200">
                                    <span className="font-semibold">Promedio:</span> {form.data.map_corte_promedio_bajo + 1}–{form.data.map_corte_promedio}%
                                </span>
                                <span className="inline-flex items-center gap-1.5 rounded border border-teal-200 bg-teal-50 px-2 py-1 text-teal-800 dark:border-teal-900 dark:bg-teal-950/60 dark:text-teal-200">
                                    <span className="font-semibold">Promedio alto:</span> {form.data.map_corte_promedio + 1}–{form.data.map_corte_promedio_alto}%
                                </span>
                                <span className="inline-flex items-center gap-1.5 rounded border border-emerald-200 bg-emerald-50 px-2 py-1 text-emerald-800 dark:border-emerald-900 dark:bg-emerald-950/60 dark:text-emerald-200">
                                    <span className="font-semibold">Alto:</span> &gt; {form.data.map_corte_promedio_alto}%
                                </span>
                            </div>
                        </div>
                    </div>
                </div>

                <div className="flex items-center justify-end gap-3 pt-2">
                    <Button type="submit" disabled={form.processing}>
                        {form.processing ? 'Guardando...' : 'Guardar configuración'}
                    </Button>
                </div>
            </form>
        </SeguimientoLayout>
    );
}
