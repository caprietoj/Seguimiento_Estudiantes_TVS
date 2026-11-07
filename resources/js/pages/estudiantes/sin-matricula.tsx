import SeguimientoLayout from '@/layouts/seguimiento-layout';
import { Head, router } from '@inertiajs/react';

interface Props {
    student: { id: number; full_name: string; photo_url: string | null };
    schoolYears: { id: number; name: string }[];
    selectedYearId: number | null;
}

export default function SinMatricula({ student, schoolYears, selectedYearId }: Props) {
    return (
        <SeguimientoLayout>
            <Head title={`Ficha · ${student.full_name}`} />
            <div className="border-border bg-card rounded-lg border p-6">
                <div className="flex items-center gap-4">
                    {student.photo_url ? (
                        <img src={student.photo_url} alt={student.full_name} className="h-24 w-24 rounded-lg object-cover" />
                    ) : (
                        <div className="bg-primary text-primary-foreground flex h-24 w-24 items-center justify-center rounded-lg text-2xl font-bold">
                            {student.full_name
                                .split(' ')
                                .slice(0, 2)
                                .map((w) => w[0])
                                .join('')}
                        </div>
                    )}
                    <div>
                        <h2 className="mb-2 text-xl font-semibold">{student.full_name}</h2>
                        <p className="text-foreground">Este estudiante no tiene matrícula registrada en el año seleccionado.</p>
                    </div>
                </div>
                <div className="mt-3 flex items-center gap-2">
                    <label className="text-muted-foreground text-sm">Ver otro año:</label>
                    <select
                        className="border-border bg-background rounded-md border px-2 py-1.5 text-sm"
                        defaultValue={selectedYearId ?? ''}
                        onChange={(e) => router.get(`/estudiantes/${student.id}`, { anio: e.target.value })}
                    >
                        {schoolYears.map((y) => (
                            <option key={y.id} value={y.id}>
                                {y.name}
                            </option>
                        ))}
                    </select>
                </div>
            </div>
        </SeguimientoLayout>
    );
}
