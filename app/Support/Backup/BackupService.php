<?php

namespace App\Support\Backup;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;
use ZipArchive;

/**
 * Respaldos de la aplicación: un .zip con
 *
 *  - manifest.json   versión del formato, fecha, tipo, quién lo creó, migraciones y conteo por tabla;
 *  - data/{tabla}.jsonl  una fila por línea, de cada tabla propia del seguimiento;
 *  - fotos/…         las fotos de los estudiantes.
 *
 * Solo incluye las tablas del seguimiento (TABLES). Usuarios, roles, permisos y sesiones vienen
 * de la intranet (tablas compartidas): nunca se respaldan ni se tocan al restaurar.
 *
 * La restauración reemplaza esas tablas dentro de una sola transacción (si algo falla no queda
 * nada a medias) y antes hace automáticamente un respaldo «previo a restauración».
 */
class BackupService
{
    public const FORMAT = 1;

    public const APP = 'seguimiento-estudiantes-tvs';

    /** Tablas propias del seguimiento, de padres a hijos. */
    public const TABLES = [
        'school_years', 'sections', 'grade_levels', 'subjects', 'grade_subject', 'import_batches',
        'groups', 'teaching_assignments', 'students', 'enrollments', 'follow_ups', 'group_meetings',
        'contributions', 'strategies', 'strategy_reviews', 'commitments', 'external_supports',
        'grades', 'committee_decisions', 'disciplinary_cases', 'audit_logs',
    ];

    public const KINDS = [
        'manual' => 'Manual',
        'automatico' => 'Automático',
        'previo-restauracion' => 'Previo a restauración',
        'subido' => 'Subido',
    ];

    private const PHOTOS_DIR = 'fotos';

    private const NAME_PATTERN = '/^[A-Za-z0-9_.-]+\.(zip|sql\.gz)$/';

    public static function directory(): string
    {
        $dir = (string) config('seguimiento.respaldos.directorio', storage_path('app/backups'));
        File::ensureDirectoryExists($dir);

        return $dir;
    }

    /** Ruta absoluta de un respaldo por su nombre, o null si el nombre no es válido o no existe. */
    public static function path(string $name): ?string
    {
        if (! preg_match(self::NAME_PATTERN, $name)) {
            return null;
        }

        $path = self::directory().DIRECTORY_SEPARATOR.$name;

        return is_file($path) ? $path : null;
    }

    /** @return array{name: string, size: int, manifest: array<string, mixed>} */
    public function create(string $kind = 'manual', ?User $by = null): array
    {
        if (! array_key_exists($kind, self::KINDS)) {
            throw new RuntimeException("Tipo de respaldo desconocido: {$kind}");
        }

        $name = sprintf('respaldo_%s_%s.zip', now()->format('Y-m-d_His'), $kind);
        $path = self::directory().DIRECTORY_SEPARATOR.$name;
        $suffix = 1;
        while (is_file($path)) {
            $name = sprintf('respaldo_%s-%d_%s.zip', now()->format('Y-m-d_His'), ++$suffix, $kind);
            $path = self::directory().DIRECTORY_SEPARATOR.$name;
        }

        $tmp = storage_path('app/tmp/backup-'.Str::random(12));
        File::ensureDirectoryExists($tmp.'/data');

        try {
            $tables = [];
            foreach (self::TABLES as $table) {
                if (! Schema::hasTable($table)) {
                    continue;
                }

                $file = fopen("{$tmp}/data/{$table}.jsonl", 'wb');
                $rows = 0;
                foreach (DB::table($table)->cursor() as $row) {
                    fwrite($file, json_encode((array) $row, JSON_UNESCAPED_UNICODE | JSON_PRESERVE_ZERO_FRACTION | JSON_THROW_ON_ERROR)."\n");
                    $rows++;
                }
                fclose($file);

                $tables[$table] = ['rows' => $rows, 'columns' => Schema::getColumnListing($table)];
            }

            $photos = Storage::disk('local')->allFiles(self::PHOTOS_DIR);

            $manifest = [
                'app' => self::APP,
                'format' => self::FORMAT,
                'kind' => $kind,
                'created_at' => now()->toIso8601String(),
                'created_by' => $by?->name,
                'migrations' => DB::table('migrations')->orderBy('id')->pluck('migration')->all(),
                'tables' => $tables,
                'photos' => count($photos),
            ];

            $zip = new ZipArchive;
            if ($zip->open($path, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
                throw new RuntimeException('No se pudo crear el archivo de respaldo.');
            }
            $zip->addFromString('manifest.json', json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR));
            foreach (array_keys($tables) as $table) {
                $zip->addFile("{$tmp}/data/{$table}.jsonl", "data/{$table}.jsonl");
            }
            foreach ($photos as $photo) {
                $zip->addFile(Storage::disk('local')->path($photo), $photo);
            }
            $zip->close();
        } finally {
            File::deleteDirectory($tmp);
        }

        return ['name' => $name, 'size' => (int) filesize($path), 'manifest' => $manifest];
    }

