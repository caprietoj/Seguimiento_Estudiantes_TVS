import { AdminNav } from '@/components/admin-nav';
import { Button } from '@/components/ui/button';
import SeguimientoLayout from '@/layouts/seguimiento-layout';
import { Head, router, useForm } from '@inertiajs/react';
import { useState } from 'react';

interface SectionRow {
    id: number;
    name: string;
    groups_count: number;
    emc_users_count: number;
}

export default function AdminSecciones({ sections }: { sections: SectionRow[] }) {
    const [open, setOpen] = useState(false);
    const form = useForm({ name: '' });
    const [editingId, setEditingId] = useState<number | null>(null);
    const [editingName, setEditingName] = useState('');
    const [confirmDeleteId, setConfirmDeleteId] = useState<number | null>(null);

    return (
        <SeguimientoLayout>
            <Head title="Administración · Secciones" />
            <AdminNav />

            <div className="mb-4 flex items-center justify-between">
                <h2 className="font-display text-primary text-xl">Secciones</h2>
                <Button size="sm" onClick={() => setOpen(!open)}>
                    {open ? 'Cancelar' : '+ Nueva sección'}
                </Button>
            </div>

            {open && (
                <form
                    className="border-border bg-accent mb-4 flex flex-wrap items-end gap-3 rounded-lg border p-3"
                    onSubmit={(e) => {
                        e.preventDefault();
                        form.post('/admin/secciones', {
                            onSuccess: () => {
                                form.reset();
                                setOpen(false);
                            },
                        });
                    }}
                >
                    <div className="flex flex-col gap-1">
                        <label className="text-muted-foreground text-xs">Nombre (ej. PAI (5.°–9.°))</label>
                        <input
                            required
                            value={form.data.name}
                            onChange={(e) => form.setData('name', e.target.value)}
                            className="border-border bg-background w-80 rounded-md border p-2 text-sm"
                        />
                        {form.errors.name && <span className="text-crit text-xs">{form.errors.name}</span>}
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
                            <th className="p-2">Nombre</th>
                            <th className="p-2">Grupos</th>
                            <th className="p-2">EMC asignados</th>
                            <th className="p-2">Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        {sections.map((s) => (
                            <tr key={s.id} className="border-border border-t">
                                <td className="p-2 font-semibold">
                                    {editingId === s.id ? (
                                        <input
                                            autoFocus
                                            value={editingName}
                                            onChange={(e) => setEditingName(e.target.value)}
                                            className="border-border bg-background w-64 rounded-md border p-1 text-sm"
                                        />
                                    ) : (
                                        s.name
                                    )}
                                </td>
                                <td className="p-2">{s.groups_count}</td>
                                <td className="p-2">{s.emc_users_count}</td>
                                <td className="p-2">
                                    <div className="flex gap-2">
                                        {editingId === s.id ? (
                                            <>
                                                <button
                                                    type="button"
                                                    className="text-primary text-xs font-semibold hover:underline"
                                                    onClick={() => {
                                                        router.put(
                                                            `/admin/secciones/${s.id}`,
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
                                                    setEditingId(s.id);
                                                    setEditingName(s.name);
                                                }}
                                            >
                                                Editar
                                            </button>
                                        )}
                                        <button
                                            type="button"
                                            className={
                                                'text-xs font-semibold ' +
                                                (confirmDeleteId === s.id ? 'text-crit' : 'text-muted-foreground hover:text-crit')
                                            }
                                            onClick={() => {
                                                if (confirmDeleteId !== s.id) {
                                                    setConfirmDeleteId(s.id);
                                                    return;
                                                }
                                                router.delete(`/admin/secciones/${s.id}`, { preserveScroll: true });
                                                setConfirmDeleteId(null);
                                            }}
                                            onBlur={() => setConfirmDeleteId(null)}
                                        >
                                            {confirmDeleteId === s.id ? '¿Eliminar? Confirmar' : 'Eliminar'}
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        ))}
                        {sections.length === 0 && (
                            <tr>
                                <td colSpan={4} className="text-muted-foreground p-3 text-center text-xs italic">
                                    Sin secciones registradas.
                                </td>
                            </tr>
                        )}
                    </tbody>
                </table>
            </div>
        </SeguimientoLayout>
    );
}
