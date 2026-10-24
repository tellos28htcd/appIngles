<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Actividades del coordinador:
 * - "Horas autorizadas por teacher" (Evaluación de Club y de Sesión: 2 h al mes por cada teacher);
 *   en la tabla HA/HP se ve un renglón por teacher.
 * - Columna de la tabla HA/HP (1 = izquierda, 2 = derecha), como el Excel.
 * - Nuevas: "Carpetas mensuales de Oscar" y "Reporte mensual CN de Oscar" (2 h, sin teacher).
 */
return new class extends Migration
{
    /** nombre => [orden, columna]; las evaluaciones son por teacher. */
    private const LEFT = [
        'Carpetas mensuales de Oscar' => 1,
        'Reporte mensual CN de Oscar' => 2,
        'Evaluación de Club' => 3,
        'Evaluación de Sesión' => 4,
    ];

    public function up(): void
    {
        Schema::table('coordinator_activities', function (Blueprint $table) {
            $table->boolean('per_teacher')->default(false)->after('authorized_hours');
            $table->unsignedTinyInteger('table_column')->default(2)->after('panel_mark');
        });

        $now = now();
        $schools = DB::table('coordinator_activities')->whereNotNull('school_id')->distinct()->pluck('school_id');

        foreach (['Carpetas mensuales de Oscar', 'Reporte mensual CN de Oscar'] as $name) {
            $row = ['name' => $name, 'authorized_hours' => 2, 'per_teacher' => false, 'requires_teacher' => false, 'uses_teacher_hour' => false,
                'panel_mark' => null, 'table_column' => 1, 'sort_order' => self::LEFT[$name], 'is_active' => true, 'created_at' => $now, 'updated_at' => $now];
            $baseId = DB::table('coordinator_activities')->whereNull('school_id')->where('name', $name)->value('id')
                ?? DB::table('coordinator_activities')->insertGetId(['school_id' => null, 'base_id' => null, ...$row]);

            foreach ($schools as $schoolId) {
                if (! DB::table('coordinator_activities')->where('school_id', $schoolId)->where('name', $name)->exists()) {
                    DB::table('coordinator_activities')->insert(['school_id' => $schoolId, 'base_id' => $baseId, ...$row]);
                }
            }
        }

        foreach (self::LEFT as $name => $order) {
            DB::table('coordinator_activities')->where('name', $name)->update([
                'table_column' => 1,
                'sort_order' => $order,
                'per_teacher' => str_starts_with($name, 'Evaluación de'),
            ]);
        }
    }

    public function down(): void
    {
        DB::table('coordinator_activities')->whereIn('name', ['Carpetas mensuales de Oscar', 'Reporte mensual CN de Oscar'])->whereNotExists(
            fn ($query) => $query->from('coordinator_sessions')->whereColumn('coordinator_sessions.coordinator_activity_id', 'coordinator_activities.id')
        )->delete();

        Schema::table('coordinator_activities', fn (Blueprint $table) => $table->dropColumn(['per_teacher', 'table_column']));
    }
};
