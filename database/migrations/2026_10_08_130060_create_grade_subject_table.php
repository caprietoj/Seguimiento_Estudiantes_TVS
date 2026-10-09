<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Grados (6 a 11) en los que aplica una asignatura. Una asignatura sin filas aquí
     * aplica a todos los grados (ver App\Models\Subject::appliesToGrade()).
     */
    public function up(): void
    {
        Schema::create('grade_subject', function (Blueprint $table) {
            $table->foreignId('subject_id')->constrained('subjects')->cascadeOnDelete();
            $table->unsignedTinyInteger('grade');
            $table->primary(['subject_id', 'grade']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('grade_subject');
    }
};
