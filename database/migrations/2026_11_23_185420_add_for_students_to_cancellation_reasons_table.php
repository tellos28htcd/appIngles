<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Motivos de cancelación que el alumno elige al cancelar él mismo en "Agendar" (no cuentan como falta ni como
 * asistencia): base + copia por escuela.
 */
return new class extends Migration
{
    public const STUDENT_REASONS = [
        'Por mas tiempo en el trabajo',
        'Por viaje de trabajo',
        'Por estudio',
        'Por enfermedad',
        'Por accidente',
        'Por trafico',
        'Por vacaciones',
        'Otra',
    ];

    public function up(): void
    {
        Schema::table('cancellation_reasons', function (Blueprint $table) {
            $table->boolean('for_students')->default(false)->after('attendance');
        });

        $now = now();
        foreach ([null, ...DB::table('schools')->pluck('id')] as $schoolId) {
            $order = 100;
            foreach (self::STUDENT_REASONS as $name) {
                $order += 10;
                $query = DB::table('cancellation_reasons')->where('name', $name)
                    ->when($schoolId === null, fn ($query) => $query->whereNull('school_id'), fn ($query) => $query->where('school_id', $schoolId));

                if ($query->exists()) {
                    $query->update(['for_students' => true, 'updated_at' => $now]);

                    continue;
                }

                $baseId = $schoolId === null ? null : DB::table('cancellation_reasons')->whereNull('school_id')->where('name', $name)->value('id');
                DB::table('cancellation_reasons')->insert(['school_id' => $schoolId, 'base_id' => $baseId, 'name' => $name, 'attendance' => 'none',
                    'for_students' => true, 'sort_order' => $order, 'is_active' => true, 'created_at' => $now, 'updated_at' => $now]);
            }
        }
    }

    public function down(): void
    {
        Schema::table('cancellation_reasons', function (Blueprint $table) {
            $table->dropColumn('for_students');
        });
    }
};
