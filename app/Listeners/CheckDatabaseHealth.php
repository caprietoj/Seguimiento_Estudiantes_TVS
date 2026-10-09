<?php

namespace App\Listeners;

use Illuminate\Foundation\Events\DiagnosingHealth;
use Illuminate\Support\Facades\DB;

/**
 * Extiende la ruta /up de Laravel (bootstrap/app.php) para que el HEALTHCHECK de Docker
 * (sección 9 del encargo) falle también si la aplicación no puede conectarse a MySQL,
 * y no solo si el proceso de PHP sigue vivo.
 */
class CheckDatabaseHealth
{
    public function handle(DiagnosingHealth $event): void
    {
        DB::connection()->getPdo();
    }
}
