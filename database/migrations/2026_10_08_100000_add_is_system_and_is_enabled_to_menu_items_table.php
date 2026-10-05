<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('menu_items', function (Blueprint $table) {
            // Definida en código (MenuSeeder): no se elimina desde la pantalla.
            $table->boolean('is_system')->default(false)->after('status');
            // Interruptor del Super Admin: desactivada = oculta para todos y rutas bloqueadas.
            $table->boolean('is_enabled')->default(true)->after('is_system');
        });

        // Todo lo existente hasta hoy vino del código.
        DB::table('menu_items')->update(['is_system' => true]);
    }

    public function down(): void
    {
        Schema::table('menu_items', function (Blueprint $table) {
            $table->dropColumn(['is_system', 'is_enabled']);
        });
    }
};
