<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/** El menú "Mi escuela" pasa a llamarse "Mi Instituto" (si no se renombró a mano). */
return new class extends Migration
{
    public function up(): void
    {
        DB::table('menu_items')->where('slug', 'settings-school')->where('label', 'Mi escuela')->update(['label' => 'Mi Instituto']);
    }

    public function down(): void
    {
        DB::table('menu_items')->where('slug', 'settings-school')->where('label', 'Mi Instituto')->update(['label' => 'Mi escuela']);
    }
};
