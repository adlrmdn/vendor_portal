<?php

namespace App\Console\Commands;

use App\Exports\FabricVendorAccountsExport;
use App\Mail\FabricVendorAccountsMailable;
use App\Models\User;
use App\Models\Vendor;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Maatwebsite\Excel\Excel as ExcelWriter;
use Maatwebsite\Excel\Facades\Excel;

class RegisterFabricVendorAccounts extends Command
{
    protected $signature = 'vendors:register-fabric-accounts
                            {--email= : Send the resulting Excel roster to this address}
                            {--reset-password= : Reset EVERY fabric_vendor account (existing + new) to this password and include it in the export}
                            {--dry-run : List what would be created, write nothing and send nothing}';

    protected $description = 'Create a fabric_vendor login for any fabric Vendor missing one, then export the full account roster to Excel (optionally emailed)';

    public function handle()
    {
        $vendors = Vendor::where('type', 'fabric')->orderBy('name')->get();
        $this->info('Found '.$vendors->count().' fabric vendors.');

        $missing = $vendors->filter(fn ($v) => ! $v->users()->where('role', 'fabric_vendor')->exists());
        $resetPassword = $this->option('reset-password');

        if ($this->option('dry-run')) {
            if ($missing->isEmpty()) {
                $this->info('Dry run: every fabric vendor already has an account. Nothing to create.');
            } else {
                $this->warn('Dry run: '.$missing->count().' vendor(s) would get a new account:');
                $this->table(['Code', 'Name'], $missing->map(fn ($v) => [$v->vendor_code, $v->name])->all());
            }
            if ($resetPassword) {
                $this->warn('Dry run: would also reset the password for all '.$vendors->count().' fabric_vendor account(s).');
            }

            return Command::SUCCESS;
        }

        $newAccounts = 0;
        $resetAccounts = 0;
        $newCredentials = []; // vendor id => plaintext password to show in the export

        DB::beginTransaction();
        try {
            foreach ($missing as $vendor) {
                $email = $this->uniqueLoginEmail($vendor->name);
                $password = $resetPassword ?: Str::password(12, symbols: false);

                $user = new User([
                    'name' => $vendor->name,
                    'email' => $email,
                    'password' => bcrypt($password),
                    'role' => 'fabric_vendor',
                    'vendor_id' => $vendor->id,
                ]);
                $user->id = (string) Str::uuid();
                $user->save();

                $newCredentials[$vendor->id] = $password;
                $newAccounts++;
            }

            if ($resetPassword) {
                $existingUsers = User::where('role', 'fabric_vendor')
                    ->whereIn('vendor_id', $vendors->pluck('id'))
                    ->whereNotIn('vendor_id', $missing->pluck('id'))
                    ->get();

                foreach ($existingUsers as $user) {
                    $user->password = bcrypt($resetPassword);
                    $user->save();

                    $newCredentials[$user->vendor_id] = $resetPassword;
                    $resetAccounts++;
                }
            }

            DB::commit();
        } catch (\Throwable $e) {
            DB::rollBack();
            $this->error('Failed to register vendor accounts: '.$e->getMessage().' (rolled back — no changes).');

            return Command::FAILURE;
        }

        $this->info("Registered {$newAccounts} new fabric vendor account(s).");
        if ($resetPassword) {
            $this->info("Reset password for {$resetAccounts} existing fabric vendor account(s).");
        }

        // Reload so every vendor (existing + newly created) carries its current user.
        $vendors = Vendor::where('type', 'fabric')->orderBy('name')->with(['users' => function ($q) {
            $q->where('role', 'fabric_vendor');
        }])->get();

        $rows = $vendors->map(function ($vendor) use ($newCredentials, $missing, $resetPassword) {
            $user = $vendor->users->first();
            $isNew = $missing->contains('id', $vendor->id);
            $hasKnownPassword = isset($newCredentials[$vendor->id]);

            $status = 'Existing';
            if ($isNew) {
                $status = 'Newly registered';
            } elseif ($resetPassword && $hasKnownPassword) {
                $status = 'Password reset';
            } elseif (! $user) {
                $status = 'No account';
            }

            return [
                'code' => $vendor->vendor_code,
                'name' => $vendor->name,
                'group' => $vendor->group,
                'contact_email' => $vendor->contact_info['email'] ?? null,
                'phone' => $vendor->contact_info['phone'] ?? null,
                'login_email' => $user?->email,
                'password' => $hasKnownPassword ? $newCredentials[$vendor->id] : null,
                'status' => $status,
                'is_active' => (bool) $vendor->is_active,
            ];
        })->all();

        $binary = Excel::raw(new FabricVendorAccountsExport($rows), ExcelWriter::XLSX);
        $path = base_path('fabric_vendors_accounts.xlsx');
        file_put_contents($path, $binary);
        $this->info('Wrote vendor account roster to '.$path);

        $this->newLine();
        $this->table(
            ['Vendor Code', 'Vendor Name', 'Login Email', 'Status'],
            array_map(fn ($r) => [$r['code'], $r['name'], $r['login_email'] ?? '(no account)', $r['status']], $rows)
        );

        $recipient = $this->option('email');
        if ($recipient) {
            try {
                Mail::to($recipient)->send(new FabricVendorAccountsMailable($binary, $vendors->count(), $newAccounts, $resetAccounts));
                $this->info("Emailed the roster to {$recipient}.");
            } catch (\Throwable $e) {
                Log::error('Fabric vendor accounts email failed: '.$e->getMessage());
                $this->warn('Roster generated, but sending the email failed: '.$e->getMessage());
            }
        }

        return Command::SUCCESS;
    }

    /** Unique login email derived from the vendor name (same convention as d365:sync-fabric-vendors). */
    private function uniqueLoginEmail(string $vendorName): string
    {
        $cleanName = $this->abbreviateVendorName($vendorName);
        $email = "vendor@{$cleanName}.com";
        $baseEmail = $email;
        $counter = 1;
        while (User::where('email', $email)->exists()) {
            $email = str_replace('@', $counter.'@', $baseEmail);
            $counter++;
        }

        return $email;
    }

    private function abbreviateVendorName(string $name): string
    {
        $words = array_filter(explode(' ', strtolower($name)));
        $prefixes = ['pt', 'cv', 'fa', 'ud', 'pd', 'koperasi'];

        if (count($words) > 1 && in_array($words[0], $prefixes, true)) {
            array_shift($words);
        }

        if (empty($words)) {
            return 'fabric';
        }

        $concat = implode('', $words);
        $cleanConcat = preg_replace('/[^a-z0-9]/', '', $concat);

        if (strlen($cleanConcat) <= 15) {
            return $cleanConcat;
        }

        $firstWord = preg_replace('/[^a-z0-9]/', '', $words[0]);
        $initials = '';
        for ($i = 1; $i < count($words); $i++) {
            $char = substr($words[$i], 0, 1);
            $cleanChar = preg_replace('/[^a-z0-9]/', '', $char);
            $initials .= $cleanChar;
        }

        $result = $firstWord.$initials;
        if (strlen($result) > 15) {
            $result = substr($result, 0, 15);
        }

        return $result;
    }
}
