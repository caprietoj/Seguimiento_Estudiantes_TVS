import { type SharedData } from '@/types';
import { Link, usePage } from '@inertiajs/react';
import { type ReactNode } from 'react';

interface SeguimientoLayoutProps {
    children: ReactNode;
}

const baseTabs = [
    { label: 'Reunión de grupo', href: '/reunion' },
    { label: 'Ficha del estudiante', href: '/estudiantes' },
    { label: 'Compromisos', href: '/compromisos' },
];
const adminTab = { label: 'Administración', href: '/admin' };

/**
 * Barra superior institucional (sección 8): azul #364E76, franja roja inferior, pestañas
 * y, a la derecha, nombre/rol de quien ingresó y "Salir". Reemplaza el layout con menú
 * lateral del starter kit para las vistas propias de la aplicación.
 */
export default function SeguimientoLayout({ children }: SeguimientoLayoutProps) {
    const { props, url } = usePage<SharedData>();
    const { auth } = props;
    const tabs = auth.isAdmin ? [...baseTabs, adminTab] : baseTabs;

    return (
        <div className="bg-background min-h-screen">
            <header className="border-brand-red bg-primary text-primary-foreground border-b-4">
                <div className="mx-auto flex max-w-[1480px] flex-wrap items-center gap-3 px-4 py-2.5">
                    <h1 className="font-display mr-auto text-[17px] font-medium tracking-wide uppercase">Seguimiento de Estudiantes</h1>

                    <nav className="flex gap-0.5 rounded-lg bg-white/10 p-1" aria-label="Vistas">
                        {tabs.map((tab) => (
                            <Link
                                key={tab.href}
                                href={tab.href}
                                className={
                                    'rounded-md px-3 py-1.5 text-sm font-semibold ' +
                                    (url.startsWith(tab.href) ? 'text-primary bg-white' : 'text-primary-foreground/85 hover:text-primary-foreground')
                                }
                            >
                                {tab.label}
                            </Link>
                        ))}
                    </nav>

                    <div className="flex items-center gap-3 text-sm">
                        <div className="text-right leading-tight">
                            <div className="font-semibold">{auth.user.name}</div>
                            <div className="text-primary-foreground/75 text-xs">{auth.roleLabels.join(', ')}</div>
                        </div>
                        <Link
                            method="post"
                            href="/logout"
                            as="button"
                            className="rounded-md border border-white/30 px-3 py-1.5 text-sm font-semibold hover:bg-white/10"
                        >
                            Salir
                        </Link>
                    </div>
                </div>
            </header>

            <main className="mx-auto max-w-[1480px] px-4 pt-4 pb-10">{children}</main>
        </div>
    );
}
