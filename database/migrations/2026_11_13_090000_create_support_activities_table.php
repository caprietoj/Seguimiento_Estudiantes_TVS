<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Registro de actividades del Departamento de Apoyo (casos en seguimiento, familias atendidas,
 * reuniones de seguimiento, PIAR, intervenciones en crisis…). Alimenta la estadística general.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('support_activities', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_year_id')->constrained('school_years')->restrictOnDelete();
            $table->string('area', 30);
            $table->string('type', 40);
            $table->foreignId('group_id')->nullable()->constrained('groups')->nullOnDelete();
            $table->foreignId('student_id')->nullable()->constrained('students')->cascadeOnDelete();
            $table->date('occurred_on')->nullable();
            // 1 = I semestre (jul–dic), 2 = II semestre (ene–jun). Se deduce de la fecha si no se indica.
            $table->unsignedTinyInteger('semester')->nullable();
            $table->unsignedSmallInteger('quantity')->default(1);
            $table->text('description')->nullable();
            $table->foreignId('author_id')->constrained('users')->restrictOnDelete();
            $table->timestamps();
            $table->foreignId('import_batch_id')->nullable()->constrained('import_batches')->nullOnDelete();
            $table->string('source_file')->nullable();
            $table->unsignedInteger('source_row')->nullable();

            $table->index(['school_year_id', 'type', 'area']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('support_activities');
    }
};
