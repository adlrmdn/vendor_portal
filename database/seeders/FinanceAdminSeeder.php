<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class FinanceAdminSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        User::firstOrCreate(
            ['email' => 'finance.admin@mp.com'],
            [
                'id' => (string) Str::uuid(),
                'name' => 'Finance Admin',
                'password' => Hash::make('admin'),
                'role' => 'finance_admin',
            ]
        );
    }
}
