<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('grade_levels', function (Blueprint $table) {
            $table->id();
            $table->unsignedTinyInteger('value')->unique(); // 0 = Preescolar
            $table->string('name');
            $table->boolean('active')->default(true);
            $table->timestamps();
        });

        // Catálogo base: Preescolar a 11.°, disponible en toda instalación.
        $now = now();
        foreach ([0 => 'Preescolar', 1 => '1.°', 2 => '2.°', 3 => '3.°', 4 => '4.°', 5 => '5.°', 6 => '6.°', 7 => '7.°', 8 => '8.°', 9 => '9.°', 10 => '10.°', 11 => '11.°'] as $value => $name) {
            DB::table('grade_levels')->insert([
                'value' => $value,
                'name' => $name,
                'active' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('grade_levels');
    }
};
