<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Catálogos base de la plataforma y el Super Administrador inicial.
     */
    public function run(): void
    {
        $this->call([
            InegiCatalogSeeder::class,
            CatalogBaseSeeder::class,
            RoleSeeder::class,
            MenuSeeder::class,
            SuperAdminSeeder::class,
        ]);
    }
}
