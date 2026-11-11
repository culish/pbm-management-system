<?php

namespace Database\Seeders;

use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Seeder;

class AdminSeeder extends Seeder
{
    public function run(): void
    {
        $email = env('ADMIN_EMAIL');
        $password = env('ADMIN_PASSWORD');

        if (! $email || ! $password) {
            $this->command?->warn('ADMIN_EMAIL dan ADMIN_PASSWORD belum diisi. Akun administrator tidak dibuat.');

            return;
        }

        User::updateOrCreate(
            ['email' => $email],
            [
                'name' => env('ADMIN_NAME', 'Administrator PBM'),
                'password' => $password,
                'role_id' => Role::where('key', 'admin')->value('id'),
                'is_active' => true,
            ],
        );
    }
}
