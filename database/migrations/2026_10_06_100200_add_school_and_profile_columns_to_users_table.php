<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->foreignId('school_id')->nullable()->after('role_id')->constrained()->restrictOnDelete();
            $table->string('first_name', 80)->nullable()->after('school_id');
            $table->string('last_name', 80)->nullable()->after('first_name');
            $table->string('second_last_name', 80)->nullable()->after('last_name');
            $table->text('notes')->nullable()->after('email');

            // Nadie captura contraseñas: queda nula hasta que el usuario la crea (RN-25).
            $table->string('password')->nullable()->change();

            $table->index(['school_id', 'role_id']);
            $table->index('name');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropForeign(['school_id']);
            $table->dropIndex(['school_id', 'role_id']);
            $table->dropIndex(['name']);
            $table->dropColumn('school_id');
            $table->dropColumn(['first_name', 'last_name', 'second_last_name', 'notes']);
            $table->string('password')->nullable(false)->change();
        });
    }
};
