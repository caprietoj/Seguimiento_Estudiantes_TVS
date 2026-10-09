<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('group_meetings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('group_id')->constrained('groups')->cascadeOnDelete();
            $table->foreignId('follow_up_id')->constrained('follow_ups')->cascadeOnDelete();
            $table->date('held_on')->nullable();
            $table->timestamps();

            $table->unique(['group_id', 'follow_up_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('group_meetings');
    }
};
