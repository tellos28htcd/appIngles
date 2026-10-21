<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Fecha de ingreso del teacher: aparece en el panel de disponibilidad desde ese día.
 * Los teachers ya registrados ingresan el 1 de octubre de 2026.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('teachers', function (Blueprint $table) {
            $table->date('hired_on')->nullable()->after('status');
        });

        DB::table('teachers')->whereNull('hired_on')->update(['hired_on' => '2026-10-01']);
    }

    public function down(): void
    {
        Schema::table('teachers', fn (Blueprint $table) => $table->dropColumn('hired_on'));
    }
};
