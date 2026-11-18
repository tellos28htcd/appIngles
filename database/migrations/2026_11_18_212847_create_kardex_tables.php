<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Kardex → Calificaciones:
 * - grades: A, B, C (actividades 4–7) y F (firma de las actividades 1–3 y de los clubs), base + copia por escuela.
 * - ungraded_reasons: Revisar, Pendiente, Otro (sin calificación o calificada días después), base + copia.
 * - activities.grade_type: qué tipo de calificación lleva cada actividad.
 * - bookings.ungraded_reason_id / ungraded_comment: la reservación que se queda sin calificación.
 * - kardex_entries: lo calificado a cada alumno (práctica de la ruta o club).
 * - Menú: Kardex → Calificaciones; "Mi avance" del alumno abre su Kardex.
 */
return new class extends Migration
{
    private const GRADES = [
        'A' => ['Excelente', 'score'],
        'B' => ['Bien', 'score'],
        'C' => ['Suficiente', 'score'],
        'F' => ['Firma', 'signature'],
    ];

    private const REASONS = ['Revisar', 'Pendiente', 'Otro'];

    public function up(): void
    {
        Schema::create('grades', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id')->nullable()->constrained()->cascadeOnDelete();
            $table->foreignId('base_id')->nullable()->constrained('grades')->nullOnDelete();
            $table->string('code', 2);
            $table->string('name', 40);
            $table->string('type', 20);
            $table->unsignedTinyInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->unique(['school_id', 'code']);
        });

        Schema::create('ungraded_reasons', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id')->nullable()->constrained()->cascadeOnDelete();
            $table->foreignId('base_id')->nullable()->constrained('ungraded_reasons')->nullOnDelete();
            $table->string('name', 80);
            $table->unsignedTinyInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->unique(['school_id', 'name']);
        });

        Schema::table('activities', function (Blueprint $table) {
            $table->string('grade_type', 20)->default('score')->after('minutes');
        });
        DB::table('activities')->where('number', '<=', 3)->update(['grade_type' => 'signature']);

        Schema::table('bookings', function (Blueprint $table) {
            $table->foreignId('ungraded_reason_id')->nullable()->after('cancelled_at')->constrained()->nullOnDelete();
            $table->string('ungraded_comment', 255)->nullable()->after('ungraded_reason_id');
        });

        Schema::create('kardex_entries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->foreignId('student_id')->constrained()->cascadeOnDelete();
            $table->foreignId('booking_id')->nullable()->constrained()->nullOnDelete();
            $table->string('kind', 20);
            $table->foreignId('book_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('lesson_id')->nullable()->constrained()->nullOnDelete();
            $table->unsignedTinyInteger('activity_number')->nullable();
            $table->unsignedSmallInteger('sort_order')->nullable();
            $table->unsignedInteger('points')->nullable();
            $table->foreignId('club_session_id')->nullable()->constrained()->nullOnDelete();
            $table->string('club_code', 20)->nullable();
            $table->foreignId('grade_id')->constrained()->restrictOnDelete();
            $table->foreignId('teacher_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->date('session_date');
            $table->foreignId('late_reason_id')->nullable()->constrained('ungraded_reasons')->nullOnDelete();
            $table->string('late_comment', 255)->nullable();
            $table->timestamps();
            $table->unique(['student_id', 'lesson_id', 'activity_number']);
            $table->index(['school_id', 'session_date']);
            $table->index(['student_id', 'kind', 'sort_order']);
        });

        $this->seedCatalogs();
        $this->seedMenu();
    }

    private function seedCatalogs(): void
    {
        $now = now();
        $schools = DB::table('schools')->pluck('id');

        foreach ([null, ...$schools] as $schoolId) {
            $order = 0;
            foreach (self::GRADES as $code => [$name, $type]) {
                $order += 10;
                $baseId = $schoolId === null ? null : DB::table('grades')->whereNull('school_id')->where('code', $code)->value('id');
                DB::table('grades')->insert(['school_id' => $schoolId, 'base_id' => $baseId, 'code' => $code, 'name' => $name, 'type' => $type,
                    'sort_order' => $order, 'is_active' => true, 'created_at' => $now, 'updated_at' => $now]);
            }

            foreach (self::REASONS as $index => $name) {
                $baseId = $schoolId === null ? null : DB::table('ungraded_reasons')->whereNull('school_id')->where('name', $name)->value('id');
                DB::table('ungraded_reasons')->insert(['school_id' => $schoolId, 'base_id' => $baseId, 'name' => $name,
                    'sort_order' => ($index + 1) * 10, 'is_active' => true, 'created_at' => $now, 'updated_at' => $now]);
            }
        }
    }

    /** Solo si el menú ya existe (en una instalación nueva lo arma MenuSeeder). */
    private function seedMenu(): void
    {
        if (DB::table('menu_items')->where('slug', 'agenda')->doesntExist() || DB::table('menu_items')->where('slug', 'kardex')->exists()) {
            return;
        }

        $now = now();
        $moduleId = DB::table('menu_items')->insertGetId(['parent_id' => null, 'slug' => 'kardex', 'label' => 'Kardex', 'icon' => 'book',
            'route_name' => null, 'status' => 'active', 'is_system' => true, 'is_enabled' => true, 'sort_order' => 25, 'created_at' => $now, 'updated_at' => $now]);
        $gradesId = DB::table('menu_items')->insertGetId(['parent_id' => $moduleId, 'slug' => 'kardex-grades', 'label' => 'Calificaciones', 'icon' => null,
            'route_name' => 'kardex.grades', 'status' => 'active', 'is_system' => true, 'is_enabled' => true, 'sort_order' => 10, 'created_at' => $now, 'updated_at' => $now]);

        $roles = DB::table('roles')->whereIn('slug', ['platform_admin', 'school_admin', 'academic_coordinator', 'teacher', 'reception'])->pluck('id');
        foreach ($roles as $roleId) {
            DB::table('menu_item_role')->insertOrIgnore([['menu_item_id' => $moduleId, 'role_id' => $roleId], ['menu_item_id' => $gradesId, 'role_id' => $roleId]]);
        }

        DB::table('menu_items')->where('slug', 'student-portal-progress')->update(['route_name' => 'my-kardex.index', 'status' => 'active', 'updated_at' => $now]);
    }

    public function down(): void
    {
        DB::table('menu_items')->where('slug', 'student-portal-progress')->update(['route_name' => null, 'status' => 'coming_soon']);
        DB::table('menu_items')->whereIn('slug', ['kardex-grades', 'kardex'])->delete();

        Schema::dropIfExists('kardex_entries');
        Schema::table('bookings', function (Blueprint $table) {
            $table->dropConstrainedForeignId('ungraded_reason_id');
            $table->dropColumn('ungraded_comment');
        });
        Schema::table('activities', function (Blueprint $table) {
            $table->dropColumn('grade_type');
        });
        Schema::dropIfExists('ungraded_reasons');
        Schema::dropIfExists('grades');
    }
};
