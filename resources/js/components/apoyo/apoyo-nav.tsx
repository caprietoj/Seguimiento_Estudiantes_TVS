import { Link, usePage } from '@inertiajs/react';

const items = [
    { label: 'Estadística general', href: '/apoyo/estadistica' },
    { label: 'Registro de actividades', href: '/apoyo/actividades' },
];

/** Pestañas de la sección Dpto. de Apoyo. Conserva el año elegido al cambiar de pestaña. */
export function ApoyoNav({ yearId }: { yearId?: number }) {
    const { url } = usePage();

    return (
        <nav className="border-border mb-4 flex gap-1 overflow-x-auto border-b">
            {items.map((item) => (
                <Link
                    key={item.href}
                    href={yearId ? `${item.href}?anio=${yearId}` : item.href}
                    prefetch
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

/** Colores fijos por área (validados para daltonismo); el color sigue al área, no a su posición. */
export const AREA_COLORS: Record<string, string> = {
    psychology: '#2a78d6',
    ot: '#eb6834',
    speech: '#1baf7a',
    neuro: '#eda100',
};
