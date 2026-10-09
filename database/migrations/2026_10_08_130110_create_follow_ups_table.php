<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('follow_ups', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_year_id')->constrained('school_years')->restrictOnDelete();
            $table->unsignedTinyInteger('period');
            $table->unsignedTinyInteger('number');
            $table->timestamps();

            $table->unique(['school_year_id', 'period', 'number']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('follow_ups');
    }
};
