import { AdminNav } from '@/components/admin-nav';
import { Button } from '@/components/ui/button';
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import SeguimientoLayout from '@/layouts/seguimiento-layout';
import { Head, router, useForm } from '@inertiajs/react';
import { useState } from 'react';

interface BackupRow {
    name: string;
    size: number;
    created_at: string;
    kind: string;
    kind_label: string;
    created_by: string | null;
    rows: number;
    students: number;
    photos: number;
    restorable: boolean;
    problem: string | null;
}

interface Props {
    backups: BackupRow[];
    tables: number;
    keepDays: number;
    flash: { success: string | null; error: string | null };
}

const KIND_STYLE: Record<string, string> = {
    manual: 'bg-accent text-primary',
    automatico: 'bg-muted text-muted-foreground',
    'previo-restauracion': 'bg-warn-soft text-warn',
    subido: 'bg-good-soft text-good',
};

function formatSize(bytes: number): string {
    if (bytes < 1024 * 1024) return `${Math.max(1, Math.round(bytes / 1024))} KB`;
    return `${(bytes / 1024 / 1024).toLocaleString('es-CO', { maximumFractionDigits: 1 })} MB`;
}

function formatDate(iso: string): string {
    return new Date(iso).toLocaleString('es-CO', { day: '2-digit', month: 'short', year: 'numeric', hour: '2-digit', minute: '2-digit' });
}

