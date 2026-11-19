<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Mi Instituto ya no tiene "Cupo de alumnos por club", "Alumnos por aula virtual" ni "Aulas virtuales por
 * horario": el cupo de clubs y aulas virtuales sale de la capacidad de cada aula (Configuración → Aulas) y se
 * ofrecen todas las aulas virtuales con teacher programado.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('schools', function (Blueprint $table) {
            $table->dropColumn(['club_capacity', 'virtual_room_capacity', 'virtual_rooms_per_slot']);
        });
    }

    public function down(): void
    {
        Schema::table('schools', function (Blueprint $table) {
            $table->unsignedSmallInteger('club_capacity')->nullable()->after('session_capacity');
            $table->unsignedSmallInteger('virtual_room_capacity')->nullable()->after('club_capacity');
            $table->unsignedTinyInteger('virtual_rooms_per_slot')->default(0)->after('virtual_room_capacity');
        });
    }
};
