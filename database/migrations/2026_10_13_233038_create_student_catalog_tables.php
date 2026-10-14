<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/**
 * Catálogos del módulo Alumnos. Planes, origen de la información, tipos de
 * alumno, puestos de asesor y preguntas de la encuesta tienen base en
 * Plataforma (school_id nulo) y una copia por escuela (base_id). Los asesores
 * de ventas son solo de cada escuela.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('student_plans', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id')->nullable()->constrained()->cascadeOnDelete();
            $table->foreignId('base_id')->nullable()->constrained('student_plans')->nullOnDelete();
            $table->string('code', 10);
            $table->string('name', 80);
            $table->unsignedTinyInteger('installments')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['school_id', 'code']);
        });

        Schema::create('information_sources', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id')->nullable()->constrained()->cascadeOnDelete();
            $table->foreignId('base_id')->nullable()->constrained('information_sources')->nullOnDelete();
            $table->string('name', 80);
            $table->string('channel', 80)->nullable();
            $table->string('short_name', 20)->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['school_id', 'name']);
        });

        Schema::create('student_types', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id')->nullable()->constrained()->cascadeOnDelete();
            $table->foreignId('base_id')->nullable()->constrained('student_types')->nullOnDelete();
            $table->string('code', 10);
            $table->string('name', 80);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['school_id', 'code']);
        });

        Schema::create('advisor_positions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id')->nullable()->constrained()->cascadeOnDelete();
            $table->foreignId('base_id')->nullable()->constrained('advisor_positions')->nullOnDelete();
            $table->string('name', 80);
            $table->unsignedTinyInteger('rank')->default(1);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['school_id', 'name']);
        });

        Schema::create('survey_questions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id')->nullable()->constrained()->cascadeOnDelete();
            $table->foreignId('base_id')->nullable()->constrained('survey_questions')->nullOnDelete();
            $table->string('section', 80);
            $table->string('question', 255);
            $table->string('type', 20);
            $table->json('options')->nullable();
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['school_id', 'sort_order']);
        });

        Schema::create('sales_advisors', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->foreignId('advisor_position_id')->nullable()->constrained()->nullOnDelete();
            $table->string('name', 120);
            $table->date('hired_on')->nullable();
            $table->date('left_on')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['school_id', 'name']);
        });

        // Matrícula: PREFIJO (4) + año (2) + mes (2) + consecutivo continuo por escuela (4).
        Schema::table('schools', function (Blueprint $table) {
            $table->string('enrollment_prefix', 4)->nullable()->after('code');
            $table->unsignedInteger('last_enrollment_sequence')->default(0)->after('enrollment_prefix');
        });

        DB::table('schools')->orderBy('id')->each(function (object $school): void {
            $word = Str::of(Str::ascii((string) $school->name))->upper()->explode(' ')->first(fn (string $part) => $part !== '') ?? 'ESCU';
            $prefix = Str::of(preg_replace('/[^A-Z]/', '', $word))->padRight(4, 'X')->substr(0, 4)->toString();

            DB::table('schools')->where('id', $school->id)->update(['enrollment_prefix' => $prefix]);
        });
    }

    public function down(): void
    {
        Schema::table('schools', function (Blueprint $table) {
            $table->dropColumn(['enrollment_prefix', 'last_enrollment_sequence']);
        });

        Schema::dropIfExists('sales_advisors');
        Schema::dropIfExists('survey_questions');
        Schema::dropIfExists('advisor_positions');
        Schema::dropIfExists('student_types');
        Schema::dropIfExists('information_sources');
        Schema::dropIfExists('student_plans');
    }
};
