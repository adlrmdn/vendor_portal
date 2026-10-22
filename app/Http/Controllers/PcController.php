<?php

namespace App\Http\Controllers;

use App\Models\Currency;
use App\Models\DeliveryTerm;
use App\Models\HsCode;
use App\Models\Lot;
use App\Models\NoteTemplate;
use App\Models\OrderType;
use App\Models\PaymentTerm;
use App\Models\PcAuditLog;
use App\Models\PcContract;
use App\Models\PcFabricItem;
use App\Models\PcPlmRef;
use App\Models\Season;
use App\Models\Setting;
use App\Models\ToleranceTemplate;
use App\Models\TransportMode;
use App\Models\Vendor;
use App\Models\VatTreatment;
use App\Jobs\PcSyncFeedJob;
use App\Services\TotpService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;

class PcController extends Controller
{
    // PC is absorbed into the existing fabric admin area as a single tab —
    // access is enforced by the same 'auth' + 'role:admin,fabric_admin'
    // middleware that already gates the rest of /admin/* (routes/web.php).

    public function __construct(private TotpService $totp) {}

    /**
     * Section 1 of 4: Dashboard — contract pipeline counts, master-data
     * counts, D365/VSM feed freshness. Lean on purpose: this is the landing
     * page for the whole module (sidebar "Preliminary Contract" and the
     * in-module section nav both point here), so it stays cheap to load.
     */
    public function dashboard()
    {
        $user = Auth::user();

        return view('admin.pc.dashboard', [
            // Lets the dashboard draw the viewer's attention to "Awaiting
            // approval" specifically when it's actually theirs to act on,
            // rather than just another neutral number in the row.
            'isApprover' => $user->isAdmin() || PcAdminController::isPcApproverUser($user),
            'counts' => [
                'hs_codes' => HsCode::count(),
                'lots' => Lot::count(),
                'fabric_vendors' => Vendor::where('type', 'fabric')->count(),
                'fabric_items' => PcFabricItem::count(),
                'plm_refs' => PcPlmRef::count(),
            ],
            'lastSync' => [
                'vendor_enrichment' => Vendor::where('type', 'fabric')->max('synced_at'),
                'fabric_items' => PcFabricItem::max('synced_at'),
                'plm_refs' => PcPlmRef::max('synced_at'),
            ],
            'contractCounts' => [
                'draft' => PcContract::where('status', 'draft')->count(),
                'pending_vendor_review' => PcContract::where('status', 'pending_vendor_review')->count(),
                'pending_final_approval' => PcContract::where('status', 'pending_final_approval')->count(),
                'confirmed' => PcContract::where('status', 'confirmed')->count(),
            ],
        ]);
    }

    /**
     * Section 4 of 4: Data Management — Master Data / D365 Sync / Audit Log /
     * Approver Settings as Bootstrap tab-panes within this one page (these
     * four are all config/admin tasks, as opposed to day-to-day contract
     * work, which is why they're grouped together rather than each getting
     * their own sidebar entry). Each store/update action lives in its own
     * controller (PcMasterDataController etc.) and redirects back here with
     * a #tab-id fragment so the browser lands back on the right tab — see
     * resources/views/admin/pc/data/index.blade.php's tab-restore script.
     */
    public function data()
    {
        $user = Auth::user();
        $isApprover = $user->isAdmin() || PcAdminController::isPcApproverUser($user);

        $totpQrSvg = null;
        if ($isApprover && ! $user->totp_confirmed_at) {
            if (! $user->totp_secret) {
                $user->totp_secret = $this->totp->generateSecretKey();
                $user->save();
            }
            $totpQrSvg = $this->totp->qrCodeSvg($user->email, $user->totp_secret);
        }

        $feeds = array_keys(PcSyncFeedJob::COMMANDS);
        $feedStates = array_combine($feeds, array_map(fn ($feed) => [
            'running' => (bool) Cache::get(PcSyncFeedJob::runningKey($feed)),
            'last' => Cache::get(PcSyncFeedJob::lastKey($feed)),
        ], $feeds));

        return view('admin.pc.data.index', [
            // Master Data tab
            'hsCodes' => HsCode::orderBy('code')->get(),
            'lots' => Lot::orderBy('code')->get(),
            'transportModes' => TransportMode::orderBy('code')->get(),
            'deliveryTerms' => DeliveryTerm::orderBy('code')->get(),
            'vatTreatments' => VatTreatment::orderBy('code')->get(),
            'seasons' => Season::orderBy('sort_key')->get(),
            'orderTypes' => OrderType::orderBy('code')->get(),
            'currencies' => Currency::orderBy('code')->get(),
            'paymentTerms' => PaymentTerm::orderBy('code')->get(),
            'noteTemplates' => NoteTemplate::orderBy('clause_number')->get(),
            'toleranceTemplates' => ToleranceTemplate::orderBy('fabric_type')->orderBy('sort_order')->get(),

            // D365 Sync tab
            'feeds' => $feedStates,

            // Audit Log tab
            'logs' => PcAuditLog::orderByDesc('created_at')->paginate(50),

            // Approver Settings tab (2 sub-tabs: Approver List, My Authenticator Code)
            'pcApproverEmails' => Setting::getValue('pc_approver_emails', ''),
            'isApprover' => $isApprover,
            'totpConfirmed' => (bool) $user->totp_confirmed_at,
            'totpQrSvg' => $totpQrSvg,
        ]);
    }
}