    /**
     * Respaldos disponibles, del más reciente al más antiguo.
     *
     * @return list<array{name: string, size: int, created_at: string, kind: string, kind_label: string, created_by: ?string, rows: int, students: int, photos: int, restorable: bool, problem: ?string}>
     */
    public function list(): array
    {
        $items = [];

        foreach (File::files(self::directory()) as $file) {
            $name = $file->getFilename();
            if (! preg_match(self::NAME_PATTERN, $name)) {
                continue;
            }

            $item = [
                'name' => $name,
                'size' => $file->getSize(),
                'created_at' => date(DATE_ATOM, $file->getMTime()),
                'kind' => 'legado',
                'kind_label' => 'Base de datos completa (formato anterior)',
                'created_by' => null,
                'rows' => 0,
                'students' => 0,
                'photos' => 0,
                'restorable' => false,
                'problem' => null,
            ];

            if (str_ends_with($name, '.zip')) {
                try {
                    $manifest = $this->readManifest($file->getPathname());
                    $item['created_at'] = (string) $manifest['created_at'];
                    $item['kind'] = (string) $manifest['kind'];
                    $item['kind_label'] = self::KINDS[$manifest['kind']] ?? (string) $manifest['kind'];
                    $item['created_by'] = $manifest['created_by'] ?? null;
                    $item['rows'] = array_sum(array_map(fn ($t) => (int) ($t['rows'] ?? 0), (array) $manifest['tables']));
                    $item['students'] = (int) ($manifest['tables']['students']['rows'] ?? 0);
                    $item['photos'] = (int) ($manifest['photos'] ?? 0);
                    $item['problem'] = $this->compatibilityProblem($manifest);
                    $item['restorable'] = $item['problem'] === null;
                } catch (Throwable $e) {
                    $item['kind_label'] = 'Archivo dañado';
                    $item['problem'] = $e->getMessage();
                }
            } else {
                $item['problem'] = 'Respaldo completo de MySQL del formato anterior: se puede descargar, pero se restaura con mysql desde el servidor.';
            }

            $items[] = $item;
        }

        usort($items, fn ($a, $b) => strcmp($b['created_at'], $a['created_at']));

        return $items;
    }

    /**
     * Valida un .zip subido y lo guarda junto a los demás respaldos.
     *
     * @return array<string, mixed> manifiesto
     */
    public function storeUpload(string $uploadedPath): array
    {
        $manifest = $this->readManifest($uploadedPath);

        $name = sprintf('respaldo_%s_subido.zip', now()->format('Y-m-d_His'));
        File::copy($uploadedPath, self::directory().DIRECTORY_SEPARATOR.$name);

        return $manifest + ['name' => $name];
    }

