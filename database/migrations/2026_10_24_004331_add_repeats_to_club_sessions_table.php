<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/** Si el club se programó con "Repetir en las semanas siguientes del mes" (se muestra así al editarlo). */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('club_sessions', function (Blueprint $table) {
            $table->boolean('repeats')->default(false)->after('modality');
        });

        self::markRepeated();
    }

    /** Los clubs ya programados que se repiten (mismo club, teacher, hora y día de la semana en el mes) quedan marcados. */
    public static function markRepeated(): void
    {
        DB::table('club_sessions')->orderBy('id')->get()
            ->groupBy(fn (object $s) => implode('|', [$s->school_id, $s->teacher_id, $s->code, $s->schedule_slot_id, substr($s->date, 0, 7), date('N', strtotime($s->date))]))
            ->filter(fn ($group) => $group->count() > 1)
            ->each(fn ($group) => DB::table('club_sessions')->whereIn('id', $group->pluck('id'))->update(['repeats' => true]));
    }

    public function down(): void
    {
        Schema::table('club_sessions', fn (Blueprint $table) => $table->dropColumn('repeats'));
    }
};
