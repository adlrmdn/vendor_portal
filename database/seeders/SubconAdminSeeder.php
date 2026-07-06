<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class SubconAdminSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        User::firstOrCreate(
            ['email' => 'subcon.admin@mp.com'],
            [
                'id' => (string) Str::uuid(),
                'name' => 'Subcon Admin',
                'password' => Hash::make('password'),
                'role' => 'subcon_admin',
            ]
        );
    }
}
