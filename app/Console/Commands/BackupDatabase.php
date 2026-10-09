<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Process;

/**
 * Copia de seguridad diaria de la base de datos (sección 9 del encargo), programada en
 * routes/console.php y ejecutada por el contenedor "scheduler" de docker-compose.prod.yml.
 * Escribe un mysqldump comprimido en storage/app/backups (volumen "backups" en producción)
 * y conserva solo los últimos --keep-days días para no llenar el disco.
 */
class BackupDatabase extends Command
{
    protected $signature = 'app:respaldar-base-datos {--keep-days=14}';

    protected $description = 'Genera un mysqldump de la base de datos y borra los respaldos más antiguos que --keep-days';

    public function handle(): int
    {
        $connection = config('database.default');
        $config = config("database.connections.{$connection}");

        if (($config['driver'] ?? null) !== 'mysql') {
            $this->error("La conexión '{$connection}' no es MySQL; este comando solo respalda MySQL.");

            return self::FAILURE;
        }

        $directory = storage_path('app/backups');
        File::ensureDirectoryExists($directory);

        $path = $directory.DIRECTORY_SEPARATOR.sprintf(
            '%s_%s.sql.gz',
            $config['database'],
            now()->format('Y-m-d_His')
        );

        $this->info("Generando respaldo en {$path}...");

        $dump = Process::timeout(600)
            ->env(['MYSQL_PWD' => $config['password'] ?? ''])
            ->run([
                'mysqldump',
                '--host='.$config['host'],
                '--port='.(string) $config['port'],
                '--user='.$config['username'],
                '--single-transaction',
                '--quick',
                $config['database'],
            ]);

        if (! $dump->successful()) {
            $this->error('mysqldump falló: '.$dump->errorOutput());

            return self::FAILURE;
        }

        $gz = gzopen($path, 'wb9');
        gzwrite($gz, $dump->output());
        gzclose($gz);

        $this->info('Respaldo generado.');

        $this->pruneOldBackups($directory, (int) $this->option('keep-days'));

        return self::SUCCESS;
    }

    private function pruneOldBackups(string $directory, int $keepDays): void
    {
        $cutoff = now()->subDays($keepDays);

        foreach (File::files($directory) as $file) {
            if (now()->createFromTimestamp($file->getMTime())->lt($cutoff)) {
                File::delete($file->getPathname());
            }
        }
    }
}
