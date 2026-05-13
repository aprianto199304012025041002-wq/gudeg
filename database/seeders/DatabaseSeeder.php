<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // Membuat akun Admin Utama secara otomatis jika belum ada di database
        User::firstOrCreate(
            ['email' => 'admin@dummy.com'],
            [
                'name'      => 'Administrator',
                'password'  => Hash::make('password123'), // Silakan ganti password default ini
                'role'      => 'admin',
                'is_active' => true,
            ]
        );
    }
}