export default function AdminRespaldos({ backups, tables, keepDays, flash }: Props) {
    const [creating, setCreating] = useState(false);
    const [restoring, setRestoring] = useState<BackupRow | null>(null);
    const upload = useForm<{ file: File | null }>({ file: null });
    const [uploadKey, setUploadKey] = useState(0);
    const confirm = useForm({ confirmacion: '' });

    const lastBackup = backups.find((b) => b.kind !== 'legado');

    return (
        <SeguimientoLayout>
            <Head title="Administración · Respaldos" />
            <AdminNav />

            <div className="mb-1 flex flex-wrap items-center justify-between gap-3">
                <h2 className="font-display text-primary text-xl">Respaldos</h2>
                <Button
                    size="sm"
                    disabled={creating}
                    onClick={() =>
                        router.post(
                            '/admin/respaldos',
                            {},
                            { preserveScroll: true, onStart: () => setCreating(true), onFinish: () => setCreating(false) },
                        )
                    }
                >
                    {creating ? 'Creando respaldo…' : 'Crear respaldo ahora'}
                </Button>
            </div>
            <p className="text-muted-foreground mb-4 max-w-4xl text-sm">
                Cada respaldo guarda todos los datos del seguimiento ({tables} tablas: años, grupos, estudiantes, aportes, estrategias, compromisos,
                notas, apoyos, comité, convivencia, importaciones y auditoría) y las fotos de los estudiantes. Los usuarios y roles no se incluyen
                porque vienen de la intranet. Se crea uno automático cada noche a las 2:00 a. m. y se conservan {keepDays} días; los manuales no se
                borran solos.
                {lastBackup && (
                    <>
                        {' '}
                        Último respaldo: <b>{formatDate(lastBackup.created_at)}</b>.
                    </>
                )}
            </p>

            {flash.success && <div className="border-good bg-good-soft mb-4 rounded-md border p-3 text-sm">{flash.success}</div>}
            {flash.error && <div className="border-crit bg-crit-soft text-crit mb-4 rounded-md border p-3 text-sm">{flash.error}</div>}

            <div className="border-border bg-card overflow-x-auto rounded-lg border">
                <table className="w-full text-sm">
                    <thead>
                        <tr className="bg-muted text-muted-foreground text-left text-[11px] uppercase">
                            <th className="p-2">Fecha</th>
                            <th className="p-2">Tipo</th>
                            <th className="p-2">Contenido</th>
                            <th className="p-2">Tamaño</th>
                            <th className="p-2">Creado por</th>
                            <th className="p-2 text-right">Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        {backups.map((b) => (
                            <tr key={b.name} className="border-border border-t align-top">
                                <td className="p-2">
                                    <div className="font-semibold">{formatDate(b.created_at)}</div>
                                    <div className="text-muted-foreground font-mono text-[11px]">{b.name}</div>
                                </td>
                                <td className="p-2">
                                    <span
                                        className={
                                            'rounded-full px-2 py-0.5 text-[11px] font-semibold ' +
                                            (KIND_STYLE[b.kind] ?? 'bg-muted text-muted-foreground')
                                        }
                                    >
                                        {b.kind_label}
                                    </span>
                                </td>
                                <td className="p-2 text-xs">
                                    {b.kind === 'legado' ? (
                                        <span className="text-muted-foreground">—</span>
                                    ) : (
                                        <>
                                            {b.students.toLocaleString('es-CO')} estudiantes · {b.rows.toLocaleString('es-CO')} registros · {b.photos}{' '}
                                            fotos
                                        </>
                                    )}
                                    {b.problem && <div className="text-warn mt-1 max-w-sm">{b.problem}</div>}
                                </td>
                                <td className="p-2 text-xs">{formatSize(b.size)}</td>
                                <td className="p-2 text-xs">{b.created_by ?? (b.kind === 'automatico' ? 'Sistema' : '—')}</td>
                                <td className="p-2">
                                    <div className="flex justify-end gap-1.5">
                                        <Button size="sm" variant="outline" asChild>
                                            <a href={`/admin/respaldos/${encodeURIComponent(b.name)}/descargar`}>Descargar</a>
                                        </Button>
                                        <Button
                                            size="sm"
                                            variant="outline"
                                            disabled={!b.restorable}
                                            title={b.restorable ? 'Reemplazar los datos actuales por los de este respaldo' : (b.problem ?? '')}
                                            onClick={() => {
                                                confirm.reset();
                                                confirm.clearErrors();
                                                setRestoring(b);
                                            }}
                                        >
                                            Restaurar
                                        </Button>
                                        <Button
                                            size="sm"
                                            variant="ghost"
                                            className="text-crit"
                                            onClick={() => {
                                                if (
                                                    window.confirm(
                                                        `¿Eliminar el respaldo del ${formatDate(b.created_at)}? Esta acción no se puede deshacer.`,
                                                    )
                                                ) {
                                                    router.delete(`/admin/respaldos/${encodeURIComponent(b.name)}`, { preserveScroll: true });
                                                }
                                            }}
                                        >
                                            Eliminar
                                        </Button>
                                    </div>
                                </td>
                            </tr>
                        ))}
                        {backups.length === 0 && (
                            <tr>
                                <td colSpan={6} className="text-muted-foreground p-4 text-center text-xs italic">
                                    Todavía no hay respaldos. Crea el primero con «Crear respaldo ahora».
                                </td>
                            </tr>
                        )}
                    </tbody>
                </table>
            </div>

            <form
                className="border-border bg-card mt-6 rounded-lg border p-4"
                onSubmit={(e) => {
                    e.preventDefault();
                    upload.post('/admin/respaldos/subir', {
                        forceFormData: true,
                        preserveScroll: true,
                        onSuccess: () => {
                            upload.reset();
                            setUploadKey((k) => k + 1);
                        },
                    });
                }}
            >
                <h3 className="font-display text-primary text-lg">Subir un respaldo</h3>
                <p className="text-muted-foreground mb-3 text-sm">
                    Para traer un respaldo descargado antes (o de otro servidor). Se valida que sea de esta aplicación y queda en la lista para
                    restaurarlo.
                </p>
                <div className="flex flex-wrap items-end gap-3">
                    <input
                        key={uploadKey}
                        type="file"
                        accept=".zip"
                        required
                        onChange={(e) => upload.setData('file', e.target.files?.[0] ?? null)}
                        className="border-border bg-background rounded-md border p-1.5 text-sm"
                    />
                    <Button type="submit" size="sm" variant="outline" disabled={upload.processing || !upload.data.file}>
                        {upload.processing ? 'Subiendo…' : 'Subir respaldo'}
                    </Button>
                </div>
                {upload.errors.file && <div className="text-crit mt-2 text-sm">{upload.errors.file}</div>}
            </form>

            <Dialog open={restoring !== null} onOpenChange={(open) => !open && !confirm.processing && setRestoring(null)}>
                <DialogContent>
                    <DialogHeader>
                        <DialogTitle>Restaurar respaldo</DialogTitle>
                        <DialogDescription>
                            {restoring && (
                                <>
                                    Todos los datos actuales del seguimiento y las fotos se reemplazarán por los del respaldo del{' '}
                                    <b>{formatDate(restoring.created_at)}</b> ({restoring.students} estudiantes,{' '}
                                    {restoring.rows.toLocaleString('es-CO')} registros). Lo registrado después de esa fecha se perderá.
                                </>
                            )}
                        </DialogDescription>
                    </DialogHeader>
                    <div className="bg-warn-soft rounded-md p-3 text-sm">
                        Antes de restaurar se guarda automáticamente un respaldo del estado actual («Previo a restauración»), por si necesitas volver
                        atrás. Los usuarios y roles no cambian. Evita restaurar mientras otras personas están registrando información.
                    </div>
                    <form
                        id="restore-form"
                        onSubmit={(e) => {
                            e.preventDefault();
                            if (!restoring) return;
                            confirm.post(`/admin/respaldos/${encodeURIComponent(restoring.name)}/restaurar`, {
                                preserveScroll: true,
                                onSuccess: () => setRestoring(null),
                            });
                        }}
                    >
                        <label className="text-sm">
                            Escribe <b>RESTAURAR</b> para confirmar
                            <input
                                autoFocus
                                value={confirm.data.confirmacion}
                                onChange={(e) => confirm.setData('confirmacion', e.target.value)}
                                className="border-border bg-background mt-1 block w-full rounded-md border p-2 font-mono text-sm"
                            />
                        </label>
                        {confirm.errors.confirmacion && <div className="text-crit mt-1 text-sm">{confirm.errors.confirmacion}</div>}
                    </form>
                    <DialogFooter>
                        <Button variant="outline" disabled={confirm.processing} onClick={() => setRestoring(null)}>
                            Cancelar
                        </Button>
                        <Button
                            type="submit"
                            form="restore-form"
                            className="bg-crit hover:bg-crit/90 text-white"
                            disabled={confirm.processing || confirm.data.confirmacion !== 'RESTAURAR'}
                        >
                            {confirm.processing ? 'Restaurando…' : 'Restaurar'}
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>
        </SeguimientoLayout>
    );
}
