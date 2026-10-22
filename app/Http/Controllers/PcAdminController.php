<?php

namespace App\Http\Controllers;

use App\Jobs\PcSyncFeedJob;
use App\Models\Setting;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;

class PcAdminController extends Controller
{
    // PC is absorbed into the existing fabric admin area as a single tabbed
    // page (PcController::index()) — access is enforced by the same 'auth'
    // + 'role:admin,fabric_admin' middleware that already gates the rest of
    // /admin/* (routes/web.php). Every action here only mutates/polls and
    // redirects back to the relevant tab; the read-only listings themselves
    // are gathered by PcController::index().

    /**
     * Whether this account is a configured PC Approver — i.e. their email
     * appears in the `pc_approver_emails` setting. There is no dedicated
     * "pc_approver" role; the pool is existing fabric_admin/admin accounts,
     * chosen here instead. Mirrors SubconAdminController::isDirectorUser()
     * exactly (same Setting-list-of-emails pattern).
     */
    public static function isPcApproverUser($user): bool
    {
        $email = strtolower(trim((string) ($user->email ?? '')));
        if ($email === '') {
            return false;
        }

        try {
            return collect(preg_split('/[,;]+/', (string) Setting::getValue('pc_approver_emails')))
                ->map(fn ($e) => strtolower(trim($e)))
                ->filter()
                ->contains($email);
        } catch (\Throwable $e) {
            return false;
        }
    }

    /**
     * Manages the pc_approver_emails setting — mirrors
     * AdminController::workflow()/updateWorkflow()'s fabric_approver_email
     * shape exactly.
     */
    public function updateWorkflow(Request $request)
    {
        $multiEmail = function (string $attribute, $value, $fail) {
            foreach (preg_split('/[,;]+/', (string) $value) as $email) {
                $email = trim($email);
                if ($email === '') {
                    continue;
                }
                if (! filter_var($email, FILTER_VALIDATE_EMAIL)) {
                    $fail("The {$attribute} field contains an invalid email address: {$email}");
                }
            }
        };

        $request->validate([
            'pc_approver_emails' => ['nullable', $multiEmail],
        ]);

        Setting::updateOrCreate(
            ['key' => 'pc_approver_emails'],
            [
                'value' => $request->input('pc_approver_emails', ''),
                'group' => 'pc',
                'type' => 'string',
                'description' => 'Email address(es) of fabric_admin/admin accounts allowed to approve PC contracts (comma-separated for multiple)',
            ]
        );

        return redirect(route('admin.pc.data.index').'#approver-settings')->with('success', 'PC workflow settings updated successfully.');
    }

    /** JSON poll endpoint for the Sync tab's JS. */
    public function syncPoll()
    {
        $feeds = array_keys(PcSyncFeedJob::COMMANDS);

        return response()->json(array_combine($feeds, array_map(fn ($feed) => [
            'running' => (bool) Cache::get(PcSyncFeedJob::runningKey($feed)),
            'last' => Cache::get(PcSyncFeedJob::lastKey($feed)),
        ], $feeds)));
    }

    public function refreshFeed(Request $request, string $feed)
    {
        if (! array_key_exists($feed, PcSyncFeedJob::COMMANDS)) {
            abort(404);
        }

        if (Cache::get(PcSyncFeedJob::runningKey($feed))) {
            return response()->json([
                'running' => true,
                'message' => 'A sync for this feed is already running.',
            ], 409);
        }

        Cache::put(
            PcSyncFeedJob::runningKey($feed),
            ['started_at' => now()->toIso8601String(), 'by' => Auth::user()->name ?? Auth::user()->email],
            PcSyncFeedJob::RUNNING_TTL
        );

        PcSyncFeedJob::dispatch($feed);

        return response()->json([
            'running' => true,
            'message' => 'Sync started in the background.',
        ]);
    }
}
