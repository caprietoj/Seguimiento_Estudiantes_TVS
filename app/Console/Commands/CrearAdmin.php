<?php

namespace App\Console\Commands;

use App\Enums\RoleName;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

/**
 * Crea el primer usuario ADMIN de una instalación nueva (ver README, "Despliegue").
 * No depende de que ya exista ningún otro usuario, para poder correrla justo después
 * de `docker compose up` en un servidor limpio.
 */
class CrearAdmin extends Command
{
    protected $signature = 'app:crear-admin {email} {--nombre=Administrador}';

    protected $description = 'Crea (o re-habilita) un usuario ADMIN y muestra su contraseña temporal una sola vez';

    public function handle(): int
    {
        $email = $this->argument('email');

        $validator = Validator::make(['email' => $email], ['email' => ['required', 'email']]);
        if ($validator->fails()) {
            $this->error($validator->errors()->first('email'));

            return self::FAILURE;
        }

        $password = Str::password(16);

        $user = User::updateOrCreate(
            ['email' => $email],
            [
                'name' => $this->option('nombre'),
                'password' => Hash::make($password),
                'active' => true,
                'must_change_password' => true,
                'login_method' => 'local',
            ]
        );

        $user->syncRoles([RoleName::Admin->value]);

        $this->newLine();
        $this->info('Usuario ADMIN listo. Entrega esta contraseña temporal; no volverá a mostrarse:');
        $this->newLine();
        $this->line("  Correo:      {$user->email}");
        $this->line("  Contraseña:  {$password}");
        $this->newLine();
        $this->comment('Se le pedirá cambiarla en el primer ingreso.');

        return self::SUCCESS;
    }
}
