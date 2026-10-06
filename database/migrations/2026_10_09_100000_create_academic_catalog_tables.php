<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Catálogos académicos. school_id nulo = catálogo base (Super Admin);
 * con escuela = copia de esa escuela. base_id = registro base del que viene la copia.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('shifts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id')->nullable()->constrained()->cascadeOnDelete();
            $table->foreignId('base_id')->nullable()->constrained('shifts')->nullOnDelete();
            $table->string('name', 60);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['school_id', 'sort_order']);
        });

        Schema::create('schedule_slots', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id')->nullable()->constrained()->cascadeOnDelete();
            $table->foreignId('base_id')->nullable()->constrained('schedule_slots')->nullOnDelete();
            $table->foreignId('shift_id')->constrained()->restrictOnDelete();
            $table->unsignedSmallInteger('number');
            $table->time('starts_at');
            $table->time('ends_at');
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['school_id', 'number']);
        });

        Schema::create('books', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id')->nullable()->constrained()->cascadeOnDelete();
            $table->foreignId('base_id')->nullable()->constrained('books')->nullOnDelete();
            $table->unsignedTinyInteger('level');
            $table->string('name', 80);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['school_id', 'level']);
        });

        Schema::create('lessons', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id')->nullable()->constrained()->cascadeOnDelete();
            $table->foreignId('base_id')->nullable()->constrained('lessons')->nullOnDelete();
            $table->foreignId('book_id')->constrained()->restrictOnDelete();
            $table->unsignedSmallInteger('number');
            $table->string('name', 120);
            $table->string('type', 20)->default('lesson');
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['book_id', 'sort_order']);
            $table->index('school_id');
        });

        Schema::create('activities', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id')->nullable()->constrained()->cascadeOnDelete();
            $table->foreignId('base_id')->nullable()->constrained('activities')->nullOnDelete();
            $table->unsignedSmallInteger('number');
            $table->string('code', 10);
            $table->string('description', 150);
            $table->unsignedSmallInteger('minutes')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['school_id', 'number']);
        });

        Schema::create('clubs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id')->nullable()->constrained()->cascadeOnDelete();
            $table->foreignId('base_id')->nullable()->constrained('clubs')->nullOnDelete();
            $table->string('name', 100);
            $table->string('description', 255)->nullable();
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['school_id', 'sort_order']);
        });

        // Horas del club por nivel (libro). Sin fila = el club no se ofrece en ese nivel.
        Schema::create('book_club', function (Blueprint $table) {
            $table->foreignId('club_id')->constrained()->cascadeOnDelete();
            $table->foreignId('book_id')->constrained()->cascadeOnDelete();
            $table->decimal('hours', 5, 1);

            $table->primary(['club_id', 'book_id']);
            $table->index('book_id');
        });

        // "¿A partir de cuál lección el alumno puede participar en clubes?" (número de actividad de la lección).
        Schema::table('schools', function (Blueprint $table) {
            $table->unsignedSmallInteger('club_min_lesson_number')->nullable()->after('failed_activity_policy');
        });
    }

    public function down(): void
    {
        Schema::table('schools', function (Blueprint $table) {
            $table->dropColumn('club_min_lesson_number');
        });

        Schema::dropIfExists('book_club');
        Schema::dropIfExists('clubs');
        Schema::dropIfExists('activities');
        Schema::dropIfExists('lessons');
        Schema::dropIfExists('books');
        Schema::dropIfExists('schedule_slots');
        Schema::dropIfExists('shifts');
    }
};
