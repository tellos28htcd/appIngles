<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('schools', function (Blueprint $table) {
            $table->id();

            // Datos del plantel
            $table->string('code', 20)->unique();
            $table->string('name', 150);

            // Domicilio
            $table->string('street', 150)->nullable();
            $table->string('exterior_number', 20)->nullable();
            $table->string('interior_number', 20)->nullable();
            $table->string('neighborhood', 120)->nullable();
            $table->char('postal_code', 5)->nullable();
            $table->foreignId('state_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignId('municipality_id')->nullable()->constrained()->restrictOnDelete();

            // Contacto y datos fiscales
            $table->string('phone', 20)->nullable();
            $table->string('rfc', 13)->nullable();
            $table->string('legal_name', 200)->nullable();

            // Folios de recibos
            $table->unsignedInteger('last_folio_series_a')->default(0);
            $table->unsignedInteger('last_folio_series_b')->default(0);

            // Identidad visual (white-label)
            $table->string('logo_path')->nullable();
            $table->char('brand_primary', 7);
            $table->char('brand_accent', 7)->nullable();

            // Operación
            $table->unsignedTinyInteger('session_capacity');
            $table->string('timezone', 64)->default('America/Mexico_City');
            $table->char('currency', 3)->default('MXN');

            // Configuración del plantel
            $table->boolean('works_sundays')->default(false);
            $table->boolean('schedules_classrooms')->default(true);
            $table->boolean('books_without_classroom')->default(false);
            $table->boolean('hybrid_clubs')->default(false);
            $table->boolean('requires_progress')->default(true);
            $table->string('self_booking', 30)->default('disabled');
            $table->string('max_sessions_scope', 20)->default('student');
            $table->unsignedSmallInteger('max_sessions')->nullable();
            $table->string('failed_activity_policy', 20)->default('shift');

            $table->string('status', 20)->default('active')->index();
            $table->timestamps();

            $table->index('name');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('schools');
    }
};
