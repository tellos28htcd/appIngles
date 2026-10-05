<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Roles base del catálogo: no se eliminan. Los creados desde la pantalla sí (si no tienen usuarios).
        Schema::table('roles', function (Blueprint $table) {
            $table->boolean('is_system')->default(false)->after('scope');
        });

        // Bitácora de cambios (quién, cuándo, qué). Solo se inserta y se conserva para siempre (RN-12).
        Schema::create('audit_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('school_id')->nullable()->constrained()->nullOnDelete();
            $table->string('auditable_type', 120);
            $table->unsignedBigInteger('auditable_id');
            $table->string('event', 40);
            $table->json('old_values')->nullable();
            $table->json('new_values')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['auditable_type', 'auditable_id', 'created_at']);
            $table->index(['school_id', 'created_at']);
        });

        // Nuevo nombre del módulo; desde ahora el nombre se edita en pantalla.
        DB::table('menu_items')
            ->where('slug', 'platform-roles')
            ->where('label', 'Roles y menú')
            ->update(['label' => 'Roles y permisos']);
    }

    public function down(): void
    {
        Schema::dropIfExists('audit_logs');

        Schema::table('roles', function (Blueprint $table) {
            $table->dropColumn('is_system');
        });
    }
};
