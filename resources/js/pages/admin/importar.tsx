import { AdminNav } from '@/components/admin-nav';
import { Button } from '@/components/ui/button';
import SeguimientoLayout from '@/layouts/seguimiento-layout';
import { Head, Link, router, useForm } from '@inertiajs/react';
import { useState } from 'react';

interface Issue {
    sheet: string | null;
    row: number | null;
    message: string;
}

interface Report {
    rows_read?: number;
    rows_imported?: number;
    rows_failed?: number;
    created?: Record<string, number>;
    errors?: Issue[];
    errors_total?: number;
    warnings?: Issue[];
    warnings_total?: number;
    preview?: Record<string, string | number | null>[];
    reverted?: Record<string, number>;
    photos_result?: { ok: number; failed: number; finished_at: string };
    reverted_at?: string;
}

interface BatchSummary {
    id: number;
    file_name: string;
    template_label: string;
    school_year: string;
    status: 'validated' | 'imported' | 'reverted' | 'failed';
    status_label: string;
    rows_read: number;
    rows_imported: number;
    rows_failed: number;
    user: string;
    created_at: string | null;
    updated_at: string | null;
}

interface Props {
    schoolYears: { id: number; name: string; is_read_only: boolean }[];
    activeYearId: number | null;
    templates: { value: string; label: string; has_template: boolean }[];
    maxMb: number;
    batches: BatchSummary[];
    selected: (BatchSummary & { report: Report }) | null;
}

const DESCRIPTIONS: Record<string, string> = {
    current_format:
        'El Excel «Seguimiento y acuerdos» que usa hoy el colegio, tal cual: puede traer varias hojas (una por reunión). El grupo se toma del título y el periodo, seguimiento, fecha y director de la cabecera.',
    phidias_students:
        'El archivo «exported.xls» que descargas de Phidias, sin modificarlo. Toma solo código, nombre, curso, estado y foto; las demás columnas se ignoran.',
    students_enrollments:
        'Una fila por estudiante: crea los que no existen y los matricula en su grupo del año escolar elegido (crea el grupo si hace falta).',
    teaching_assignments:
        'Una fila por profesor, grupo y asignatura (o dirección de grupo). Define qué grupos ve cada docente que entra desde la intranet.',
};

const MAPPING = [
    ['Fortalezas / avances', 'Aporte · Fortalezas / avances'],
    ['Aspectos por mejorar', 'Aporte · Aspectos de mejora'],
    ['Asignaturas para tener en cuenta', 'Aporte · Asignaturas para tener en cuenta'],
    ['Estrategias + Responsables', 'Estrategia individual activa, con su responsable'],
    ['Psicología, Fonoaudiología, T. ocupacional, Neuropsicología', 'Nota del Dpto. de apoyo (confidencial, mismos permisos que hoy)'],
    ['Coordinación', 'Nota de Coordinación'],
    ['Observación', 'Aporte · Observación (columna de la reunión de grupo)'],
    ['Fecha / Director(a) de grupo', 'Fecha de la reunión y director(a) del grupo si no tenía'],
];

const STATUS_STYLE: Record<BatchSummary['status'], string> = {
    validated: 'bg-warn-soft text-warn',
    imported: 'bg-good-soft text-good',
    reverted: 'bg-muted text-muted-foreground',
    failed: 'bg-crit-soft text-crit',
};

const PREVIEW_LABELS: Record<string, string> = {
    hoja: 'Hoja',
    fila: 'Fila',
    en_el_archivo: 'En el archivo',
    estudiante: 'Estudiante en la aplicación',
    codigo: 'Código',
    grupo: 'Grupo',
    seguimiento: 'Seguimiento',
    registros: 'Registros',
    docente: 'Docente',
    correo: 'Correo',
    asignacion: 'Asignación',
    estado: 'Estado',
    accion: 'Qué pasa',
};

const PHIDIAS_MAPPING = [
    ['Código', 'Código institucional (con él se reconoce al estudiante en cada importación)'],
    ['Estudiante («APELLIDOS, NOMBRES»)', 'Nombre completo «Nombres Apellidos»'],
    ['Sección («Sexto 6A»)', 'Grupo 6A del año escolar elegido (se crea si no existe)'],
    ['Estado', 'Activo / inactivo'],
    ['URL de la foto', 'Foto del estudiante, solo si todavía no tiene (se descarga en segundo plano)'],
];

function StatusBadge({ status, label }: { status: BatchSummary['status']; label: string }) {
    return <span className={'rounded-full px-2 py-0.5 text-[11px] font-semibold ' + STATUS_STYLE[status]}>{label}</span>;
}

