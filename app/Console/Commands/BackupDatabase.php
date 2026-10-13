<?php

namespace App\Console\Commands;

use App\Support\Backup\BackupService;
use Illuminate\Console\Command;
use Throwable;

/**
 * Copia de seguridad diaria (sección 9 del encargo), programada en routes/console.php y
 * ejecutada por el contenedor "scheduler" de docker-compose.prod.yml. Genera el mismo .zip
 * que el botón de Administración · Respaldos (datos del seguimiento + fotos), así que los
 * respaldos automáticos también se pueden restaurar desde la pantalla. Conserva solo los
 * automáticos de los últimos --keep-days días.
 */
class BackupDatabase extends Command
{
    protected $signature = 'app:respaldar-base-datos {--keep-days=14}';

    protected $description = 'Genera un respaldo de los datos del seguimiento y las fotos, y borra los automáticos más antiguos que --keep-days';

    public function handle(BackupService $backups): int
    {
        try {
            $backup = $backups->create('automatico');
        } catch (Throwable $e) {
            $this->error('No se pudo generar el respaldo: '.$e->getMessage());

            return self::FAILURE;
        }

        $this->info("Respaldo generado: {$backup['name']} (".number_format($backup['size'] / 1024, 0, ',', '.').' KB).');

        $deleted = $backups->prune((int) $this->option('keep-days'));
        if ($deleted > 0) {
            $this->info("Se borraron {$deleted} respaldo(s) automático(s) antiguo(s).");
        }

        return self::SUCCESS;
    }
}
