<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Actividades del coordinador que se programan con un teacher sin ocupar su hora (la coordinadora entra
 * a su clase, p. ej. Evaluación de Sesión). Sin teacher solo quedan las de planeación de clubs y juntas.
 * Se agrega "Evaluación de Sesión" (2 h).
 */
return new class extends Migration
{
    private const WITHOUT_TEACHER = ['Programación de Clubs', 'Elaboración de club en Canva', 'Elaboración de clubs', 'Preparación de junta Mensual', 'Junta mensual'];

    public function up(): void
    {
        Schema::table('coordinator_activities', function (Blueprint $table) {
            $table->boolean('requires_teacher')->default(false)->after('authorized_hours');
        });

        DB::table('coordinator_activities')->whereNotIn('name', self::WITHOUT_TEACHER)->update(['requires_teacher' => true]);

        // "Evaluación de Sesión" junto a "Evaluación de Club", en la base y en cada escuela que tenga el catálogo.
        $now = now();
        $base = DB::table('coordinator_activities')->whereNull('school_id')->where('name', 'Evaluación de Sesión')->value('id')
            ?? DB::table('coordinator_activities')->insertGetId([
                'school_id' => null, 'base_id' => null, 'name' => 'Evaluación de Sesión', 'authorized_hours' => 2, 'requires_teacher' => true,
                'uses_teacher_hour' => false, 'panel_mark' => null, 'sort_order' => 15, 'is_active' => true, 'created_at' => $now, 'updated_at' => $now,
            ]);

        $schools = DB::table('coordinator_activities')->whereNotNull('school_id')->distinct()->pluck('school_id');
        foreach ($schools as $schoolId) {
            if (! DB::table('coordinator_activities')->where('school_id', $schoolId)->where('name', 'Evaluación de Sesión')->exists()) {
                DB::table('coordinator_activities')->insert([
                    'school_id' => $schoolId, 'base_id' => $base, 'name' => 'Evaluación de Sesión', 'authorized_hours' => 2, 'requires_teacher' => true,
                    'uses_teacher_hour' => false, 'panel_mark' => null, 'sort_order' => 15, 'is_active' => true, 'created_at' => $now, 'updated_at' => $now,
                ]);
            }
        }
    }

    public function down(): void
    {
        DB::table('coordinator_activities')->where('name', 'Evaluación de Sesión')->whereNotExists(
            fn ($query) => $query->from('coordinator_sessions')->whereColumn('coordinator_sessions.coordinator_activity_id', 'coordinator_activities.id')
        )->delete();

        Schema::table('coordinator_activities', fn (Blueprint $table) => $table->dropColumn('requires_teacher'));
    }
};
