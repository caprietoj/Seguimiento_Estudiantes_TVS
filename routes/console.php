<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Copia de seguridad diaria de la base de datos (sección 9). La ejecuta el contenedor
// "scheduler" de docker-compose.prod.yml mediante `php artisan schedule:work`.
Schedule::command('app:respaldar-base-datos')
    ->daily()
    ->at('02:00')
    ->timezone(config('app.timezone'))
    ->onOneServer();

// TODO (Fase 5 — asistente de migración, sección 7): programar aquí la limpieza de los
// archivos subidos ya procesados, conservando solo su hash y el reporte de importación.
