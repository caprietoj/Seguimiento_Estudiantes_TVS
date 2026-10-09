import { AdminNav } from '@/components/admin-nav';
import { Button } from '@/components/ui/button';
import SeguimientoLayout from '@/layouts/seguimiento-layout';
import { Head, router, useForm } from '@inertiajs/react';
import { useState } from 'react';

interface YearRow {
    id: number;
    name: string;
    starts_on: string;
    ends_on: string;
    is_active: boolean;
    is_read_only: boolean;
    groups_count: number;
    follow_ups_count: number;
}

export default function AdminAnios({ years }: { years: YearRow[] }) {
    const [open, setOpen] = useState(false);
    const form = useForm({ name: '', starts_on: '', ends_on: '' });

    return (
        <SeguimientoLayout>
            <Head title="Administración · Años escolares" />
            <AdminNav />

            <div className="mb-4 flex items-center justify-between">
                <h2 className="font-display text-primary text-xl">Años escolares</h2>
                <Button size="sm" onClick={() => setOpen(!open)}>
                    {open ? 'Cancelar' : '+ Nuevo año escolar'}
                </Button>
            </div>

            {open && (
                <form
                    className="border-border bg-accent mb-4 flex flex-wrap items-end gap-3 rounded-lg border p-3"
                    onSubmit={(e) => {
                        e.preventDefault();
                        form.post('/admin/anios', {
                            onSuccess: () => {
                                form.reset();
                                setOpen(false);
                            },
                        });
                    }}
                >
                    <div className="flex flex-col gap-1">
                        <label className="text-muted-foreground text-xs">Nombre (ej. 2027–2028)</label>
                        <input
                            required
                            value={form.data.name}
                            onChange={(e) => form.setData('name', e.target.value)}
                            className="border-border bg-background rounded-md border p-2 text-sm"
                        />
                    </div>
                    <div className="flex flex-col gap-1">
                        <label className="text-muted-foreground text-xs">Inicio</label>
                        <input
                            type="date"
                            required
                            value={form.data.starts_on}
                            onChange={(e) => form.setData('starts_on', e.target.value)}
                            className="border-border bg-background rounded-md border p-2 text-sm"
                        />
                    </div>
                    <div className="flex flex-col gap-1">
                        <label className="text-muted-foreground text-xs">Fin</label>
                        <input
                            type="date"
                            required
                            value={form.data.ends_on}
                            onChange={(e) => form.setData('ends_on', e.target.value)}
                            className="border-border bg-background rounded-md border p-2 text-sm"
                        />
                    </div>
                    <Button type="submit" size="sm" disabled={form.processing}>
                        Crear (genera los 6 seguimientos)
                    </Button>
                </form>
            )}

            <div className="border-border bg-card overflow-x-auto rounded-lg border">
                <table className="w-full text-sm">
                    <thead>
                        <tr className="bg-muted text-muted-foreground text-left text-[11px] uppercase">
                            <th className="p-2">Nombre</th>
                            <th className="p-2">Inicio</th>
                            <th className="p-2">Fin</th>
                            <th className="p-2">Grupos</th>
                            <th className="p-2">Seguimientos</th>
                            <th className="p-2">Estado</th>
                            <th className="p-2">Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        {years.map((y) => (
                            <tr key={y.id} className="border-border border-t">
                                <td className="p-2 font-semibold">{y.name}</td>
                                <td className="p-2 font-mono">{y.starts_on}</td>
                                <td className="p-2 font-mono">{y.ends_on}</td>
                                <td className="p-2">{y.groups_count}</td>
                                <td className="p-2">{y.follow_ups_count}</td>
                                <td className="p-2">
                                    <div className="flex flex-wrap gap-1">
                                        {y.is_active && (
                                            <span className="bg-good-soft text-good rounded-full px-2 py-0.5 text-[11px] font-semibold">Activo</span>
                                        )}
                                        {y.is_read_only && (
                                            <span className="bg-muted text-muted-foreground rounded-full px-2 py-0.5 text-[11px] font-semibold">
                                                Solo lectura
                                            </span>
                                        )}
                                    </div>
                                </td>
                                <td className="p-2">
                                    <div className="flex gap-2">
                                        {!y.is_active && (
                                            <button
                                                type="button"
                                                className="text-primary text-xs font-semibold hover:underline"
                                                onClick={() => router.post(`/admin/anios/${y.id}/activar`, {}, { preserveScroll: true })}
                                            >
                                                Activar
                                            </button>
                                        )}
                                        <button
                                            type="button"
                                            className="text-muted-foreground text-xs font-semibold hover:underline"
                                            onClick={() =>
                                                router.put(
                                                    `/admin/anios/${y.id}`,
                                                    { name: y.name, starts_on: y.starts_on, ends_on: y.ends_on, is_read_only: !y.is_read_only },
                                                    { preserveScroll: true },
                                                )
                                            }
                                        >
                                            {y.is_read_only ? 'Quitar solo lectura' : 'Marcar solo lectura'}
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        ))}
                    </tbody>
                </table>
            </div>
        </SeguimientoLayout>
    );
}
