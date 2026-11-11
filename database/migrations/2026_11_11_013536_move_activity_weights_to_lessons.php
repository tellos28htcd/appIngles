<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Pesos newton por lección: cada lección guarda el peso de cada actividad ({"1":5,"3":40,…}; sin la actividad =
 * no se hace en esa lección). Se llenan con los pesos por tipo que tenían las actividades de su escuela (o la base)
 * y esas columnas se quitan; en Actividades solo queda "uno a uno" (PR).
 */
return new class extends Migration
{
    private const TYPE_COLUMNS = ['lesson' => 'weight_lesson', 'checkpoint' => 'weight_checkpoint', 'verb_in_context' => 'weight_verb_in_context'];

    public function up(): void
    {
        Schema::table('lessons', function (Blueprint $table) {
            $table->json('activity_weights')->nullable()->after('type');
        });

        // Pesos por tipo de lección de cada catálogo (base = school_id null).
        $byCatalog = DB::table('activities')->where('is_active', true)->orderBy('number')->get()
            ->groupBy(fn ($activity) => (string) $activity->school_id);

        DB::table('lessons')->orderBy('id')->each(function ($lesson) use ($byCatalog) {
            $column = self::TYPE_COLUMNS[$lesson->type] ?? null;
            $weights = [];

            foreach ($column ? $byCatalog->get((string) $lesson->school_id, collect()) : [] as $activity) {
                if ($activity->{$column} !== null) {
                    $weights[(string) $activity->number] = (int) $activity->{$column};
                }
            }

            if ($weights !== []) {
                DB::table('lessons')->where('id', $lesson->id)->update(['activity_weights' => json_encode($weights)]);
            }
        });

        Schema::table('activities', function (Blueprint $table) {
            $table->dropColumn(['weight_lesson', 'weight_checkpoint', 'weight_verb_in_context']);
        });
    }

    public function down(): void
    {
        Schema::table('activities', function (Blueprint $table) {
            $table->unsignedSmallInteger('weight_lesson')->nullable()->after('minutes');
            $table->unsignedSmallInteger('weight_checkpoint')->nullable()->after('weight_lesson');
            $table->unsignedSmallInteger('weight_verb_in_context')->nullable()->after('weight_checkpoint');
        });

        Schema::table('lessons', function (Blueprint $table) {
            $table->dropColumn('activity_weights');
        });
    }
};
