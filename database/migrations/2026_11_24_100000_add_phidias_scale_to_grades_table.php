<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Calificaciones de Phidias en su propia escala: `score` es el promedio de los criterios o
 * indicadores del trimestre (Preescolar/Primaria 1–5, PAI 1–8, DP 1–7), `scale_max` el tope de
 * esa escala y `performance` el desempeño (Bajo, Básico, Alto, Superior). `level` (1–7) sigue
 * para las notas que se registran a mano o con la plantilla de calificaciones IB.
 *
 * `student_subjects` guarda de qué importación salió cada asignatura del estudiante, para
 * poder revertirla.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('grades', function (Blueprint $table) {
            $table->decimal('score', 5, 2)->nullable()->after('level');
            $table->unsignedTinyInteger('scale_max')->nullable()->after('score');
            $table->string('performance', 30)->nullable()->after('scale_max');
        });

        Schema::table('student_subjects', function (Blueprint $table) {
            $table->foreignId('import_batch_id')->nullable()->after('school_year_id')->constrained('import_batches')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('student_subjects', function (Blueprint $table) {
            $table->dropConstrainedForeignId('import_batch_id');
        });

        Schema::table('grades', function (Blueprint $table) {
            $table->dropColumn(['score', 'scale_max', 'performance']);
        });
    }
};
