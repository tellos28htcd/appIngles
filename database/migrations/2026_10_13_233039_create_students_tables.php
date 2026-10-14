<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Expediente del alumno: datos generales, inscripciones (contratos),
 * ausencias, histórico de estatus, tutores y respuestas de la encuesta.
 * Los datos de cobro van en el módulo de Cobranza.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('students', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            // Sin correo no hay usuario; se crea al capturarlo.
            $table->foreignId('user_id')->nullable()->unique()->constrained()->nullOnDelete();
            $table->string('enrollment_number', 30)->unique();
            $table->string('first_name', 80);
            $table->string('last_name', 80);
            $table->string('second_last_name', 80)->nullable();
            $table->string('mobile_phone', 20)->nullable();
            $table->string('address', 255)->nullable();
            $table->string('email')->nullable();
            $table->date('birth_date')->nullable();
            $table->foreignId('student_type_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('information_source_id')->nullable()->constrained()->nullOnDelete();
            $table->boolean('advertising_consent')->default(false);
            $table->boolean('learns_visual')->default(false);
            $table->boolean('learns_auditory')->default(false);
            $table->boolean('learns_kinesthetic')->default(false);
            $table->string('photo_path')->nullable();
            $table->string('photo_source_url', 500)->nullable();
            $table->string('status', 20)->default('active');
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->unique(['school_id', 'email']);
            $table->index(['school_id', 'status']);
            $table->index(['school_id', 'last_name', 'first_name']);
        });

        Schema::create('student_enrollments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->foreignId('student_id')->constrained()->cascadeOnDelete();
            $table->string('contract_number', 30)->nullable();
            $table->foreignId('sales_advisor_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('student_plan_id')->nullable()->constrained()->nullOnDelete();
            $table->unsignedTinyInteger('installments')->nullable();
            $table->date('registered_on')->nullable();
            $table->date('starts_on')->nullable();
            $table->date('ends_on')->nullable();
            $table->date('withdrawn_on')->nullable();
            $table->boolean('includes_term_extension')->default(false);
            $table->boolean('includes_toefl')->default(false);
            $table->boolean('includes_gold_card')->default(false);
            $table->string('material_serial', 40)->nullable();
            $table->boolean('material_assigned')->default(false);
            $table->boolean('commission_applies')->default(false);
            $table->foreignId('start_book_id')->nullable()->constrained('books')->nullOnDelete();
            $table->foreignId('start_lesson_id')->nullable()->constrained('lessons')->nullOnDelete();
            $table->foreignId('start_activity_id')->nullable()->constrained('activities')->nullOnDelete();
            $table->date('reenrolled_on')->nullable();
            $table->date('graduated_on')->nullable();
            $table->timestamps();

            $table->index(['school_id', 'registered_on']);
            $table->index(['school_id', 'contract_number']);
        });

        Schema::create('student_absences', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->foreignId('student_id')->constrained()->cascadeOnDelete();
            $table->date('starts_on');
            $table->date('ends_on')->nullable();
            $table->string('reason', 255)->nullable();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamps();
        });

        // Histórico: solo se inserta (bajas con su motivo, pausas, reingresos…).
        Schema::create('student_status_changes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->foreignId('student_id')->constrained()->cascadeOnDelete();
            $table->string('from_status', 20)->nullable();
            $table->string('to_status', 20);
            $table->date('changed_on');
            $table->text('reason')->nullable();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['student_id', 'changed_on']);
        });

        Schema::create('guardians', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            // Su acceso se da a mano cuando el tutor lo solicita.
            $table->foreignId('user_id')->nullable()->unique()->constrained()->nullOnDelete();
            $table->string('name', 160);
            $table->string('mobile_phone', 20)->nullable();
            $table->string('email')->nullable();
            $table->json('receives')->nullable();
            $table->timestamps();

            $table->index(['school_id', 'name']);
        });

        Schema::create('guardian_student', function (Blueprint $table) {
            $table->id();
            $table->foreignId('guardian_id')->constrained()->cascadeOnDelete();
            $table->foreignId('student_id')->constrained()->cascadeOnDelete();
            $table->string('relationship', 40)->nullable();
            $table->timestamps();

            $table->unique(['guardian_id', 'student_id']);
        });

        Schema::create('student_survey_answers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->foreignId('student_id')->constrained()->cascadeOnDelete();
            $table->foreignId('survey_question_id')->constrained()->cascadeOnDelete();
            $table->text('answer');
            $table->timestamps();

            $table->unique(['student_id', 'survey_question_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('student_survey_answers');
        Schema::dropIfExists('guardian_student');
        Schema::dropIfExists('guardians');
        Schema::dropIfExists('student_status_changes');
        Schema::dropIfExists('student_absences');
        Schema::dropIfExists('student_enrollments');
        Schema::dropIfExists('students');
    }
};
