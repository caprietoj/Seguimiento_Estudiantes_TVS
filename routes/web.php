<?php

use App\Http\Controllers\Admin\FollowUpController as AdminFollowUpController;
use App\Http\Controllers\Admin\GradeLevelController as AdminGradeLevelController;
use App\Http\Controllers\Admin\GroupController as AdminGroupController;
use App\Http\Controllers\Admin\SchoolYearController;
use App\Http\Controllers\Admin\SectionController as AdminSectionController;
use App\Http\Controllers\Admin\StudentController as AdminStudentController;
use App\Http\Controllers\Admin\SubjectController as AdminSubjectController;
use App\Http\Controllers\CommitmentController;
use App\Http\Controllers\CommitteeDecisionController;
use App\Http\Controllers\CompromisosController;
use App\Http\Controllers\ContributionController;
use App\Http\Controllers\DisciplinaryCaseController;
use App\Http\Controllers\ExternalSupportController;
use App\Http\Controllers\GradeController;
use App\Http\Controllers\GroupMeetingController;
use App\Http\Controllers\PhotoController;
use App\Http\Controllers\ReunionController;
use App\Http\Controllers\StrategyController;
use App\Http\Controllers\StrategyReviewController;
use App\Http\Controllers\StudentController;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::get('/', function () {
    return Inertia::render('welcome');
})->name('home');

Route::middleware(['auth'])->group(function () {
    Route::redirect('dashboard', '/reunion')->name('dashboard');

    Route::get('reunion', [ReunionController::class, 'show'])->name('reunion.index');
    Route::get('reunion/{group}/{followUpId}', [ReunionController::class, 'show'])->name('reunion.show');
    Route::put('reunion/{group}/{followUp}/fecha', [GroupMeetingController::class, 'update'])->name('reunion.fecha.update');

    Route::get('estudiantes', [StudentController::class, 'index'])->name('estudiantes.index');
    Route::get('estudiantes/{student}', [StudentController::class, 'show'])->name('estudiantes.show');
    Route::get('estudiantes/{student}/pdf', [StudentController::class, 'exportPdf'])->name('estudiantes.pdf');
    Route::post('estudiantes/{student}/foto', [PhotoController::class, 'store'])->name('fotos.store');
    Route::get('estudiantes/{student}/foto', [PhotoController::class, 'show'])->name('fotos.show');

    Route::get('compromisos', [CompromisosController::class, 'index'])->name('compromisos.index');
    Route::post('compromisos', [CommitmentController::class, 'store'])->name('compromisos.store');
    Route::patch('compromisos/{commitment}/estado', [CommitmentController::class, 'updateStatus'])->name('compromisos.estado');
    Route::delete('compromisos/{commitment}', [CommitmentController::class, 'destroy'])->name('compromisos.destroy');

    Route::post('aportes', [ContributionController::class, 'store'])->name('aportes.store');
    Route::delete('aportes/{contribution}', [ContributionController::class, 'destroy'])->name('aportes.destroy');

    Route::post('estrategias', [StrategyController::class, 'store'])->name('estrategias.store');
    Route::delete('estrategias/{strategy}', [StrategyController::class, 'destroy'])->name('estrategias.destroy');
    Route::post('estrategias/{strategy}/seguimientos', [StrategyReviewController::class, 'store'])->name('estrategias.seguimientos.store');

    Route::post('apoyos-externos', [ExternalSupportController::class, 'store'])->name('apoyos-externos.store');
    Route::delete('apoyos-externos/{externalSupport}', [ExternalSupportController::class, 'destroy'])->name('apoyos-externos.destroy');

    Route::put('desempeno', [GradeController::class, 'update'])->name('desempeno.update');

    Route::post('comite', [CommitteeDecisionController::class, 'store'])->name('comite.store');

    Route::post('disciplina', [DisciplinaryCaseController::class, 'store'])->name('disciplina.store');
    Route::patch('disciplina/{disciplinaryCase}/estado', [DisciplinaryCaseController::class, 'updateStatus'])->name('disciplina.estado');

    Route::middleware(['can:admin'])->prefix('admin')->name('admin.')->group(function () {
        Route::redirect('/', '/admin/anios');

        Route::get('anios', [SchoolYearController::class, 'index'])->name('anios.index');
        Route::post('anios', [SchoolYearController::class, 'store'])->name('anios.store');
        Route::put('anios/{schoolYear}', [SchoolYearController::class, 'update'])->name('anios.update');
        Route::post('anios/{schoolYear}/activar', [SchoolYearController::class, 'activate'])->name('anios.activar');

        Route::get('seguimientos', [AdminFollowUpController::class, 'index'])->name('seguimientos.index');
        Route::post('seguimientos', [AdminFollowUpController::class, 'store'])->name('seguimientos.store');
        Route::put('seguimientos/{followUp}', [AdminFollowUpController::class, 'update'])->name('seguimientos.update');
        Route::delete('seguimientos/{followUp}', [AdminFollowUpController::class, 'destroy'])->name('seguimientos.destroy');
        Route::post('anios/{schoolYear}/generar-seguimientos', [AdminFollowUpController::class, 'generateDefaults'])->name('seguimientos.generar');

        Route::get('secciones', [AdminSectionController::class, 'index'])->name('secciones.index');
        Route::post('secciones', [AdminSectionController::class, 'store'])->name('secciones.store');
        Route::put('secciones/{section}', [AdminSectionController::class, 'update'])->name('secciones.update');
        Route::delete('secciones/{section}', [AdminSectionController::class, 'destroy'])->name('secciones.destroy');

        Route::get('grados', [AdminGradeLevelController::class, 'index'])->name('grados.index');
        Route::post('grados', [AdminGradeLevelController::class, 'store'])->name('grados.store');
        Route::put('grados/{gradeLevel}', [AdminGradeLevelController::class, 'update'])->name('grados.update');
        Route::delete('grados/{gradeLevel}', [AdminGradeLevelController::class, 'destroy'])->name('grados.destroy');

        Route::get('grupos', [AdminGroupController::class, 'index'])->name('grupos.index');
        Route::post('grupos', [AdminGroupController::class, 'store'])->name('grupos.store');
        Route::put('grupos/{group}', [AdminGroupController::class, 'update'])->name('grupos.update');

        Route::get('estudiantes', [AdminStudentController::class, 'index'])->name('estudiantes.index');
        Route::post('estudiantes', [AdminStudentController::class, 'store'])->name('estudiantes.store');
        Route::put('estudiantes/{student}', [AdminStudentController::class, 'update'])->name('estudiantes.update');
        Route::delete('estudiantes/{student}', [AdminStudentController::class, 'destroy'])->name('estudiantes.destroy');

        Route::get('asignaturas', [AdminSubjectController::class, 'index'])->name('asignaturas.index');
        Route::post('asignaturas', [AdminSubjectController::class, 'store'])->name('asignaturas.store');
        Route::put('asignaturas/{subject}', [AdminSubjectController::class, 'update'])->name('asignaturas.update');
    });
});

require __DIR__.'/settings.php';
require __DIR__.'/auth.php';
