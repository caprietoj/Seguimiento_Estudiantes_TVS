<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('map_scores', function (Blueprint $table) {
            $table->id();
            $table->foreignId('student_id')->constrained('students')->cascadeOnDelete();
            $table->foreignId('school_year_id')->constrained('school_years')->restrictOnDelete();
            $table->string('subject', 50);
            $table->unsignedTinyInteger('period');
            $table->unsignedSmallInteger('rit_score')->nullable();
            $table->unsignedTinyInteger('percentile')->nullable();
            $table->string('performance_level', 30)->nullable();
            $table->foreignId('recorded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['student_id', 'school_year_id', 'subject', 'period'], 'map_scores_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('map_scores');
    }
};
