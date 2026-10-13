<?php

namespace App\Providers;

use App\Enums\RoleName;
use App\Models\User;
use App\Support\Scope;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Administración (sección 6.5): toda la configuración (años, grupos, asignaturas,
        // usuarios) es exclusiva del rol admin.
        Gate::define('admin', fn (User $user) => $user->hasRole(RoleName::Admin->value));

        // La caché de asignaciones docentes de Scope es por request: se limpia al
        // terminar, para no servir datos obsoletos en procesos persistentes (Octane)
        // ni entre pruebas.
        $this->app->terminating(fn () => Scope::flush());
    }
}
