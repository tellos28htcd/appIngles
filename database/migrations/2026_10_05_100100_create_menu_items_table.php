<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('menu_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('parent_id')->nullable()->constrained('menu_items')->cascadeOnDelete();
            $table->string('slug', 80)->unique();
            $table->string('label', 100);
            $table->string('icon', 40)->nullable();
            $table->string('route_name', 120)->nullable()->unique();
            $table->string('status', 20)->default('active');
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();

            $table->index(['parent_id', 'sort_order']);
        });

        Schema::create('menu_item_role', function (Blueprint $table) {
            $table->foreignId('menu_item_id')->constrained()->cascadeOnDelete();
            $table->foreignId('role_id')->constrained()->cascadeOnDelete();

            $table->primary(['menu_item_id', 'role_id']);
            $table->index('role_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('menu_item_role');
        Schema::dropIfExists('menu_items');
    }
};
