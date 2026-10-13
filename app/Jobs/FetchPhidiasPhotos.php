<?php

namespace App\Jobs;

use App\Enums\ImportBatchStatus;
use App\Models\ImportBatch;
use App\Models\Student;
use App\Support\Import\PhidiasStudentsImporter;
use App\Support\StudentPhoto;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Throwable;

/**
 * Descarga en segundo plano las fotos de Phidias de un lote importado. Solo pone foto a
 * quien todavía no tiene (nunca reemplaza una foto subida a mano) y la guarda como
 * `fotos/{id}-phidias.jpg`, para que la reversión del lote sepa cuáles quitar.
 */
class FetchPhidiasPhotos implements ShouldQueue
{
    use Queueable;

    public int $timeout = 900;

    public function __construct(public int $batchId) {}

    public static function pathFor(int $studentId): string
    {
        return "fotos/{$studentId}-phidias.jpg";
    }

    public function handle(): void
    {
        $batch = ImportBatch::find($this->batchId);

        if ($batch === null || $batch->status !== ImportBatchStatus::Imported) {
            return;
        }

        /** @var array<int|string, string> $photos */
        $photos = $batch->error_report['photos'] ?? [];
        $done = 0;
        $failed = 0;

        foreach ($photos as $studentId => $url) {
            $student = Student::find((int) $studentId);

            if ($student === null || $student->photo_path !== null || ! PhidiasStudentsImporter::isAllowedPhotoUrl($url)) {
                continue;
            }

            try {
                $response = Http::timeout(15)->retry(2, 500, throw: false)->get($url);
                $jpeg = $response->successful() ? StudentPhoto::squareJpeg($response->body()) : null;
            } catch (Throwable) {
                $jpeg = null;
            }

            if ($jpeg === null) {
                $failed++;

                continue;
            }

            Storage::disk('local')->put(self::pathFor($student->id), $jpeg);
            $student->update(['photo_path' => self::pathFor($student->id)]);
            $done++;
        }

        // Si el lote se revirtió mientras se descargaban, se deshace lo recién guardado.
        $batch->refresh();
        if ($batch->status !== ImportBatchStatus::Imported) {
            foreach (array_keys($photos) as $studentId) {
                self::removePhoto((int) $studentId);
            }

            return;
        }

        $batch->update(['error_report' => [
            ...($batch->error_report ?? []),
            'photos_result' => ['ok' => $done, 'failed' => $failed, 'finished_at' => now()->toIso8601String()],
        ]]);
    }

    /** Quita la foto traída de Phidias, si sigue siendo esa (no toca una foto subida a mano). */
    public static function removePhoto(int $studentId): bool
    {
        $path = self::pathFor($studentId);
        $student = Student::withTrashed()->find($studentId);

        if ($student === null || $student->photo_path !== $path) {
            return false;
        }

        Storage::disk('local')->delete($path);
        $student->update(['photo_path' => null]);

        return true;
    }
}
