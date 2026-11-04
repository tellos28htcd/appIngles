<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Portal del alumno: una sola opción "Agendar" (prácticas y clubs). "Agendar club" se oculta
 * (se conserva su código y se puede reactivar desde Plataforma → Menú).
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::table('menu_items')->where('slug', 'student-portal-book-class')->where('label', 'Agendar clase')->update(['label' => 'Agendar']);
        DB::table('menu_items')->where('slug', 'student-portal-book-club')->update(['is_enabled' => false]);
    }

    public function down(): void
    {
        DB::table('menu_items')->where('slug', 'student-portal-book-class')->where('label', 'Agendar')->update(['label' => 'Agendar clase']);
        DB::table('menu_items')->where('slug', 'student-portal-book-club')->update(['is_enabled' => true]);
    }
};
