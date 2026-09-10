<?php

namespace App\Http\Controllers;

use App\Exports\SubconCuttingReportExport;
use App\Jobs\SyncSubconOrdersJob;
use App\Models\SubconCuttingReport;
use App\Models\SubconOrder;
use App\Models\User;
use App\Models\Vendor;
use App\Services\QcReportPdfService;
use App\Services\RpaQueueReadService;
use App\Services\SubconConsumptionService;
use App\Services\SubconLabelService;
use App\Services\SubconProductionService;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Maatwebsite\Excel\Facades\Excel;

class SubconAdminController extends Controller
{
    public function __construct(private RpaQueueReadService $rpaQueueReader, private QcReportPdfService $reportPdf)
    {
        $this->middleware('auth');
        $this->middleware(function ($request, $next) {
            if (! in_array(Auth::user()->role, ['admin', 'subcon_admin'])) {
                abort(403, 'Unauthorized access.');
            }

            return $next($request);
        });
    }

    public function dashboard()
    {
        $stats = [
            'total_orders' => SubconOrder::count(),
            'active_orders' => SubconOrder::whereIn('status', ['pending', 'in_progress'])->count(),
            'completed' => SubconOrder::where('status', 'completed')->count(),
            'total_vendors' => Vendor::where('type', 'subcon')->where('is_active', true)->count(),
            'pending_approvals' => SubconOrder::whereIn('workflow_stage', [SubconOrder::STAGE_CUTTING_REVIEW, SubconOrder::STAGE_GRAMASI_REVIEW])->count()
                + SubconOrder::pendingFinalApprovalCount(),
        ];

        $recentOrders = SubconOrder::with('vendor')
            ->orderBy('created_at', 'desc')
            ->limit(10)
            ->get();

        $syncRunning = (bool) Cache::get(SyncSubconOrdersJob::RUNNING_KEY);
        $syncLast = Cache::get(SyncSubconOrdersJob::LAST_KEY);

        return view('subcon.admin.dashboard', compact('stats', 'recentOrders', 'syncRunning', 'syncLast'));
    }

    /**
     * Read-only view of invoice RPA jobs for subcon vendors — same underlying
     * data as the finance admin Invoices tab (RpaQueueReadService), scoped to
     * portal='Subcon' (cross-vendor, matches the admin's domain) and with no
     * write actions (no Checked toggle, no force-complete — that stays
     * exclusive to Finance). Reports are still viewable/downloadable — that's
     * a read, not a mutation.
     */
    public function invoices(Request $request)
    {
        $rows = $this->rpaQueueReader->rows('invoice')->where('portal', 'Subcon')->values();

        $status = $request->query('status', '');
        if ($status !== '' && in_array($status, RpaQueueReadService::STATUSES, true)) {
            $rows = $rows->where('status', $status);
        }

        $checked = $request->query('checked', '');
        if ($checked !== '') {
            $rows = $rows->where('checked', $checked === '1');
        }

        $search = trim((string) $request->query('q', ''));
        if ($search !== '') {
            $needle = strtolower($search);
            $rows = $rows->filter(fn ($row) => str_contains(strtolower($row->po), $needle)
                || str_contains(strtolower($row->vendor_name), $needle));
        }

        $rows = $rows->values();
        $perPage = 25;
        $page = max(1, (int) $request->query('page', 1));

        $paginated = new LengthAwarePaginator(
            $rows->slice(($page - 1) * $perPage, $perPage)->values(),
            $rows->count(),
            $perPage,
            $page,
            ['path' => $request->url(), 'query' => $request->query()]
        );

        // PO -> work order id, so the PO cell can link straight to the order
        // (only the current page's rows need it — one batched lookup).
        $poNumbers = $paginated->getCollection()->pluck('po')->filter()->unique()->values()->all();
        $orderIdsByPo = SubconOrder::whereIn('order_number', $poNumbers)->pluck('id', 'order_number');
        $paginated->getCollection()->transform(function ($row) use ($orderIdsByPo) {
            $row->work_order_id = $orderIdsByPo->get($row->po);

            return $row;
        });

        return view('subcon.admin.invoices', [
            'rows' => $paginated,
            'filters' => ['status' => $status, 'checked' => $checked, 'q' => $search],
        ]);
    }

