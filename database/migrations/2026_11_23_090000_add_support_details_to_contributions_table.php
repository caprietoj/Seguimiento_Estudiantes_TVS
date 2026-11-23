<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Notas del Dpto. de Apoyo (Psicología, Fonoaudiología, T.O., Neuropsicología) con el formato
 * del colegio: `body` = habilidades/observación, más acuerdos/estrategias y seguimiento a las
 * estrategias, y las marcas de las que sale la estadística general del departamento.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('contributions', function (Blueprint $table) {
            $table->text('agreements')->nullable()->after('body');
            $table->text('strategy_follow_up')->nullable()->after('agreements');
            $table->boolean('in_follow_up')->default(false)->after('strategy_follow_up');
            $table->boolean('family_attended')->default(false)->after('in_follow_up');
            $table->boolean('piar_meeting')->default(false)->after('family_attended');
            $table->boolean('crisis_attention')->default(false)->after('piar_meeting');
        });
    }

    public function down(): void
    {
        Schema::table('contributions', function (Blueprint $table) {
            $table->dropColumn(['agreements', 'strategy_follow_up', 'in_follow_up', 'family_attended', 'piar_meeting', 'crisis_attention']);
        });
    }
};
