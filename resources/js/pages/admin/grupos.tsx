import { AdminNav } from '@/components/admin-nav';
import { Button } from '@/components/ui/button';
import SeguimientoLayout from '@/layouts/seguimiento-layout';
import { Head, router, useForm } from '@inertiajs/react';
import { useState } from 'react';

interface GroupRow {
    id: number;
    code: string;
    grade: number;
    grade_label: string;
    section_id: number;
    section_name: string;
    director_id: number | null;
    director_name: string | null;
    students_count: number;
}

interface Props {
    schoolYears: { id: number; name: string }[];
    selectedYearId: number;
    sections: { id: number; name: string }[];
    directors: { id: number; name: string }[];
    gradeLevels: { value: number; label: string }[];
    groups: GroupRow[];
}

export default function AdminGrupos({ schoolYears, selectedYearId, sections, directors, gradeLevels, groups }: Props) {
    const [open, setOpen] = useState(false);
    const form = useForm({
        code: '',
        grade: gradeLevels[0]?.value ?? 0,
        section_id: sections[0]?.id ?? '',
        school_year_id: selectedYearId,
        director_id: '',
    });

    return (
        <SeguimientoLayout>
            <Head title="Administración · Grupos" />
            <AdminNav />

            <div className="mb-4 flex flex-wrap items-center justify-between gap-3">
                <div className="flex items-end gap-2">
                    <h2 className="font-display text-primary text-xl">Grupos</h2>
                    <select
                        className="border-border bg-background rounded-md border px-2 py-1.5 text-sm"
                        value={selectedYearId}
                        onChange={(e) => router.get('/admin/grupos', { anio: e.target.value })}
                    >
                        {schoolYears.map((y) => (
                            <option key={y.id} value={y.id}>
                                {y.name}
                            </option>
                        ))}
                    </select>
                </div>
                <Button size="sm" onClick={() => setOpen(!open)}>
                    {open ? 'Cancelar' : '+ Nuevo grupo'}
                </Button>
            </div>

            {open && (
                <form
                    className="border-border bg-accent mb-4 flex flex-wrap items-end gap-3 rounded-lg border p-3"
                    onSubmit={(e) => {
                        e.preventDefault();
                        form.transform((data) => ({ ...data, school_year_id: selectedYearId }));
                        form.post('/admin/grupos', {
                            onSuccess: () => {
                                form.reset();
                                setOpen(false);
                            },
                        });
                    }}
                >
                    <div className="flex flex-col gap-1">
                        <label className="text-muted-foreground text-xs">Código (ej. 10A)</label>
                        <input
                            required
                            value={form.data.code}
                            onChange={(e) => form.setData('code', e.target.value)}
                            className="border-border bg-background w-28 rounded-md border p-2 text-sm"
                        />
                    </div>
                    <div className="flex flex-col gap-1">
                        <label className="text-muted-foreground text-xs">Grado</label>
                        <select
                            value={form.data.grade}
                            onChange={(e) => form.setData('grade', Number(e.target.value))}
                            className="border-border bg-background rounded-md border p-2 text-sm"
                        >
                            {[...gradeLevels]
                                .sort((a, b) => a.value - b.value)
                                .map((g) => (
                                    <option key={g.value} value={g.value}>
                                        {g.label}
                                    </option>
                                ))}
                        </select>
                    </div>
                    <div className="flex flex-col gap-1">
                        <label className="text-muted-foreground text-xs">Sección</label>
                        <select
                            value={form.data.section_id}
                            onChange={(e) => form.setData('section_id', Number(e.target.value))}
                            className="border-border bg-background rounded-md border p-2 text-sm"
                        >
                            {sections.map((s) => (
                                <option key={s.id} value={s.id}>
                                    {s.name}
                                </option>
                            ))}
                        </select>
                    </div>
                    <div className="flex flex-col gap-1">
                        <label className="text-muted-foreground text-xs">Director(a) de grupo</label>
                        <select
                            value={form.data.director_id}
                            onChange={(e) => form.setData('director_id', e.target.value)}
                            className="border-border bg-background rounded-md border p-2 text-sm"
                        >
                            <option value="">Sin asignar</option>
                            {directors.map((d) => (
                                <option key={d.id} value={d.id}>
                                    {d.name}
                                </option>
                            ))}
                        </select>
                    </div>
                    {Object.keys(form.errors).length > 0 && <div className="text-crit w-full text-sm">{Object.values(form.errors).join(' · ')}</div>}
                    <Button type="submit" size="sm" disabled={form.processing}>
                        Crear
                    </Button>
                </form>
            )}

            <div className="border-border bg-card overflow-x-auto rounded-lg border">
                <table className="w-full text-sm">
                    <thead>
                        <tr className="bg-muted text-muted-foreground text-left text-[11px] uppercase">
                            <th className="p-2">Código</th>
                            <th className="p-2">Grado</th>
                            <th className="p-2">Sección</th>
                            <th className="p-2">Director(a) de grupo</th>
                            <th className="p-2">Estudiantes</th>
                        </tr>
                    </thead>
                    <tbody>
                        {groups.map((g) => (
                            <tr key={g.id} className="border-border border-t">
                                <td className="p-2 font-semibold">{g.code}</td>
                                <td className="p-2">{g.grade_label}</td>
                                <td className="p-2">
                                    <select
                                        defaultValue={g.section_id}
                                        className="border-border bg-background rounded-md border px-2 py-1 text-xs"
                                        onChange={(e) =>
                                            router.put(
                                                `/admin/grupos/${g.id}`,
                                                { code: g.code, grade: g.grade, section_id: Number(e.target.value), director_id: g.director_id },
                                                { preserveScroll: true },
                                            )
                                        }
                                    >
                                        {sections.map((s) => (
                                            <option key={s.id} value={s.id}>
                                                {s.name}
                                            </option>
                                        ))}
                                    </select>
                                </td>
                                <td className="p-2">
                                    <select
                                        defaultValue={g.director_id ?? ''}
                                        className="border-border bg-background rounded-md border px-2 py-1 text-xs"
                                        onChange={(e) =>
                                            router.put(
                                                `/admin/grupos/${g.id}`,
                                                { code: g.code, grade: g.grade, section_id: g.section_id, director_id: e.target.value || null },
                                                { preserveScroll: true },
                                            )
                                        }
                                    >
                                        <option value="">Sin asignar</option>
                                        {directors.map((d) => (
                                            <option key={d.id} value={d.id}>
                                                {d.name}
                                            </option>
                                        ))}
                                    </select>
                                </td>
                                <td className="p-2">{g.students_count}</td>
                            </tr>
                        ))}
                        {groups.length === 0 && (
                            <tr>
                                <td colSpan={5} className="text-muted-foreground p-3 text-center text-xs italic">
                                    Sin grupos en este año escolar.
                                </td>
                            </tr>
                        )}
                    </tbody>
                </table>
            </div>
        </SeguimientoLayout>
    );
}
