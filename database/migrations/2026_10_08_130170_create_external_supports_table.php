<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('external_supports', function (Blueprint $table) {
            $table->id();
            $table->foreignId('student_id')->constrained('students')->cascadeOnDelete();
            $table->string('provider');
            $table->string('specialty');
            $table->string('frequency')->nullable();
            $table->string('contact')->nullable();
            $table->text('notes')->nullable();
            $table->foreignId('author_id')->constrained('users')->restrictOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->foreignId('import_batch_id')->nullable()->constrained('import_batches')->nullOnDelete();
            $table->string('source_file')->nullable();
            $table->unsignedInteger('source_row')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('external_supports');
    }
};