    /**
     * Reemplaza los datos del seguimiento y las fotos por los del respaldo.
     *
     * @return array{tables: array<string, int>, photos: int, safety_backup: string}
     */
    public function restore(string $zipPath, ?User $by = null): array
    {
        $manifest = $this->readManifest($zipPath);

        if (($problem = $this->compatibilityProblem($manifest)) !== null) {
            throw new RuntimeException($problem);
        }

        $zip = new ZipArchive;
        if ($zip->open($zipPath) !== true) {
            throw new RuntimeException('No se pudo abrir el respaldo.');
        }

        $tmp = storage_path('app/tmp/restore-'.Str::random(12));
        File::ensureDirectoryExists($tmp);

        try {
            $this->extractSafely($zip, $tmp);
            $zip->close();

            // Red de seguridad: si algo sale mal (o se restauró el respaldo equivocado), se puede volver.
            $safety = $this->create('previo-restauracion', $by);

            $restored = $this->restoreTables($tmp, $manifest);
            $photos = $this->restorePhotos($tmp);
        } finally {
            File::deleteDirectory($tmp);
        }

        return ['tables' => $restored, 'photos' => $photos, 'safety_backup' => $safety['name']];
    }

    public function delete(string $name): bool
    {
        $path = self::path($name);

        return $path !== null && File::delete($path);
    }

    /**
     * Borra los respaldos automáticos (y los del formato anterior) de más de $days días.
     * Los manuales, subidos y previos a restauración no se borran solos.
     */
    public function prune(int $days): int
    {
        $cutoff = now()->subDays($days)->getTimestamp();
        $deleted = 0;

        foreach (File::files(self::directory()) as $file) {
            $name = $file->getFilename();
            $automatic = str_ends_with($name, '_automatico.zip') || str_ends_with($name, '.sql.gz');

            if ($automatic && $file->getMTime() < $cutoff && File::delete($file->getPathname())) {
                $deleted++;
            }
        }

        return $deleted;
    }

    /** @return array<string, mixed> */
    public function readManifest(string $zipPath): array
    {
        $zip = new ZipArchive;
        if ($zip->open($zipPath, ZipArchive::RDONLY) !== true) {
            throw new RuntimeException('El archivo no es un .zip válido.');
        }

        $json = $zip->getFromName('manifest.json');
        $zip->close();

        $manifest = is_string($json) ? json_decode($json, true) : null;

        if (! is_array($manifest) || ($manifest['app'] ?? null) !== self::APP) {
            throw new RuntimeException('El archivo no es un respaldo de esta aplicación (falta manifest.json).');
        }

        if ((int) ($manifest['format'] ?? 0) > self::FORMAT) {
            throw new RuntimeException('El respaldo es de una versión más nueva de la aplicación.');
        }

        if (! is_array($manifest['tables'] ?? null) || ! is_array($manifest['migrations'] ?? null)) {
            throw new RuntimeException('El manifiesto del respaldo está incompleto.');
        }

        return $manifest;
    }

