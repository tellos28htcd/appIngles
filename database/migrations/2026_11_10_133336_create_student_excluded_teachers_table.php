<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Teachers con los que el alumno no quiere hacer prácticas (hasta 3, en su expediente).
 * La agenda no lo acomoda en sus aulas.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('student_excluded_teachers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->foreignId('student_id')->constrained()->cascadeOnDelete();
            $table->foreignId('teacher_id')->constrained()->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['student_id', 'teacher_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('student_excluded_teachers');
    }
};
