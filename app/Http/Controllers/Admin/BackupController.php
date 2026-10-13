<?php

namespace App\Http\Controllers\Admin;

use App\Enums\AuditAction;
use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Support\Backup\BackupService;
use Illuminate\Contracts\Cache\LockTimeoutException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Throwable;

/**
 * Administración · Respaldos: crear, descargar, subir, restaurar y eliminar respaldos de los
 * datos del seguimiento y las fotos (ver App\Support\Backup\BackupService).
 */
class BackupController extends Controller
{
    public function __construct(private BackupService $backups) {}

    public function index(): Response
    {
        return Inertia::render('admin/respaldos', [
            'backups' => $this->backups->list(),
            'tables' => count(BackupService::TABLES),
            'keepDays' => 14,
            'flash' => ['success' => session('success'), 'error' => session('error')],
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        try {
            $backup = $this->withLock(fn () => $this->backups->create('manual', $request->user()));
        } catch (Throwable $e) {
            return back()->with('error', 'No se pudo crear el respaldo: '.$e->getMessage());
        }

        $this->audit($request, AuditAction::Backup, ['backup' => $backup['name']]);

        return back()->with('success', "Respaldo creado: {$backup['name']}.");
    }

    public function upload(Request $request): RedirectResponse
    {
        $request->validate([
            'file' => ['required', 'file', 'extensions:zip', 'max:512000'],
        ], [], ['file' => 'archivo de respaldo']);

        try {
            $manifest = $this->backups->storeUpload((string) $request->file('file')?->getRealPath());
        } catch (Throwable $e) {
            return back()->with('error', $e->getMessage());
        }

        $this->audit($request, AuditAction::Create, ['backup_subido' => $manifest['name']]);

        return back()->with('success', "Respaldo subido ({$manifest['name']}). Ya puedes restaurarlo desde la lista.");
    }

    public function download(string $name): BinaryFileResponse
    {
        $path = BackupService::path($name);
        abort_if($path === null, 404);

        return response()->download($path, $name);
    }

    public function restore(Request $request, string $name): RedirectResponse
    {
        $request->validate(['confirmacion' => ['required', 'in:RESTAURAR']], [
            'confirmacion.in' => 'Escribe RESTAURAR en mayúsculas para confirmar.',
        ]);

        $path = BackupService::path($name);
        abort_if($path === null, 404);

        @set_time_limit(600);

        try {
            $result = $this->withLock(fn () => $this->backups->restore($path, $request->user()));
        } catch (LockTimeoutException) {
            return back()->with('error', 'Hay otro respaldo o restauración en curso. Intenta en unos minutos.');
        } catch (Throwable $e) {
            return back()->with('error', 'No se restauró nada: '.$e->getMessage());
        }

        // Se registra después de restaurar: la tabla de auditoría también viene del respaldo.
        $this->audit($request, AuditAction::Restore, [
            'backup' => $name,
            'respaldo_previo' => $result['safety_backup'],
            'filas' => array_sum($result['tables']),
            'fotos' => $result['photos'],
        ]);

        return back()->with('success', sprintf(
            'Restauración completa: %s registros y %d foto(s). Antes de restaurar se guardó el estado anterior como «%s».',
            number_format(array_sum($result['tables']), 0, ',', '.'),
            $result['photos'],
            $result['safety_backup'],
        ));
    }

    public function destroy(Request $request, string $name): RedirectResponse
    {
        abort_if(BackupService::path($name) === null, 404);

        $this->backups->delete($name);
        $this->audit($request, AuditAction::Delete, ['backup' => $name]);

        return back()->with('success', "Respaldo eliminado: {$name}.");
    }

    /**
     * Un respaldo o restauración a la vez.
     *
     * @template T
     *
     * @param  callable(): T  $callback
     * @return T
     */
    private function withLock(callable $callback): mixed
    {
        return Cache::lock('admin-respaldos', 900)->block(5, $callback);
    }

    /** @param array<string, mixed> $changes */
    private function audit(Request $request, AuditAction $action, array $changes): void
    {
        AuditLog::create([
            'user_id' => $request->user()?->id,
            'action' => $action,
            'auditable_type' => 'backup',
            'auditable_id' => null,
            'changes' => $changes,
            'ip' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'created_at' => now(),
        ]);
    }
}
