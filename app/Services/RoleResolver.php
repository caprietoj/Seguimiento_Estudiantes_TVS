<?php

namespace App\Services;

use App\Models\User;

/**
 * Punto único donde la aplicación decide qué roles tiene un usuario.
 *
 * Hoy los roles los asigna el ADMIN manualmente desde /admin (spatie/laravel-permission).
 * Cuando se conecte la intranet institucional (ver "Integración futura con la intranet" en
 * el README), este servicio podrá sincronizar los roles de spatie a partir de lo que la
 * intranet devuelva (por ejemplo, un claim de grupos en el token), sin que el resto de la
 * aplicación —Policies, controladores, vistas— tenga que cambiar: todos consultan los roles
 * del usuario a través de $user->hasRole(...), nunca de la fuente original.
 */
class RoleResolver
{
    /** @return string[] Los roles (slugs de spatie/laravel-permission) que tiene hoy el usuario. */
    public function rolesFor(User $user): array
    {
        return $user->getRoleNames()->all();
    }

    /**
     * Asigna un conjunto de roles al usuario, reemplazando los que tenía.
     * Hoy la llama solo la administración (creación/edición de usuarios). Cuando exista
     * sincronización con la intranet, también podrá llamarla un listener de login.
     *
     * @param  string[]  $roles
     */
    public function syncRoles(User $user, array $roles): void
    {
        $user->syncRoles($roles);
    }
}
