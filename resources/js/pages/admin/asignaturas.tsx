import { AdminNav } from '@/components/admin-nav';
import { Button } from '@/components/ui/button';
import SeguimientoLayout from '@/layouts/seguimiento-layout';
import { Head, router, useForm } from '@inertiajs/react';
import { useState } from 'react';

interface SubjectRow {
    id: number;
    name: string;
    active: boolean;
    grades: number[];
}

interface GradeOption {
    value: number;
    label: string;
}

interface Props {
    gradeLevels: GradeOption[];
    subjects: SubjectRow[];
}

function GradeCheckboxes({ grades, allGrades, onChange }: { grades: number[]; allGrades: GradeOption[]; onChange: (grades: number[]) => void }) {
    return (
        <div className="flex flex-wrap gap-2">
            {allGrades.map((g) => (
                <label key={g.value} className="flex items-center gap-1 text-xs">
                    <input
                        type="checkbox"
                        checked={grades.includes(g.value)}
                        onChange={(e) => onChange(e.target.checked ? [...grades, g.value] : grades.filter((x) => x !== g.value))}
                    />
                    {g.label}
                </label>
            ))}
        </div>
    );
}

export default function AdminAsignaturas({ gradeLevels, subjects }: Props) {
    const [open, setOpen] = useState(false);
    const form = useForm<{ name: string; grades: number[] }>({ name: '', grades: [] });

    return (
        <SeguimientoLayout>
            <Head title="Administración · Asignaturas" />
            <AdminNav />

            <div className="mb-4 flex items-center justify-between">
                <h2 className="font-display text-primary text-xl">Asignaturas</h2>
                <Button size="sm" onClick={() => setOpen(!open)}>
                    {open ? 'Cancelar' : '+ Nueva asignatura'}
                </Button>
            </div>

            {open && (
                <form
                    className="border-border bg-accent mb-4 flex flex-wrap items-end gap-4 rounded-lg border p-3"
                    onSubmit={(e) => {
                        e.preventDefault();
                        form.post('/admin/asignaturas', {
                            onSuccess: () => {
                                form.reset();
                                setOpen(false);
                            },
                        });
                    }}
                >
                    <div className="flex flex-col gap-1">
                        <label className="text-muted-foreground text-xs">Nombre</label>
                        <input
                            required
                            value={form.data.name}
                            onChange={(e) => form.setData('name', e.target.value)}
                            className="border-border bg-background rounded-md border p-2 text-sm"
                        />
                    </div>
                    <div className="flex flex-col gap-1">
                        <label className="text-muted-foreground text-xs">Grados (ninguno = todos)</label>
                        <GradeCheckboxes grades={form.data.grades} allGrades={gradeLevels} onChange={(g) => form.setData('grades', g)} />
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
                            <th className="p-2">Activa</th>
                            <th className="p-2">Grados</th>
                        </tr>
                    </thead>
                    <tbody>
                        {subjects.map((s) => (
                            <SubjectRow key={s.id} subject={s} allGrades={gradeLevels} />
                        ))}
                    </tbody>
                </table>
            </div>
        </SeguimientoLayout>
    );
}

function SubjectRow({ subject, allGrades }: { subject: SubjectRow; allGrades: GradeOption[] }) {
    const [grades, setGrades] = useState(subject.grades);

    return (
        <tr className="border-border border-t align-top">
            <td className="p-2 font-semibold">{subject.name}</td>
            <td className="p-2">
                <input
                    type="checkbox"
                    defaultChecked={subject.active}
                    onChange={(e) =>
                        router.put(
                            `/admin/asignaturas/${subject.id}`,
                            { name: subject.name, active: e.target.checked, grades },
                            { preserveScroll: true },
                        )
                    }
                />
            </td>
            <td className="p-2">
                <div className="flex flex-wrap items-center gap-2">
                    <GradeCheckboxes grades={grades} allGrades={allGrades} onChange={setGrades} />
                    <button
                        type="button"
                        className="text-primary text-xs font-semibold hover:underline"
                        onClick={() =>
                            router.put(
                                `/admin/asignaturas/${subject.id}`,
                                { name: subject.name, active: subject.active, grades },
                                { preserveScroll: true },
                            )
                        }
                    >
                        Guardar grados
                    </button>
                </div>
            </td>
        </tr>
    );
}
