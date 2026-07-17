<?php

namespace App\Http\Controllers;

use App\Exports\SubconCuttingReportExport;
use App\Jobs\SyncSubconOrdersJob;
use App\Models\SubconCuttingReport;
use App\Models\SubconOrder;
use App\Models\User;
use App\Models\Vendor;
use App\Services\SubconLabelService;
use App\Services\SubconProductionService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Maatwebsite\Excel\Facades\Excel;

class SubconAdminController extends Controller
{
    public function __construct()
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
        $logs = \App\Models\SubconJobLog::with('order')
            ->orderBy('created_at', 'desc')
            ->paginate(30);

        return view('subcon.admin.logs.index', compact('logs'));
    }

    public function vendors()
    {
        $vendors = Vendor::where('type', 'subcon')
            ->withCount('subconOrders')
            ->orderBy('name')
            ->paginate(20);

        return view('subcon.admin.vendors', compact('vendors'));
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
            Vendor::create([
                'id' => $vendorId,
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

        return view('subcon.admin.orders.index', compact('orders', 'vendors'));
    }

    /** Orders currently awaiting a cutting or gramasi approval decision. */
    public function approvals()
    {
        $orders = SubconOrder::with('vendor')
            ->whereIn('workflow_stage', [SubconOrder::STAGE_CUTTING_REVIEW, SubconOrder::STAGE_GRAMASI_REVIEW])
            ->orderBy('updated_at', 'desc')
            ->paginate(20);

        // Final (QC-console) approvals: stage-1 confirmed on the QMS session but
        // still awaiting Head-Office / Final sign-off. Read best-effort from QMS —
        // never let an unreachable/older QMS 500 this tab.
        $finalApprovals = $this->pendingFinalApprovals();

        return view('subcon.admin.approvals', compact('orders', 'finalApprovals'));
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
     */
    public function directorApprovals()
    {
        abort_unless(self::isDirectorUser(Auth::user()), 403);

        $pending = $this->pendingDirectorApprovals();

        $recentDecisions = \App\Models\SubconApprovalLog::query()
            ->where('gate', 'director')
            ->orderByDesc('created_at')
            ->limit(15)
            ->get();

        return view('subcon.admin.director-approvals', compact('pending', 'recentDecisions'));
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
                // Exclude projects completed under the old two-stage flow —
                // authorizing those would re-queue real invoice RPA jobs.
                ->whereNotIn('project_id', function ($q) {
                    $q->select('project_id')->from('packaging_projects')->where('status', 'completed');
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
                    'version' => $s->version ?? null,
                    'result' => $s->result ?? null,
                    'ho_signature' => $s->ho_approval_signature ?? null,
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

            $hasValidationCol = Schema::connection('qms')->hasColumn('packaging_project_sessions', 'ho_validation_signature');

            $sessions = DB::connection('qms')->table('packaging_project_sessions')
                ->whereNotNull('approval_token')
                ->where('approval_status', 'approved')
                ->where(function ($q) use ($hasValidationCol) {
                    // Step 1 pending: no HO signature yet.
                    $q->where(function ($qq) {
                        $qq->whereNull('ho_approval_signature')->orWhere('ho_approval_signature', '');
                    });
                    // Step 2 pending: approved but not yet validated & sent to
                    // the Director (and not already director-actioned).
                    if ($hasValidationCol) {
                        $q->orWhere(function ($qq) {
                            $qq->where('ho_approval_signature', 'like', 'Digitally Signed:%')
                                ->where(function ($v) {
                                    $v->whereNull('ho_validation_signature')->orWhere('ho_validation_signature', '');
                                })
                                ->where(function ($d) {
                                    $d->whereNull('director_approval_signature')->orWhere('director_approval_signature', '');
                                })
                                ->whereNotIn('project_id', function ($p) {
                                    $p->select('project_id')->from('packaging_projects')->where('status', 'completed');
                                });
                        });
                    }
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

                $hoSigned = str_starts_with((string) ($s->ho_approval_signature ?? ''), 'Digitally Signed:');

                return [
                    'token' => $s->approval_token,
                    'order_id' => $order->id ?? null,
                    'order_number' => $order->order_number ?? ($s->project_id ?? '—'),
                    'style' => $order->title ?? null,
                    'vendor' => $order?->vendor?->name ?? '—',
                    'production_group' => $pg,
                    'approved_at' => $s->approved_at ?? null,
                    // Which step of the MD gate is pending: 'approve' → Review &
                    // Approve, 'send' → Validate & Send Approval.
                    'step' => $hoSigned ? 'send' : 'approve',
                ];
            })->all();
        } catch (\Throwable $e) {
            Log::warning('Pending final approvals unavailable', ['error' => $e->getMessage()]);

            return [];
        }
    }

    public function waitingDistribution()
    {
        $orders = SubconOrder::with('vendor')
            ->where('workflow_stage', SubconOrder::STAGE_WAITING_DISTRIBUTION)
            ->orderBy('updated_at', 'desc')
            ->paginate(20);

        return view('subcon.admin.waiting-distribution', compact('orders'));
    }

    public function generateLabelsManual(Request $request, string $id)
    {
        $model = SubconOrder::findOrFail($id);

        if ($model->workflow_stage !== SubconOrder::STAGE_WAITING_DISTRIBUTION) {
            return back()->with('error', 'This work order is not waiting for distribution details.');
        }

        // Button-lock guard: don't stack a second run while one is in flight.
        if ($model->isGeneratingLabels()) {
            return back()->with('info', 'Label generation is already running for '.$model->order_number.'. Please wait for it to finish.');
        }

        // Lock the button (persisted state) then dispatch. Resolution + DTT call
        // run off the request cycle; on success the worker advances to "labels",
        // on failure it records the reason — both surfaced back on this page.
        $model->markLabelGenStarted();
        \App\Jobs\GenerateSubconLabels::dispatch($model->id);

        return back()->with('success', 'Label generation started for work order '.$model->order_number.'. It is running in the background — printing will unlock once the labels are ready.');
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

        return view('subcon.admin.orders.show', compact('order', 'productionGroups', 'summary', 'cuttingReports', 'fabricLines', 'totalCut'));
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

        // Meta header block.
        $rows = [
            ['Cutting Report'],
            ['Work Order', $order->order_number],
            ['Vendor', optional($order->vendor)->name ?? $order->vendor_id],
            ['Style', $order->title ?: '—'],
            ['Stage', $order->stageLabel()],
            ['Blister Capacity', $order->blister_capacity ?: '—'],
            ['Sack (Karung) Capacity', $order->sack_capacity ?? 50],
        ];

        // Fabric reconciliation — one block per fabric (by description). Entered
        // by the vendor on the cutting report; empty if none recorded yet.
        try {
            $reconciliations = \App\Models\SubconFabricReconciliation::where('order_id', $order->id)
                ->orderBy('label')
                ->get();
        } catch (\Throwable $e) {
            report($e);
            $reconciliations = collect();
        }
        if ($reconciliations->isEmpty()) {
            $rows[] = ['Fabric Reconciliation', '—'];
        } else {
            // Fixed 2 decimals for money/waste; min-2/max-4 for the two consumption
            // figures (Cons. Plan, Actual Cons.) — mirrors the in-app display rules.
            $fmt2 = fn ($v) => $v === null || $v === '' ? '—' : number_format((float) $v, 2);
            // Money (IDR): "Rp " + thousands + 2 decimals — uniform currency standard.
            $money = fn ($v) => $v === null || $v === '' ? '—' : 'Rp '.number_format((float) $v, 2);
            $fmtCons = function ($v) {
                if ($v === null || $v === '') {
                    return '—';
                }
                $v = (float) $v;
                $trimmed = rtrim(rtrim(sprintf('%.4F', $v), '0'), '.');
                $dec = ($pos = strpos($trimmed, '.')) !== false ? strlen(substr($trimmed, $pos + 1)) : 0;

                return number_format($v, max(2, $dec));
            };

            $rows[] = ['Fabric Reconciliation & Consumption', ''];
            $totalDeduction = 0.0;
            foreach ($reconciliations as $rec) {
                $totalDeduction += (float) $rec->deduction;
                $rows[] = ['  '.$rec->label, ''];
                // Vendor-entered / approver-overridable waste (all four enter the calc).
                $rows[] = ['    Short Roll', $fmt2($rec->short_roll)];
                $rows[] = ['    Sisa Kain (Utuh)', $fmt2($rec->sisa_kain)];
                $rows[] = ['    Kepala Kain', $fmt2($rec->kepala_kain)];
                $rows[] = ['    Retur Kain', $fmt2($rec->retur_kain)];
                // Consumption — entered/derived at cutting approval.
                $rows[] = ['    Fabric Sent', $fmt2($rec->fabric_sent)];
                $rows[] = ['    Cons. Plan', $fmtCons($rec->consumption_plan)];
                $rows[] = ['    Cutt Plan', $rec->cutt_plan !== null ? (string) (int) $rec->cutt_plan : '—'];
                $rows[] = ['    Actual Cons.', $fmtCons($rec->actual_consumption)];
                $rows[] = ['    Overconsumption', $rec->overconsumption !== null ? $fmt2((float) $rec->overconsumption * 100).'%' : '—'];
                $rows[] = ['    Fabric Price (IDR)', $fmt2($rec->fabric_price)];
                $rows[] = ['    Deduction (IDR)', $money($rec->deduction)];
            }
            $rows[] = ['  Total Deduction (IDR)', $money($totalDeduction)];
        }

        $rows = array_merge($rows, [
            ['Exported', now()->format('Y-m-d H:i')],
            [''], // spacer — a single empty cell so the writer keeps the row (an empty [] is dropped, desyncing indices)
        ]);

        $headerRow = count($rows) + 1; // 1-based row of the table header
        $rows[] = ['Size', 'PRD ID', 'Status', 'Order Qty', 'Qty Cut', 'Balance', 'Gramasi (g)'];

        $totalOrder = 0;
        $totalCut = 0;
        $anyCut = false;
        $hadLines = false;

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
                    $hasCut ? (int) $cutQty : '',
                    $hasCut ? ((int) $cutQty - $orderQty) : '',
                    $gramasi !== null ? (float) $gramasi : '',
                ];

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
                    '',
                    $cutQty !== null ? (int) $cutQty : '',
                    '',
                    $report->gramasi !== null ? (float) $report->gramasi : '',
                ];
            }
        }

        $totalRow = count($rows) + 1; // 1-based row of the totals line
        $rows[] = [
            'TOTAL', '', '',
            $totalOrder ?: '',
            $anyCut ? $totalCut : '',
            $anyCut ? ($totalCut - $totalOrder) : '',
            '',
        ];
        if ($anyCut) {
            $balanceColors[$totalRow] = ($totalCut - $totalOrder) < 0 ? 'C92A2A' : '2B8A3E';
        }

        $safeNumber = str_replace(['/', '\\'], '-', $order->order_number);
        $filename = 'cutting-report_'.$safeNumber.'_'.now()->format('Ymd-His').'.xlsx';

        return Excel::download(
            new SubconCuttingReportExport($rows, [1, $headerRow, $totalRow], $balanceColors),
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
            'action' => 'required|in:cancel,reactivate',
        ]);

        $order = SubconOrder::findOrFail($id);

        if ($request->action === 'cancel') {
            $order->status = 'cancelled';
            $order->save();

            return back()->with('success', 'Order cancelled.');
        }

        // Reactivate: re-derive status from the current workflow stage.
        $order->status = SubconOrder::inferStatusFromStage($order->workflow_stage);
        $order->save();

        return back()->with('success', 'Order reactivated.');
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
        $directorApproverEmail = \App\Models\Setting::getValue('qc_director_approver_email', '');

        return view('subcon.admin.workflow', compact('cuttingApproverEmail', 'gramasiApproverEmail', 'labelGeneratorEmail', 'finalApproverEmail', 'directorApproverEmail'));
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

        $request->validate([
            'subcon_cutting_approver_email' => ['required', $multiEmail],
            'subcon_gramasi_approver_email' => ['required', $multiEmail],
            'subcon_label_generator_email' => ['required', $multiEmail],
            // Second-stage (Head Office) consumption approval — optional; when
            // blank the HO email falls back to the cutting approver list.
            'qc_ho_approver_email' => ['nullable', $multiEmail],
            // Third-stage (Director) authorization — optional; when blank the
            // director email falls back to the Final (HO) list.
            'qc_director_approver_email' => ['nullable', $multiEmail],
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
            ['key' => 'qc_director_approver_email'],
            [
                'value' => $request->input('qc_director_approver_email', ''),
                'group' => 'subcon',
                'type' => 'string',
                'description' => 'Director email address(es) for the third-stage authorization after MD Production approves (comma-separated for multiple); falls back to the Final approver list when blank',
            ]
        );

        return redirect()->back()->with('success', 'Workflow settings updated successfully.');
    }
}
