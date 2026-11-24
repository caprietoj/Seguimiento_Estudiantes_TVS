<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Código interno de Phidias (columna «Code» de sus exportaciones de calificaciones, p. ej.
 * 2012353). Es distinto del código institucional que ya usa la herramienta (el documento de
 * identidad, p. ej. 1019905979): se guarda aparte para no mezclar los dos.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('students', function (Blueprint $table) {
            $table->string('phidias_code', 30)->nullable()->unique()->after('institutional_code');
        });
    }

    public function down(): void
    {
        Schema::table('students', function (Blueprint $table) {
            $table->dropUnique(['phidias_code']);
            $table->dropColumn('phidias_code');
        });
    }
};
