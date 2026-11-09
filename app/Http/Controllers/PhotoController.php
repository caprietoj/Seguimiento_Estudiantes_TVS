<?php

namespace App\Http\Controllers;

use App\Models\SchoolYear;
use App\Models\Student;
use App\Support\Scope;
use App\Support\StudentPhoto;
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
    public function store(Request $request, Student $student): RedirectResponse
    {
        $yearId = $request->integer('anio');
        $year = $yearId > 0 ? SchoolYear::find($yearId) : null;

        $group = $student->enrollmentFor($year)?->group;

        $group ??= $student->enrollments()->with('group')->get()
            ->pluck('group')
            ->filter()
            ->first(fn ($candidate) => Scope::photoWrite($request->user(), $candidate));

        abort_if($group === null, 422, 'El estudiante no tiene matrícula vigente.');

        if (! Scope::photoWrite($request->user(), $group)) {
            abort(403);
        }

        $request->validate([
            'photo' => ['required', 'image', 'max:5120'],
        ]);

        $path = "fotos/{$student->id}.jpg";
        $jpeg = StudentPhoto::squareJpeg((string) file_get_contents($request->file('photo')->getRealPath()));
        abort_if($jpeg === null, 422, 'No se pudo leer la imagen.');
        Storage::disk('local')->put($path, $jpeg);

        $student->update(['photo_path' => $path]);
        Storage::disk('local')->delete(StudentPhoto::thumbPath($student->id));

        return back();
    }

    public function show(Request $request, Student $student): Response
    {
        if (! Scope::canViewStudent($request->user(), $student)) {
            abort(403);
        }

        abort_unless($student->photo_path && Storage::disk('local')->exists($student->photo_path), 404);

        return response(Storage::disk('local')->get($student->photo_path), 200)
            ->header('Content-Type', 'image/jpeg')
            ->header('Cache-Control', 'private, max-age=3600');
    }

    public function thumb(Request $request, Student $student): Response
    {
        if (! Scope::canViewStudent($request->user(), $student)) {
            abort(403);
        }

        abort_unless($student->photo_path && Storage::disk('local')->exists($student->photo_path), 404);

        $thumbPath = StudentPhoto::thumbPath($student->id);
        $sourceModified = Storage::disk('local')->lastModified($student->photo_path);

        if (! Storage::disk('local')->exists($thumbPath)
            || Storage::disk('local')->lastModified($thumbPath) < $sourceModified) {
            $jpeg = StudentPhoto::squareJpeg(
                Storage::disk('local')->get($student->photo_path),
                StudentPhoto::THUMB_SIZE,
            );
            abort_if($jpeg === null, 422, 'No se pudo leer la imagen.');
            Storage::disk('local')->put($thumbPath, $jpeg);
        }

        return response(Storage::disk('local')->get($thumbPath), 200)
            ->header('Content-Type', 'image/jpeg')
            ->header('Cache-Control', 'private, max-age=86400')
            ->header('Last-Modified', gmdate('D, d M Y H:i:s', Storage::disk('local')->lastModified($thumbPath)).' GMT');
    }
}
