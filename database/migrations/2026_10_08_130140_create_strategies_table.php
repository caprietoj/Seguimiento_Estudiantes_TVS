<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('strategies', function (Blueprint $table) {
            $table->id();
            $table->string('type');
            $table->foreignId('student_id')->nullable()->constrained('students')->cascadeOnDelete();
            $table->foreignId('group_id')->nullable()->constrained('groups')->cascadeOnDelete();
            $table->foreignId('follow_up_id')->constrained('follow_ups')->restrictOnDelete();
            $table->text('body');
            $table->string('responsible')->nullable();
            $table->foreignId('author_id')->constrained('users')->restrictOnDelete();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();

            $table->foreignId('import_batch_id')->nullable()->constrained('import_batches')->nullOnDelete();
            $table->string('source_file')->nullable();
            $table->unsignedInteger('source_row')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('strategies');
    }
};
