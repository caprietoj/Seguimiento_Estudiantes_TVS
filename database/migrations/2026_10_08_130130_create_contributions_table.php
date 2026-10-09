<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('contributions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('student_id')->constrained('students')->cascadeOnDelete();
            $table->foreignId('follow_up_id')->constrained('follow_ups')->restrictOnDelete();
            $table->string('field');
            $table->text('body');
            $table->foreignId('author_id')->constrained('users')->restrictOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->foreignId('import_batch_id')->nullable()->constrained('import_batches')->nullOnDelete();
            $table->string('source_file')->nullable();
            $table->unsignedInteger('source_row')->nullable();

            $table->index(['student_id', 'follow_up_id', 'field']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('contributions');
    }
};
