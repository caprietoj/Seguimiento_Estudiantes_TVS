<?php

namespace App\Http\Controllers;

use App\Models\Student;
use App\Support\Scope;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Storage;

/**
 * Foto del estudiante (sección 10): solo imágenes, máximo 5 MB, recortada a cuadrado y
 * comprimida en el servidor, servida solo a quien puede ver al estudiante mediante esta
 * ruta autenticada (nunca expuesta como archivo público).
 */
class PhotoController extends Controller
{
    private const SIZE = 480;

    public function store(Request $request, Student $student): RedirectResponse
    {
        $group = $student->currentGroup();
        abort_if($group === null, 422, 'El estudiante no tiene matrícula vigente.');

        if (! Scope::photoWrite($request->user(), $group)) {
            abort(403);
        }

        $request->validate([
            'photo' => ['required', 'image', 'max:5120'],
        ]);

        $path = "fotos/{$student->id}.jpg";
        Storage::disk('local')->put($path, $this->cropToSquareJpeg($request->file('photo')->getRealPath()));

        $student->update(['photo_path' => $path]);

        return back();
    }

    public function show(Request $request, Student $student): Response
    {
        $group = $student->currentGroup();
        abort_if($group === null, 404);

        if (! Scope::inScope($request->user(), $group)) {
            abort(403);
        }

        abort_unless($student->photo_path && Storage::disk('local')->exists($student->photo_path), 404);

        return response(Storage::disk('local')->get($student->photo_path), 200)
            ->header('Content-Type', 'image/jpeg')
            ->header('Cache-Control', 'private, max-age=3600');
    }

    private function cropToSquareJpeg(string $sourcePath): string
    {
        $image = imagecreatefromstring(file_get_contents($sourcePath));
        abort_if($image === false, 422, 'No se pudo leer la imagen.');

        $width = imagesx($image);
        $height = imagesy($image);
        $side = min($width, $height);
        $srcX = (int) (($width - $side) / 2);
        $srcY = (int) (($height - $side) / 2);

        $square = imagecreatetruecolor(self::SIZE, self::SIZE);
        imagecopyresampled($square, $image, 0, 0, $srcX, $srcY, self::SIZE, self::SIZE, $side, $side);

        ob_start();
        imagejpeg($square, null, 82);
        $contents = ob_get_clean();

        imagedestroy($image);
        imagedestroy($square);

        return $contents;
    }
}
