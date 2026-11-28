<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Alumnos por aula en mentoría especial y en entrega de material (Mi Instituto; normalmente 1): el aula queda solo
 * para ese tipo, hasta ese cupo.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('schools', function (Blueprint $table) {
            $table->unsignedTinyInteger('mentoring_capacity')->default(1)->after('max_clubs');
            $table->unsignedTinyInteger('material_capacity')->default(1)->after('mentoring_capacity');
        });
    }

    public function down(): void
    {
        Schema::table('schools', fn (Blueprint $table) => $table->dropColumn(['mentoring_capacity', 'material_capacity']));
    }
};
