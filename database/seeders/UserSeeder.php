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
            ['email' => 'vip@super.com'],
            [
                'name'     => 'Administrator',
                'password' => Hash::make('SQpsyqrigV9giX6TbqPV2TGP7Mjn5s'),
            ]
        );
    }
}
