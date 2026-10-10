<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Salones, métodos de pago, conceptos de cobro y días sin clase pasan al mismo
 * esquema que los catálogos académicos: school_id nulo = catálogo base;
 * base_id = registro base del que viene la copia de cada escuela.
 */
return new class extends Migration
{
    private const TABLES = ['classrooms', 'payment_methods', 'charge_concepts', 'holidays'];

    public function up(): void
    {
        foreach (self::TABLES as $table) {
            Schema::table($table, function (Blueprint $blueprint) use ($table) {
                $blueprint->dropForeign(['school_id']);
                $blueprint->foreignId('school_id')->nullable()->change();
                $blueprint->foreign('school_id')->references('id')->on('schools')->cascadeOnDelete();
                $blueprint->foreignId('base_id')->nullable()->after('school_id')->constrained($table)->nullOnDelete();
            });
        }
    }

    public function down(): void
    {
        foreach (self::TABLES as $table) {
            Schema::table($table, function (Blueprint $blueprint) {
                $blueprint->dropConstrainedForeignId('base_id');
            });
        }
    }
};
