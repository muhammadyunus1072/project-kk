<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class UserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     * Creates default users for the Kartu Keluarga translation system.
     */
    public function run(): void
    {
        User::updateOrCreate(
            ['email' => 'adi@exata.site'],
            [
                'name'     => 'Adi Irawan',
                'password' => Hash::make('password'),
            ]
        );

        User::updateOrCreate(
            ['email' => 'admin@exata.site'],
            [
                'name'     => 'Administrator',
                'password' => Hash::make('password'),
            ]
        );

        User::updateOrCreate(
            ['email' => 'admin@gmail.com'],
            [
                'name'     => 'Administrator',
                'password' => Hash::make('password'),
            ]
        );
    }
}
