<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Expediente del alumno:
 * - Teléfono fijo y domicilio separado (calle, números, colonia, municipio y estado del INEGI, C.P.).
 *   El texto original queda en `address` y se marca para revisar si no se pudo separar.
 * - Inicio del curso, fecha de baja y de graduación pasan a datos generales (antes en la inscripción).
 * - Inscripción: días de ampliación de plazo y tipo de material (F = Financiero, C = Contable).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('students', function (Blueprint $table) {
            $table->string('landline_phone', 20)->nullable()->after('mobile_phone');
            $table->string('street', 150)->nullable()->after('address');
            $table->string('exterior_number', 20)->nullable()->after('street');
            $table->string('interior_number', 20)->nullable()->after('exterior_number');
            $table->string('neighborhood', 120)->nullable()->after('interior_number');
            $table->foreignId('state_id')->nullable()->after('neighborhood')->constrained()->restrictOnDelete();
            $table->foreignId('municipality_id')->nullable()->after('state_id')->constrained()->restrictOnDelete();
            $table->char('postal_code', 5)->nullable()->after('municipality_id');
            $table->boolean('address_needs_review')->default(false)->after('postal_code');
            $table->date('course_starts_on')->nullable()->after('birth_date');
            $table->date('withdrawn_on')->nullable()->after('course_starts_on');
            $table->date('graduated_on')->nullable()->after('withdrawn_on');
        });

        Schema::table('student_enrollments', function (Blueprint $table) {
            $table->unsignedSmallInteger('extension_days')->nullable()->after('includes_term_extension');
            $table->char('material_type', 1)->nullable()->after('material_serial');
        });

        // Las fechas pasan de la inscripción a datos generales: inicio = el primero; baja y graduación = las más recientes.
        DB::table('students')->orderBy('id')->each(function (object $student): void {
            $enrollments = DB::table('student_enrollments')->where('student_id', $student->id);

            DB::table('students')->where('id', $student->id)->update([
                'course_starts_on' => (clone $enrollments)->min('starts_on'),
                'withdrawn_on' => (clone $enrollments)->max('withdrawn_on'),
                'graduated_on' => (clone $enrollments)->max('graduated_on'),
            ]);
        });
    }

    public function down(): void
    {
        Schema::table('student_enrollments', function (Blueprint $table) {
            $table->dropColumn(['extension_days', 'material_type']);
        });

        Schema::table('students', function (Blueprint $table) {
            $table->dropConstrainedForeignId('state_id');
            $table->dropConstrainedForeignId('municipality_id');
            $table->dropColumn([
                'landline_phone', 'street', 'exterior_number', 'interior_number', 'neighborhood', 'postal_code',
                'address_needs_review', 'course_starts_on', 'withdrawn_on', 'graduated_on',
            ]);
        });
    }
};
