<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * Subcon workflow team accounts (2026-07): the MD Production approver list
 * plus the Director. Names follow the signature convention — Title Case of
 * the email local part ('fitri.yeni@…' → 'Fitri Yeni') — because the QC
 * approval chain resolves signature names from these accounts.
 */
class SubconTeamSeeder extends Seeder
{
    private const TEAM = [
        'fitri.yeni@megaputragarment.co.id',
        'canya.naomi@megaperintis.co.id',
        'maria.sari@megaputragarment.co.id',
        'riena@megaputragarment.co.id',
        'dian.winarni@megaputragarment.co.id',
        'ika@megaputragarment.co.id',
        'luki.rusli@megaperintis.co.id', // Director
        'adil.ramadhan@megaperintis.co.id', // Admin — director-flow testing
    ];

    public function run(): void
    {
        foreach (self::TEAM as $email) {
            $name = Str::title(str_replace(['.', '_', '-'], ' ', Str::before($email, '@')));

            $user = User::firstOrCreate(
                ['email' => $email],
                [
                    'id' => (string) Str::uuid(),
                    'name' => $name,
                    'password' => Hash::make('password'),
                    'role' => 'subcon_admin',
                ]
            );

            $this->command?->info(sprintf(
                '%s %s <%s> (%s)',
                $user->wasRecentlyCreated ? 'Created' : 'Exists ',
                $user->name,
                $user->email,
                $user->role
            ));
        }
    }
}