    /**
     * Motivo por el que el respaldo no se puede restaurar sobre la base actual, o null si se puede.
     *
     * @param  array<string, mixed>  $manifest
     */
    public function compatibilityProblem(array $manifest): ?string
    {
        $current = DB::table('migrations')->pluck('migration')->all();
        $missing = array_diff($manifest['migrations'], $current);

        if ($missing !== []) {
            return 'El respaldo se hizo con una versión más nueva de la aplicación (tiene cambios de base de datos que esta instalación no tiene). Actualiza la aplicación antes de restaurarlo.';
        }

        foreach ($manifest['tables'] as $table => $info) {
            if (! in_array($table, self::TABLES, true)) {
                return "El respaldo incluye la tabla «{$table}», que no es del seguimiento.";
            }

            $columns = Schema::getColumnListing($table);
            $extra = array_diff($info['columns'] ?? [], $columns);

            if ($extra !== []) {
                return "La tabla «{$table}» del respaldo tiene columnas que ya no existen: ".implode(', ', $extra).'.';
            }
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $manifest
     * @return array<string, int>
     */
    private function restoreTables(string $dir, array $manifest): array
    {
        $restored = [];

        // En SQLite las llaves foráneas no se pueden apagar dentro de una transacción.
        Schema::disableForeignKeyConstraints();

        try {
            DB::transaction(function () use ($dir, $manifest, &$restored) {
                foreach (array_reverse(self::TABLES) as $table) {
                    if (Schema::hasTable($table)) {
                        DB::table($table)->delete();
                    }
                }

                foreach (self::TABLES as $table) {
                    if (! isset($manifest['tables'][$table])) {
                        continue;
                    }

                    $file = "{$dir}/data/{$table}.jsonl";
                    if (! is_file($file)) {
                        throw new RuntimeException("Al respaldo le falta el archivo de la tabla «{$table}».");
                    }

                    $handle = fopen($file, 'rb');
                    $batch = [];
                    $count = 0;

                    while (($line = fgets($handle)) !== false) {
                        if (trim($line) === '') {
                            continue;
                        }
                        $batch[] = json_decode($line, true, 512, JSON_THROW_ON_ERROR);
                        if (count($batch) === 200) {
                            DB::table($table)->insert($batch);
                            $count += count($batch);
                            $batch = [];
                        }
                    }
                    fclose($handle);

                    if ($batch !== []) {
                        DB::table($table)->insert($batch);
                        $count += count($batch);
                    }

                    $expected = (int) ($manifest['tables'][$table]['rows'] ?? $count);
                    if ($count !== $expected) {
                        throw new RuntimeException("La tabla «{$table}» trae {$count} filas y el manifiesto dice {$expected}: el respaldo está incompleto.");
                    }

                    $restored[$table] = $count;
                }
            });
        } finally {
            Schema::enableForeignKeyConstraints();
        }

        return $restored;
    }

    /**
     * Extrae solo lo que un respaldo debe traer (data/{tabla}.jsonl y fotos/*.jpg|png), sin
     * rutas relativas: un .zip manipulado no puede escribir fuera de la carpeta temporal.
     */
    private function extractSafely(ZipArchive $zip, string $dir): void
    {
        $tables = implode('|', array_map(fn (string $t) => preg_quote($t, '/'), self::TABLES));

        for ($i = 0; $i < $zip->numFiles; $i++) {
            $entry = (string) $zip->getNameIndex($i);

            $isData = preg_match('/^data\/('.$tables.')\.jsonl$/', $entry) === 1;
            $isPhoto = preg_match('/^'.self::PHOTOS_DIR.'\/[A-Za-z0-9_-]+\.(jpe?g|png|webp)$/i', $entry) === 1;

            if (! $isData && ! $isPhoto) {
                continue;
            }

            $target = $dir.DIRECTORY_SEPARATOR.$entry;
            File::ensureDirectoryExists(dirname($target));

            $in = $zip->getStream($entry);
            if ($in === false) {
                throw new RuntimeException("No se pudo leer «{$entry}» del respaldo.");
            }
            $out = fopen($target, 'wb');
            stream_copy_to_stream($in, $out);
            fclose($in);
            fclose($out);
        }
    }

    private function restorePhotos(string $dir): int
    {
        $source = $dir.DIRECTORY_SEPARATOR.self::PHOTOS_DIR;
        $disk = Storage::disk('local');

        $disk->deleteDirectory(self::PHOTOS_DIR);
        $disk->makeDirectory(self::PHOTOS_DIR);

        if (! is_dir($source)) {
            return 0;
        }

        $count = 0;
        foreach (File::allFiles($source) as $file) {
            $disk->put(self::PHOTOS_DIR.'/'.$file->getRelativePathname(), $file->getContents());
            $count++;
        }

        return $count;
    }
}
