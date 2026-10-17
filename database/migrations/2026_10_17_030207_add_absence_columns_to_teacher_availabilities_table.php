<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Una hora en la que el teacher tiene incidencia (vacaciones, falta…) se
 * conserva oculta en el panel con el aula que tenía: así quien lo cubre usa
 * esa aula y, si se borra la incidencia, la hora vuelve con su aula.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('teacher_availabilities', function (Blueprint $table) {
            $table->boolean('is_absent')->default(false)->after('classroom_id');
            $table->foreignId('held_classroom_id')->nullable()->after('is_absent')->constrained('classrooms')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('teacher_availabilities', function (Blueprint $table) {
            $table->dropConstrainedForeignId('held_classroom_id');
            $table->dropColumn('is_absent');
        });
    }
};
