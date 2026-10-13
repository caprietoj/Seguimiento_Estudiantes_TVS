import SeguimientoLayout from '@/layouts/seguimiento-layout';
import { Head } from '@inertiajs/react';

export default function SinGrupos() {
    return (
        <SeguimientoLayout>
            <Head title="Reunión de grupo" />
            <div className="border-border bg-card rounded-lg border p-6">
                <p className="text-foreground">Tu usuario todavía no tiene grupos asignados.</p>
                <p className="text-muted-foreground mt-1 text-sm">
                    Solo verás los estudiantes de los grupos donde eres director(a) de grupo o tienes una asignatura asignada. Si crees que esto es un
                    error, pide a un ADMIN que revise tus asignaciones en Administración · Asignaciones.
                </p>
            </div>
        </SeguimientoLayout>
    );
}
