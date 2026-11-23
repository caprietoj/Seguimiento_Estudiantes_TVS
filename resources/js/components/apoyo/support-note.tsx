import { Button } from '@/components/ui/button';
import { useForm } from '@inertiajs/react';
import { Plus } from 'lucide-react';
import { useState } from 'react';

/** Marcas Sí/No de una nota del Dpto. de Apoyo; de ellas sale la estadística general. */
export const SUPPORT_FLAGS = [
    { key: 'in_follow_up', label: 'Estudiante en seguimiento del área', short: 'En seguimiento' },
    { key: 'family_attended', label: 'Se atendió a la familia', short: 'Familia atendida' },
    { key: 'piar_meeting', label: 'Reunión PIAR', short: 'Reunión PIAR' },
    { key: 'crisis_attention', label: 'Atención en crisis', short: 'Atención en crisis' },
] as const;

type FlagKey = (typeof SUPPORT_FLAGS)[number]['key'];

export interface SupportNoteData {
    id: number;
    body: string;
    agreements?: string | null;
    strategy_follow_up?: string | null;
    flags?: { key: string; label: string }[];
}

/** Las tres partes del formato del colegio, rotuladas, y las marcas como etiquetas. */
export function SupportNoteBody({ note, size = 'xs' }: { note: SupportNoteData; size?: 'xs' | 'sm' }) {
    const text = size === 'sm' ? 'text-sm' : 'text-xs';
    const label = 'text-muted-foreground font-semibold';
    // Notas antiguas o de otros formatos solo traen texto libre: sin rótulo «Habilidades».
    const labelBody = Boolean(note.agreements || note.strategy_follow_up);

    return (
        <div className={'flex flex-col gap-1 leading-relaxed ' + text}>
            {note.body && (
                <p className="whitespace-pre-wrap">
                    {labelBody && <span className={label}>Habilidades: </span>}
                    {note.body}
                </p>
            )}
            {note.agreements && (
                <p className="whitespace-pre-wrap">
                    <span className={label}>Acuerdos / estrategias: </span>
                    {note.agreements}
                </p>
            )}
            {note.strategy_follow_up && (
                <p className="whitespace-pre-wrap">
                    <span className={label}>Seguimiento a las estrategias: </span>
                    {note.strategy_follow_up}
                </p>
            )}
            {note.flags && note.flags.length > 0 && (
                <div className="flex flex-wrap gap-1 pt-0.5">
                    {note.flags.map((f) => (
                        <span key={f.key} className="rounded-full bg-indigo-100 px-1.5 py-0.5 text-[10.5px] font-semibold text-indigo-800">
                            {f.label}
                        </span>
                    ))}
                </div>
            )}
        </div>
    );
}

interface FormProps {
    studentId: number;
    followUpId: number;
    field: string;
    /** Si viene, se edita esa nota (PUT); si no, se crea una nueva. */
    note?: SupportNoteData;
    onDone: () => void;
}

/** Formulario de una nota del Dpto. de Apoyo: tres textos opcionales y las cuatro marcas. */
export function SupportNoteForm({ studentId, followUpId, field, note, onDone }: FormProps) {
    const has = (k: FlagKey) => Boolean(note?.flags?.some((f) => f.key === k));
    const form = useForm({
        student_id: studentId,
        follow_up_id: followUpId,
        field,
        body: note?.body ?? '',
        agreements: note?.agreements ?? '',
        strategy_follow_up: note?.strategy_follow_up ?? '',
        in_follow_up: has('in_follow_up'),
        family_attended: has('family_attended'),
        piar_meeting: has('piar_meeting'),
        crisis_attention: has('crisis_attention'),
    });

    const area =
        'border-border bg-background focus:ring-primary w-full resize-y rounded-md border p-2 text-xs leading-relaxed focus:ring-1 focus:outline-none';
    const options = {
        preserveScroll: true,
        onSuccess: () => {
            form.reset();
            onDone();
        },
    };

    return (
        <form
            className="border-primary/30 bg-primary/5 flex flex-col gap-1.5 rounded-md border p-2.5 shadow-sm"
            onSubmit={(e) => {
                e.preventDefault();
                if (note) {
                    form.put(`/aportes/${note.id}`, options);
                } else {
                    form.post('/aportes', options);
                }
            }}
            onKeyDown={(e) => {
                if (e.key === 'Escape') onDone();
                if (e.key === 'Enter' && (e.ctrlKey || e.metaKey)) e.currentTarget.requestSubmit();
            }}
        >
            <label className="text-[10.5px] font-semibold">
                Habilidades / observación
                <textarea
                    autoFocus
                    value={form.data.body}
                    onChange={(e) => form.setData('body', e.target.value)}
                    className={area + ' mt-0.5 min-h-14 font-normal'}
                />
            </label>
            <label className="text-[10.5px] font-semibold">
                Acuerdos / estrategias
                <textarea
                    value={form.data.agreements}
                    onChange={(e) => form.setData('agreements', e.target.value)}
                    className={area + ' mt-0.5 min-h-10 font-normal'}
                />
            </label>
            <label className="text-[10.5px] font-semibold">
                Seguimiento a las estrategias
                <textarea
                    value={form.data.strategy_follow_up}
                    onChange={(e) => form.setData('strategy_follow_up', e.target.value)}
                    className={area + ' mt-0.5 min-h-10 font-normal'}
                />
            </label>
            <fieldset className="grid grid-cols-1 gap-x-3 gap-y-1 pt-0.5 sm:grid-cols-2">
                <legend className="text-muted-foreground mb-0.5 text-[10.5px]">Para la estadística del Dpto.</legend>
                {SUPPORT_FLAGS.map((f) => (
                    <label key={f.key} className="flex items-center gap-1.5 text-[11px]">
                        <input
                            type="checkbox"
                            checked={form.data[f.key]}
                            onChange={(e) => form.setData(f.key, e.target.checked)}
                            className="accent-primary size-3.5"
                        />
                        {f.label}
                    </label>
                ))}
            </fieldset>
            {form.errors.body && <p className="text-crit text-[11px]">{form.errors.body}</p>}
            <div className="flex items-center justify-between pt-0.5">
                <span className="text-muted-foreground text-[10px]">Ctrl+Enter para guardar</span>
                <div className="flex gap-1.5">
                    <Button type="button" size="sm" variant="ghost" className="h-7 px-2 text-xs" onClick={onDone}>
                        Cancelar
                    </Button>
                    <Button type="submit" size="sm" className="h-7 px-2.5 text-xs font-semibold" disabled={form.processing}>
                        Guardar
                    </Button>
                </div>
            </div>
        </form>
    );
}

/** Botón «Añadir nota del área» que abre el formulario. */
export function AddSupportNote(props: Omit<FormProps, 'note' | 'onDone'> & { label?: string }) {
    const [open, setOpen] = useState(false);

    if (open) {
        return <SupportNoteForm {...props} onDone={() => setOpen(false)} />;
    }

    return (
        <button
            type="button"
            onClick={() => setOpen(true)}
            className="text-primary hover:border-primary/80 hover:bg-primary/5 border-primary/40 inline-flex items-center gap-1 self-start rounded-md border border-dashed px-2 py-1 text-[11px] font-semibold transition"
        >
            <Plus className="size-3" />
            {props.label ?? 'Añadir nota del área'}
        </button>
    );
}
