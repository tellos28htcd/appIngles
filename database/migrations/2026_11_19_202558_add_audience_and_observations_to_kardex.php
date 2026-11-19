<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Motivos del Kardex por quién los usa al calificar (teacher, personal o ambos) y observación por práctica
 * calificada. Los motivos Revisar, Pendiente y Otro se reemplazan por los del teacher y los del personal
 * (los que ya se usaron solo se desactivan).
 */
return new class extends Migration
{
    /** nombre => quién lo usa */
    private const REASONS = [
        'Pendiente de completar' => 'teacher',
        'Pendiente de revisar' => 'teacher',
        'Repetir' => 'teacher',
        'Sección A completada' => 'teacher',
        'Revisar correcciones' => 'teacher',
        'Completar correcciones' => 'teacher',
        'Material extra' => 'all',
        'Planeación de club' => 'staff',
        'Planeación de field trip' => 'staff',
        'Field trip externo' => 'staff',
        'Field trip interno' => 'staff',
        'Reporte de Alumno' => 'staff',
        'Junta academica' => 'staff',
        'Capacitacion' => 'staff',
        'Contestar libro' => 'staff',
        'Convivencia' => 'staff',
        'Solicitud de permisos para field trip' => 'staff',
        'Compra de material para Field trip' => 'staff',
    ];

    private const OLD = ['Revisar', 'Pendiente', 'Otro'];

    public function up(): void
    {
        Schema::table('ungraded_reasons', function (Blueprint $table) {
            $table->string('audience', 20)->default('all')->after('name');
        });

        Schema::table('kardex_entries', function (Blueprint $table) {
            $table->foreignId('observation_reason_id')->nullable()->after('late_comment')->constrained('ungraded_reasons')->nullOnDelete();
        });

        $this->replaceReasons();
    }

    private function replaceReasons(): void
    {
        $now = now();

        // Los anteriores: se borran si nadie los usó; si ya se usaron, solo se desactivan.
        $old = DB::table('ungraded_reasons')->whereIn('name', self::OLD)->orderByRaw('school_id IS NULL')->get(['id', 'school_id']);
        foreach ($old as $reason) {
            $used = DB::table('bookings')->where('ungraded_reason_id', $reason->id)->exists()
                || DB::table('kardex_entries')->where('late_reason_id', $reason->id)->exists();
            $used || $reason->school_id === null && DB::table('ungraded_reasons')->where('base_id', $reason->id)->exists()
                ? DB::table('ungraded_reasons')->where('id', $reason->id)->update(['is_active' => false, 'updated_at' => $now])
                : DB::table('ungraded_reasons')->where('id', $reason->id)->delete();
        }

        foreach ([null, ...DB::table('schools')->pluck('id')] as $schoolId) {
            $order = 0;
            foreach (self::REASONS as $name => $audience) {
                $order += 10;
                $exists = DB::table('ungraded_reasons')->where('name', $name)
                    ->when($schoolId === null, fn ($query) => $query->whereNull('school_id'), fn ($query) => $query->where('school_id', $schoolId))->exists();
                if ($exists) {
                    continue;
                }

                $baseId = $schoolId === null ? null : DB::table('ungraded_reasons')->whereNull('school_id')->where('name', $name)->value('id');
                DB::table('ungraded_reasons')->insert(['school_id' => $schoolId, 'base_id' => $baseId, 'name' => $name, 'audience' => $audience,
                    'sort_order' => $order, 'is_active' => true, 'created_at' => $now, 'updated_at' => $now]);
            }
        }
    }

    public function down(): void
    {
        Schema::table('kardex_entries', function (Blueprint $table) {
            $table->dropConstrainedForeignId('observation_reason_id');
        });

        Schema::table('ungraded_reasons', function (Blueprint $table) {
            $table->dropColumn('audience');
        });
    }
};
