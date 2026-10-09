import { AdminNav } from '@/components/admin-nav';
import { Button } from '@/components/ui/button';
import SeguimientoLayout from '@/layouts/seguimiento-layout';
import { Head, router, useForm, usePage } from '@inertiajs/react';
import { useState } from 'react';

interface GradeRow {
    id: number;
    value: number;
    name: string;
    active: boolean;
    groups_count: number;
}

export default function AdminGrados({ levels }: { levels: GradeRow[] }) {
    const [open, setOpen] = useState(false);
    const form = useForm({ name: '', value: 12, active: true });
    const [editingId, setEditingId] = useState<number | null>(null);
    const [editingName, setEditingName] = useState('');
    const [confirmDeleteId, setConfirmDeleteId] = useState<number | null>(null);
    const { errors } = usePage().props as { errors?: { name?: string; value?: string } };

    return (
        <SeguimientoLayout>
            <Head title="Administración · Grados" />
            <AdminNav />

            <div className="mb-4 flex items-center justify-between">
                <h2 className="font-display text-primary text-xl">Grados</h2>
                <Button size="sm" onClick={() => setOpen(!open)}>
                    {open ? 'Cancelar' : '+ Nuevo grado'}
                </Button>
            </div>

            {errors?.name && <div className="text-crit mb-3 text-sm">{errors.name}</div>}

            {open && (
                <form
                    className="border-border bg-accent mb-4 flex flex-wrap items-end gap-3 rounded-lg border p-3"
                    onSubmit={(e) => {
                        e.preventDefault();
                        form.post('/admin/grados', {
                            onSuccess: () => {
                                form.reset();
                                setOpen(false);
                            },
                        });
                    }}
                >
                    <div className="flex flex-col gap-1">
                        <label className="text-muted-foreground text-xs">Nombre (ej. 6.°)</label>
                        <input
                            required
                            value={form.data.name}
                            onChange={(e) => form.setData('name', e.target.value)}
                            className="border-border bg-background w-64 rounded-md border p-2 text-sm"
                        />
                        {form.errors.name && <span className="text-crit text-xs">{form.errors.name}</span>}
                    </div>
                    <div className="flex flex-col gap-1">
                        <label className="text-muted-foreground text-xs">Valor (0 = Preescolar)</label>
                        <input
                            required
                            type="number"
                            min={0}
                            max={15}
                            value={form.data.value}
                            onChange={(e) => form.setData('value', Number(e.target.value))}
                            className="border-border bg-background w-24 rounded-md border p-2 text-sm"
                        />
                        {form.errors.value && <span className="text-crit text-xs">{form.errors.value}</span>}
                    </div>
                    <Button type="submit" size="sm" disabled={form.processing}>
                        Crear
                    </Button>
                </form>
            )}

            <div className="border-border bg-card overflow-x-auto rounded-lg border">
                <table className="w-full text-sm">
                    <thead>
                        <tr className="bg-muted text-muted-foreground text-left text-[11px] uppercase">
                            <th className="p-2">Grado</th>
                            <th className="p-2">Valor</th>
                            <th className="p-2">Estado</th>
                            <th className="p-2">Grupos</th>
                            <th className="p-2">Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        {levels.map((g) => (
                            <tr key={g.id} className="border-border border-t">
                                <td className="p-2 font-semibold">
                                    {editingId === g.id ? (
                                        <input
                                            autoFocus
                                            value={editingName}
                                            onChange={(e) => setEditingName(e.target.value)}
                                            className="border-border bg-background w-48 rounded-md border p-1 text-sm"
                                        />
                                    ) : (
                                        g.name
                                    )}
                                </td>
                                <td className="text-muted-foreground p-2">{g.value}</td>
                                <td className="p-2">
                                    <span
                                        className={
                                            'rounded-full px-2 py-0.5 text-[11px] font-semibold ' +
                                            (g.active ? 'bg-accent text-primary' : 'text-muted-foreground bg-muted')
                                        }
                                    >
                                        {g.active ? 'Activo' : 'Inactivo'}
                                    </span>
                                </td>
                                <td className="p-2">{g.groups_count}</td>
                                <td className="p-2">
                                    <div className="flex gap-2">
                                        {editingId === g.id ? (
                                            <>
                                                <button
                                                    type="button"
                                                    className="text-primary text-xs font-semibold hover:underline"
                                                    onClick={() => {
                                                        router.put(
                                                            `/admin/grados/${g.id}`,
                                                            { name: editingName },
                                                            { preserveScroll: true, onSuccess: () => setEditingId(null) },
                                                        );
                                                    }}
                                                >
                                                    Guardar
                                                </button>
                                                <button
                                                    type="button"
                                                    className="text-muted-foreground text-xs hover:underline"
                                                    onClick={() => setEditingId(null)}
                                                >
                                                    Cancelar
                                                </button>
                                            </>
                                        ) : (
                                            <button
                                                type="button"
                                                className="text-primary text-xs font-semibold hover:underline"
                                                onClick={() => {
                                                    setEditingId(g.id);
                                                    setEditingName(g.name);
                                                }}
                                            >
                                                Editar
                                            </button>
                                        )}
                                        <button
                                            type="button"
                                            className="text-muted-foreground text-xs hover:underline"
                                            onClick={() => {
                                                router.put(`/admin/grados/${g.id}`, { active: !g.active }, { preserveScroll: true });
                                            }}
                                        >
                                            {g.active ? 'Desactivar' : 'Activar'}
                                        </button>
                                        <button
                                            type="button"
                                            className={
                                                'text-xs font-semibold ' +
                                                (confirmDeleteId === g.id ? 'text-crit' : 'text-muted-foreground hover:text-crit')
                                            }
                                            onClick={() => {
                                                if (confirmDeleteId !== g.id) {
                                                    setConfirmDeleteId(g.id);
                                                    return;
                                                }
                                                router.delete(`/admin/grados/${g.id}`, { preserveScroll: true });
                                                setConfirmDeleteId(null);
                                            }}
                                            onBlur={() => setConfirmDeleteId(null)}
                                        >
                                            {confirmDeleteId === g.id ? '¿Eliminar? Confirmar' : 'Eliminar'}
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        ))}
                        {levels.length === 0 && (
                            <tr>
                                <td colSpan={5} className="text-muted-foreground p-3 text-center text-xs italic">
                                    Sin grados registrados.
                                </td>
                            </tr>
                        )}
                    </tbody>
                </table>
            </div>
        </SeguimientoLayout>
    );
}
