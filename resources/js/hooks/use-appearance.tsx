import { useEffect, useState } from 'react';

export type Appearance = 'light' | 'dark' | 'system';

// Sección 8 del encargo: "Fondo blanco. Sin modo oscuro." La app nunca aplica la clase
// "dark", sin importar lo que el sistema operativo o una preferencia guardada indiquen.
const applyTheme = () => {
    document.documentElement.classList.remove('dark');
};

const mediaQuery = window.matchMedia('(prefers-color-scheme: dark)');

const handleSystemThemeChange = () => {
    applyTheme();
};

export function initializeTheme() {
    applyTheme();

    // Add the event listener for system theme changes...
    mediaQuery.addEventListener('change', handleSystemThemeChange);
}

export function useAppearance() {
    const [appearance, setAppearance] = useState<Appearance>('system');

    const updateAppearance = (mode: Appearance) => {
        setAppearance(mode);
        localStorage.setItem('appearance', mode);
        applyTheme();
    };

    useEffect(() => {
        const savedAppearance = localStorage.getItem('appearance') as Appearance | null;
        updateAppearance(savedAppearance || 'system');

        return () => mediaQuery.removeEventListener('change', handleSystemThemeChange);
    }, []);

    return { appearance, updateAppearance };
}