function IssueTable({ items, total, tone }: { items: Issue[]; total: number; tone: 'crit' | 'warn' }) {
    return (
        <div className="border-border overflow-x-auto rounded-md border">
            <table className="w-full text-xs">
                <thead>
                    <tr className="bg-muted text-muted-foreground text-left uppercase">
                        <th className="w-40 p-2">Hoja</th>
                        <th className="w-14 p-2">Fila</th>
                        <th className="p-2">Detalle</th>
                    </tr>
                </thead>
                <tbody>
                    {items.map((issue, i) => (
                        <tr key={i} className="border-border border-t align-top">
                            <td className="p-2">{issue.sheet ?? '—'}</td>
                            <td className="p-2">{issue.row ?? '—'}</td>
                            <td className={'p-2 ' + (tone === 'crit' ? 'text-crit' : '')}>{issue.message}</td>
                        </tr>
                    ))}
                </tbody>
            </table>
            {total > items.length && <p className="text-muted-foreground p-2 text-xs">… y {total - items.length} más.</p>}
        </div>
    );
}

function ReportPanel({ batch }: { batch: BatchSummary & { report: Report } }) {
    const r = batch.report;
    const isSimulation = batch.status === 'validated';
    const errors = r.errors ?? [];
    const warnings = r.warnings ?? [];
    const created = Object.entries(r.created ?? {});
    const preview = r.preview ?? [];
    const previewCols = preview.length > 0 ? Object.keys(preview[0]) : [];
    const [showWarnings, setShowWarnings] = useState(warnings.length <= 8);
    const [busy, setBusy] = useState(false);

    const post = (url: string, message: string) => {
        if (!confirm(message)) return;
        router.post(url, {}, { preserveScroll: true, onStart: () => setBusy(true), onFinish: () => setBusy(false) });
    };

    return (
        <section className="border-border bg-card mb-6 rounded-lg border p-4">
            <div className="mb-3 flex flex-wrap items-start justify-between gap-3">
                <div>
                    <div className="flex items-center gap-2">
                        <h3 className="font-display text-primary text-lg">{isSimulation ? 'Resultado de la simulación' : `Lote #${batch.id}`}</h3>
                        <StatusBadge status={batch.status} label={batch.status_label} />
                    </div>
                    <p className="text-muted-foreground text-xs">
                        {batch.file_name} · {batch.template_label} · {batch.school_year} · {batch.user} · {batch.updated_at}
                    </p>
                </div>
                <div className="flex gap-2">
                    {isSimulation && (
                        <>
                            <Button
                                size="sm"
                                variant="outline"
                                disabled={busy}
                                onClick={() => {
                                    if (confirm('¿Descartar esta simulación? No se guardó nada.')) router.delete(`/admin/importar/${batch.id}`);
                                }}
                            >
                                Descartar
                            </Button>
                            <Button
                                size="sm"
                                disabled={busy || batch.rows_imported === 0}
                                onClick={() =>
                                    post(
                                        `/admin/importar/${batch.id}/confirmar`,
                                        `Se importarán ${batch.rows_imported} filas` +
                                            (batch.rows_failed > 0 ? ` y se omitirán ${batch.rows_failed} con error` : '') +
                                            '. Podrás revertir el lote completo después. ¿Continuar?',
                                    )
                                }
                            >
                                {busy ? 'Importando…' : 'Confirmar importación'}
                            </Button>
                        </>
                    )}
                    {batch.status === 'imported' && (
                        <Button
                            size="sm"
                            variant="outline"
                            className="border-crit text-crit"
                            disabled={busy}
                            onClick={() =>
                                post(
                                    `/admin/importar/${batch.id}/revertir`,
                                    'Se borrará todo lo que creó este lote (aportes, estrategias, estudiantes, matrículas y asignaciones) y se deshacen los cambios de grupo y de dirección. Lo agregado a mano después se conserva. ¿Revertir?',
                                )
                            }
                        >
                            {busy ? 'Revirtiendo…' : 'Revertir lote'}
                        </Button>
                    )}
                </div>
            </div>

            <div className="mb-4 grid grid-cols-3 gap-3 text-center">
                <div className="bg-muted rounded-md p-3">
                    <div className="font-display text-2xl">{batch.rows_read}</div>
                    <div className="text-muted-foreground text-xs">Filas leídas</div>
                </div>
                <div className="bg-good-soft rounded-md p-3">
                    <div className="font-display text-good text-2xl">{batch.rows_imported}</div>
                    <div className="text-muted-foreground text-xs">{isSimulation ? 'Se importarán' : 'Importadas'}</div>
                </div>
                <div className={'rounded-md p-3 ' + (batch.rows_failed > 0 ? 'bg-crit-soft' : 'bg-muted')}>
                    <div className={'font-display text-2xl ' + (batch.rows_failed > 0 ? 'text-crit' : '')}>{batch.rows_failed}</div>
                    <div className="text-muted-foreground text-xs">Con error (no se importan)</div>
                </div>
            </div>

            {created.length > 0 && batch.status !== 'reverted' && (
                <div className="mb-4">
                    <h4 className="mb-1 text-sm font-semibold">{isSimulation ? 'Lo que se va a crear' : 'Lo que se creó'}</h4>
                    <div className="flex flex-wrap gap-2">
                        {created.map(([label, n]) => (
                            <span key={label} className="border-border bg-background rounded-full border px-3 py-1 text-xs">
                                <b>{n}</b> {label}
                            </span>
                        ))}
                    </div>
                    {batch.status === 'imported' && (r.created?.['Fotos por traer de Phidias'] ?? 0) > 0 && (
                        <p className="text-muted-foreground mt-2 text-xs">
                            {r.photos_result
                                ? `Fotos: ${r.photos_result.ok} descargadas` +
                                  (r.photos_result.failed > 0 ? `, ${r.photos_result.failed} no se pudieron traer.` : '.')
                                : 'Las fotos se están descargando en segundo plano; recarga la página en unos minutos para ver el resultado.'}
                        </p>
                    )}
                </div>
            )}

            {batch.status === 'reverted' && r.reverted && (
                <div className="bg-muted mb-4 rounded-md p-3 text-sm">
                    <b>Revertido.</b>{' '}
                    {Object.entries(r.reverted)
                        .map(([label, n]) => `${n} ${label.toLowerCase()}`)
                        .join(' · ') || 'No había nada que deshacer.'}
                </div>
            )}

            {errors.length > 0 && (
                <div className="mb-4">
                    <h4 className="text-crit mb-1 text-sm font-semibold">Errores ({r.errors_total ?? errors.length}) — estas filas no se importan</h4>
                    <IssueTable items={errors} total={r.errors_total ?? errors.length} tone="crit" />
                </div>
            )}

            {warnings.length > 0 && (
                <div className="mb-4">
                    <button type="button" className="text-warn mb-1 text-sm font-semibold" onClick={() => setShowWarnings(!showWarnings)}>
                        {showWarnings ? '▾' : '▸'} Avisos ({r.warnings_total ?? warnings.length}) — revísalos antes de confirmar
                    </button>
                    {showWarnings && <IssueTable items={warnings} total={r.warnings_total ?? warnings.length} tone="warn" />}
                </div>
            )}

            {preview.length > 0 && (
                <div>
                    <h4 className="mb-1 text-sm font-semibold">Vista previa {preview.length >= 200 ? '(primeras 200 filas)' : ''}</h4>
                    <div className="border-border max-h-96 overflow-auto rounded-md border">
                        <table className="w-full text-xs">
                            <thead className="sticky top-0">
                                <tr className="bg-muted text-muted-foreground text-left uppercase">
                                    {previewCols.map((c) => (
                                        <th key={c} className="p-2">
                                            {PREVIEW_LABELS[c] ?? c}
                                        </th>
                                    ))}
                                </tr>
                            </thead>
                            <tbody>
                                {preview.map((row, i) => (
                                    <tr key={i} className="border-border border-t">
                                        {previewCols.map((c) => (
                                            <td key={c} className="p-2">
                                                {row[c] ?? '—'}
                                            </td>
                                        ))}
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                </div>
            )}
        </section>
    );
}

export default function AdminImportar({ schoolYears, activeYearId, templates, maxMb, batches, selected }: Props) {
    const form = useForm<{
        file: File | null;
        template_type: string;
        school_year_id: number | string;
        group_code: string;
        create_students: boolean;
        download_photos: boolean;
    }>({
        file: null,
        template_type: templates[0]?.value ?? 'current_format',
        school_year_id: activeYearId ?? schoolYears[0]?.id ?? '',
        group_code: '',
        create_students: true,
        download_photos: true,
    });
    const [fileInputKey, setFileInputKey] = useState(0);
    const isCurrent = form.data.template_type === 'current_format';
    const isPhidias = form.data.template_type === 'phidias_students';
    const year = schoolYears.find((y) => String(y.id) === String(form.data.school_year_id));

    return (
        <SeguimientoLayout>
            <Head title="Administración · Importar" />
            <AdminNav />

            <h2 className="font-display text-primary mb-1 text-xl">Importar información</h2>
            <p className="text-muted-foreground mb-4 text-sm">
                1. Sube el archivo y simula: no se guarda nada y ves fila por fila qué pasaría. 2. Revisa errores y avisos. 3. Confirma. Cada
                importación queda como un lote que se puede revertir completo.
            </p>

            {selected && <ReportPanel key={`${selected.id}-${selected.status}`} batch={selected} />}

            <form
                className="border-border bg-card mb-6 rounded-lg border p-4"
                onSubmit={(e) => {
                    e.preventDefault();
                    form.post('/admin/importar', {
                        forceFormData: true,
                        onSuccess: () => {
                            form.setData('file', null);
                            setFileInputKey((k) => k + 1);
                        },
                    });
                }}
            >
                <h3 className="font-display text-primary mb-3 text-lg">Nueva importación</h3>

                <div className="mb-4 grid gap-3 md:grid-cols-2 xl:grid-cols-4">
                    {templates.map((t) => (
                        <label
                            key={t.value}
                            className={
                                'cursor-pointer rounded-lg border p-3 ' +
                                (form.data.template_type === t.value ? 'border-primary bg-accent ring-primary ring-1' : 'border-border bg-background')
                            }
                        >
                            <input
                                type="radio"
                                name="template_type"
                                className="sr-only"
                                checked={form.data.template_type === t.value}
                                onChange={() => form.setData('template_type', t.value)}
                            />
                            <span className="text-sm font-semibold">{t.label}</span>
                            <span className="text-muted-foreground mt-1 block text-xs">{DESCRIPTIONS[t.value]}</span>
                            {t.has_template ? (
                                <a
                                    href={`/admin/importar/plantilla/${t.value}`}
                                    className="text-primary mt-2 inline-block text-xs underline"
                                    onClick={(e) => e.stopPropagation()}
                                >
                                    Descargar plantilla
                                </a>
                            ) : (
                                <span className="text-muted-foreground mt-2 block text-xs italic">Phidias → Estudiantes → Exportar</span>
                            )}
                        </label>
                    ))}
                </div>

                <div className="flex flex-wrap items-end gap-3">
                    <div className="flex flex-col gap-1">
                        <label className="text-muted-foreground text-xs">Año escolar al que pertenece la información</label>
                        <select
                            value={form.data.school_year_id}
                            onChange={(e) => form.setData('school_year_id', Number(e.target.value))}
                            className="border-border bg-background rounded-md border p-2 text-sm"
                        >
                            {schoolYears.map((y) => (
                                <option key={y.id} value={y.id}>
                                    {y.name}
                                    {y.id === activeYearId ? ' (activo)' : ''}
                                </option>
                            ))}
                        </select>
                    </div>
                    {isCurrent && (
                        <div className="flex flex-col gap-1">
                            <label className="text-muted-foreground text-xs">Grupo (solo si el título no lo trae)</label>
                            <input
                                value={form.data.group_code}
                                onChange={(e) => form.setData('group_code', e.target.value)}
                                placeholder="Automático"
                                className="border-border bg-background w-32 rounded-md border p-2 text-sm"
                            />
                        </div>
                    )}
                    <div className="flex flex-col gap-1">
                        <label className="text-muted-foreground text-xs">Archivo (.xlsx, .xls o .csv, máx. {maxMb} MB)</label>
                        <input
                            key={fileInputKey}
                            type="file"
                            required
                            accept=".xlsx,.xls,.csv"
                            onChange={(e) => form.setData('file', e.target.files?.[0] ?? null)}
                            className="border-border bg-background rounded-md border p-1.5 text-sm"
                        />
                    </div>
                    <Button type="submit" disabled={form.processing || !form.data.file}>
                        {form.processing ? 'Simulando…' : 'Simular importación'}
                    </Button>
                </div>

                {isCurrent && (
                    <label className="mt-3 flex items-center gap-2 text-sm">
                        <input
                            type="checkbox"
                            checked={form.data.create_students}
                            onChange={(e) => form.setData('create_students', e.target.checked)}
                        />
                        Crear los estudiantes que no existan y matricularlos en el grupo (si no, esas filas se reportan como error)
                    </label>
                )}
                {isPhidias && (
                    <label className="mt-3 flex items-center gap-2 text-sm">
                        <input
                            type="checkbox"
                            checked={form.data.download_photos}
                            onChange={(e) => form.setData('download_photos', e.target.checked)}
                        />
                        Traer las fotos de Phidias a los estudiantes que todavía no tienen foto (se descargan en segundo plano después de confirmar)
                    </label>
                )}
                {year?.is_read_only && (
                    <p className="text-warn mt-2 text-xs">
                        Este año escolar es de solo lectura: la importación sí está permitida para cargar históricos.
                    </p>
                )}
                {Object.keys(form.errors).length > 0 && <div className="text-crit mt-2 text-sm">{Object.values(form.errors).join(' · ')}</div>}

                {isCurrent && (
                    <details className="mt-4 text-sm">
                        <summary className="text-primary cursor-pointer font-semibold">¿Cómo se acomoda el Excel actual en la aplicación?</summary>
                        <table className="mt-2 w-full max-w-3xl text-xs">
                            <tbody>
                                {MAPPING.map(([from, to]) => (
                                    <tr key={from} className="border-border border-t">
                                        <td className="w-1/2 p-1.5 font-medium">{from}</td>
                                        <td className="text-muted-foreground p-1.5">→ {to}</td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                        <p className="text-muted-foreground mt-2 text-xs">
                            Las columnas se reconocen por su encabezado, no por su posición. Los estudiantes se buscan por nombre (sin importar
                            tildes, mayúsculas ni el orden nombre/apellido) entre los matriculados en el grupo y luego en todo el colegio. Los aportes
                            quedan registrados a nombre de quien importa.
                        </p>
                    </details>
                )}
                {isPhidias && (
                    <details className="mt-4 text-sm" open>
                        <summary className="text-primary cursor-pointer font-semibold">¿Qué toma del archivo de Phidias?</summary>
                        <table className="mt-2 w-full max-w-3xl text-xs">
                            <tbody>
                                {PHIDIAS_MAPPING.map(([from, to]) => (
                                    <tr key={from} className="border-border border-t">
                                        <td className="w-1/3 p-1.5 font-medium">{from}</td>
                                        <td className="text-muted-foreground p-1.5">→ {to}</td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                        <p className="text-muted-foreground mt-2 text-xs">
                            Las demás columnas (documento, dirección, ruta, EPS…) no se importan. Phidias manda sobre la matrícula: si un estudiante
                            está en otro grupo ese año, se cambia al de Phidias. Los retirados que no están en la aplicación no se crean, y se avisa
                            de los estudiantes del grupo que están en la aplicación pero no en el archivo.
                        </p>
                    </details>
                )}
            </form>

            <h3 className="font-display text-primary mb-2 text-lg">Historial</h3>
            <div className="border-border bg-card overflow-x-auto rounded-lg border">
                <table className="w-full text-sm">
                    <thead>
                        <tr className="bg-muted text-muted-foreground text-left text-[11px] uppercase">
                            <th className="p-2">#</th>
                            <th className="p-2">Archivo</th>
                            <th className="p-2">Tipo</th>
                            <th className="p-2">Año</th>
                            <th className="p-2">Filas</th>
                            <th className="p-2">Estado</th>
                            <th className="p-2">Por</th>
                            <th className="p-2">Fecha</th>
                        </tr>
                    </thead>
                    <tbody>
                        {batches.map((b) => (
                            <tr key={b.id} className={'border-border border-t ' + (selected?.id === b.id ? 'bg-accent' : '')}>
                                <td className="p-2">{b.id}</td>
                                <td className="p-2">
                                    <Link href={`/admin/importar?lote=${b.id}`} className="text-primary underline" preserveScroll>
                                        {b.file_name}
                                    </Link>
                                </td>
                                <td className="p-2 text-xs">{b.template_label}</td>
                                <td className="p-2 text-xs">{b.school_year}</td>
                                <td className="p-2 text-xs">
                                    {b.rows_imported}/{b.rows_read}
                                    {b.rows_failed > 0 && <span className="text-crit"> · {b.rows_failed} con error</span>}
                                </td>
                                <td className="p-2">
                                    <StatusBadge status={b.status} label={b.status_label} />
                                </td>
                                <td className="p-2 text-xs">{b.user}</td>
                                <td className="p-2 text-xs">{b.created_at}</td>
                            </tr>
                        ))}
                        {batches.length === 0 && (
                            <tr>
                                <td colSpan={8} className="text-muted-foreground p-3 text-center text-xs italic">
                                    Todavía no hay importaciones.
                                </td>
                            </tr>
                        )}
                    </tbody>
                </table>
            </div>
        </SeguimientoLayout>
    );
}
