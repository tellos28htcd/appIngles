<?php

use Database\Seeders\PracticeRouteData;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Catálogo de prácticas (ruta de avance, Excel Cat_Practicas.xlsx): cada práctica es libro + lección + actividad,
 * con su orden (consecutivo en el que se asignan en la agenda y se califican en el Kardex), puntos acumulados y,
 * si lo tiene, un nombre propio en esa lección (A6 del Check Point: DIRECT P. / CON. EXP.).
 *
 * También: se eliminan las actividades 8 y 9 (PR, DP, CE) y la regla "uno a uno"; la actividad 7 es SP; los pesos
 * newton no cambian, solo se agregan los de las prácticas nuevas (CP A7 y VIC A3, con el peso de la Lesson 1).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('practices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id')->nullable()->constrained()->cascadeOnDelete();
            $table->foreignId('base_id')->nullable()->constrained('practices')->nullOnDelete();
            $table->foreignId('book_id')->constrained()->cascadeOnDelete();
            $table->foreignId('lesson_id')->constrained()->cascadeOnDelete();
            $table->unsignedSmallInteger('activity_number');
            $table->string('name', 40)->nullable();
            $table->unsignedInteger('sort_order');
            $table->unsignedInteger('points');
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['lesson_id', 'activity_number']);
            $table->index(['school_id', 'sort_order']);
        });

        // Base de Plataforma (school_id null) y cada escuela: libros por nivel y lecciones por número.
        $baseIds = [];
        foreach (DB::table('books')->select('school_id')->distinct()->pluck('school_id')->sortBy(fn ($id) => $id === null ? 0 : 1) as $schoolId) {
            $books = DB::table('books')->where('school_id', $schoolId)->pluck('id', 'level');
            $lessons = DB::table('lessons')->whereIn('book_id', $books->values())->get()->keyBy(fn ($lesson) => $lesson->book_id.'|'.$lesson->number);
            $now = now();

            foreach (PracticeRouteData::ROWS as [$level, $lessonNumber, $activity, $order, $points, $name]) {
                $bookId = $books[$level] ?? null;
                $lesson = $bookId ? $lessons->get($bookId.'|'.$lessonNumber) : null;

                if ($lesson === null) {
                    continue;
                }

                $id = DB::table('practices')->insertGetId([
                    'school_id' => $schoolId, 'base_id' => $schoolId === null ? null : ($baseIds["$level|$lessonNumber|$activity"] ?? null),
                    'book_id' => $bookId, 'lesson_id' => $lesson->id, 'activity_number' => $activity, 'name' => $name,
                    'sort_order' => $order, 'points' => $points, 'is_active' => true, 'created_at' => $now, 'updated_at' => $now,
                ]);

                if ($schoolId === null) {
                    $baseIds["$level|$lessonNumber|$activity"] = $id;
                }
            }
        }

        // Pesos newton: se quitan las actividades 8 y 9 y se agregan las prácticas nuevas con el peso de la Lesson 1 del catálogo.
        foreach (DB::table('lessons')->whereNotNull('activity_weights')->get() as $lesson) {
            $weights = json_decode($lesson->activity_weights, true) ?: [];
            unset($weights['8'], $weights['9']);

            $template = DB::table('lessons')->where('book_id', $lesson->book_id)->where('number', 1)->value('activity_weights');
            $template = json_decode((string) $template, true) ?: [];
            $needed = DB::table('practices')->where('lesson_id', $lesson->id)->pluck('activity_number');

            foreach ($needed as $number) {
                if (! array_key_exists((string) $number, $weights) && isset($template[(string) $number])) {
                    $weights[(string) $number] = $template[(string) $number];
                }
            }

            ksort($weights);
            DB::table('lessons')->where('id', $lesson->id)->update(['activity_weights' => json_encode((object) $weights)]);
        }

        // Actividades: sin 8 y 9 (PR, DP, CE); la 7 es SP; sin la regla "uno a uno".
        DB::table('activities')->where('number', '>=', 8)->delete();
        DB::table('activities')->where('number', 7)->where('code', 'SD')->update(['code' => 'SP']);

        if (Schema::hasColumn('activities', 'is_one_to_one')) {
            Schema::table('activities', function (Blueprint $table) {
                $table->dropColumn('is_one_to_one');
            });
        }
    }

    public function down(): void
    {
        Schema::table('activities', function (Blueprint $table) {
            $table->boolean('is_one_to_one')->default(false)->after('minutes');
        });

        Schema::dropIfExists('practices');
    }
};
