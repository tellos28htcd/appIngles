<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * - Turnos: días en que aplican (1 = lunes … 7 = domingo). Un turno con domingo
 *   agrega el domingo al panel de disponibilidad y al horario base.
 * - Teachers: fecha de baja.
 * - Ficha de inscripción: fecha de entrega de material.
 * - Escuela: desde qué fecha de la ficha se cuenta el fin del curso (contrato o material).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('shifts', function (Blueprint $table) {
            $table->json('weekdays')->nullable()->after('name');
        });

        // Los turnos existentes conservan su comportamiento (lunes a sábado); "Domingo" y "Sabatino" por su nombre.
        DB::table('shifts')->orderBy('id')->each(function (object $shift): void {
            $name = mb_strtolower($shift->name);
            $weekdays = match (true) {
                str_contains($name, 'domingo') || str_contains($name, 'dominical') => [7],
                str_contains($name, 'sabatino') || str_contains($name, 'sábado') || str_contains($name, 'sabado') => [6],
                default => [1, 2, 3, 4, 5, 6],
            };

            DB::table('shifts')->where('id', $shift->id)->update(['weekdays' => json_encode($weekdays)]);
        });

        Schema::table('teachers', function (Blueprint $table) {
            $table->date('terminated_on')->nullable()->after('status');
        });

        Schema::table('student_enrollments', function (Blueprint $table) {
            $table->date('material_delivered_on')->nullable()->after('registered_on');
        });

        Schema::table('schools', function (Blueprint $table) {
            $table->string('course_end_from', 20)->default('contract')->after('works_sundays');
        });
    }

    public function down(): void
    {
        Schema::table('schools', fn (Blueprint $table) => $table->dropColumn('course_end_from'));
        Schema::table('student_enrollments', fn (Blueprint $table) => $table->dropColumn('material_delivered_on'));
        Schema::table('teachers', fn (Blueprint $table) => $table->dropColumn('terminated_on'));
        Schema::table('shifts', fn (Blueprint $table) => $table->dropColumn('weekdays'));
    }
};
