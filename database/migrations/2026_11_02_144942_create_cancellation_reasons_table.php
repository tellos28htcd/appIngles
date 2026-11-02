<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Catálogo de motivos de cancelación de la agenda (base en Plataforma + copia por escuela).
 * Cada motivo dice cómo cuenta para el índice de asistencia: asistió, faltó o no cuenta.
 * Las reservaciones pasan del motivo fijo en el código al del catálogo.
 */
return new class extends Migration
{
    /** valor anterior => [nombre, efecto en la asistencia, orden] */
    private const REASONS = [
        'no_show' => ['No se presentó', 'absent', 10],
        'notice' => ['Aviso para cancelar', 'none', 20],
        'unfinished' => ['No terminó la actividad', 'attended', 30],
        'attended_cancelled' => ['Asistió, decidió cancelar', 'attended', 40],
    ];

    public function up(): void
    {
        Schema::create('cancellation_reasons', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id')->nullable()->constrained()->cascadeOnDelete();
            $table->foreignId('base_id')->nullable()->constrained('cancellation_reasons')->nullOnDelete();
            $table->string('name', 80);
            $table->string('attendance', 10)->default('none');
            $table->unsignedTinyInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['school_id', 'name']);
        });

        Schema::table('bookings', function (Blueprint $table) {
            $table->foreignId('cancellation_reason_id')->nullable()->after('status')->constrained()->nullOnDelete();
        });

        // Base y copia para cada escuela; las reservaciones canceladas toman el motivo de su escuela.
        $now = now();
        $base = [];
        foreach (self::REASONS as $value => [$name, $attendance, $order]) {
            $base[$value] = DB::table('cancellation_reasons')->insertGetId([
                'school_id' => null, 'name' => $name, 'attendance' => $attendance, 'sort_order' => $order,
                'is_active' => true, 'created_at' => $now, 'updated_at' => $now,
            ]);
        }

        foreach (DB::table('schools')->pluck('id') as $schoolId) {
            foreach (self::REASONS as $value => [$name, $attendance, $order]) {
                $id = DB::table('cancellation_reasons')->insertGetId([
                    'school_id' => $schoolId, 'base_id' => $base[$value], 'name' => $name, 'attendance' => $attendance,
                    'sort_order' => $order, 'is_active' => true, 'created_at' => $now, 'updated_at' => $now,
                ]);
                DB::table('bookings')->where('school_id', $schoolId)->where('cancellation_reason', $value)->update(['cancellation_reason_id' => $id]);
            }
        }

        Schema::table('bookings', function (Blueprint $table) {
            $table->dropColumn('cancellation_reason');
        });
    }

    public function down(): void
    {
        Schema::table('bookings', function (Blueprint $table) {
            $table->string('cancellation_reason', 20)->nullable()->after('status');
        });

        foreach (self::REASONS as $value => [$name]) {
            $ids = DB::table('cancellation_reasons')->where('name', $name)->pluck('id');
            DB::table('bookings')->whereIn('cancellation_reason_id', $ids)->update(['cancellation_reason' => $value]);
        }

        Schema::table('bookings', function (Blueprint $table) {
            $table->dropConstrainedForeignId('cancellation_reason_id');
        });
        Schema::dropIfExists('cancellation_reasons');
    }
};