    /**
     * Same PDF stream as the finance admin report route, but scoped: only
     * serves invoice jobs whose vendor actually resolves to a subcon vendor,
     * keeping fabric-side jobs (if that ever exists) out of this admin's view.
     */
    public function invoiceReport(int $id)
    {
        $row = DB::connection('rpa')->table('rpa_queues')
            ->where('id', $id)->where('rpa_type', 'invoice')
            ->first(['id', 'payload']);

        if (! $row) {
            abort(404);
        }

        $payload = json_decode($row->payload ?? '', true) ?: [];
        $vendorName = (string) ($payload['vendor_name'] ?? '');
        $vendorType = Vendor::where('name', 'ILIKE', $vendorName)->value('type');

        if ($vendorType !== 'subcon') {
            abort(404);
        }

        $signedDoc = $this->rpaQueueReader->resolveSignedDoc($payload);
        if ($signedDoc === '' || ! str_contains($signedDoc, 'base64,')) {
            abort(404, 'No report attached to this job.');
        }

        $pdf = base64_decode(substr($signedDoc, strpos($signedDoc, 'base64,') + 7));

        return new Response($pdf, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="invoice-'.$id.'.pdf"',
        ]);
    }

    /**
     * Read-only view of debit note (deduction) RPA jobs for subcon vendors —
     * mirrors invoices() above. Includes 'review' status (RpaQueueReadService
     * ::STATUSES) so subcon admin can see debit notes finance hasn't released
     * yet; release itself stays exclusive to Finance
     * (FinanceAdminController::releaseDeduction).
     */
    public function debitNotes(Request $request)
    {
        $rows = $this->rpaQueueReader->rows('deduction')->where('portal', 'Subcon')->values();

        $status = $request->query('status', '');
        if ($status !== '' && in_array($status, RpaQueueReadService::STATUSES, true)) {
            $rows = $rows->where('status', $status);
        }

        $checked = $request->query('checked', '');
        if ($checked !== '') {
            $rows = $rows->where('checked', $checked === '1');
        }

        $search = trim((string) $request->query('q', ''));
        if ($search !== '') {
            $needle = strtolower($search);
            $rows = $rows->filter(fn ($row) => str_contains(strtolower($row->po), $needle)
                || str_contains(strtolower($row->vendor_name), $needle));
        }

        $rows = $rows->values();
        $perPage = 25;
        $page = max(1, (int) $request->query('page', 1));

        $paginated = new LengthAwarePaginator(
            $rows->slice(($page - 1) * $perPage, $perPage)->values(),
            $rows->count(),
            $perPage,
            $page,
            ['path' => $request->url(), 'query' => $request->query()]
        );

        $poNumbers = $paginated->getCollection()->pluck('po')->filter()->unique()->values()->all();
        $orderIdsByPo = SubconOrder::whereIn('order_number', $poNumbers)->pluck('id', 'order_number');
        $paginated->getCollection()->transform(function ($row) use ($orderIdsByPo) {
            $row->work_order_id = $orderIdsByPo->get($row->po);

            return $row;
        });

        return view('subcon.admin.debit-notes', [
            'rows' => $paginated,
            'filters' => ['status' => $status, 'checked' => $checked, 'q' => $search],
        ]);
    }

    /**
     * Same PDF stream as invoiceReport(), scoped to rpa_type='deduction' —
     * but always the plain report (RpaQueueReadService::resolveOriginalReport),
     * never the debit-note-combined document Finance's tab shows. Falls back
     * to resolveSignedDoc() for rows queued before original_report existed
     * (queueDeduction() only started writing it once this split landed).
     */
    public function debitNoteReport(int $id)
    {
        $row = DB::connection('rpa')->table('rpa_queues')
            ->where('id', $id)->where('rpa_type', 'deduction')
            ->first(['id', 'payload']);

        if (! $row) {
            abort(404);
        }

        $payload = json_decode($row->payload ?? '', true) ?: [];
        $vendorName = (string) ($payload['vendor_name'] ?? '');
        $vendorType = Vendor::where('name', 'ILIKE', $vendorName)->value('type');

        if ($vendorType !== 'subcon') {
            abort(404);
        }

        $signedDoc = $this->rpaQueueReader->resolveOriginalReport($payload);
        if ($signedDoc === '' || ! str_contains($signedDoc, 'base64,')) {
            $signedDoc = $this->rpaQueueReader->resolveSignedDoc($payload);
        }
        if ($signedDoc === '' || ! str_contains($signedDoc, 'base64,')) {
            abort(404, 'No report attached to this job.');
        }

        $pdf = base64_decode(substr($signedDoc, strpos($signedDoc, 'base64,') + 7));

        return new Response($pdf, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="debit-note-'.$id.'.pdf"',
        ]);
    }

    /**
     * Trigger a D365 subcon PO sync in the background. Returns immediately;
     * the dashboard polls syncStatus() until it finishes. Refuses to start a
     * second run while one is already in progress.
     */
    public function syncOrders(Request $request)
    {
        if (Cache::get(SyncSubconOrdersJob::RUNNING_KEY)) {
            return response()->json([
                'running' => true,
                'message' => 'A sync is already running.',
            ], 409);
        }

        Cache::put(
            SyncSubconOrdersJob::RUNNING_KEY,
            ['started_at' => now()->toIso8601String(), 'by' => Auth::user()->name ?? Auth::user()->email],
            SyncSubconOrdersJob::RUNNING_TTL
        );

        SyncSubconOrdersJob::dispatch();

        return response()->json([
            'running' => true,
            'message' => 'PO sync started in the background.',
        ]);
    }

    /** Poll endpoint: current sync state + last finished outcome. */
    public function syncStatus()
    {
        return response()->json([
            'running' => (bool) Cache::get(SyncSubconOrdersJob::RUNNING_KEY),
            'stage' => Cache::get(SyncSubconOrdersJob::STAGE_KEY),
            'last' => Cache::get(SyncSubconOrdersJob::LAST_KEY),
        ]);
    }

    public function logs(Request $request)
    {
        $query = \App\Models\SubconJobLog::with('order');

        $search = trim((string) $request->query('q', ''));
        if ($search !== '') {
            $driver = DB::connection()->getDriverName();
            $likeOperator = $driver === 'pgsql' ? 'ilike' : 'like';
            $query->where('order_number', $likeOperator, '%'.$search.'%');
        }
        $jobType = (string) $request->query('job_type', '');
        if ($jobType !== '') {
            $query->where('job_type', $jobType);
        }
        $status = (string) $request->query('status', '');
        if ($status !== '') {
            $query->where('status', $status);
        }

        $logs = $query->orderBy('created_at', 'desc')->paginate(30)->withQueryString();

        return view('subcon.admin.logs.index', compact('logs', 'search', 'jobType', 'status'));
    }

    public function vendors(Request $request)
    {
        $query = Vendor::where('type', 'subcon')
            ->withCount('subconOrders')
            ->with(['users' => function ($q) {
                $q->where('role', 'subcon_vendor');
            }]);

        if ($search = trim((string) $request->input('search'))) {
            $query->where(function ($q) use ($search) {
                $term = '%'.strtolower($search).'%';
                $q->whereRaw('LOWER(name) LIKE ?', [$term])
                    ->orWhereRaw('LOWER(vendor_code) LIKE ?', [$term])
                    ->orWhereRaw('LOWER("group") LIKE ?', [$term])
                    ->orWhereHas('users', function ($userQuery) use ($term) {
                        $userQuery->whereRaw('LOWER(email) LIKE ?', [$term]);
                    });
            });
        }

        $vendors = $query->orderBy('name')
            ->paginate(20)
            ->withQueryString();

        return view('subcon.admin.vendors', compact('vendors'));
    }

    public function resetVendorPassword(Request $request, string $id)
    {
        $vendor = Vendor::where('type', 'subcon')->findOrFail($id);

        $data = $request->validate([
            'password' => 'nullable|string|min:6|max:255',
        ]);

        $password = $data['password'] ?? 'password';

        $users = User::where('vendor_id', $vendor->id)
            ->where('role', 'subcon_vendor')
            ->get();

        if ($users->isEmpty()) {
            $loginEmail = $this->deriveVendorLoginEmail($vendor->name);
            $user = new User([
                'name' => $vendor->name,
                'email' => $loginEmail,
                'password' => bcrypt($password),
                'role' => 'subcon_vendor',
                'vendor_id' => $vendor->id,
            ]);
            $user->id = (string) Str::uuid();
            $user->save();
            $email = $loginEmail;
        } else {
            foreach ($users as $user) {
                $user->password = bcrypt($password);
                $user->save();
            }
            $email = $users->first()->email;
        }

        return redirect()->route('subcon.admin.vendors', $request->only('search'))
            ->with('success', 'Password reset successfully for vendor "'.$vendor->name.'".')
            ->with('new_vendor_credentials', [
                'name' => $vendor->name,
                'login_email' => $email,
                'password' => $password,
                'is_reset' => true,
            ]);
    }

    public function createVendorAccount(Request $request, string $id)
    {
        $vendor = Vendor::where('type', 'subcon')->findOrFail($id);

        $existingUser = User::where('vendor_id', $vendor->id)
            ->where('role', 'subcon_vendor')
            ->first();

        if ($existingUser) {
            return redirect()->route('subcon.admin.vendors', $request->only('search'))
                ->with('warning', 'Vendor already has a portal login account ('.$existingUser->email.').');
        }

        $loginEmail = $this->deriveVendorLoginEmail($vendor->name);
        $password = 'password';

        $user = new User([
            'name' => $vendor->name,
            'email' => $loginEmail,
            'password' => bcrypt($password),
            'role' => 'subcon_vendor',
            'vendor_id' => $vendor->id,
        ]);
        $user->id = (string) Str::uuid();
        $user->save();

        return redirect()->route('subcon.admin.vendors', $request->only('search'))
            ->with('success', 'Portal login account created for vendor "'.$vendor->name.'".')
            ->with('new_vendor_credentials', [
                'name' => $vendor->name,
                'login_email' => $loginEmail,
                'password' => $password,
            ]);
    }

    public function storeVendor(Request $request)
    {
        $data = $request->validate([
            'name' => 'required|string|max:255',
            'vendor_code' => 'required|string|max:50|unique:vendors,vendor_code',
            'group' => 'nullable|string|max:100',
            'contact_info.phone' => 'nullable|string|max:255',
            'contact_info.email' => 'nullable|email|max:255',
            'contact_info.address' => 'nullable|string|max:1000',
        ]);

        // Create the vendor + its portal login account together, so the admin
        // gets usable credentials to hand over the moment the vendor is saved.
        $vendorId = Str::uuid()->toString();
        $loginEmail = $this->deriveVendorLoginEmail($data['name']);
        // Basic shared default password — the vendor is expected to change it.
        $password = 'password';

        DB::transaction(function () use ($data, $vendorId, $loginEmail, $password) {
            // 'id' isn't mass-assignable on Vendor (no creating hook either), so
            // Vendor::create(['id' => ...]) silently drops it and the DB assigns
            // its own id — set it directly, matching SyncD365SubconVendors.
            $vendor = new Vendor([
                'name' => $data['name'],
                'vendor_code' => $data['vendor_code'],
                'group' => $data['group'] ?? null,
                'type' => 'subcon',
                'contact_info' => array_filter([
                    'phone' => $data['contact_info']['phone'] ?? null,
                    'email' => $data['contact_info']['email'] ?? null,
                    'address' => $data['contact_info']['address'] ?? null,
                ], fn ($v) => $v !== null),
                'is_active' => true,
            ]);
            $vendor->id = $vendorId;
            $vendor->save();

            $user = new User([
                'name' => $data['name'],
                'email' => $loginEmail,
                'password' => bcrypt($password),
                'role' => 'subcon_vendor',
                'vendor_id' => $vendorId,
            ]);
            $user->id = (string) Str::uuid();
            $user->save();
        });

        // Flash the plaintext password once — it is never stored in readable form,
        // so this is the only chance to copy it. Shown in a modal on redirect.
        return redirect()->route('subcon.admin.vendors')
            ->with('success', 'Subcon vendor "'.$data['name'].'" created with a portal login.')
            ->with('new_vendor_credentials', [
                'name' => $data['name'],
                'login_email' => $loginEmail,
                'password' => $password,
            ]);
    }

    /**
     * Derive a unique, readable portal login email from a vendor name: keep a
     * leading company-form prefix (PT/CV/…), spell the first distinctive word in
     * full, then append the initials of the remaining words. e.g.
     * "PT. TUPAI ADYAMAS INDONESIA" → vendor@pttupaiai.com (suffixing on collision).
     */
    private function deriveVendorLoginEmail(string $name): string
    {
        $words = array_values(array_filter(
            array_map(fn ($w) => preg_replace('/[^a-z0-9]/', '', strtolower($w)), explode(' ', $name)),
            fn ($w) => $w !== ''
        ));

        $prefixes = ['pt', 'cv', 'fa', 'ud', 'pd', 'koperasi'];
        $slug = '';
        if (! empty($words)) {
            $start = 0;
            // Keep a leading company-form prefix as-is (e.g. "pt").
            if (count($words) > 1 && in_array($words[0], $prefixes, true)) {
                $slug .= $words[0];
                $start = 1;
            }
            // First meaningful word in full, then initials of the rest.
            $slug .= $words[$start] ?? '';
            for ($i = $start + 1; $i < count($words); $i++) {
                $slug .= substr($words[$i], 0, 1);
            }
        }
        if ($slug === '') {
            $slug = 'subcon';
        }

        $email = "vendor@{$slug}.com";
        $counter = 1;
        while (User::where('email', $email)->exists()) {
            $email = "vendor@{$slug}{$counter}.com";
            $counter++;
        }

        return $email;
    }

    public function updateVendor(Request $request, string $id)
    {
        // Scope to subcon: a subcon admin must never act on a fabric vendor.
        $vendor = Vendor::where('type', 'subcon')->findOrFail($id);

        $data = $request->validate([
            'name' => 'required|string|max:255',
            'vendor_code' => 'required|string|max:50|unique:vendors,vendor_code,'.$id,
            'group' => 'nullable|string|max:100',
            'contact_info.phone' => 'nullable|string|max:255',
            'contact_info.email' => 'nullable|email|max:255',
            'contact_info.address' => 'nullable|string|max:1000',
            'is_active' => 'nullable|boolean',
        ]);

        $vendor->update([
            'name' => $data['name'],
            'vendor_code' => $data['vendor_code'],
            'group' => $data['group'] ?? null,
            'contact_info' => array_filter([
                'phone' => $data['contact_info']['phone'] ?? null,
                'email' => $data['contact_info']['email'] ?? null,
                'address' => $data['contact_info']['address'] ?? null,
            ], fn ($v) => $v !== null),
            'is_active' => $request->has('is_active') ? (bool) $request->input('is_active') : false,
        ]);

        return redirect()->route('subcon.admin.vendors')->with('success', 'Subcon vendor updated.');
    }

    public function toggleVendorStatus(string $id)
    {
        $vendor = Vendor::where('type', 'subcon')->findOrFail($id);
        $vendor->is_active = ! $vendor->is_active;
        $vendor->save();

        $status = $vendor->is_active ? 'activated' : 'deactivated';

        return redirect()->route('subcon.admin.vendors')->with('success', 'Subcon vendor '.$status.' successfully.');
    }

    public function deleteVendor(string $id)
    {
        $vendor = Vendor::where('type', 'subcon')->findOrFail($id);

        \Illuminate\Support\Facades\DB::transaction(function () use ($vendor) {
            // Delete associated portal login users to avoid orphans
            \App\Models\User::where('vendor_id', $vendor->id)->delete();

            // Delete vendor (cascade deletes orders and cutting reports)
            $vendor->delete();
        });

        return redirect()->route('subcon.admin.vendors')->with('success', 'Subcon vendor and all associated data deleted.');
    }

    public function orders(Request $request)
    {
        $query = SubconOrder::with('vendor');

        if ($request->filled('vendor_id')) {
            $query->where('vendor_id', $request->vendor_id);
        }
        if ($request->filled('workflow_stage')) {
            $query->where('workflow_stage', $request->workflow_stage);
        }
        if ($request->filled('search')) {
            $search = trim($request->search);
            $words = array_filter(explode(' ', $search));
            $driver = \Illuminate\Support\Facades\DB::connection()->getDriverName();
            $likeOperator = $driver === 'pgsql' ? 'ilike' : 'like';

            $query->where(function ($q) use ($words, $likeOperator) {
                foreach ($words as $word) {
                    $q->where(function ($sub) use ($word, $likeOperator) {
                        $sub->where('order_number', $likeOperator, '%'.$word.'%')
                            ->orWhere('title', $likeOperator, '%'.$word.'%')
                            ->orWhere('production_group', $likeOperator, '%'.$word.'%');
                    });
                }
            });
        }

        $orders = $query->orderBy('created_at', 'desc')->paginate(20);
        $vendors = Vendor::where('type', 'subcon')->where('is_active', true)->orderBy('name')->get();

        // Post-completion QC/Finance pipeline stage, batched for the page of
        // "Completed" rows only — see qcPipelineStatusForOrders()'s docblock
        // for why the plain workflow_stage/status columns go silent here.
        $qcPipelineByOrder = $this->qcPipelineStatusForOrders(collect($orders->items()));

        return view('subcon.admin.orders.index', compact('orders', 'vendors', 'qcPipelineByOrder'));
    }

    /** Orders currently awaiting a cutting or gramasi approval decision. */
    public function approvals(Request $request)
    {
        $search = trim((string) $request->query('q', ''));

        $query = SubconOrder::with('vendor')
            ->whereIn('workflow_stage', [SubconOrder::STAGE_CUTTING_REVIEW, SubconOrder::STAGE_GRAMASI_REVIEW]);

        if ($search !== '') {
            $words = array_filter(explode(' ', $search));
            $driver = DB::connection()->getDriverName();
            $likeOperator = $driver === 'pgsql' ? 'ilike' : 'like';

            $query->where(function ($q) use ($words, $likeOperator) {
                foreach ($words as $word) {
                    $q->where(function ($sub) use ($word, $likeOperator) {
                        $sub->where('order_number', $likeOperator, '%'.$word.'%')
                            ->orWhere('title', $likeOperator, '%'.$word.'%')
                            ->orWhere('production_group', $likeOperator, '%'.$word.'%')
                            ->orWhereHas('vendor', function ($vq) use ($word, $likeOperator) {
                                $vq->where('name', $likeOperator, '%'.$word.'%');
                            });
                    });
                }
            });
        }

        $orders = $query->orderBy('updated_at', 'desc')->paginate(20)->withQueryString();

        // Final (QC-console) approvals: stage-1 confirmed on the QMS session but
        // still awaiting Head-Office / Final sign-off. Read best-effort from QMS —
        // never let an unreachable/older QMS 500 this tab. It's a plain array (no
        // query builder to filter), so the same search text is applied in PHP.
        $finalApprovals = $this->pendingFinalApprovals();
        if ($search !== '') {
            $finalApprovals = self::filterPendingRows($finalApprovals, $search);
        }

        return view('subcon.admin.approvals', compact('orders', 'finalApprovals', 'search'));
    }

    /**
     * Report Validation tab: sessions MD Production has approved (step 1 —
     * RAF run queued) that still await the "Validate & Send Approval" step.
     * Read-only listing; the buttons open the token-based validate form.
     */
    public function reportValidations(Request $request)
    {
        $search = trim((string) $request->query('q', ''));

        $pending = $this->pendingValidateSends();
        if ($search !== '') {
            $pending = self::filterPendingRows($pending, $search);
        }

        return view('subcon.admin.report-validations', compact('pending', 'search'));
    }

    /**
     * Case-insensitive substring match across the fields common to every
     * QMS-backed pending-approval row (order_number/style/vendor/production_group).
     * These lists are plain arrays (joined in PHP against local orders), not
     * query builders, so filtering happens here rather than in SQL.
     */
    private static function filterPendingRows(array $rows, string $q): array
    {
        $needle = mb_strtolower($q);

        return array_values(array_filter($rows, function ($row) use ($needle) {
            foreach (['order_number', 'style', 'vendor', 'production_group'] as $field) {
                if (! empty($row[$field]) && str_contains(mb_strtolower((string) $row[$field]), $needle)) {
                    return true;
                }
            }

            return false;
        }));
    }

    /**
     * Sessions approved by MD Production but not yet validated & sent to the
     * Director, mapped to local orders like pendingDirectorApprovals(). Each
     * row carries the live RAF run status (one batched read from the remote
     * RPA DB, best-effort). Never 500s the tab.
     *
     * @return array<int, array<string, mixed>>
     */
    private function pendingValidateSends(): array
    {
        try {
            if (! Schema::connection('qms')->hasTable('packaging_project_sessions')
                || ! Schema::connection('qms')->hasColumn('packaging_project_sessions', 'ho_validation_signature')) {
                return [];
            }

            $sessions = DB::connection('qms')->table('packaging_project_sessions')
                ->whereNotNull('approval_token')
                ->where('ho_approval_signature', 'like', 'Digitally Signed:%')
                ->where(function ($q) {
                    $q->whereNull('ho_validation_signature')->orWhere('ho_validation_signature', '');
                })
                ->where(function ($q) {
                    $q->whereNull('director_approval_signature')->orWhere('director_approval_signature', '');
                })
                ->whereNotIn('project_id', function ($q) {
                    $q->select('project_id')->from('packaging_projects')
                        ->whereIn('status', SubconOrder::QMS_INACTIVE_PROJECT_STATUSES);
                })
                ->get();

            if ($sessions->isEmpty()) {
                return [];
            }

            $pgByProject = [];
            $projectsById = collect();
            $projectIds = $sessions->pluck('project_id')->filter()->unique()->values()->all();
            if (! empty($projectIds) && Schema::connection('qms')->hasTable('packaging_projects')) {
                $projectsById = DB::connection('qms')->table('packaging_projects')
                    ->whereIn('project_id', $projectIds)
                    ->get(['project_id', 'production_group', 'po_info', 'po_vendor', 'article_name'])
                    ->keyBy('project_id');
                $pgByProject = $projectsById->pluck('production_group', 'project_id')->all();
            }

            // Live RAF run status per project — one batched query, best-effort.
            $rafByProject = [];
            try {
                $rafByProject = DB::connection('rpa')->table('rpa_queues')
                    ->whereIn('entity_id', $projectIds)
                    ->where('rpa_type', 'job_trans_raf')
                    ->orderBy('id')
                    ->pluck('status', 'entity_id')->all();
            } catch (\Throwable $e) {
                // Remote RPA DB unreachable — statuses just render as unknown.
            }

            $pgs = array_values(array_filter(array_unique(array_values($pgByProject))));
            $ordersByPg = empty($pgs)
                ? collect()
                : SubconOrder::with('vendor')->whereIn('production_group', $pgs)->get()->keyBy('production_group');

            // Deduction total per session — same figure shown on the Director tab
            // and actually queued to Finance (RpaQueueService::deductionTotal):
            // fabric overconsumption charges by project + manual deduction lines
            // by session, both batched (grouped sums) rather than N+1 per row.
            // The reject/lost-items penalty is per-session and not batchable the
            // same way (non-linear 1%-limit step function), so it's added per
            // row below via QcReportPdfService::calculateDeductions() — this list
            // is small (pending approvals only), so that's not a real N+1 concern.
            // Best-effort: an unreachable QMS table just yields 0 everywhere.
            $fabricDedByProject = [];
            $manualDedBySession = [];
            try {
                if (Schema::connection('qms')->hasTable('packaging_project_fabric_lines')) {
                    $fabricDedByProject = DB::connection('qms')->table('packaging_project_fabric_lines')
                        ->whereIn('project_id', $projectIds)
                        ->groupBy('project_id')
                        ->selectRaw('project_id, SUM(deduction) as total')
                        ->pluck('total', 'project_id')->all();
                }
            } catch (\Throwable $e) {
                // Leave empty — deduction shows as 0 rather than failing the tab.
            }
            $sessionIds = $sessions->pluck('session_id')->filter()->unique()->values()->all();
            try {
                if (Schema::connection('qms')->hasTable('packaging_session_deduction_lines')) {
                    $manualDedBySession = DB::connection('qms')->table('packaging_session_deduction_lines')
                        ->whereIn('session_id', $sessionIds)
                        ->groupBy('session_id')
                        ->selectRaw('session_id, SUM(amount) as total')
                        ->pluck('total', 'session_id')->all();
                }
            } catch (\Throwable $e) {
                // Leave empty — deduction shows as 0 rather than failing the tab.
            }

            // Material Flow status per order — one batched read, best-effort
            // (an unreachable `wms` connection just leaves the badge off).
            // Uses the REAL gate (MaterialReturnService::pendingStatusForOrders(),
            // the same rule hoApprove()'s isReturnCheckPending() enforces) —
            // not just the latest task's own status, which can say "checked"
            // while newer undispatched lines still block Report Validation.
            $materialReturnByOrder = [];
            try {
                $orderIds = $ordersByPg->pluck('id')->filter()->unique()->values()->all();
                $materialReturnSvc = app(\App\Services\MaterialReturnService::class);
                $autoApprovedIds = $materialReturnSvc->autoApprovedStatusForOrders($orderIds);
                $materialReturnByOrder = collect($materialReturnSvc->pendingStatusForOrders($orderIds))
                    ->map(function ($pending, $oid) use ($autoApprovedIds) {
                        if (isset($autoApprovedIds[$oid])) {
                            return 'auto_approved';
                        }

                        return $pending ? 'pending' : 'checked';
                    });
            } catch (\Throwable $e) {
                // Leave empty — the "Send to Material Flow" state just shows as unknown.
            }

            $rows = $sessions->map(function ($s) use ($pgByProject, $ordersByPg, $projectsById, $rafByProject, $fabricDedByProject, $manualDedBySession, $materialReturnByOrder) {
                $pg = $pgByProject[$s->project_id] ?? null;
                $order = $pg ? ($ordersByPg[$pg] ?? null) : null;
                // No local order yet (e.g. the D365 PO hasn't been Confirmed so
                // the sync won't create it) — fall back to the QMS console's own
                // project record instead of showing a bare project_id/"—".
                $project = $projectsById[$s->project_id] ?? null;
                $penalty = $this->reportPdf->calculateDeductions((string) $s->project_id, (string) $s->session_id);
                $deductionTotal = round(
                    (float) ($fabricDedByProject[$s->project_id] ?? 0)
                    + (float) ($manualDedBySession[$s->session_id] ?? 0)
                    + (float) $penalty['rejectProduksiPenalty']
                    + (float) $penalty['barangHilangPenalty'],
                    2
                );

                return [
                    'token' => $s->approval_token,
                    'order_id' => $order->id ?? null,
                    'order_number' => $order->order_number ?? $project->po_info ?? ($s->project_id ?? '—'),
                    'style' => $order->title ?? $project->article_name ?? null,
                    'vendor' => $order?->vendor?->name ?? $project->po_vendor ?? '—',
                    'production_group' => $pg,
                    'version' => $s->version ?? null,
                    'result' => $s->result ?? null,
                    'ho_signature' => $s->ho_approval_signature ?? null,
                    'raf_status' => $rafByProject[$s->project_id] ?? null,
                    'deduction_total' => $deductionTotal,
                    'material_return_status' => $order ? ($materialReturnByOrder[$order->id] ?? null) : null,
                ];
            })->all();

            // A row lands back in this list either because it was never sent to
            // the Director yet, or because the Director just rejected it (which
            // resets ho_validation_signature/director_approval_signature to null
            // — see QcApprovalController::directorDecline). Flag rows the
            // Director bounced back; the full reason is shown on the Validate &
            // Send page itself (QcApprovalController::hoApprovalForm), not here.
            $orderNumbers = array_values(array_unique(array_filter(array_column($rows, 'order_number'))));
            $rejectedOrderNumbers = [];
            if (! empty($orderNumbers)) {
                $rejectedOrderNumbers = \App\Models\SubconApprovalLog::where('gate', 'director')
                    ->where('decision', 'declined')
                    ->whereIn('order_number', $orderNumbers)
                    ->distinct()
                    ->pluck('order_number')
                    ->flip()
                    ->all();
            }

            return array_map(function ($row) use ($rejectedOrderNumbers) {
                $row['director_rejected'] = isset($rejectedOrderNumbers[$row['order_number']]);

                return $row;
            }, $rows);
        } catch (\Throwable $e) {
            Log::warning('Pending report validations unavailable', ['error' => $e->getMessage()]);

            return [];
        }
    }

    /**
     * Whether this portal account is a configured Director — i.e. their email
     * appears in the `qc_director_approver_email` workflow setting. Drives the
     * separated "Director" approvals tab so the Director's stage never mixes
     * into the regular admins' Approvals flow.
     */
    public static function isDirectorUser($user): bool
    {
        $email = strtolower(trim((string) ($user->email ?? '')));
        if ($email === '') {
            return false;
        }

        try {
            return collect(preg_split('/[,;]+/', (string) \App\Models\Setting::getValue('qc_director_approver_email')))
                ->map(fn ($e) => strtolower(trim($e)))
                ->filter()
                ->contains($email);
        } catch (\Throwable $e) {
            return false;
        }
    }

    /**
     * Separated Director tab: QMS sessions MD Production has signed that are
     * awaiting the Director's authorization. Read-only listing — the action
     * buttons open the existing token-based Director workflow forms, so the
     * signature/routing contract is untouched.
     *
     * Visible to any subcon admin, not just the configured Director(s), so
     * admins can track progress — but only an actual Director (isDirectorUser)
     * gets the Authorize/Reject actions; everyone else is view/report-only.
     */
    public function directorApprovals(Request $request)
    {
        // Access here is already gated to admin|subcon_admin by the controller's
        // constructor middleware — $isDirector just decides whether the
        // Authorize/Reject actions render, or the page is report-view-only.
        $isDirector = self::isDirectorUser(Auth::user());
        $search = trim((string) $request->query('q', ''));

        $pending = $this->pendingDirectorApprovals();
        if ($search !== '') {
            $pending = self::filterPendingRows($pending, $search);
        }

        $recentDecisions = \App\Models\SubconApprovalLog::query()
            ->where('gate', 'director')
            ->when($search !== '', function ($query) use ($search) {
                $driver = DB::connection()->getDriverName();
                $likeOperator = $driver === 'pgsql' ? 'ilike' : 'like';
                $query->where(function ($q) use ($search, $likeOperator) {
                    $q->where('order_number', $likeOperator, '%'.$search.'%')
                        ->orWhere('vendor_name', $likeOperator, '%'.$search.'%');
                });
            })
            ->orderByDesc('created_at')
            ->limit(15)
            ->get();

        return view('subcon.admin.director-approvals', compact('pending', 'recentDecisions', 'isDirector', 'search'));
    }

    /**
     * Admin-initiated recall: pulls a session back from the Director queue to
     * Report Validation by clearing `ho_validation_signature`. Unlike Director
     * Reject (QcApprovalController::directorDecline) this is silent — no
     * reason prompt, no email to MD Production, no "Rejected" stamp — just an
     * internal correction path any subcon admin can use to pull a row back
     * before the Director acts on it (e.g. to fix a mistake spotted after
     * Validate & Send). Logged under its own 'recalled' decision so it does
     * not show up as a Director rejection on the Report Validation tab.
     */
    public function recallToReportValidation(Request $request, string $token)
    {
        if (! Str::isUuid($token)) {
            return back()->with('error', 'Invalid inspection reference.');
        }

        if (! Schema::connection('qms')->hasColumn('packaging_project_sessions', 'ho_validation_signature')) {
            return back()->with('error', 'Recall is not available on this environment.');
        }

        $row = DB::connection('qms')->table('packaging_project_sessions')
            ->where('approval_token', $token)->first();

        if (! $row) {
            return back()->with('error', 'Inspection not found.');
        }

        if (trim((string) ($row->director_approval_signature ?? '')) !== '') {
            return back()->with('error', 'This inspection has already been actioned by the Director and can no longer be recalled.');
        }

        $affected = DB::connection('qms')->table('packaging_project_sessions')
            ->where('approval_token', $token)
            ->where(function ($q) {
                $q->whereNull('director_approval_signature')->orWhere('director_approval_signature', '');
            })
            ->update(['ho_validation_signature' => null]);

        if ($affected === 0) {
            return back()->with('error', 'This inspection is no longer awaiting Director authorization.');
        }

        try {
            $doc = $this->reportPdf->renderDataUri((string) $row->project_id, (string) $row->session_id);
            if ($doc !== null) {
                $this->reportPdf->writeVerifiedDoc((string) $row->project_id, $doc);
            }
        } catch (\Throwable $e) {
            Log::warning('QC verified_doc refresh failed after recall', ['project' => $row->project_id ?? null, 'error' => $e->getMessage()]);
        }

        $pg = null;
        if (Schema::connection('qms')->hasTable('packaging_projects')) {
            $pg = DB::connection('qms')->table('packaging_projects')->where('project_id', $row->project_id)->value('production_group');
        }
        $subcon = $pg ? SubconOrder::where('production_group', $pg)->first() : null;

        $actor = Auth::user()->name ?: Auth::user()->email;
        \App\Models\SubconApprovalLog::record([
            'order_id' => $subcon->id ?? null,
            'order_number' => $subcon->order_number ?? ($row->project_id ?? null),
            'vendor_name' => $subcon?->vendor?->name,
            'gate' => 'director',
            'decision' => 'recalled',
            'actor' => $actor,
            'source' => 'portal',
            'note' => 'Recalled to Report Validation by '.$actor.'.',
        ]);

        return back()->with('success', 'Recalled to Report Validation.');
    }

    /**
     * Sessions pending Director authorization, mapped to local orders the same
     * way as pendingFinalApprovals(). Best-effort — never 500s the tab.
     *
     * @return array<int, array<string, mixed>>
     */
    private function pendingDirectorApprovals(): array
    {
        try {
            if (! Schema::connection('qms')->hasTable('packaging_project_sessions')
                || ! Schema::connection('qms')->hasColumn('packaging_project_sessions', 'director_approval_signature')) {
                return [];
            }

            $sessions = DB::connection('qms')->table('packaging_project_sessions')
                ->whereNotNull('approval_token')
                ->where('ho_approval_signature', 'like', 'Digitally Signed:%')
                // Two-step MD gate: only sessions MD has validated & sent are
                // actually awaiting the Director (mirrors directorStageGuard).
                ->when(
                    Schema::connection('qms')->hasColumn('packaging_project_sessions', 'ho_validation_signature'),
                    fn ($q) => $q->whereNotNull('ho_validation_signature')->where('ho_validation_signature', '!=', '')
                )
                ->where(function ($q) {
                    $q->whereNull('director_approval_signature')->orWhere('director_approval_signature', '');
                })
                // Exclude projects completed under the old two-stage flow
                // (authorizing those would re-queue real invoice RPA jobs) and
                // projects removed in the QC console.
                ->whereNotIn('project_id', function ($q) {
                    $q->select('project_id')->from('packaging_projects')
                        ->whereIn('status', SubconOrder::QMS_INACTIVE_PROJECT_STATUSES);
                })
                ->get();

            if ($sessions->isEmpty()) {
                return [];
            }

            $pgByProject = [];
            $projectIds = $sessions->pluck('project_id')->filter()->unique()->values()->all();
            if (! empty($projectIds) && Schema::connection('qms')->hasTable('packaging_projects')) {
                $pgByProject = DB::connection('qms')->table('packaging_projects')
                    ->whereIn('project_id', $projectIds)
                    ->pluck('production_group', 'project_id')->all();
            }

            $pgs = array_values(array_filter(array_unique(array_values($pgByProject))));
            $ordersByPg = empty($pgs)
                ? collect()
                : SubconOrder::with('vendor')->whereIn('production_group', $pgs)->get()->keyBy('production_group');

            // Deduction total per session — same figure the Invoice/Deduction RPA
            // will actually queue on authorization (RpaQueueService::deductionTotal):
            // fabric overconsumption charges (by project) + manual deduction lines
            // (by session) + the reject/lost-items penalty (per row below, via
            // QcReportPdfService::calculateDeductions — not batchable, see the
            // twin comment in pendingValidateSends()). Batched here (grouped
            // sums) rather than N+1 per row for the two batchable parts.
            // Best-effort: an unreachable QMS table just yields 0 everywhere.
            $fabricDedByProject = [];
            $manualDedBySession = [];
            try {
                if (Schema::connection('qms')->hasTable('packaging_project_fabric_lines')) {
                    $fabricDedByProject = DB::connection('qms')->table('packaging_project_fabric_lines')
                        ->whereIn('project_id', $projectIds)
                        ->groupBy('project_id')
                        ->selectRaw('project_id, SUM(deduction) as total')
                        ->pluck('total', 'project_id')->all();
                }
            } catch (\Throwable $e) {
                // Leave empty — deduction shows as 0 rather than failing the tab.
            }
            $sessionIds = $sessions->pluck('session_id')->filter()->unique()->values()->all();
            try {
                if (Schema::connection('qms')->hasTable('packaging_session_deduction_lines')) {
                    $manualDedBySession = DB::connection('qms')->table('packaging_session_deduction_lines')
                        ->whereIn('session_id', $sessionIds)
                        ->groupBy('session_id')
                        ->selectRaw('session_id, SUM(amount) as total')
                        ->pluck('total', 'session_id')->all();
                }
            } catch (\Throwable $e) {
                // Leave empty — deduction shows as 0 rather than failing the tab.
            }

            return $sessions->map(function ($s) use ($pgByProject, $ordersByPg, $fabricDedByProject, $manualDedBySession) {
                $pg = $pgByProject[$s->project_id] ?? null;
                $order = $pg ? ($ordersByPg[$pg] ?? null) : null;
                $penalty = $this->reportPdf->calculateDeductions((string) $s->project_id, (string) $s->session_id);
                $deductionTotal = round(
                    (float) ($fabricDedByProject[$s->project_id] ?? 0)
                    + (float) ($manualDedBySession[$s->session_id] ?? 0)
                    + (float) $penalty['rejectProduksiPenalty']
                    + (float) $penalty['barangHilangPenalty'],
                    2
                );

                return [
                    'token' => $s->approval_token,
                    'order_id' => $order->id ?? null,
                    'order_number' => $order->order_number ?? ($s->project_id ?? '—'),
                    'style' => $order->title ?? null,
                    'vendor' => $order?->vendor?->name ?? '—',
                    'production_group' => $pg,
                    'version' => $s->version ?? null,
                    'result' => $s->result ?? null,
                    'ho_signature' => $s->ho_approval_signature ?? null,
                    'deduction_total' => $deductionTotal,
                ];
            })->all();
        } catch (\Throwable $e) {
            Log::warning('Pending director approvals unavailable', ['error' => $e->getMessage()]);

            return [];
        }
    }

    /**
     * Audit trail of every cutting/gramasi approve & reject decision, newest
     * first. Optional gate/decision filters. See SubconApprovalController::logDecision().
     */
    public function approvalLogs(Request $request)
    {
        $query = \App\Models\SubconApprovalLog::query()->orderByDesc('created_at');

        if (in_array($request->input('gate'), ['cutting', 'gramasi', 'final'], true)) {
            $query->where('gate', $request->input('gate'));
        }
        if (in_array($request->input('decision'), ['approved', 'declined'], true)) {
            $query->where('decision', $request->input('decision'));
        }
        if ($request->filled('q')) {
            $query->where('order_number', 'like', '%'.trim($request->input('q')).'%');
        }

        $logs = $query->paginate(30)->withQueryString();

        return view('subcon.admin.approval-logs', [
            'logs' => $logs,
            'gate' => $request->input('gate'),
            'decision' => $request->input('decision'),
            'q' => $request->input('q'),
        ]);
    }

    /**
     * QMS packaging sessions that passed stage-1 (approval_status='approved') but
     * have no Final/HO signature yet, mapped back to the local subcon order via
     * project_id → packaging_projects.production_group → subcon_orders.
     *
     * @return array<int, array<string, mixed>>
     */
    private function pendingFinalApprovals(): array
    {
        try {
            if (! Schema::connection('qms')->hasTable('packaging_project_sessions')
                || ! Schema::connection('qms')->hasColumn('packaging_project_sessions', 'approval_token')) {
                return [];
            }

            $sessions = DB::connection('qms')->table('packaging_project_sessions')
                ->whereNotNull('approval_token')
                ->where('approval_status', 'approved')
                ->where(function ($q) {
                    $q->whereNull('ho_approval_signature')->orWhere('ho_approval_signature', '');
                })
                ->whereNotIn('project_id', function ($q) {
                    $q->select('project_id')->from('packaging_projects')
                        ->whereIn('status', SubconOrder::QMS_INACTIVE_PROJECT_STATUSES);
                })
                ->get();

            if ($sessions->isEmpty()) {
                return [];
            }

            // project_id → production_group
            $pgByProject = [];
            $projectIds = $sessions->pluck('project_id')->filter()->unique()->values()->all();
            if (! empty($projectIds) && Schema::connection('qms')->hasTable('packaging_projects')) {
                $pgByProject = DB::connection('qms')->table('packaging_projects')
                    ->whereIn('project_id', $projectIds)
                    ->pluck('production_group', 'project_id')->all();
            }

            // production_group → local subcon order
            $pgs = array_values(array_filter(array_unique(array_values($pgByProject))));
            $ordersByPg = empty($pgs)
                ? collect()
                : SubconOrder::with('vendor')->whereIn('production_group', $pgs)->get()->keyBy('production_group');

            return $sessions->map(function ($s) use ($pgByProject, $ordersByPg) {
                $pg = $pgByProject[$s->project_id] ?? null;
                $order = $pg ? ($ordersByPg[$pg] ?? null) : null;

                return [
                    'token' => $s->approval_token,
                    'order_id' => $order->id ?? null,
                    'order_number' => $order->order_number ?? ($s->project_id ?? '—'),
                    'style' => $order->title ?? null,
                    'vendor' => $order?->vendor?->name ?? '—',
                    'production_group' => $pg,
                    'approved_at' => $s->approved_at ?? null,
                ];
            })->all();
        } catch (\Throwable $e) {
            Log::warning('Pending final approvals unavailable', ['error' => $e->getMessage()]);

            return [];
        }
    }

    public function waitingDistribution(Request $request)
    {
        $query = SubconOrder::with('vendor')
            ->where('workflow_stage', SubconOrder::STAGE_WAITING_DISTRIBUTION);

        $search = trim((string) $request->query('q', ''));
        if ($search !== '') {
            $words = array_filter(explode(' ', $search));
            $driver = DB::connection()->getDriverName();
            $likeOperator = $driver === 'pgsql' ? 'ilike' : 'like';

            $query->where(function ($q) use ($words, $likeOperator) {
                foreach ($words as $word) {
                    $q->where(function ($sub) use ($word, $likeOperator) {
                        $sub->where('order_number', $likeOperator, '%'.$word.'%')
                            ->orWhere('title', $likeOperator, '%'.$word.'%')
                            ->orWhere('production_group', $likeOperator, '%'.$word.'%')
                            ->orWhereHas('vendor', function ($vq) use ($word, $likeOperator) {
                                $vq->where('name', $likeOperator, '%'.$word.'%');
                            });
                    });
                }
            });
        }

        // Failed label generations surface first so they don't get buried behind
        // routine "waiting" rows — everything else falls back to most-recently-updated.
        $orders = $query
            ->orderByRaw('CASE WHEN label_gen_status = ? THEN 0 ELSE 1 END', [SubconOrder::LABEL_GEN_FAILED])
            ->orderBy('updated_at', 'desc')
            ->paginate(20)
            ->withQueryString();

        return view('subcon.admin.waiting-distribution', compact('orders', 'search'));
    }

    /**
     * Triggers label generation. Gate is "gramasi & blister approved"
     * (`gramasi_approved_at` set) rather than a specific workflow stage, so this
     * doubles as both the first-time generation (stage still
     * `waiting_distribution`) and an on-demand re-trigger for an
     * already-labelled order — e.g. after the admin corrects blister/sack
     * capacity, which only needs a Coli/Blister resync (see
     * GenerateSubconLabels::recalculateColiBlister()), not a full DTT re-run.
     */
    public function generateLabelsManual(Request $request, string $id)
    {
        $model = SubconOrder::findOrFail($id);

        if (empty($model->gramasi_approved_at)) {
            return back()->with('error', 'Gramasi & blister capacity must be approved before labels can be generated.');
        }

        // Button-lock guard: don't stack a second run while one is in flight.
        if ($model->isGeneratingLabels()) {
            return back()->with('info', 'Label generation is already running for '.$model->order_number.'. Please wait for it to finish.');
        }

        // Lock the button (persisted state) then dispatch. Resolution + DTT call
        // (or, for an already-labelled order, the Coli/Blister resync) run off
        // the request cycle; on success the worker advances to "labels" (or, on
        // resync, just updates D365) — on failure it records the reason — both
        // surfaced back on this page.
        $model->markLabelGenStarted();
        \App\Jobs\GenerateSubconLabels::dispatch($model->id);

        $message = $model->canPrintLabels()
            ? 'Recalculating Coli/Blister for work order '.$model->order_number.' with the current capacity. This runs in the background.'
            : 'Label generation started for work order '.$model->order_number.'. It is running in the background — printing will unlock once the labels are ready.';

        return back()->with('success', $message);
    }

    public function viewOrder(string $id, SubconProductionService $production)
    {
        $order = SubconOrder::with(['vendor', 'items'])->findOrFail($id);
        $productionGroups = $production->forPo($order->order_number);
        $summary = $production->summarize($productionGroups);

        $cuttingReports = SubconCuttingReport::where('order_id', $order->id)
            ->get()
            ->keyBy('prod_id');

        // Per-fabric reconciliation + consumption (admin fills consumption here at
        // cutting-report approval). Best-effort — a VSM/DB hiccup must not 500.
        try {
            $fabricLines = $production->fabricLinesWithData($order);
        } catch (\Throwable $e) {
            report($e);
            $fabricLines = [];
        }
        $totalCut = $production->totalCutForOrder($order);

        // Accessory counterpart of $fabricLines — same VSM-sourced lines +
        // D365 Goods Receive / Material Issue reference data the Final
        // Approval form (QcApprovalController::hoApprovalForm) already shows;
        // wired into this page too (via $showAccessory below) so admins don't
        // have to wait for the QC-console stage to see accessory reconciliation
        // at all. Best-effort — a VSM/D365 hiccup must not 500 the order page.
        try {
            $accessoryLines = $production->accessoryLinesForPo($order->order_number);
        } catch (\Throwable $e) {
            report($e);
            $accessoryLines = [];
        }
        try {
            $accessoryGoodsReceive = $production->resolveGoodsReceipts($accessoryLines);
        } catch (\Throwable $e) {
            report($e);
            $accessoryGoodsReceive = [];
        }
        try {
            $accessoryIssue = $production->materialIssueForOrder((string) $order->production_group);
        } catch (\Throwable $e) {
            report($e);
            $accessoryIssue = [];
        }

        $materialReturnService = app(\App\Services\MaterialReturnService::class);
        $materialReturns = $materialReturnService->attachmentsFor($order);
        $materialReturnTask = $materialReturnService->activeTaskFor($order);
        // See QcApprovalController::hoApprovalForm()'s identical comment —
        // must reflect the real gate, not just the latest task's status.
        $materialReturnPending = $materialReturnService->isReturnCheckPending($order);
        $materialReturnAutoApproved = $materialReturnService->isAutoApproved($order);
        $accessoryRecon = $materialReturnService->linesFor($order)->where('item_type', 'accessory')->keyBy('label');

        // Post-labels report pipeline (QC-console inspection → Final Approval →
        // Report Validation → Director): currently only traceable by hunting for
        // this PO across the separate Approvals / Report Validation / Director
        // Approvals tabs. Surfaced here so the order page itself tracks which
        // QMS project this order is and exactly where its report sits.
        // Best-effort — never blocks the order page.
        $qcPipeline = $this->qcPipelineStatusFor($order);
        // Richer fallback for production-detail.blade.php's per-size table when
        // VSM's PLM chain is broken — see qcSizeOrderQtyFor()'s docblock.
        $qcSizeOrderQty = $this->qcSizeOrderQtyFor($order);

        return view('subcon.admin.orders.show', compact('order', 'productionGroups', 'summary', 'cuttingReports', 'fabricLines', 'totalCut', 'accessoryLines', 'accessoryGoodsReceive', 'accessoryIssue', 'accessoryRecon', 'materialReturns', 'materialReturnTask', 'materialReturnPending', 'materialReturnAutoApproved', 'qcPipeline', 'qcSizeOrderQty'));
    }

    /**
     * Standalone "Save Material Reconciliation" — persists the Fabric
     * consumption table AND the Accessory return-quantity table, independent
     * of any workflow-stage gate. Before this, editing consumption was only
     * possible as a side effect of the cutting-gate Approve action
     * (SubconApprovalController::approveInApp) or the QC-console Final
     * Approval form (QcApprovalController::hoApprove/hoSendApproval) — an
     * admin who just needed to correct a figure, or fill in reconciliation
     * for an order stuck outside the cutting-review window (e.g. no VSM/PLM
     * link at all — manual "Add fabric"/"Add accessory" rows are the only
     * path for those), had no way to do so without re-triggering an
     * approval. This route changes only SubconFabricReconciliation /
     * MaterialReturnLine — it never touches workflow_stage.
     *
     * Same two persistence calls the approval gates already use
     * (SubconConsumptionService::persist() for fabric,
     * MaterialReturnService::persistReconciliation() for accessory — fabric
     * side passed empty since consumption already covers it), same
     * validation shape as QcApprovalController::hoApprove(), same
     * lockReturKain:false (admin's submitted Retur Kain wins, matching the
     * approval forms' "approver can override it directly" rule).
     */
    public function saveMaterialReconciliation(Request $request, string $id, SubconConsumptionService $consumption, \App\Services\MaterialReturnService $materialReturns)
    {
        $order = SubconOrder::with('vendor')->findOrFail($id);

        $data = $request->validate([
            'fabrics' => 'nullable|array',
            'fabrics.*.label' => 'required_with:fabrics|string|max:500',
            'fabrics.*.short_roll' => 'nullable|numeric|min:0',
            'fabrics.*.sisa_kain' => 'nullable|numeric|min:0',
            'fabrics.*.kepala_kain' => 'nullable|numeric|min:0',
            'fabrics.*.retur_kain' => 'nullable|numeric|min:0',
            'fabrics.*.fabric_sent' => 'nullable|numeric|min:0',
            'fabrics.*.consumption_plan' => 'nullable|numeric|min:0',
            'fabrics.*.fabric_price' => 'nullable|numeric|min:0',
            'accessories_recon' => 'nullable|array',
            'accessories_recon.*.label' => 'required_with:accessories_recon|string|max:500',
            'accessories_recon.*.qty' => 'nullable|numeric|min:0',
            'accessories_recon.*.mats_sent' => 'nullable|numeric|min:0',
            'accessories_recon.*.price' => 'nullable|numeric|min:0',
            'accessories_recon.*.unit' => 'nullable|string|max:20',
        ]);

        DB::transaction(function () use ($consumption, $order, $data) {
            $consumption->persist($order, $data['fabrics'] ?? []);
        });

        $actor = Auth::user()->name ?: Auth::user()->email;
        $materialReturns->persistReconciliation(
            $order,
            [],
            $data['accessories_recon'] ?? [],
            \App\Models\MaterialReturnAttachment::ROLE_ADMIN,
            $actor,
            lockReturKain: false
        );

        return back()->with('success', 'Material reconciliation saved.');
    }

    /**
     * Best-effort per-size Order Qty from the QC console's own report-line
     * snapshot (`qms.packaging_project_reports`, `session_id IS NULL` — the
     * base "system" line every session's reject/session figures are computed
     * against). This is the SAME source the signed inspection report's own
     * per-size table reads Order Qty from (QcReportPdfService::context()) —
     * captured once at inspection time and stored in QMS, decoupled from a
     * live VSM `production_group_lines` lookup that can go missing/archived
     * on VSM's side even after a real inspection already ran and the figures
     * are sitting right here. Exists as a richer fallback for
     * production-detail.blade.php when the VSM/PLM chain is broken but the
     * order has already been through QC inspection.
     *
     * Returns [] if there's no QMS project for this order yet, or the `qms`
     * connection/schema is unavailable.
     *
     * @return array<string,int> size => order qty
     */
    private function qcSizeOrderQtyFor(SubconOrder $order): array
    {
        if (empty($order->production_group)) {
            return [];
        }

        try {
            if (! Schema::connection('qms')->hasTable('packaging_projects')
                || ! Schema::connection('qms')->hasTable('packaging_project_reports')) {
                return [];
            }

            $projectId = DB::connection('qms')->table('packaging_projects')
                ->where('production_group', $order->production_group)
                ->orderByDesc('created_at')
                ->value('project_id');

            if (! $projectId) {
                return [];
            }

            return DB::connection('qms')->table('packaging_project_reports')
                ->where('project_id', $projectId)
                ->whereNull('session_id')
                ->pluck('qty_order', 'size_val')
                ->map(fn ($v) => (int) $v)
                ->all();
        } catch (\Throwable $e) {
            return [];
        }
    }

    /**
     * Best-effort QC-console pipeline status for one order, sourced from `qms`
     * (latest packaging_projects row + its latest session, both joined by
     * production_group — same key SubconOrder::qmsColumnQuery() and
     * QcApprovalController::subconContext() resolve through). Mirrors the
     * staging logic behind pendingFinalApprovalCount()/pendingValidateSendCount()/
     * pendingDirectorApprovalCount(), but scoped to a single order instead of a
     * portal-wide count, so the order page can show exactly where this PO sits
     * without the admin having to search the separate tabs for it.
     *
     * `steps` is an explicit sent/not-sent checklist (Inspection → Final
     * Approval → Report Validation → Director) rather than just one collapsed
     * label — "Final Approval" and "Report Validation" ('Validate & Send') are
     * two distinct signatures (ho_approval_signature / ho_validation_signature)
     * and admins need to see which of the two has actually gone out, not just
     * an overall stage name. Each step's 'done'/'name'/'date' comes from
     * QcReportPdfService::parseSignature() on the matching signature column.
     *
     * ALWAYS returns a checklist (never null just because the order hasn't
     * reached this pipeline yet) — an admin looking at a cutting-stage order
     * needs to see "Final Approval: not yet sent" just as clearly as one at
     * the director stage needs to see who signed and when. Only a genuine
     * `qms` connection/schema failure returns null (best-effort: the card
     * just doesn't render rather than erroring the order page).
     *
     * @return array{stage:string,label:string,badge:string,token:?string,project_id:?string,session_id:?string,steps:array<int,array>}|null
     */
    private function qcPipelineStatusFor(SubconOrder $order): ?array
    {
        try {
            if (empty($order->production_group)) {
                return [
                    'stage' => 'no_pg', 'label' => 'No production group linked', 'badge' => 'secondary',
                    'token' => null, 'project_id' => null, 'session_id' => null,
                    'steps' => $this->buildQcSteps(false, null, '', '', ''),
                ];
            }

            if (! Schema::connection('qms')->hasTable('packaging_projects')
                || ! Schema::connection('qms')->hasTable('packaging_project_sessions')) {
                return null;
            }

            $project = DB::connection('qms')->table('packaging_projects')
                ->where('production_group', $order->production_group)
                ->orderByDesc('created_at')
                ->first();

            if (! $project) {
                return [
                    'stage' => 'no_session', 'label' => 'Not yet in QC console', 'badge' => 'secondary',
                    'token' => null, 'project_id' => null, 'session_id' => null,
                    'steps' => $this->buildQcSteps(false, null, '', '', ''),
                ];
            }

            $session = DB::connection('qms')->table('packaging_project_sessions')
                ->where('project_id', $project->project_id)
                ->orderByDesc('cycle_number')
                ->first();

            $ho = trim((string) ($session->ho_approval_signature ?? ''));
            $validation = trim((string) ($session->ho_validation_signature ?? ''));
            $director = trim((string) ($session->director_approval_signature ?? ''));
            $isInactive = in_array($project->status, SubconOrder::QMS_INACTIVE_PROJECT_STATUSES, true);

            [$stage, $label, $badge] = self::classifyQcStage($ho, $validation, $director, $isInactive, (bool) $session, $session->approval_status ?? null);

            $steps = $this->buildQcSteps((bool) $session, $session->approval_status ?? null, $ho, $validation, $director);

            return [
                'stage' => $stage,
                'label' => $label,
                'badge' => $badge,
                'token' => $session->approval_token ?? null,
                'project_id' => $project->project_id ?? null,
                'session_id' => $session->session_id ?? null,
                'steps' => $steps,
            ];
        } catch (\Throwable $e) {
            return null;
        }
    }

    /**
     * Shared stage classification for the QC-console pipeline — the same
     * priority order qcPipelineStatusFor() and qcPipelineStatusForOrders() both
     * need, extracted once so the list (batched) and single-order (detailed)
     * reads can never drift apart on what counts as "awaiting X".
     *
     * @return array{0:string,1:string,2:string} [stage, label, badge]
     */
    private static function classifyQcStage(string $ho, string $validation, string $director, bool $isInactive, bool $hasSession, ?string $approvalStatus): array
    {
        return match (true) {
            $isInactive => ['completed', 'Completed', 'success'],
            ! $hasSession => ['no_session', 'Not yet in QC console', 'secondary'],
            str_starts_with($director, 'Rejected') => ['director_rejected', 'Director rejected', 'danger'],
            $director !== '' => ['director_approved', 'Director approved', 'success'],
            $validation !== '' => ['awaiting_director', 'Awaiting Director', 'warning'],
            str_starts_with($ho, 'Rejected') => ['ho_rejected', 'MD Production rejected', 'danger'],
            $ho !== '' => ['awaiting_validation', 'Awaiting Report Validation', 'warning'],
            $approvalStatus === 'approved' => ['awaiting_final', 'Awaiting Final Approval', 'warning'],
            default => ['inspecting', 'Inspection in progress', 'info'],
        };
    }

    /**
     * The explicit sent/not-sent checklist (Inspection → Final Approval →
     * Report Validation → Director) shared by every qcPipelineStatusFor()
     * return path, including the "nothing in QMS yet" ones — so a
     * cutting-stage order shows "Final Approval: not yet sent" just as
     * plainly as a director-stage one shows who signed and when, instead of
     * the card just not existing yet.
     *
     * @return array<int,array{key:string,label:string,sent:bool,sig:?array,note:?string}>
     */
    private function buildQcSteps(bool $hasSession, ?string $approvalStatus, string $ho, string $validation, string $director): array
    {
        $sig = fn (string $raw) => $raw === '' ? null : $this->reportPdf->parseSignature($raw);

        return [
            [
                'key' => 'inspection',
                'label' => 'Inspection',
                'sent' => $hasSession,
                'sig' => null,
                'note' => $hasSession ? ($approvalStatus === 'approved' ? 'Passed' : 'In progress') : 'Not started',
            ],
            [
                'key' => 'final_approval',
                'label' => 'Final Approval',
                'sent' => $ho !== '',
                'sig' => $sig($ho),
                'note' => $ho !== '' ? null : 'Not yet sent to Final Approval',
            ],
            [
                'key' => 'report_validation',
                'label' => 'Report Validation (sent to Director)',
                'sent' => $validation !== '',
                'sig' => $sig($validation),
                'note' => $validation !== '' ? null : 'Not sent yet',
            ],
            [
                'key' => 'director',
                'label' => 'Director Authorization',
                'sent' => $director !== '',
                'sig' => $sig($director),
                'note' => $director !== '' ? null : 'Not sent yet',
            ],
        ];
    }

    /**
     * Batched QC-pipeline stage for every given order that has reached
     * STAGE_COMPLETED — the point where SubconOrder::stageLabel()/status stop
     * saying anything further, even though the QC-console inspection →
     * approval → debit-note pipeline is often still running behind it. Without
     * this the orders LIST shows "Completed" for every such order with no way
     * to tell which ones are still waiting on someone vs. actually done —
     * admins had to open each order individually (qcPipelineStatusFor()) to
     * find out. One batched read per collection (not N+1): mirrors
     * pendingValidateSends()'s batching shape. Best-effort — an unreachable
     * `qms` connection just leaves every row without a badge.
     *
     * @param  \Illuminate\Support\Collection<int,SubconOrder>  $orders
     * @return array<string,array{stage:string,label:string,badge:string}> keyed by order id
     */
    private function qcPipelineStatusForOrders($orders): array
    {
        $completed = $orders->filter(fn ($o) => $o->workflow_stage === SubconOrder::STAGE_COMPLETED && ! empty($o->production_group));
        if ($completed->isEmpty()) {
            return [];
        }

        try {
            if (! Schema::connection('qms')->hasTable('packaging_projects')
                || ! Schema::connection('qms')->hasTable('packaging_project_sessions')) {
                return [];
            }

            $pgs = $completed->pluck('production_group')->unique()->values()->all();

            // Latest project per production_group.
            $projects = DB::connection('qms')->table('packaging_projects')
                ->whereIn('production_group', $pgs)
                ->orderByDesc('created_at')
                ->get(['project_id', 'production_group', 'status'])
                ->unique('production_group')
                ->keyBy('production_group');

            $projectIds = $projects->pluck('project_id')->values()->all();
            if (empty($projectIds)) {
                return [];
            }

            // Latest session per project_id.
            $sessions = DB::connection('qms')->table('packaging_project_sessions')
                ->whereIn('project_id', $projectIds)
                ->orderByDesc('cycle_number')
                ->get(['project_id', 'ho_approval_signature', 'ho_validation_signature', 'director_approval_signature', 'approval_status'])
                ->unique('project_id')
                ->keyBy('project_id');

            $result = [];
            foreach ($completed as $order) {
                $project = $projects->get($order->production_group);
                if (! $project) {
                    continue;
                }
                $session = $sessions->get($project->project_id);
                $ho = trim((string) ($session->ho_approval_signature ?? ''));
                $validation = trim((string) ($session->ho_validation_signature ?? ''));
                $director = trim((string) ($session->director_approval_signature ?? ''));
                $isInactive = in_array($project->status, SubconOrder::QMS_INACTIVE_PROJECT_STATUSES, true);

                [$stage, $label, $badge] = self::classifyQcStage($ho, $validation, $director, $isInactive, (bool) $session, $session->approval_status ?? null);
                $result[$order->id] = ['stage' => $stage, 'label' => $label, 'badge' => $badge];
            }

            return $result;
        } catch (\Throwable $e) {
            return [];
        }
    }

    /**
     * Admin/MD Production-side material-return delivery note. Open only from
     * Final Approval through Report Validation — see
     * SubconOrder::materialReturnAdminWindowOpen().
     */
    public function uploadMaterialReturn(Request $request, string $id, \App\Services\MaterialReturnService $materialReturns)
    {
        $order = SubconOrder::with('vendor')->findOrFail($id);

        if (! $order->materialReturnAdminWindowOpen()) {
            return back()->with('error', 'A material-return note can only be attached between Final Approval and Report Validation.');
        }

        $data = $request->validate([
            'file' => 'required|file|mimes:pdf,jpg,jpeg,png|max:10240',
            'note' => 'nullable|string|max:1000',
        ]);

        $materialReturns->upload($order, $data['file'], $data['note'] ?? null, \App\Models\MaterialReturnAttachment::ROLE_ADMIN, Auth::user()->name);

        return back()->with('success', 'Material-return delivery note attached.');
    }

    /**
     * Removes an admin-uploaded delivery note. Same window as
     * uploadMaterialReturn() above; scoped to admin's own uploads — see
     * MaterialReturnService::deleteAttachment()'s docblock.
     */
    public function deleteMaterialReturn(string $id, string $attachment, \App\Services\MaterialReturnService $materialReturns)
    {
        $order = SubconOrder::with('vendor')->findOrFail($id);

        if (! $order->materialReturnAdminWindowOpen()) {
            return back()->with('error', 'A material-return note can only be removed between Final Approval and Report Validation.');
        }

        $error = $materialReturns->deleteAttachment($order, $attachment, \App\Models\MaterialReturnAttachment::ROLE_ADMIN);

        return $error
            ? back()->with('error', $error)
            : back()->with('success', 'Delivery note removed.');
    }

    /**
     * "Send to Material Flow" — MD Production dispatches an inventory-check
     * task to value_stream_ops. From this point, QcApprovalController::
     * hoSendApproval refuses to send Report Validation to the Director until
     * value_stream_ops flips the task to 'checked'. In-app/authenticated
     * only — deliberately not reachable from the no-login signed email link
     * (a bare GET must never mutate; see the fabric tolerance prefetch fix).
     */
    public function dispatchMaterialReturnTask(string $id, \App\Services\MaterialReturnService $materialReturns)
    {
        $order = SubconOrder::with('vendor')->findOrFail($id);

        if (! $order->materialReturnAdminWindowOpen()) {
            return back()->with('error', 'Material Flow can only be dispatched between Final Approval and Report Validation.');
        }

        $materialReturns->dispatchTask($order, Auth::user()->name);

        return back()->with('success', 'Sent to Material Flow — inventory will check the returned material.');
    }

    /**
     * Export the order's cutting report to Excel for the admin's records.
     * Available at any stage — exports whatever cutting qty / gramasi has been
     * entered so far (blank where not yet filled), so it is useful both before
     * and after approval.
     */
    public function exportCuttingReport(string $id, SubconProductionService $production)
    {
        $order = SubconOrder::with('vendor')->findOrFail($id);
        $groups = $production->forPo($order->order_number);
        $reports = SubconCuttingReport::where('order_id', $order->id)->get()->keyBy('prod_id');

        // Meta header block — plain label/value pairs, styled by the export class.
        $rows = [
            ['Cutting Report'],
            ['Work Order', $order->order_number],
            ['Vendor', optional($order->vendor)->name ?? $order->vendor_id],
            ['Style', $order->title ?: '—'],
            ['Stage', $order->stageLabel()],
            ['Blister Capacity', $order->blister_capacity ?: '—'],
            ['Sack (Karung) Capacity', $order->sack_capacity ?? 50],
        ];
        $metaRows = range(2, count($rows));

        // Fabric reconciliation — a real table (one row per fabric, one column per
        // metric), not a vertical label:value stack. Values stay raw numeric types
        // (not pre-formatted strings) so Excel can sum/sort/filter them; display
        // formatting (decimals, thousands separators, "Rp", "%") is applied as
        // native cell number formats in the export class instead.
        try {
            $reconciliations = \App\Models\SubconFabricReconciliation::where('order_id', $order->id)
                ->orderBy('label')
                ->get();
        } catch (\Throwable $e) {
            report($e);
            $reconciliations = collect();
        }

        $rows[] = ['']; // spacer — a single empty cell so the writer keeps the row (an empty [] is dropped, desyncing indices)
        $rows[] = ['Fabric Reconciliation & Consumption'];
        $reconSectionRow = count($rows);
        $reconHeaderRow = null;
        $reconDataRows = [];
        $reconTotalRow = null;

        if ($reconciliations->isEmpty()) {
            $rows[] = ['No fabric reconciliation recorded yet.'];
        } else {
            $reconHeaderRow = count($rows) + 1;
            $rows[] = [
                'Fabric', 'Short Roll', 'Sisa Kain (Utuh)', 'Kepala Kain', 'Retur Kain',
                'Fabric Sent', 'Cons. Plan', 'Cutt Plan', 'Actual Cons.', 'Overconsumption',
                'Fabric Price (IDR)', 'Deduction (IDR)',
            ];

            $totalDeduction = 0.0;
            foreach ($reconciliations as $rec) {
                $totalDeduction += (float) $rec->deduction;
                $rows[] = [
                    $rec->label,
                    (float) $rec->short_roll,
                    (float) $rec->sisa_kain,
                    (float) $rec->kepala_kain,
                    (float) $rec->retur_kain,
                    $rec->fabric_sent !== null ? (float) $rec->fabric_sent : null,
                    $rec->consumption_plan !== null ? (float) $rec->consumption_plan : null,
                    $rec->cutt_plan !== null ? (int) $rec->cutt_plan : null,
                    $rec->actual_consumption !== null ? (float) $rec->actual_consumption : null,
                    // Stored as a fraction (0.0284), not x100 — the '0.00%' cell
                    // format below multiplies for display, matching Excel's own
                    // native percentage convention.
                    $rec->overconsumption !== null ? (float) $rec->overconsumption : null,
                    $rec->fabric_price !== null ? (float) $rec->fabric_price : null,
                    (float) $rec->deduction,
                ];
                $reconDataRows[] = count($rows);
            }

            $reconTotalRow = count($rows) + 1;
            $rows[] = ['TOTAL DEDUCTION', null, null, null, null, null, null, null, null, null, null, $totalDeduction];
        }

        $rows[] = ['']; // spacer — a single empty cell so the writer keeps the row (an empty [] is dropped, desyncing indices)
        $rows[] = ['Exported', now()->format('Y-m-d H:i')];
        $rows[] = ['']; // spacer — a single empty cell so the writer keeps the row (an empty [] is dropped, desyncing indices)

        $sizeHeaderRow = count($rows) + 1;
        $rows[] = ['Size', 'PRD ID', 'Status', 'Order Qty', 'Qty Cut', 'Balance', 'Gramasi (g)'];

        $totalOrder = 0;
        $totalCut = 0;
        $anyCut = false;
        $hadLines = false;
        $sizeDataRows = [];

        // Balance column (F) font colours, keyed by 1-based row: under-cut
        // (shortfall) red, exact/over-cut (surplus) green — matching the in-app
        // production table and the approval email.
        $balanceColors = [];

        foreach ($groups as $g) {
            foreach (($g['lines'] ?? []) as $line) {
                $hadLines = true;
                $orderQty = (int) $line->Qty;
                $report = $reports->get($line->ProdId);
                $cutQty = $report?->cutting_qty;
                $gramasi = $report?->gramasi;
                $hasCut = $cutQty !== null;

                $totalOrder += $orderQty;
                if ($hasCut) {
                    $totalCut += (int) $cutQty;
                    $anyCut = true;
                }

                $rows[] = [
                    $line->Size ?: '—',
                    $line->ProdId,
                    $line->ProdStatus ?: '—',
                    $orderQty,
                    $hasCut ? (int) $cutQty : null,
                    $hasCut ? ((int) $cutQty - $orderQty) : null,
                    $gramasi !== null ? (float) $gramasi : null,
                ];
                $sizeDataRows[] = count($rows);

                if ($hasCut) {
                    $balanceColors[count($rows)] = ((int) $cutQty - $orderQty) < 0 ? 'C92A2A' : '2B8A3E';
                }
            }
        }

        // Fallback: if VSM has no production lines (unreachable / no PLM link)
        // but the vendor already submitted reports, export those directly so the
        // record is never empty.
        if (! $hadLines && $reports->isNotEmpty()) {
            foreach ($reports as $report) {
                $cutQty = $report->cutting_qty;
                if ($cutQty !== null) {
                    $totalCut += (int) $cutQty;
                    $anyCut = true;
                }
                $rows[] = [
                    $report->size ?: '—',
                    $report->prod_id,
                    '—',
                    null,
                    $cutQty !== null ? (int) $cutQty : null,
                    null,
                    $report->gramasi !== null ? (float) $report->gramasi : null,
                ];
                $sizeDataRows[] = count($rows);
            }
        }

        $totalRow = count($rows) + 1; // 1-based row of the totals line
        $rows[] = [
            'TOTAL', null, null,
            $totalOrder ?: null,
            $anyCut ? $totalCut : null,
            $anyCut ? ($totalCut - $totalOrder) : null,
            null,
        ];
        if ($anyCut) {
            $balanceColors[$totalRow] = ($totalCut - $totalOrder) < 0 ? 'C92A2A' : '2B8A3E';
        }

        $safeNumber = str_replace(['/', '\\'], '-', $order->order_number);
        $filename = 'cutting-report_'.$safeNumber.'_'.now()->format('Ymd-His').'.xlsx';

        return Excel::download(
            new SubconCuttingReportExport($rows, [
                'titleRow' => 1,
                'metaRows' => $metaRows,
                'reconSectionRow' => $reconSectionRow,
                'reconHeaderRow' => $reconHeaderRow,
                'reconDataRows' => $reconDataRows,
                'reconTotalRow' => $reconTotalRow,
                'sizeHeaderRow' => $sizeHeaderRow,
                'sizeDataRows' => $sizeDataRows,
                'totalRow' => $totalRow,
                'balanceColors' => $balanceColors,
            ]),
            $filename
        );
    }

    /**
     * Status is normally auto-derived from the workflow stage. The only manual
     * lever is cancelling (a terminal state) and reactivating back to the
     * stage-inferred status.
     */
    public function updateOrderStatus(Request $request, string $id)
    {
        $request->validate([
            'action' => 'required|in:cancel,reactivate,complete',
        ]);

        $order = SubconOrder::findOrFail($id);

        if ($request->action === 'cancel') {
            $order->status = 'cancelled';
            $order->save();

            return back()->with('success', 'Order cancelled.');
        }

        if ($request->action === 'complete') {
            // Same gate as the vendor's own completeOrder(): labels stage
            // reached, not yet completed — admin can finish it on the
            // vendor's behalf, but not skip ahead of the workflow.
            if (! $order->canComplete()) {
                return back()->with('error', 'This order cannot be completed at its current stage ('.$order->stageLabel().').');
            }

            $order->workflow_stage = SubconOrder::STAGE_COMPLETED;
            // status is derived from workflow_stage in the model's saving hook.
            $order->save();

            return back()->with('success', 'Work order marked as completed.');
        }

        // Reactivate: re-derive status from the current workflow stage.
        $order->status = SubconOrder::inferStatusFromStage($order->workflow_stage);
        $order->save();

        return back()->with('success', 'Order reactivated.');
    }

    /**
     * Admin override for the per-order blister/sack capacity — normally set by
     * the vendor on the gramasi form, but the admin can correct it directly.
     */
    public function updateCapacity(Request $request, string $id)
    {
        $data = $request->validate([
            'blister_capacity' => 'nullable|integer|min:1',
            'sack_capacity' => 'nullable|integer|min:1',
        ]);

        $order = SubconOrder::findOrFail($id);
        $order->blister_capacity = ! empty($data['blister_capacity'])
            ? (int) $data['blister_capacity']
            : null;
        $order->sack_capacity = ! empty($data['sack_capacity'])
            ? (int) $data['sack_capacity']
            : null;
        $order->save();

        return back()->with('success', 'Blister/sack capacity updated.');
    }

    public function printPackagingLabels(Request $request, string $id, SubconLabelService $labels)
    {
        $order = SubconOrder::with('vendor')->findOrFail($id);

        $scope = in_array($request->query('scope'), ['store', 'warehouse'], true) ? $request->query('scope') : 'all';
        $data = $labels->buildViewData($order, $scope);
        if (isset($data['error'])) {
            return back()->with('error', $data['error']);
        }

        // Rendering can span 100+ label pages — give DomPDF room beyond the
        // default 128M/web timeouts so it does not fatal mid-render.
        ini_set('memory_limit', '512M');
        @set_time_limit(180);

        $pdf = \Barryvdh\DomPDF\Facade\Pdf::loadView('subcon.pdf.packaging-labels', $data)
            ->setPaper([0, 0, 288, 432]); // 4in x 6in label

        $safeOrderNumber = str_replace(['/', '\\'], '-', $order->order_number);
        $prefix = ['store' => 'store-labels-', 'warehouse' => 'replenish-online-labels-'][$scope] ?? 'packaging-labels-';

        return $pdf->stream($prefix.$safeOrderNumber.'.pdf');
    }

    /**
     * Show subcon admin workflow settings page.
     */
    public function workflow()
    {
        $cuttingApproverEmail = \App\Models\Setting::getValue('subcon_cutting_approver_email', '');
        $gramasiApproverEmail = \App\Models\Setting::getValue('subcon_gramasi_approver_email', '');
        $labelGeneratorEmail = \App\Models\Setting::getValue('subcon_label_generator_email', '');
        $finalApproverEmail = \App\Models\Setting::getValue('qc_ho_approver_email', '');
        $qcHeadNotificationEmail = \App\Models\Setting::getValue('qc_head_notification_email', '');
        $directorApproverEmail = \App\Models\Setting::getValue('qc_director_approver_email', '');
        $directorApproverPhone = \App\Models\Setting::getValue('qc_director_approver_phone', '');
        $materialFlowAutoApprove = app(\App\Services\MaterialReturnService::class)->autoApproveActive();

        return view('subcon.admin.workflow', compact('cuttingApproverEmail', 'gramasiApproverEmail', 'labelGeneratorEmail', 'finalApproverEmail', 'qcHeadNotificationEmail', 'directorApproverEmail', 'directorApproverPhone', 'materialFlowAutoApprove'));
    }

    /**
     * Bahasa Indonesia PDF user guide for the subcon admin portal.
     */
    public function userGuide()
    {
        $pdf = \Barryvdh\DomPDF\Facade\Pdf::loadView('subcon.pdf.admin-guide-id')->setPaper('a4');

        return $pdf->stream('Panduan Admin - Portal Subkontraktor.pdf');
    }

    /**
     * Update subcon admin workflow settings.
     */
    public function updateWorkflow(\Illuminate\Http\Request $request)
    {
        // Each field may hold one OR many comma/semicolon-separated addresses.
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

        // May hold one OR many comma/semicolon-separated phone numbers; each
        // just needs to leave at least one digit after stripping non-digits
        // (WhatsAppNotificationService::toChatId does the real normalization).
        $multiPhone = function (string $attribute, $value, $fail) {
            foreach (preg_split('/[,;]+/', (string) $value) as $phone) {
                $phone = trim($phone);
                if ($phone === '' || str_contains($phone, '@')) {
                    continue;
                }
                if (preg_replace('/\D+/', '', $phone) === '') {
                    $fail("The {$attribute} field contains an invalid phone number: {$phone}");
                }
            }
        };

        $request->validate([
            'subcon_cutting_approver_email' => ['required', $multiEmail],
            'subcon_gramasi_approver_email' => ['required', $multiEmail],
            'subcon_label_generator_email' => ['required', $multiEmail],
            // Second-stage (Head Office) consumption approval — optional; when
            // blank the HO email falls back to the cutting approver list.
            'qc_ho_approver_email' => ['nullable', $multiEmail],
            // QC Head — notification only, no approval action. Cc'd on the
            // completion email once the Director authorizes.
            'qc_head_notification_email' => ['nullable', $multiEmail],
            // Third-stage (Director) authorization — optional; when blank the
            // director email falls back to the Final (HO) list.
            'qc_director_approver_email' => ['nullable', $multiEmail],
            // Director WhatsApp notification — optional, on top of the email.
            'qc_director_approver_phone' => ['nullable', $multiPhone],
        ]);

        \App\Models\Setting::updateOrCreate(
            ['key' => 'subcon_cutting_approver_email'],
            [
                'value' => $request->input('subcon_cutting_approver_email'),
                'group' => 'subcon',
                'type' => 'string',
                'description' => 'Email address(es) for routing cutting workflow approval requests (comma-separated for multiple)',
            ]
        );

        \App\Models\Setting::updateOrCreate(
            ['key' => 'subcon_gramasi_approver_email'],
            [
                'value' => $request->input('subcon_gramasi_approver_email'),
                'group' => 'subcon',
                'type' => 'string',
                'description' => 'Email address(es) for routing gramasi workflow approval requests (comma-separated for multiple)',
            ]
        );

        \App\Models\Setting::updateOrCreate(
            ['key' => 'subcon_label_generator_email'],
            [
                'value' => $request->input('subcon_label_generator_email', ''),
                'group' => 'subcon',
                'type' => 'string',
                'description' => 'Email address(es) that receive the signed link to generate distribution packing labels (comma-separated for multiple)',
            ]
        );

        \App\Models\Setting::updateOrCreate(
            ['key' => 'qc_ho_approver_email'],
            [
                'value' => $request->input('qc_ho_approver_email', ''),
                'group' => 'subcon',
                'type' => 'string',
                'description' => 'Final (Head Office) approver email address(es) for the second-stage consumption approval (comma-separated for multiple); falls back to the cutting approver list when blank',
            ]
        );

        \App\Models\Setting::updateOrCreate(
            ['key' => 'qc_head_notification_email'],
            [
                'value' => $request->input('qc_head_notification_email', ''),
                'group' => 'subcon',
                'type' => 'string',
                'description' => 'QC Head email address(es) notified when the Director authorizes and the project completes (comma-separated for multiple); notification only, no approval action',
            ]
        );

        \App\Models\Setting::updateOrCreate(
            ['key' => 'qc_director_approver_email'],
            [
                'value' => $request->input('qc_director_approver_email', ''),
                'group' => 'subcon',
                'type' => 'string',
                'description' => 'Director email address(es) for the third-stage authorization after MD Production approves (comma-separated for multiple); falls back to the Final approver list when blank',
            ]
        );

        \App\Models\Setting::updateOrCreate(
            ['key' => 'qc_director_approver_phone'],
            [
                'value' => $request->input('qc_director_approver_phone', ''),
                'group' => 'subcon',
                'type' => 'string',
                'description' => 'Director WhatsApp number(s) for the third-stage authorization, sent alongside the email (comma-separated for multiple, e.g. 08123456789)',
            ]
        );

        // Material Flow auto-approve override — see MaterialReturnService::
        // autoApproveActive(). A checkbox: absent from the request entirely
        // when unchecked. "Keep note" — log every actual flip (not every
        // save) as its own audit row, distinct from the per-send note
        // QcApprovalController::hoApprove() appends when a send goes through
        // under this override.
        $materialFlowAutoApproveNew = $request->boolean('subcon_material_flow_auto_approve');
        $materialFlowAutoApproveWas = app(\App\Services\MaterialReturnService::class)->autoApproveActive();
        \App\Models\Setting::updateOrCreate(
            ['key' => 'subcon_material_flow_auto_approve'],
            [
                'value' => $materialFlowAutoApproveNew ? '1' : '0',
                'group' => 'subcon',
                'type' => 'boolean',
                'description' => 'Bypasses the "inventory must check returned material" Material Flow gate for every order while on — an operational override, not a real check. See MaterialReturnService::autoApproveActive().',
            ]
        );
        if ($materialFlowAutoApproveNew !== $materialFlowAutoApproveWas) {
            \App\Models\SubconApprovalLog::record([
                'order_id' => null,
                'order_number' => null,
                'vendor_name' => null,
                'gate' => 'material_flow_override',
                'decision' => $materialFlowAutoApproveNew ? 'enabled' : 'disabled',
                'actor' => $request->user()->name ?? 'Admin',
                'source' => 'portal',
                'note' => $materialFlowAutoApproveNew
                    ? 'Material Flow check auto-approve turned ON — applies to every order until turned off.'
                    : 'Material Flow check auto-approve turned OFF — the real inventory-check gate is enforced again.',
            ]);
        }

        return redirect()->back()->with('success', 'Workflow settings updated successfully.');
    }
}
