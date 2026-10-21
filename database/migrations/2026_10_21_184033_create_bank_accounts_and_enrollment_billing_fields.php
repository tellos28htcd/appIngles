<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * - Catálogo de cuentas bancarias de cada escuela (a dónde depositan los alumnos).
 * - Ficha de inscripción: fecha del primer cobro (Cobranza calcula las demás mensualidades),
 *   razón social (a nombre de quién se factura) y cuenta bancaria de pago.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bank_accounts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->string('name', 80);
            $table->string('bank', 80);
            $table->string('account_holder', 200)->nullable();
            $table->string('account_number', 30)->nullable();
            $table->char('clabe', 18)->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['school_id', 'name']);
        });

        Schema::table('student_enrollments', function (Blueprint $table) {
            $table->date('first_charge_on')->nullable()->after('installments');
            $table->string('billing_name', 200)->nullable()->after('first_charge_on');
            $table->foreignId('bank_account_id')->nullable()->after('billing_name')->constrained()->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('student_enrollments', function (Blueprint $table) {
            $table->dropConstrainedForeignId('bank_account_id');
            $table->dropColumn(['first_charge_on', 'billing_name']);
        });

        Schema::dropIfExists('bank_accounts');
    }
};
