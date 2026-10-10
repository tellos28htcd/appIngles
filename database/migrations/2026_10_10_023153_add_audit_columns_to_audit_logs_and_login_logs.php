<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Nombre legible del registro afectado al momento del evento (sobrevive aunque se elimine).
        Schema::table('audit_logs', function (Blueprint $table) {
            $table->string('auditable_label', 160)->nullable()->after('auditable_id');
            $table->index(['event', 'created_at']);
        });

        // Escuela del usuario que inició sesión: el administrador de escuela ve los accesos de su gente.
        Schema::table('login_logs', function (Blueprint $table) {
            $table->foreignId('school_id')->nullable()->after('user_id')->constrained()->nullOnDelete();
            $table->index(['school_id', 'created_at']);
            $table->index(['event', 'created_at']);
        });

        DB::table('login_logs')
            ->whereNotNull('user_id')
            ->update(['school_id' => DB::raw('(select users.school_id from users where users.id = login_logs.user_id)')]);

        // Los eventos de una escuela (alta, copia de catálogos) pertenecen a esa escuela.
        DB::table('audit_logs')
            ->where('auditable_type', 'App\\Models\\School')
            ->whereIn('auditable_id', DB::table('schools')->select('id'))
            ->update(['school_id' => DB::raw('auditable_id')]);
    }

    public function down(): void
    {
        Schema::table('login_logs', function (Blueprint $table) {
            $table->dropIndex(['event', 'created_at']);
            $table->dropForeign(['school_id']);
            $table->dropIndex(['school_id', 'created_at']);
            $table->dropColumn('school_id');
        });

        Schema::table('audit_logs', function (Blueprint $table) {
            $table->dropIndex(['event', 'created_at']);
            $table->dropColumn('auditable_label');
        });
    }
};
