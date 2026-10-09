import { AdminNav } from '@/components/admin-nav';
import { Button } from '@/components/ui/button';
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import SeguimientoLayout from '@/layouts/seguimiento-layout';
import { Head, router, useForm } from '@inertiajs/react';
import { CalendarDays, CalendarPlus, Clock, Edit2, FileText, Layers, Sparkles, Trash2, Users } from 'lucide-react';
import { useState } from 'react';

interface FollowUpRow {
    id: number;
    period: number;
    number: number;
    label: string;
    short_label: string;
    meetings_count: number;
    contributions_count: number;
    strategies_count: number;
    can_delete: boolean;
}

interface SchoolYearOption {
    id: number;
    name: string;
}

interface Props {
    schoolYears: SchoolYearOption[];
    selectedYearId: number;
    selectedYearIsReadOnly: boolean;
    followUps: FollowUpRow[];
    stats: {
        periods: number;
        follow_ups: number;
        meetings: number;
        contributions: number;
    };
}

export default function AdminSeguimientos({ schoolYears, selectedYearId, selectedYearIsReadOnly, followUps, stats }: Props) {
    const [openCreate, setOpenCreate] = useState(false);
    const [editingFollowUp, setEditingFollowUp] = useState<FollowUpRow | null>(null);
    const [deletingFollowUp, setDeletingFollowUp] = useState<FollowUpRow | null>(null);

    const createForm = useForm({
        school_year_id: selectedYearId,
        period: 1,
        number: 1,
    });

    const editForm = useForm({
        period: 1,
        number: 1,
    });

    const activeYearName = schoolYears.find((y) => y.id === selectedYearId)?.name ?? 'Año seleccionado';

    const handleCreateFollowUp = (e: React.FormEvent) => {
        e.preventDefault();
        createForm.transform((data) => ({
            ...data,
            school_year_id: selectedYearId,
        }));
        createForm.post('/admin/seguimientos', {
            onSuccess: () => {
                createForm.reset();
                setOpenCreate(false);
            },
        });
    };

    const openEditModal = (item: FollowUpRow) => {
        setEditingFollowUp(item);
        editForm.setData({
            period: item.period,
            number: item.number,
        });
    };

    const handleUpdateFollowUp = (e: React.FormEvent) => {
        e.preventDefault();
        if (!editingFollowUp) return;

        editForm.put(`/admin/seguimientos/${editingFollowUp.id}`, {
            preserveScroll: true,
            onSuccess: () => {
                setEditingFollowUp(null);
            },
        });
    };

    const handleDeleteFollowUp = () => {
        if (!deletingFollowUp) return;
        router.delete(`/admin/seguimientos/${deletingFollowUp.id}`, {
            preserveScroll: true,
            onSuccess: () => setDeletingFollowUp(null),
        });
    };

    const handleGenerateDefaults = () => {
        router.post(`/admin/anios/${selectedYearId}/generar-seguimientos`, {}, { preserveScroll: true });
    };

    return (
        <SeguimientoLayout>
            <Head title="Administración · Periodo y seguimiento" />
            <AdminNav />

            {/* Encabezado */}
            <div className="mb-4 flex flex-wrap items-center justify-between gap-3">
                <div className="flex flex-wrap items-center gap-2">
                    <h2 className="font-display text-primary text-xl">Periodo y seguimiento</h2>
                    <div className="flex items-center gap-1.5">
                        <span className="text-muted-foreground text-xs">Año escolar:</span>
                        <select
                            className="border-border bg-background rounded-md border px-2.5 py-1.5 text-sm font-medium"
                            value={selectedYearId}
                            onChange={(e) => router.get('/admin/seguimientos', { anio: e.target.value }, { preserveState: true })}
                        >
                            {schoolYears.map((y) => (
                                <option key={y.id} value={y.id}>
                                    {y.name}
                                </option>
                            ))}
                        </select>
                    </div>

                    {selectedYearIsReadOnly && (
                        <span className="bg-muted text-muted-foreground rounded-full px-2 py-0.5 text-[11px] font-semibold">Solo lectura</span>
                    )}
                </div>

                {!selectedYearIsReadOnly && (
                    <div className="flex items-center gap-2">
                        {followUps.length === 0 && (
                            <Button size="sm" variant="outline" onClick={handleGenerateDefaults} className="flex items-center gap-1.5">
                                <Sparkles className="h-4 w-4" />
                                Generar 6 seguimientos estándar
                            </Button>
                        )}
                        <Button size="sm" className="flex items-center gap-1.5" onClick={() => setOpenCreate(!openCreate)}>
                            <CalendarPlus className="h-4 w-4" />
                            {openCreate ? 'Cancelar' : '+ Nuevo periodo y seguimiento'}
                        </Button>
                    </div>
                )}
            </div>

            {/* Métricas / KPIs */}
            <div className="mb-4 grid grid-cols-2 gap-3 sm:grid-cols-4">
                <div className="border-border bg-card flex items-center gap-3 rounded-lg border p-3">
                    <div className="bg-primary/10 text-primary flex h-9 w-9 items-center justify-center rounded-lg">
                        <Layers className="h-5 w-5" />
                    </div>
                    <div>
                        <div className="text-muted-foreground text-[11px] font-medium tracking-wide uppercase">Periodos ({activeYearName})</div>
                        <div className="font-mono text-lg font-bold">{stats.periods}</div>
                    </div>
                </div>

                <div className="border-border bg-card flex items-center gap-3 rounded-lg border p-3">
                    <div className="bg-primary/10 text-primary flex h-9 w-9 items-center justify-center rounded-lg">
                        <CalendarDays className="h-5 w-5" />
                    </div>
                    <div>
                        <div className="text-muted-foreground text-[11px] font-medium tracking-wide uppercase">Seguimientos totales</div>
                        <div className="font-mono text-lg font-bold">{stats.follow_ups}</div>
                    </div>
                </div>

                <div className="border-border bg-card flex items-center gap-3 rounded-lg border p-3">
                    <div className="bg-good-soft text-good flex h-9 w-9 items-center justify-center rounded-lg">
                        <Users className="h-5 w-5" />
                    </div>
                    <div>
                        <div className="text-muted-foreground text-[11px] font-medium tracking-wide uppercase">Reuniones de grupo</div>
                        <div className="font-mono text-lg font-bold">{stats.meetings}</div>
                    </div>
                </div>

                <div className="border-border bg-card flex items-center gap-3 rounded-lg border p-3">
                    <div className="bg-primary/10 text-primary flex h-9 w-9 items-center justify-center rounded-lg">
                        <FileText className="h-5 w-5" />
                    </div>
                    <div>
                        <div className="text-muted-foreground text-[11px] font-medium tracking-wide uppercase">Aportes registrados</div>
                        <div className="font-mono text-lg font-bold">{stats.contributions}</div>
                    </div>
                </div>
            </div>

            {/* Formulario para crear (+ Nuevo seguimiento) */}
            {openCreate && (
                <div className="border-border bg-accent/40 mb-5 rounded-lg border p-4 shadow-xs">
                    <div className="mb-3 flex items-center justify-between">
                        <h3 className="font-display text-primary flex items-center gap-2 text-base font-semibold">
                            <CalendarPlus className="h-4 w-4" />
                            Agregar periodo y seguimiento en {activeYearName}
                        </h3>
                        <span className="text-muted-foreground text-xs">Define el número de periodo y el corte de seguimiento correspondiente.</span>
                    </div>

                    <form onSubmit={handleCreateFollowUp} className="space-y-3">
                        <div className="grid grid-cols-1 gap-3 sm:grid-cols-2">
                            <div className="flex flex-col gap-1">
                                <label className="text-foreground text-xs font-semibold">
                                    Número de periodo <span className="text-crit">*</span>
                                </label>
                                <input
                                    type="number"
                                    min={1}
                                    max={10}
                                    required
                                    value={createForm.data.period}
                                    onChange={(e) => createForm.setData('period', Number(e.target.value))}
                                    className="border-border bg-background rounded-md border p-2 text-sm"
                                />
                                {createForm.errors.period && <span className="text-crit text-xs">{createForm.errors.period}</span>}
                            </div>

                            <div className="flex flex-col gap-1">
                                <label className="text-foreground text-xs font-semibold">
                                    Número de seguimiento <span className="text-crit">*</span>
                                </label>
                                <input
                                    type="number"
                                    min={1}
                                    max={10}
                                    required
                                    value={createForm.data.number}
                                    onChange={(e) => createForm.setData('number', Number(e.target.value))}
                                    className="border-border bg-background rounded-md border p-2 text-sm"
                                />
                                {createForm.errors.number && <span className="text-crit text-xs">{createForm.errors.number}</span>}
                            </div>
                        </div>

                        <div className="border-border bg-background/80 flex items-center justify-between rounded-md border p-2.5 text-xs">
                            <span className="text-muted-foreground">Vista previa de etiqueta:</span>
                            <span className="text-primary font-semibold">
                                Periodo {createForm.data.period} · Seguimiento {createForm.data.number} (P{createForm.data.period}·S
                                {createForm.data.number})
                            </span>
                        </div>

                        <div className="flex justify-end gap-2 pt-2">
                            <Button type="button" variant="ghost" size="sm" onClick={() => setOpenCreate(false)}>
                                Cancelar
                            </Button>
                            <Button type="submit" size="sm" disabled={createForm.processing}>
                                {createForm.processing ? 'Guardando...' : 'Guardar seguimiento'}
                            </Button>
                        </div>
                    </form>
                </div>
            )}

            {/* Tabla de seguimientos */}
            <div className="border-border bg-card overflow-x-auto rounded-lg border shadow-xs">
                <table className="w-full text-sm">
                    <thead>
                        <tr className="bg-muted text-muted-foreground text-left text-[11px] tracking-wider uppercase">
                            <th className="p-3">Periodo y seguimiento</th>
                            <th className="p-3">Identificador</th>
                            <th className="p-3">Reuniones de grupo</th>
                            <th className="p-3">Aportes docentes</th>
                            <th className="p-3">Estrategias</th>
                            <th className="p-3 text-right">Acciones</th>
                        </tr>
                    </thead>
                    <tbody className="divide-border divide-y">
                        {followUps.map((f) => (
                            <tr key={f.id} className="hover:bg-muted/40 transition-colors">
                                <td className="p-3">
                                    <div className="flex items-center gap-2">
                                        <Clock className="text-primary h-4 w-4" />
                                        <span className="font-semibold">{f.label}</span>
                                    </div>
                                </td>
                                <td className="p-3">
                                    <span className="bg-primary/10 text-primary rounded px-2 py-0.5 font-mono text-xs font-bold">
                                        {f.short_label}
                                    </span>
                                </td>
                                <td className="p-3">
                                    <span className="font-mono">{f.meetings_count}</span>
                                </td>
                                <td className="p-3">
                                    <span className="font-mono">{f.contributions_count}</span>
                                </td>
                                <td className="p-3">
                                    <span className="font-mono">{f.strategies_count}</span>
                                </td>
                                <td className="p-3 text-right">
                                    {!selectedYearIsReadOnly ? (
                                        <div className="flex items-center justify-end gap-1.5">
                                            <Button
                                                size="sm"
                                                variant="ghost"
                                                onClick={() => openEditModal(f)}
                                                className="h-7 px-2 text-xs"
                                                title="Editar numeración"
                                            >
                                                <Edit2 className="h-3.5 w-3.5" />
                                            </Button>

                                            {f.can_delete ? (
                                                <Button
                                                    size="sm"
                                                    variant="ghost"
                                                    onClick={() => setDeletingFollowUp(f)}
                                                    className="text-muted-foreground hover:text-crit h-7 px-2 text-xs"
                                                    title="Eliminar seguimiento"
                                                >
                                                    <Trash2 className="h-3.5 w-3.5" />
                                                </Button>
                                            ) : (
                                                <span
                                                    className="text-muted-foreground/60 text-[11px] italic"
                                                    title="Tiene reuniones o aportes asociados"
                                                >
                                                    En uso
                                                </span>
                                            )}
                                        </div>
                                    ) : (
                                        <span className="text-muted-foreground text-xs italic">Protegido</span>
                                    )}
                                </td>
                            </tr>
                        ))}

                        {followUps.length === 0 && (
                            <tr>
                                <td colSpan={6} className="p-8 text-center">
                                    <div className="flex flex-col items-center justify-center gap-2">
                                        <CalendarDays className="text-muted-foreground/60 h-8 w-8" />
                                        <p className="text-muted-foreground text-sm font-medium">
                                            No hay seguimientos registrados para {activeYearName}.
                                        </p>
                                        {!selectedYearIsReadOnly && (
                                            <div className="flex gap-2">
                                                <Button size="sm" onClick={handleGenerateDefaults} className="flex items-center gap-1.5">
                                                    <Sparkles className="h-4 w-4" />
                                                    Generar 6 seguimientos estándar
                                                </Button>
                                                <Button size="sm" variant="outline" onClick={() => setOpenCreate(true)}>
                                                    + Crear manualmente
                                                </Button>
                                            </div>
                                        )}
                                    </div>
                                </td>
                            </tr>
                        )}
                    </tbody>
                </table>
            </div>

            {/* Modal para editar */}
            <Dialog open={editingFollowUp !== null} onOpenChange={(isOpen) => !isOpen && setEditingFollowUp(null)}>
                <DialogContent className="sm:max-w-md">
                    <DialogHeader>
                        <DialogTitle>Editar periodo y seguimiento</DialogTitle>
                        <DialogDescription>Modifica el periodo o el número de corte para este seguimiento.</DialogDescription>
                    </DialogHeader>

                    {editingFollowUp && (
                        <form onSubmit={handleUpdateFollowUp} className="space-y-4">
                            <div className="grid grid-cols-2 gap-3">
                                <div className="flex flex-col gap-1">
                                    <label className="text-foreground text-xs font-semibold">
                                        Número de periodo <span className="text-crit">*</span>
                                    </label>
                                    <input
                                        type="number"
                                        min={1}
                                        max={10}
                                        required
                                        value={editForm.data.period}
                                        onChange={(e) => editForm.setData('period', Number(e.target.value))}
                                        className="border-border bg-background rounded-md border p-2 text-sm"
                                    />
                                    {editForm.errors.period && <span className="text-crit text-xs">{editForm.errors.period}</span>}
                                </div>

                                <div className="flex flex-col gap-1">
                                    <label className="text-foreground text-xs font-semibold">
                                        Número de seguimiento <span className="text-crit">*</span>
                                    </label>
                                    <input
                                        type="number"
                                        min={1}
                                        max={10}
                                        required
                                        value={editForm.data.number}
                                        onChange={(e) => editForm.setData('number', Number(e.target.value))}
                                        className="border-border bg-background rounded-md border p-2 text-sm"
                                    />
                                    {editForm.errors.number && <span className="text-crit text-xs">{editForm.errors.number}</span>}
                                </div>
                            </div>

                            <DialogFooter>
                                <Button type="button" variant="ghost" onClick={() => setEditingFollowUp(null)}>
                                    Cancelar
                                </Button>
                                <Button type="submit" disabled={editForm.processing}>
                                    {editForm.processing ? 'Guardando...' : 'Guardar cambios'}
                                </Button>
                            </DialogFooter>
                        </form>
                    )}
                </DialogContent>
            </Dialog>

            {/* Modal para eliminar */}
            <Dialog open={deletingFollowUp !== null} onOpenChange={(isOpen) => !isOpen && setDeletingFollowUp(null)}>
                <DialogContent className="sm:max-w-md">
                    <DialogHeader>
                        <DialogTitle className="text-crit">Eliminar seguimiento</DialogTitle>
                        <DialogDescription>
                            ¿Estás seguro de que deseas eliminar{' '}
                            <strong className="text-foreground">
                                {deletingFollowUp?.label} ({deletingFollowUp?.short_label})
                            </strong>
                            ? Esta acción no se puede deshacer.
                        </DialogDescription>
                    </DialogHeader>

                    <DialogFooter>
                        <Button type="button" variant="ghost" onClick={() => setDeletingFollowUp(null)}>
                            Cancelar
                        </Button>
                        <Button type="button" variant="destructive" onClick={handleDeleteFollowUp}>
                            Eliminar seguimiento
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>
        </SeguimientoLayout>
    );
}
