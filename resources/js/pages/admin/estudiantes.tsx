import { AdminNav } from '@/components/admin-nav';
import { Button } from '@/components/ui/button';
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import SeguimientoLayout from '@/layouts/seguimiento-layout';
import { Head, Link, router, useForm } from '@inertiajs/react';
import { CheckCircle2, Edit2, ExternalLink, FilterX, Search, Trash2, UserCheck, UserMinus, UserPlus, Users } from 'lucide-react';
import { useState } from 'react';

interface StudentRow {
    id: number;
    institutional_code: string | null;
    full_name: string;
    active: boolean;
    photo_url: string | null;
    group_id: number | null;
    group_code: string | null;
}

interface GroupOption {
    id: number;
    code: string;
}

interface SchoolYearOption {
    id: number;
    name: string;
}

interface Props {
    schoolYears: SchoolYearOption[];
    selectedYearId: number;
    groups: GroupOption[];
    students: StudentRow[];
    filters: {
        buscar: string;
        grupo: string;
        estado: string;
    };
    stats: {
        total: number;
        activos: number;
        matriculados: number;
    };
}

export default function AdminEstudiantes({ schoolYears, selectedYearId, groups, students, filters, stats }: Props) {
    const [openCreate, setOpenCreate] = useState(false);
    const [editingStudent, setEditingStudent] = useState<StudentRow | null>(null);
    const [deletingStudent, setDeletingStudent] = useState<StudentRow | null>(null);

    // Search and filter state
    const [searchTerm, setSearchTerm] = useState(filters.buscar || '');
    const [groupFilter, setGroupFilter] = useState(filters.grupo || '');
    const [statusFilter, setStatusFilter] = useState(filters.estado || 'todos');

    // Create form
    const createForm = useForm({
        full_name: '',
        institutional_code: '',
        group_id: '',
        school_year_id: selectedYearId,
        active: true,
    });

    // Edit form
    const editForm = useForm({
        full_name: '',
        institutional_code: '',
        active: true,
    });

    const activeYearName = schoolYears.find((y) => y.id === selectedYearId)?.name ?? 'Año seleccionado';

    const hasActiveFilters = Boolean(filters.buscar) || Boolean(filters.grupo) || filters.estado !== 'todos';

    const handleApplyFilters = (override?: { buscar?: string; grupo?: string; estado?: string; anio?: number }) => {
        router.get(
            '/admin/estudiantes',
            {
                anio: override?.anio ?? selectedYearId,
                buscar: override?.buscar !== undefined ? override.buscar : searchTerm,
                grupo: override?.grupo !== undefined ? override.grupo : groupFilter,
                estado: override?.estado !== undefined ? override.estado : statusFilter,
            },
            { preserveState: true, replace: true },
        );
    };

    const handleClearFilters = () => {
        setSearchTerm('');
        setGroupFilter('');
        setStatusFilter('todos');
        router.get('/admin/estudiantes', { anio: selectedYearId }, { preserveState: true, replace: true });
    };

    const handleCreateStudent = (e: React.FormEvent) => {
        e.preventDefault();
        createForm.transform((data) => ({
            ...data,
            school_year_id: selectedYearId,
            group_id: data.group_id === '' ? null : Number(data.group_id),
        }));
        createForm.post('/admin/estudiantes', {
            onSuccess: () => {
                createForm.reset();
                setOpenCreate(false);
            },
        });
    };

    const openEditModal = (student: StudentRow) => {
        setEditingStudent(student);
        editForm.setData({
            full_name: student.full_name,
            institutional_code: student.institutional_code ?? '',
            active: student.active,
        });
    };

    const handleUpdateStudent = (e: React.FormEvent) => {
        e.preventDefault();
        if (!editingStudent) return;

        editForm.put(`/admin/estudiantes/${editingStudent.id}`, {
            preserveScroll: true,
            onSuccess: () => {
                setEditingStudent(null);
            },
        });
    };

    const handleDeleteStudent = () => {
        if (!deletingStudent) return;
        router.delete(`/admin/estudiantes/${deletingStudent.id}`, {
            preserveScroll: true,
            onSuccess: () => setDeletingStudent(null),
        });
    };

    const handleQuickGroupChange = (student: StudentRow, newGroupId: string) => {
        router.put(
            `/admin/estudiantes/${student.id}`,
            {
                school_year_id: selectedYearId,
                group_id: newGroupId === '' ? null : Number(newGroupId),
            },
            { preserveScroll: true },
        );
    };

    const handleToggleActive = (student: StudentRow) => {
        router.put(`/admin/estudiantes/${student.id}`, { active: !student.active }, { preserveScroll: true });
    };

    const getInitials = (fullName: string) => {
        const parts = fullName.trim().split(/\s+/);
        if (parts.length >= 2) {
            return (parts[0][0] + parts[1][0]).toUpperCase();
        }
        return fullName.slice(0, 2).toUpperCase();
    };

    return (
        <SeguimientoLayout>
            <Head title="Administración · Estudiantes" />
            <AdminNav />

            {/* Encabezado y selector de año escolar */}
            <div className="mb-4 flex flex-wrap items-center justify-between gap-3">
                <div className="flex flex-wrap items-center gap-2">
                    <h2 className="font-display text-primary text-xl">Estudiantes</h2>
                    <div className="flex items-center gap-1.5">
                        <span className="text-muted-foreground text-xs">Año escolar:</span>
                        <select
                            className="border-border bg-background rounded-md border px-2.5 py-1.5 text-sm font-medium"
                            value={selectedYearId}
                            onChange={(e) => {
                                const newYearId = Number(e.target.value);
                                handleApplyFilters({ anio: newYearId });
                            }}
                        >
                            {schoolYears.map((y) => (
                                <option key={y.id} value={y.id}>
                                    {y.name}
                                </option>
                            ))}
                        </select>
                    </div>
                </div>

                <Button size="sm" className="flex items-center gap-1.5" onClick={() => setOpenCreate(!openCreate)}>
                    <UserPlus className="h-4 w-4" />
                    {openCreate ? 'Cancelar' : '+ Nuevo estudiante'}
                </Button>
            </div>

            {/* Tarjetas de estadísticas */}
            <div className="mb-4 grid grid-cols-2 gap-3 sm:grid-cols-4">
                <div className="border-border bg-card flex items-center gap-3 rounded-lg border p-3">
                    <div className="bg-primary/10 text-primary flex h-9 w-9 items-center justify-center rounded-lg">
                        <Users className="h-5 w-5" />
                    </div>
                    <div>
                        <div className="text-muted-foreground text-[11px] font-medium tracking-wide uppercase">Total estudiantes</div>
                        <div className="font-mono text-lg font-bold">{stats.total}</div>
                    </div>
                </div>

                <div className="border-border bg-card flex items-center gap-3 rounded-lg border p-3">
                    <div className="bg-good-soft text-good flex h-9 w-9 items-center justify-center rounded-lg">
                        <UserCheck className="h-5 w-5" />
                    </div>
                    <div>
                        <div className="text-muted-foreground text-[11px] font-medium tracking-wide uppercase">Activos</div>
                        <div className="font-mono text-lg font-bold">{stats.activos}</div>
                    </div>
                </div>

                <div className="border-border bg-card flex items-center gap-3 rounded-lg border p-3">
                    <div className="bg-primary/10 text-primary flex h-9 w-9 items-center justify-center rounded-lg">
                        <CheckCircle2 className="h-5 w-5" />
                    </div>
                    <div>
                        <div className="text-muted-foreground text-[11px] font-medium tracking-wide uppercase">Matriculados {activeYearName}</div>
                        <div className="font-mono text-lg font-bold">{stats.matriculados}</div>
                    </div>
                </div>

                <div className="border-border bg-card flex items-center gap-3 rounded-lg border p-3">
                    <div className="bg-warn-soft text-warn flex h-9 w-9 items-center justify-center rounded-lg">
                        <UserMinus className="h-5 w-5" />
                    </div>
                    <div>
                        <div className="text-muted-foreground text-[11px] font-medium tracking-wide uppercase">Sin grupo ({activeYearName})</div>
                        <div className="font-mono text-lg font-bold">{Math.max(0, stats.total - stats.matriculados)}</div>
                    </div>
                </div>
            </div>

            {/* Formulario de registro de estudiante (+ Nuevo estudiante) */}
            {openCreate && (
                <div className="border-border bg-accent/40 mb-5 rounded-lg border p-4 shadow-xs">
                    <div className="mb-3 flex items-center justify-between">
                        <h3 className="font-display text-primary flex items-center gap-2 text-base font-semibold">
                            <UserPlus className="h-4 w-4" />
                            Registrar nuevo estudiante
                        </h3>
                        <span className="text-muted-foreground text-xs">Se registrará en el catálogo general y podrá matricularse de inmediato.</span>
                    </div>

                    <form onSubmit={handleCreateStudent} className="space-y-3">
                        <div className="grid grid-cols-1 gap-3 sm:grid-cols-3">
                            <div className="flex flex-col gap-1 sm:col-span-1">
                                <label className="text-foreground text-xs font-semibold">
                                    Nombre completo <span className="text-crit">*</span>
                                </label>
                                <input
                                    required
                                    placeholder="ej. Restrepo Gómez, Santiago"
                                    value={createForm.data.full_name}
                                    onChange={(e) => createForm.setData('full_name', e.target.value)}
                                    className="border-border bg-background rounded-md border p-2 text-sm"
                                />
                                {createForm.errors.full_name && <span className="text-crit text-xs">{createForm.errors.full_name}</span>}
                            </div>

                            <div className="flex flex-col gap-1">
                                <label className="text-foreground text-xs font-semibold">Código institucional</label>
                                <input
                                    placeholder="ej. TVS-01055"
                                    value={createForm.data.institutional_code}
                                    onChange={(e) => createForm.setData('institutional_code', e.target.value)}
                                    className="border-border bg-background rounded-md border p-2 font-mono text-sm"
                                />
                                {createForm.errors.institutional_code && (
                                    <span className="text-crit text-xs">{createForm.errors.institutional_code}</span>
                                )}
                            </div>

                            <div className="flex flex-col gap-1">
                                <label className="text-foreground text-xs font-semibold">Grupo en {activeYearName}</label>
                                <select
                                    value={createForm.data.group_id}
                                    onChange={(e) => createForm.setData('group_id', e.target.value)}
                                    className="border-border bg-background rounded-md border p-2 text-sm"
                                >
                                    <option value="">Sin asignar (matricular luego)</option>
                                    {groups.map((g) => (
                                        <option key={g.id} value={g.id}>
                                            Grupo {g.code}
                                        </option>
                                    ))}
                                </select>
                                {createForm.errors.group_id && <span className="text-crit text-xs">{createForm.errors.group_id}</span>}
                            </div>
                        </div>

                        <div className="flex flex-wrap items-center justify-between gap-3 pt-2">
                            <label className="flex cursor-pointer items-center gap-2 text-xs font-medium">
                                <input
                                    type="checkbox"
                                    checked={createForm.data.active}
                                    onChange={(e) => createForm.setData('active', e.target.checked)}
                                    className="h-4 w-4 rounded border-gray-300"
                                />
                                Marcar como estudiante activo para el seguimiento
                            </label>

                            <div className="flex gap-2">
                                <Button type="button" variant="ghost" size="sm" onClick={() => setOpenCreate(false)}>
                                    Cancelar
                                </Button>
                                <Button type="submit" size="sm" disabled={createForm.processing}>
                                    {createForm.processing ? 'Guardando...' : 'Guardar estudiante'}
                                </Button>
                            </div>
                        </div>
                    </form>
                </div>
            )}

            {/* Barra de búsqueda y filtros */}
            <div className="border-border bg-card mb-4 flex flex-wrap items-center gap-2.5 rounded-lg border p-3">
                <div className="relative min-w-[220px] flex-1">
                    <Search className="text-muted-foreground absolute top-2.5 left-2.5 h-4 w-4" />
                    <input
                        type="search"
                        placeholder="Buscar por nombre o código institucional..."
                        value={searchTerm}
                        onChange={(e) => setSearchTerm(e.target.value)}
                        onKeyDown={(e) => {
                            if (e.key === 'Enter') {
                                handleApplyFilters({ buscar: searchTerm });
                            }
                        }}
                        className="border-border bg-background w-full rounded-md border py-1.5 pr-2 pl-8 text-sm"
                    />
                </div>

                <div className="flex items-center gap-1.5">
                    <label className="text-muted-foreground text-xs font-medium">Grupo:</label>
                    <select
                        value={groupFilter}
                        onChange={(e) => {
                            const val = e.target.value;
                            setGroupFilter(val);
                            handleApplyFilters({ grupo: val });
                        }}
                        className="border-border bg-background rounded-md border px-2.5 py-1.5 text-xs font-medium"
                    >
                        <option value="">Todos los grupos</option>
                        <option value="sin-grupo">Sin grupo ({activeYearName})</option>
                        {groups.map((g) => (
                            <option key={g.id} value={g.id}>
                                {g.code}
                            </option>
                        ))}
                    </select>
                </div>

                <div className="flex items-center gap-1.5">
                    <label className="text-muted-foreground text-xs font-medium">Estado:</label>
                    <select
                        value={statusFilter}
                        onChange={(e) => {
                            const val = e.target.value;
                            setStatusFilter(val);
                            handleApplyFilters({ estado: val });
                        }}
                        className="border-border bg-background rounded-md border px-2.5 py-1.5 text-xs font-medium"
                    >
                        <option value="todos">Todos</option>
                        <option value="activos">Solo activos</option>
                        <option value="inactivos">Solo inactivos</option>
                    </select>
                </div>

                <Button size="sm" variant="secondary" onClick={() => handleApplyFilters()} className="text-xs">
                    Filtrar
                </Button>

                {hasActiveFilters && (
                    <Button
                        size="sm"
                        variant="ghost"
                        onClick={handleClearFilters}
                        className="text-muted-foreground hover:text-foreground text-xs"
                        title="Quitar filtros"
                    >
                        <FilterX className="mr-1 h-3.5 w-3.5" />
                        Limpiar
                    </Button>
                )}
            </div>

            {/* Tabla de estudiantes */}
            <div className="border-border bg-card overflow-x-auto rounded-lg border shadow-xs">
                <table className="w-full text-sm">
                    <thead>
                        <tr className="bg-muted text-muted-foreground text-left text-[11px] tracking-wider uppercase">
                            <th className="p-3">Estudiante</th>
                            <th className="p-3">Código</th>
                            <th className="p-3">Grupo ({activeYearName})</th>
                            <th className="p-3">Estado</th>
                            <th className="p-3 text-right">Acciones</th>
                        </tr>
                    </thead>
                    <tbody className="divide-border divide-y">
                        {students.map((s) => (
                            <tr key={s.id} className="hover:bg-muted/40 transition-colors">
                                <td className="p-3">
                                    <div className="flex items-center gap-3">
                                        <div className="bg-primary/10 text-primary flex h-9 w-9 shrink-0 items-center justify-center overflow-hidden rounded-full text-xs font-semibold">
                                            {s.photo_url ? (
                                                <img src={s.photo_url} alt={s.full_name} className="h-full w-full object-cover" />
                                            ) : (
                                                getInitials(s.full_name)
                                            )}
                                        </div>
                                        <div>
                                            <div className="text-foreground font-semibold">{s.full_name}</div>
                                            {!s.active && <span className="text-muted-foreground text-[10px] italic">Inactivo en el sistema</span>}
                                        </div>
                                    </div>
                                </td>

                                <td className="p-3">
                                    {s.institutional_code ? (
                                        <span className="bg-muted text-muted-foreground rounded px-1.5 py-0.5 font-mono text-xs font-medium">
                                            {s.institutional_code}
                                        </span>
                                    ) : (
                                        <span className="text-muted-foreground text-xs italic">Sin código</span>
                                    )}
                                </td>

                                <td className="p-3">
                                    <select
                                        value={s.group_id ?? ''}
                                        onChange={(e) => handleQuickGroupChange(s, e.target.value)}
                                        className="border-border bg-background rounded-md border px-2 py-1 text-xs font-medium"
                                    >
                                        <option value="">Sin grupo asignado</option>
                                        {groups.map((g) => (
                                            <option key={g.id} value={g.id}>
                                                Grupo {g.code}
                                            </option>
                                        ))}
                                    </select>
                                </td>

                                <td className="p-3">
                                    <button
                                        type="button"
                                        onClick={() => handleToggleActive(s)}
                                        className={`inline-flex cursor-pointer items-center rounded-full px-2 py-0.5 text-[11px] font-semibold transition ${
                                            s.active ? 'bg-good-soft text-good hover:opacity-80' : 'bg-muted text-muted-foreground hover:bg-muted/80'
                                        }`}
                                        title="Haz clic para cambiar el estado"
                                    >
                                        {s.active ? 'Activo' : 'Inactivo'}
                                    </button>
                                </td>

                                <td className="p-3 text-right">
                                    <div className="flex items-center justify-end gap-1.5">
                                        <Link
                                            href={`/estudiantes/${s.id}?anio=${selectedYearId}`}
                                            className="border-border text-primary hover:bg-accent flex items-center gap-1 rounded-md border px-2 py-1 text-xs font-semibold transition"
                                            title="Ver ficha de seguimiento"
                                        >
                                            <ExternalLink className="h-3.5 w-3.5" />
                                            Ficha
                                        </Link>

                                        <Button
                                            size="sm"
                                            variant="ghost"
                                            onClick={() => openEditModal(s)}
                                            className="h-7 px-2 text-xs"
                                            title="Editar información del estudiante"
                                        >
                                            <Edit2 className="h-3.5 w-3.5" />
                                        </Button>

                                        <Button
                                            size="sm"
                                            variant="ghost"
                                            onClick={() => setDeletingStudent(s)}
                                            className="text-muted-foreground hover:text-crit h-7 px-2 text-xs"
                                            title="Eliminar estudiante"
                                        >
                                            <Trash2 className="h-3.5 w-3.5" />
                                        </Button>
                                    </div>
                                </td>
                            </tr>
                        ))}

                        {students.length === 0 && (
                            <tr>
                                <td colSpan={5} className="p-8 text-center">
                                    <div className="flex flex-col items-center justify-center gap-2">
                                        <Users className="text-muted-foreground/60 h-8 w-8" />
                                        <p className="text-muted-foreground text-sm font-medium">
                                            {hasActiveFilters
                                                ? 'No se encontraron estudiantes con los filtros aplicados.'
                                                : 'Aún no hay estudiantes registrados en el sistema.'}
                                        </p>
                                        {hasActiveFilters ? (
                                            <Button variant="outline" size="sm" onClick={handleClearFilters}>
                                                Restablecer filtros
                                            </Button>
                                        ) : (
                                            <Button size="sm" onClick={() => setOpenCreate(true)}>
                                                + Registrar primer estudiante
                                            </Button>
                                        )}
                                    </div>
                                </td>
                            </tr>
                        )}
                    </tbody>
                </table>
            </div>

            {/* Modal de edición de estudiante */}
            <Dialog open={editingStudent !== null} onOpenChange={(isOpen) => !isOpen && setEditingStudent(null)}>
                <DialogContent className="sm:max-w-md">
                    <DialogHeader>
                        <DialogTitle>Editar datos del estudiante</DialogTitle>
                        <DialogDescription>Modifica el nombre completo, código institucional o estado.</DialogDescription>
                    </DialogHeader>

                    {editingStudent && (
                        <form onSubmit={handleUpdateStudent} className="space-y-4">
                            <div className="flex flex-col gap-1">
                                <label className="text-foreground text-xs font-semibold">
                                    Nombre completo <span className="text-crit">*</span>
                                </label>
                                <input
                                    required
                                    value={editForm.data.full_name}
                                    onChange={(e) => editForm.setData('full_name', e.target.value)}
                                    className="border-border bg-background rounded-md border p-2 text-sm"
                                />
                                {editForm.errors.full_name && <span className="text-crit text-xs">{editForm.errors.full_name}</span>}
                            </div>

                            <div className="flex flex-col gap-1">
                                <label className="text-foreground text-xs font-semibold">Código institucional</label>
                                <input
                                    value={editForm.data.institutional_code}
                                    onChange={(e) => editForm.setData('institutional_code', e.target.value)}
                                    className="border-border bg-background rounded-md border p-2 font-mono text-sm"
                                />
                                {editForm.errors.institutional_code && (
                                    <span className="text-crit text-xs">{editForm.errors.institutional_code}</span>
                                )}
                            </div>

                            <label className="flex cursor-pointer items-center gap-2 text-xs font-medium">
                                <input
                                    type="checkbox"
                                    checked={editForm.data.active}
                                    onChange={(e) => editForm.setData('active', e.target.checked)}
                                    className="h-4 w-4 rounded border-gray-300"
                                />
                                Estudiante activo para el seguimiento
                            </label>

                            <DialogFooter>
                                <Button type="button" variant="ghost" onClick={() => setEditingStudent(null)}>
                                    Cancelar
                                </Button>
                                <Button type="submit" disabled={editForm.processing}>
                                    {editForm.processing ? 'Guardando...' : 'Guardar cambios'}
                                </Button>
                            </DialogFooter>
                        </form>
                    )}
                </DialogContent>
            </Dialog>

            {/* Modal de confirmación para eliminar */}
            <Dialog open={deletingStudent !== null} onOpenChange={(isOpen) => !isOpen && setDeletingStudent(null)}>
                <DialogContent className="sm:max-w-md">
                    <DialogHeader>
                        <DialogTitle className="text-crit">Eliminar estudiante</DialogTitle>
                        <DialogDescription>
                            ¿Estás seguro de que deseas eliminar a <strong className="text-foreground">{deletingStudent?.full_name}</strong>? Esta
                            acción removerá al estudiante del listado (podrá ser restaurado mediante base de datos si es necesario).
                        </DialogDescription>
                    </DialogHeader>

                    <DialogFooter>
                        <Button type="button" variant="ghost" onClick={() => setDeletingStudent(null)}>
                            Cancelar
                        </Button>
                        <Button type="button" variant="destructive" onClick={handleDeleteStudent}>
                            Eliminar estudiante
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>
        </SeguimientoLayout>
    );
}
