import { Head, useForm } from '@inertiajs/react';
import { Eye, EyeOff, GraduationCap, LoaderCircle, ShieldCheck, Users } from 'lucide-react';
import { FormEventHandler, useState } from 'react';

import InputError from '@/components/input-error';
import TextLink from '@/components/text-link';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';

interface LoginForm {
    email: string;
    password: string;
    remember: boolean;
}

interface LoginProps {
    status?: string;
    canResetPassword: boolean;
}

export default function Login({ status, canResetPassword }: LoginProps) {
    const [showPassword, setShowPassword] = useState(false);

    const { data, setData, post, processing, errors, reset } = useForm<LoginForm>({
        email: '',
        password: '',
        remember: false,
    });

    const submit: FormEventHandler = (e) => {
        e.preventDefault();
        post(route('login'), {
            onFinish: () => reset('password'),
        });
    };

    return (
        <>
            <Head title="Iniciar sesión" />

            <div className="grid min-h-svh lg:grid-cols-2">
                {/* Panel institucional (se oculta en móvil) */}
                <aside className="relative hidden flex-col justify-between overflow-hidden p-10 text-white lg:flex">
                    <div className="absolute inset-0 bg-gradient-to-br from-[#364E76] via-[#2c3f61] to-[#1a2036]" />
                    <div className="absolute -top-24 -right-24 size-72 rounded-full bg-[#ED3236]/20 blur-3xl" />
                    <div className="absolute -bottom-28 -left-24 size-72 rounded-full bg-white/10 blur-3xl" />

                    <div className="relative z-10 flex items-center gap-3">
                        <span className="flex size-11 items-center justify-center rounded-xl bg-white/15 backdrop-blur">
                            <GraduationCap className="size-6" />
                        </span>
                        <div className="leading-tight">
                            <p className="text-sm font-semibold tracking-wide">The Victoria School</p>
                            <p className="text-xs text-white/70">Portal Institucional</p>
                        </div>
                    </div>

                    <div className="relative z-10 space-y-6">
                        <h1 className="text-3xl leading-tight font-semibold">Seguimiento de Estudiantes</h1>
                        <p className="max-w-md text-sm text-white/75">
                            Acompañamiento integral y acuerdos por estudiante a lo largo del año escolar. Continuo IB: PEP, PAI y DP.
                        </p>
                        <ul className="space-y-3 text-sm text-white/85">
                            <li className="flex items-center gap-3">
                                <ShieldCheck className="size-4 text-[#ED3236]" />
                                Acceso seguro con roles institucionales
                            </li>
                            <li className="flex items-center gap-3">
                                <Users className="size-4 text-[#ED3236]" />
                                Reunión de grupo y trabajo colaborativo
                            </li>
                        </ul>
                    </div>

                    <p className="relative z-10 text-xs text-white/50">
                        © {new Date().getFullYear()} The Victoria School · Todos los derechos reservados
                    </p>
                </aside>

                {/* Formulario de acceso */}
                <main className="bg-background flex items-center justify-center p-6 md:p-10">
                    <div className="w-full max-w-sm space-y-8">
                        <div className="flex flex-col items-center gap-3 text-center lg:items-start lg:text-left">
                            <span className="flex size-12 items-center justify-center rounded-xl bg-[#364E76] text-white lg:hidden">
                                <GraduationCap className="size-6" />
                            </span>
                            <div className="space-y-1">
                                <h2 className="text-2xl font-semibold tracking-tight">Iniciar sesión</h2>
                                <p className="text-muted-foreground text-sm">Ingresa con tu correo institucional para continuar.</p>
                            </div>
                        </div>

                        {status && (
                            <div className="rounded-md border border-green-200 bg-green-50 px-4 py-3 text-sm font-medium text-green-700">
                                {status}
                            </div>
                        )}

                        <form className="space-y-5" onSubmit={submit}>
                            <div className="grid gap-2">
                                <Label htmlFor="email">Correo institucional</Label>
                                <Input
                                    id="email"
                                    type="email"
                                    required
                                    autoFocus
                                    autoComplete="email"
                                    value={data.email}
                                    onChange={(e) => setData('email', e.target.value)}
                                    placeholder="usuario@tvs.edu.co"
                                />
                                <InputError message={errors.email} />
                            </div>

                            <div className="grid gap-2">
                                <div className="flex items-center">
                                    <Label htmlFor="password">Contraseña</Label>
                                    {canResetPassword && (
                                        <TextLink href={route('password.request')} className="ml-auto text-sm" tabIndex={4}>
                                            ¿Olvidaste tu contraseña?
                                        </TextLink>
                                    )}
                                </div>
                                <div className="relative">
                                    <Input
                                        id="password"
                                        type={showPassword ? 'text' : 'password'}
                                        required
                                        autoComplete="current-password"
                                        value={data.password}
                                        onChange={(e) => setData('password', e.target.value)}
                                        placeholder="••••••••"
                                        className="pr-10"
                                    />
                                    <button
                                        type="button"
                                        onClick={() => setShowPassword((value) => !value)}
                                        className="text-muted-foreground hover:text-foreground absolute inset-y-0 right-0 flex items-center px-3"
                                        tabIndex={-1}
                                        aria-label={showPassword ? 'Ocultar contraseña' : 'Mostrar contraseña'}
                                    >
                                        {showPassword ? <EyeOff className="size-4" /> : <Eye className="size-4" />}
                                    </button>
                                </div>
                                <InputError message={errors.password} />
                            </div>

                            <div className="flex items-center space-x-3">
                                <Checkbox
                                    id="remember"
                                    name="remember"
                                    checked={data.remember}
                                    onCheckedChange={(checked) => setData('remember', checked === true)}
                                />
                                <Label htmlFor="remember">Recordarme</Label>
                            </div>

                            <Button
                                type="submit"
                                className="w-full bg-[#364E76] text-white hover:bg-[#2c3f61] focus-visible:ring-[#364E76]"
                                disabled={processing}
                                tabIndex={3}
                            >
                                {processing && <LoaderCircle className="size-4 animate-spin" />}
                                Ingresar
                            </Button>
                        </form>

                        <p className="text-muted-foreground text-center text-xs">Acceso exclusivo para personal de The Victoria School.</p>
                    </div>
                </main>
            </div>
        </>
    );
}
