import { Link, usePage } from '@inertiajs/react';

const items = [
    { label: 'Años escolares', href: '/admin/anios' },
    { label: 'Periodo y seguimiento', href: '/admin/seguimientos' },
    { label: 'Secciones', href: '/admin/secciones' },
    { label: 'Grados', href: '/admin/grados' },
    { label: 'Grupos', href: '/admin/grupos' },
    { label: 'Estudiantes', href: '/admin/estudiantes' },
    { label: 'Asignaturas', href: '/admin/asignaturas' },
    { label: 'Asignaciones', href: '/admin/asignaciones' },
    { label: 'Importar', href: '/admin/importar' },
    { label: 'Respaldos', href: '/admin/respaldos' },
];

export function AdminNav() {
    const { url } = usePage();

    return (
        <nav className="border-border mb-4 flex gap-1 overflow-x-auto border-b">
            {items.map((item) => (
                <Link
                    key={item.href}
                    href={item.href}
                    className={
                        'shrink-0 rounded-t-md px-3 py-2 text-sm font-semibold ' +
                        (url.startsWith(item.href) ? 'border-primary text-primary border-b-2' : 'text-muted-foreground hover:text-foreground')
                    }
                >
                    {item.label}
                </Link>
            ))}
        </nav>
    );
}
