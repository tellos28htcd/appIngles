<?php

namespace Database\Seeders;

use App\Enums\UserStatus;
use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class SuperAdminSeeder extends Seeder
{
    public function run(): void
    {
        $config = config('appingles.super_admin');
        $email = Str::lower($config['email']);

        if (User::where('email', $email)->exists()) {
            $this->command?->info("El Super Administrador {$email} ya existe; no se modificó.");

            return;
        }

        $password = $config['password'] ?: Str::password(16);

        User::create([
            'role_id' => Role::where('slug', Role::PLATFORM_ADMIN)->value('id'),
            'name' => $config['name'],
            'email' => $email,
            'password' => $password,
            'status' => UserStatus::Active,
        ])->markEmailAsVerified();

        $this->command?->info("Super Administrador creado: {$email}");

        if (! $config['password']) {
            $this->command?->warn("Contraseña generada (cópiala ahora, no se vuelve a mostrar): {$password}");
        }
    }
}
