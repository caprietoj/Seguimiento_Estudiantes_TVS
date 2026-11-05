<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('settings')) {
            Schema::create('settings', function (Blueprint $table) {
                $table->id();
                $table->string('key')->unique();
                $table->text('value')->nullable();
                $table->timestamps();
            });
        }

        Schema::table('map_scores', function (Blueprint $table) {
            if (! Schema::hasColumn('map_scores', 'import_batch_id')) {
                $table->foreignId('import_batch_id')->nullable()->constrained('import_batches')->nullOnDelete();
                $table->string('source_file')->nullable();
                $table->unsignedInteger('source_row')->nullable();
            }
        });
    }

    public function down(): void
    {
        Schema::table('map_scores', function (Blueprint $table) {
            if (Schema::hasColumn('map_scores', 'import_batch_id')) {
                $table->dropForeign(['import_batch_id']);
                $table->dropColumn(['import_batch_id', 'source_file', 'source_row']);
            }
        });

        Schema::dropIfExists('settings');
    }
};
