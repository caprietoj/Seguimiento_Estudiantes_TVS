import SeguimientoLayout from '@/layouts/seguimiento-layout';
import { Head } from '@inertiajs/react';

export default function SinGrupos() {
    return (
        <SeguimientoLayout>
            <Head title="Reunión de grupo" />
            <div className="border-border bg-card rounded-lg border p-6">
                <p className="text-foreground">Tu usuario todavía no tiene grupos asignados.</p>
                <p className="text-muted-foreground mt-1 text-sm">
                    Si crees que esto es un error, pide a un ADMIN que revise tus asignaciones desde Administración.
                </p>
            </div>
        </SeguimientoLayout>
    );
}
