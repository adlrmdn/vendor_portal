<?php

namespace App\Http\Controllers;

use App\Mail\SubconApprovalRequestMailable;
use App\Models\SubconCuttingReport;
use App\Models\SubconOrder;
use App\Models\User;
use App\Notifications\SubconApprovalRequested;
use App\Notifications\SubconOrderCompleted;
use App\Services\D365JobTransactionService;
use App\Services\SubconLabelService;
use App\Services\SubconProductionService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;

class SubconVendorController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth');
        $this->middleware(function ($request, $next) {
            if (Auth::user()->role !== 'subcon_vendor') {
                abort(403, 'Unauthorized access.');
            }

            return $next($request);
        });
    }

    public function dashboard()
    {
        $vendorId = Auth::user()->vendor_id;

        $stats = [
            'total_orders' => SubconOrder::where('vendor_id', $vendorId)->count(),
            'active_orders' => SubconOrder::where('vendor_id', $vendorId)
                ->whereIn('status', ['pending', 'in_progress'])->count(),
            'completed' => SubconOrder::where('vendor_id', $vendorId)
                ->where('status', 'completed')->count(),
        ];

        $recentOrders = SubconOrder::with('items')
            ->where('vendor_id', $vendorId)
            ->orderBy('created_at', 'desc')
            ->limit(5)
            ->get();

        return view('subcon.vendor.dashboard', compact('stats', 'recentOrders'));
    }

    public function orders(\Illuminate\Http\Request $request)
    {
        $vendorId = Auth::user()->vendor_id;

        $style = trim((string) $request->input('style', ''));

        $driver = \Illuminate\Support\Facades\DB::connection()->getDriverName();
        $likeOperator = $driver === 'pgsql' ? 'ilike' : 'like';

        $orders = SubconOrder::with('items')
            ->where('vendor_id', $vendorId)
            ->when($style !== '', function ($q) use ($style, $likeOperator) {
                $words = array_filter(explode(' ', $style));
                $q->where(function ($sub) use ($words, $likeOperator) {
                    foreach ($words as $word) {
                        $sub->where(function ($inner) use ($word, $likeOperator) {
                            $inner->where('title', $likeOperator, '%'.$word.'%')
                                ->orWhere('order_number', $likeOperator, '%'.$word.'%')
                                ->orWhere('production_group', $likeOperator, '%'.$word.'%');
                        });
                    }
                });
            })
            ->orderBy('created_at', 'desc')
            ->paginate(20)
            ->withQueryString();

        return view('subcon.vendor.orders.index', compact('orders', 'style'));
    }

    /**
     * Save the vendor's free-text remarks on a work order. No approval and no
     * stage gate — remarks can be updated at any point. Best-effort mirrored to
     * the QC Console.
     */
    public function saveRemarks(\Illuminate\Http\Request $request, string $id, \App\Services\SubconRemarksPublisher $remarks)
    {
        $order = SubconOrder::where('vendor_id', Auth::user()->vendor_id)->findOrFail($id);

        $data = $request->validate([
            'remarks' => 'nullable|string|max:5000',
        ]);

        $order->remarks = $data['remarks'] ?? null;
        $order->save();

        $remarks->publish($order);

        return back()->with('success', 'Remarks saved.');
    }

    public function viewOrder(string $id, SubconProductionService $production)
    {
        $vendorId = Auth::user()->vendor_id;

        $order = SubconOrder::with('items')
            ->where('vendor_id', $vendorId)
            ->findOrFail($id);

        // Garment production detail backing this CMT PO (best-effort; [] if unlinked).
        $productionGroups = $production->forPo($order->order_number);
        $summary = $production->summarize($productionGroups);

        $cuttingReports = SubconCuttingReport::where('order_id', $order->id)
            ->get()
            ->keyBy('prod_id');

        // Linked fabrics (from VSM fabric POs) + any saved per-fabric reconciliation,
        // and the accessory counterpart — fetched the same way. Both best-effort:
        // a VSM/DB hiccup must not take down the order page.
        $fabricLines = $production->fabricLinesForPo($order->order_number);
        $accessoryLines = $production->accessoryLinesForPo($order->order_number);
        try {
            $fabricRecon = \App\Models\SubconFabricReconciliation::where('order_id', $order->id)
                ->get()
                ->keyBy('label');
        } catch (\Throwable $e) {
            report($e);
            $fabricRecon = collect();
        }

        $materialReturnService = app(\App\Services\MaterialReturnService::class);
        $materialReturns = $materialReturnService->attachmentsFor($order);
        $materialReturnLines = $materialReturnService->linesFor($order);
        $materialReturnTask = $materialReturnService->activeTaskFor($order);
        // See QcApprovalController::hoApprovalForm()'s identical comment —
        // must reflect the real gate, not just the latest task's status.
        $materialReturnPending = $materialReturnService->isReturnCheckPending($order);
        $materialReturnAutoApproved = $materialReturnService->isAutoApproved($order);
        $accessoryRecon = $materialReturnLines->where('item_type', 'accessory')->keyBy('label');

        // Richer fallback for production-detail.blade.php's per-size table when
        // the VSM/PLM chain is broken — same fetch as
        // QcApprovalController::hoApprovalForm(); see that method's comment.
        $qcSizeOrderQty = [];
        if (empty($productionGroups) && ! empty($order->production_group)) {
            try {
                if (\Illuminate\Support\Facades\Schema::connection('qms')->hasTable('packaging_projects')
                    && \Illuminate\Support\Facades\Schema::connection('qms')->hasTable('packaging_project_reports')) {
                    $projectId = \Illuminate\Support\Facades\DB::connection('qms')->table('packaging_projects')
                        ->where('production_group', $order->production_group)
                        ->orderByDesc('created_at')
                        ->value('project_id');
                    if ($projectId) {
                        $qcSizeOrderQty = \Illuminate\Support\Facades\DB::connection('qms')->table('packaging_project_reports')
                            ->where('project_id', $projectId)
                            ->whereNull('session_id')
                            ->pluck('qty_order', 'size_val')
                            ->map(fn ($v) => (int) $v)
                            ->all();
                    }
                }
            } catch (\Throwable $e) {
                // Leave empty — the partial just falls further back.
            }
        }

        return view('subcon.vendor.orders.show', compact('order', 'productionGroups', 'summary', 'cuttingReports', 'fabricLines', 'fabricRecon', 'accessoryLines', 'accessoryRecon', 'materialReturns', 'materialReturnTask', 'materialReturnPending', 'materialReturnAutoApproved', 'qcSizeOrderQty'));
    }

    /**
     * Vendor-side material-return delivery note. Open any time up until
     * Report Validation is actually sent to the Director — see
     * SubconOrder::materialReturnVendorWindowOpen().
     */
    public function uploadMaterialReturn(\Illuminate\Http\Request $request, string $id, \App\Services\MaterialReturnService $materialReturns)
    {
        $vendorId = Auth::user()->vendor_id;
        $order = SubconOrder::with('vendor')->where('vendor_id', $vendorId)->findOrFail($id);

        if (! $order->materialReturnVendorWindowOpen()) {
            return back()->with('error', 'This order has already been sent to the Director — a material-return note can no longer be attached.');
        }

        $data = $request->validate([
            'file' => 'required|file|mimes:pdf,jpg,jpeg,png|max:10240',
            'note' => 'nullable|string|max:1000',
        ]);

        $materialReturns->upload($order, $data['file'], $data['note'] ?? null, \App\Models\MaterialReturnAttachment::ROLE_VENDOR, Auth::user()->name);

        return back()->with('success', 'Material-return delivery note attached.');
    }

    /**
     * Removes a vendor-uploaded delivery note. Same window as
     * uploadMaterialReturn() above; scoped to the vendor's own uploads — see
     * MaterialReturnService::deleteAttachment()'s docblock.
     */
    public function deleteMaterialReturn(string $id, string $attachment, \App\Services\MaterialReturnService $materialReturns)
    {
        $vendorId = Auth::user()->vendor_id;
        $order = SubconOrder::with('vendor')->where('vendor_id', $vendorId)->findOrFail($id);

        if (! $order->materialReturnVendorWindowOpen()) {
            return back()->with('error', 'This order has already been sent to the Director — a material-return note can no longer be removed.');
        }

        $error = $materialReturns->deleteAttachment($order, $attachment, \App\Models\MaterialReturnAttachment::ROLE_VENDOR);

        return $error
            ? back()->with('error', $error)
            : back()->with('success', 'Delivery note removed.');
    }

    /**
     * Step 3 → 4: vendor saves the cutting report (qty per size) and submits it
     * for admin approval. No D365 sync yet — that "original mechanism" only
     * starts once the cutting report is approved.
     */
    public function submitCuttingReport(\Illuminate\Http\Request $request, string $id)
    {
        $vendorId = Auth::user()->vendor_id;
        $order = SubconOrder::with('vendor')->where('vendor_id', $vendorId)->findOrFail($id);

        if (! $order->canEditCutting()) {
            return back()->with('error', 'Cutting report can no longer be edited at this stage ('.$order->stageLabel().').');
        }

        $data = $request->validate([
            'is_partial' => 'nullable|boolean',
            'blister_capacity' => 'nullable|integer|min:1',
            'sack_capacity' => 'nullable|integer|min:1',
            'fabrics_recon' => 'nullable|array',
            'fabrics_recon.*.label' => 'required_with:fabrics_recon|string|max:500',
            'fabrics_recon.*.short_roll' => 'nullable|numeric|min:0',
            'fabrics_recon.*.sisa_kain' => 'nullable|numeric|min:0',
            'fabrics_recon.*.kepala_kain' => 'nullable|numeric|min:0',
            'fabrics_recon.*.retur_kain' => 'nullable|numeric|min:0',
            'accessories_recon' => 'nullable|array',
            'accessories_recon.*.label' => 'required_with:accessories_recon|string|max:500',
            'accessories_recon.*.qty' => 'nullable|numeric|min:0',
            'accessories_recon.*.unit' => 'nullable|string|max:20',
            'reports' => 'required|array',
            'reports.*.prod_id' => 'required|string',
            'reports.*.size' => 'required|string',
            'reports.*.cutting_qty' => 'nullable|integer|min:0',
            'reports.*.gramasi' => 'nullable|numeric|min:0',
        ]);

        // Safety: don't let an empty report move to approval.
        $totalCut = collect($data['reports'])->sum(fn ($r) => (int) ($r['cutting_qty'] ?? 0));
        if ($totalCut <= 0) {
            return back()->withInput()->with('error', 'Enter at least one cutting quantity before submitting.');
        }

        $duplicate = false;
        \Illuminate\Support\Facades\DB::transaction(function () use (&$order, $data, &$duplicate) {
            // Re-check under a row lock: closes the window where a double-click
            // or resubmitted POST lands after the first request already moved
            // the order to cutting_review, which would otherwise re-spawn a
            // second approval email + admin notification for the same submit.
            $order = SubconOrder::where('id', $order->id)->lockForUpdate()->first();
            if (! $order->canEditCutting()) {
                $duplicate = true;

                return;
            }

            foreach ($data['reports'] as $reportData) {
                $updateData = [
                    'size' => $reportData['size'],
                    'cutting_qty' => $reportData['cutting_qty'] ?? 0,
                ];
                if (array_key_exists('gramasi', $reportData) && $reportData['gramasi'] !== null && $reportData['gramasi'] !== '') {
                    $updateData['gramasi'] = $reportData['gramasi'];
                }
                SubconCuttingReport::updateOrCreate(
                    ['order_id' => $order->id, 'prod_id' => $reportData['prod_id']],
                    $updateData
                );
            }

            // Blister & sack capacity can be captured early (optional here).
            if (! empty($data['blister_capacity'])) {
                $order->blister_capacity = $data['blister_capacity'];
            }
            if (! empty($data['sack_capacity'])) {
                $order->sack_capacity = $data['sack_capacity'];
            }

            app(\App\Services\MaterialReturnService::class)->persistReconciliation(
                $order,
                $data['fabrics_recon'] ?? [],
                $data['accessories_recon'] ?? [],
                \App\Models\MaterialReturnAttachment::ROLE_VENDOR,
                Auth::user()->name,
                lockReturKain: true
            );

            // Update job_trans_status sequence
            if (empty($order->job_trans_status) || $order->job_trans_status === 'not_saved') {
                $order->job_trans_status = 'first_saved';
            } elseif ($order->job_trans_status === 'first_saved') {
                $order->job_trans_status = 'qty_cutting_saved';
            }

            // Partial toggle (default: not partial): a partial report is flagged
            // to the approver, and approving it returns the order here so the
            // vendor can submit the remaining quantities. Set on every submit,
            // so a final (non-partial) resubmit clears it.
            $order->cutting_partial = (bool) ($data['is_partial'] ?? false);

            $order->workflow_stage = SubconOrder::STAGE_CUTTING_REVIEW;
            // Resubmitting clears a stale rejection for this same gate — it's no
            // longer relevant once the vendor has addressed it.
            if ($order->reject_gate === 'cutting') {
                $order->reject_gate = null;
                $order->reject_reason = null;
                $order->rejected_at = null;
            }
            // status is derived from workflow_stage in the model's saving hook.
            $order->save();
        });

        if ($duplicate) {
            return back()->with('error', 'This cutting report was already submitted and is awaiting approval.');
        }

        $this->emailApprover($order, 'cutting');
        $this->notifyAdmins(new SubconApprovalRequested($order, 'cutting'));

        return back()->with('success', $order->cutting_partial
            ? 'Partial cutting report submitted for approval. After approval you can continue entering the remaining quantities.'
            : 'Cutting report submitted for approval.');
    }

    /**
     * Material Reconciliation (Fabric + Accessory), saveable independently of
     * the cutting-report submission — usable at any stage up to Report
     * Validation being sent (materialReturnVendorWindowOpen()), not just at
     * cutting. The cutting-report form keeps its own embedded copy too (see
     * submitCuttingReport) — both call this same persistence helper.
     */
    public function saveMaterialReconciliation(\Illuminate\Http\Request $request, string $id)
    {
        $vendorId = Auth::user()->vendor_id;
        $order = SubconOrder::with('vendor')->where('vendor_id', $vendorId)->findOrFail($id);

        if (! $order->materialReturnVendorWindowOpen()) {
            return back()->with('error', 'This order has already been sent to the Director — Material Reconciliation can no longer be edited.');
        }

        $data = $request->validate([
            'fabrics_recon' => 'nullable|array',
            'fabrics_recon.*.label' => 'required_with:fabrics_recon|string|max:500',
            'fabrics_recon.*.short_roll' => 'nullable|numeric|min:0',
            'fabrics_recon.*.sisa_kain' => 'nullable|numeric|min:0',
            'fabrics_recon.*.kepala_kain' => 'nullable|numeric|min:0',
            'fabrics_recon.*.retur_kain' => 'nullable|numeric|min:0',
            'accessories_recon' => 'nullable|array',
            'accessories_recon.*.label' => 'required_with:accessories_recon|string|max:500',
            'accessories_recon.*.qty' => 'nullable|numeric|min:0',
            'accessories_recon.*.unit' => 'nullable|string|max:20',
        ]);

        app(\App\Services\MaterialReturnService::class)->persistReconciliation(
            $order,
            $data['fabrics_recon'] ?? [],
            $data['accessories_recon'] ?? [],
            \App\Models\MaterialReturnAttachment::ROLE_VENDOR,
            Auth::user()->name,
            lockReturKain: true
        );

        return back()->with('success', 'Material Reconciliation saved.');
    }

    /**
     * Step 6 → 7: vendor enters gramasi per size + the single blister capacity
     * and submits for approval. Gramasi is synced to D365 on save.
     */
    public function submitGramasi(\Illuminate\Http\Request $request, string $id)
    {
        $vendorId = Auth::user()->vendor_id;
        $order = SubconOrder::with('vendor')->where('vendor_id', $vendorId)->findOrFail($id);

        if (! $order->canEditGramasi()) {
            return back()->with('error', 'Gramasi & blister capacity can only be entered after the cutting report is approved (current stage: '.$order->stageLabel().').');
        }

        $data = $request->validate([
            'blister_capacity' => 'nullable|integer|min:1',
            'sack_capacity' => 'nullable|integer|min:1',
            'reports' => 'required|array',
            'reports.*.prod_id' => 'required|string',
            'reports.*.size' => 'required|string',
            'reports.*.gramasi' => 'nullable|numeric|min:0',
            'reports.*.cutting_qty' => 'nullable|integer|min:0',
        ]);

        $duplicate = false;
        \Illuminate\Support\Facades\DB::transaction(function () use (&$order, $data, &$duplicate) {
            // Re-check under a row lock — see the same guard in submitCuttingReport().
            $order = SubconOrder::where('id', $order->id)->lockForUpdate()->first();
            if (! $order->canEditGramasi()) {
                $duplicate = true;

                return;
            }

            foreach ($data['reports'] as $reportData) {
                $updateData = [
                    'size' => $reportData['size'],
                    'gramasi' => ! empty($reportData['gramasi']) ? $reportData['gramasi'] : null,
                ];
                if (array_key_exists('cutting_qty', $reportData) && $reportData['cutting_qty'] !== null && $reportData['cutting_qty'] !== '') {
                    $updateData['cutting_qty'] = $reportData['cutting_qty'];
                }
                SubconCuttingReport::updateOrCreate(
                    ['order_id' => $order->id, 'prod_id' => $reportData['prod_id']],
                    $updateData
                );
            }

            // Optional now — keep the previously saved value when left blank.
            $order->blister_capacity = ! empty($data['blister_capacity'])
                ? (int) $data['blister_capacity']
                : ($order->blister_capacity ?: null);
            // Sack (karung) capacity for the Pemalang distribution warehouses;
            // falls back to the standard 50 when left blank.
            $order->sack_capacity = ! empty($data['sack_capacity'])
                ? (int) $data['sack_capacity']
                : ($order->sack_capacity ?: 50);
            $order->workflow_stage = SubconOrder::STAGE_GRAMASI_REVIEW;
            // Resubmitting clears a stale rejection for this same gate — it's no
            // longer relevant once the vendor has addressed it.
            if ($order->reject_gate === 'gramasi') {
                $order->reject_gate = null;
                $order->reject_reason = null;
                $order->rejected_at = null;
            }
            $order->save();
        });

        if ($duplicate) {
            return back()->with('error', 'Gramasi & blister capacity were already submitted and are awaiting approval.');
        }

        // No D365 sync here: gramasi is only pushed to D365 once approved
        // (dispatched from SubconApprovalController), mirroring the cutting flow.
        $this->emailApprover($order, 'gramasi');
        $this->notifyAdmins(new SubconApprovalRequested($order, 'gramasi'));

        return back()->with('success', 'Gramasi & blister capacity submitted for approval.');
    }

    /** Step 9: vendor completes the PO after labels are unlocked. */
    public function completeOrder(string $id)
    {
        $vendorId = Auth::user()->vendor_id;
        $order = SubconOrder::where('vendor_id', $vendorId)->findOrFail($id);

        if (! $order->canComplete()) {
            return back()->with('error', 'This order cannot be completed at its current stage ('.$order->stageLabel().').');
        }

        $order->workflow_stage = SubconOrder::STAGE_COMPLETED;
        // status is derived from workflow_stage in the model's saving hook.
        $order->save();

        $this->notifyAdmins(new SubconOrderCompleted($order));

        return back()->with('success', 'Work order marked as completed.');
    }

    private function emailApprover(SubconOrder $order, string $gate): void
    {
        try {
            $recipients = $gate === 'cutting'
                ? SubconApprovalController::cuttingApproverRecipient()
                : SubconApprovalController::gramasiApproverRecipient();

            if (! empty($recipients)) {
                Mail::to($recipients)
                    ->send(new SubconApprovalRequestMailable($order, $gate));
            }
        } catch (\Throwable $e) {
            Log::error('Subcon approval request email failed: '.$e->getMessage());
        }
    }

    /** Send an in-app notification to all subcon admins. Best-effort. */
    private function notifyAdmins($notification): void
    {
        try {
            $admins = User::whereIn('role', ['subcon_admin', 'admin'])->get();
            if ($admins->isNotEmpty()) {
                Notification::send($admins, $notification);
            }
        } catch (\Throwable $e) {
            Log::error('Subcon admin notification failed: '.$e->getMessage());
        }
    }

    public function downloadTemplate(string $id, SubconProductionService $production)
    {
        $vendorId = Auth::user()->vendor_id;
        $order = SubconOrder::where('vendor_id', $vendorId)->findOrFail($id);

        // Pre-fill the template with this order's production lines so the vendor
        // only fills Qty Cut / Gramasi against the right size & production id.
        $lines = [];
        foreach ($production->forPo($order->order_number) as $g) {
            foreach (($g['lines'] ?? []) as $line) {
                $lines[] = [
                    'prod_id' => $line->ProdId,
                    'size' => $line->Size ?: '—',
                    'order_qty' => (int) $line->Qty,
                ];
            }
        }

        $safe = str_replace(['/', '\\'], '-', $order->order_number);

        return \Maatwebsite\Excel\Facades\Excel::download(
            new \App\Exports\SubconCuttingTemplateExport($lines, $order),
            'cutting_report_'.$safe.'.xlsx'
        );
    }

    public function uploadReport(
        \Illuminate\Http\Request $request,
        string $id,
        SubconProductionService $production,
        D365JobTransactionService $d365Service
    ) {
        $vendorId = Auth::user()->vendor_id;
        $order = SubconOrder::where('vendor_id', $vendorId)->findOrFail($id);

        if (! $order->canEditCutting() && ! $order->canEditGramasi()) {
            return back()->with('error', 'Upload is not available at this stage ('.$order->stageLabel().').');
        }

        $request->validate([
            'file' => 'required|file|mimes:xlsx,xls,pdf,csv,txt',
        ]);

        $file = $request->file('file');
        $extension = strtolower($file->getClientOriginalExtension());

        $productionGroups = $production->forPo($order->order_number);
        $validProdIds = [];
        $prodSizes = [];
        foreach ($productionGroups as $g) {
            if (! empty($g['lines'])) {
                foreach ($g['lines'] as $line) {
                    $validProdIds[] = $line->ProdId;
                    $prodSizes[$line->ProdId] = $line->Size ?: '—';
                }
            }
        }

        if (empty($validProdIds)) {
            return back()->with('error', 'Cannot upload cutting report because this order has no production details associated.');
        }

        $parsedData = [];

        try {
            if ($extension === 'pdf') {
                $parser = app(\Smalot\PdfParser\Parser::class);
                $pdf = $parser->parseFile($file->getPathname());
                $text = $pdf->getText();

                $lines = explode("\n", $text);
                foreach ($lines as $lineText) {
                    $lineText = trim($lineText);
                    foreach ($validProdIds as $prodId) {
                        if (stripos($lineText, $prodId) !== false) {
                            $remainingText = str_ireplace($prodId, '', $lineText);
                            $cuttingQty = null;
                            $gramasi = null;

                            // Label matching
                            if (preg_match('/(?:qty\s*cut|cutting\s*qty|qty|cut)[:\s]+(\d+)/i', $remainingText, $matches)) {
                                $cuttingQty = (int) $matches[1];
                            }
                            if (preg_match('/(?:gramasi|gram|gr|weight)[:\s]+(\d+(?:\.\d+)?)/i', $remainingText, $matches)) {
                                $gramasi = (float) $matches[1];
                            }

                            // Positional fallback
                            if ($cuttingQty === null && $gramasi === null) {
                                if (preg_match_all('/\b\d+(?:\.\d+)?\b/', $remainingText, $matches)) {
                                    $numbers = $matches[0];
                                    if (count($numbers) >= 2) {
                                        $cuttingQty = (int) $numbers[0];
                                        $gramasi = (float) $numbers[1];
                                    } elseif (count($numbers) === 1) {
                                        $num = $numbers[0];
                                        if (strpos($num, '.') !== false) {
                                            $gramasi = (float) $num;
                                        } else {
                                            $cuttingQty = (int) $num;
                                        }
                                    }
                                }
                            }

                            if ($cuttingQty !== null || $gramasi !== null) {
                                if (! isset($parsedData[$prodId])) {
                                    $parsedData[$prodId] = [];
                                }
                                if ($cuttingQty !== null) {
                                    $parsedData[$prodId]['cutting_qty'] = $cuttingQty;
                                }
                                if ($gramasi !== null) {
                                    $parsedData[$prodId]['gramasi'] = $gramasi;
                                }
                            }
                        }
                    }
                }
            } else {
                $sheets = \Maatwebsite\Excel\Facades\Excel::toArray([], $file);
                if (! empty($sheets) && ! empty($sheets[0])) {
                    $sheet = $sheets[0];

                    // Detect column indices dynamically based on headers
                    $colProdId = -1;
                    $colSize = -1;
                    $colQtyCut = -1;
                    $colGramasi = -1;

                    // Locate the size-table header row. Newer templates carry a
                    // "Fabric Reconciliation" reference block above it, so scan for
                    // the row holding the Production ID / Size header; fall back to
                    // row 0 for older/hand-made files.
                    $headerIdx = 0;
                    foreach ($sheet as $rIdx => $scanRow) {
                        foreach ($scanRow as $scanCell) {
                            $scanCell = trim((string) $scanCell);
                            if (stripos($scanCell, 'Production ID') !== false
                                || stripos($scanCell, 'Prod ID') !== false
                                || stripos($scanCell, 'ProdId') !== false) {
                                $headerIdx = $rIdx;
                                break 2;
                            }
                        }
                    }

                    $firstRow = $sheet[$headerIdx] ?? [];
                    foreach ($firstRow as $idx => $cellValue) {
                        $cellValue = trim($cellValue);
                        if (empty($cellValue)) {
                            continue;
                        }
                        if (stripos($cellValue, 'Production ID') !== false || stripos($cellValue, 'ProdId') !== false || stripos($cellValue, 'Prod ID') !== false) {
                            $colProdId = $idx;
                        } elseif (stripos($cellValue, 'Order Qty') !== false || stripos($cellValue, 'Order Quantity') !== false) {
                            // Reference column from the pre-filled template — never import it.
                            continue;
                        } elseif (stripos($cellValue, 'Size') !== false) {
                            $colSize = $idx;
                        } elseif (stripos($cellValue, 'Qty Cut') !== false || stripos($cellValue, 'Cutting Qty') !== false || stripos($cellValue, 'Qty') !== false || stripos($cellValue, 'Cut') !== false) {
                            $colQtyCut = $idx;
                        } elseif (stripos($cellValue, 'Gramasi') !== false || stripos($cellValue, 'Gram') !== false || stripos($cellValue, 'Weight') !== false) {
                            $colGramasi = $idx;
                        }
                    }

                    // Positional fallbacks only if no headers were recognized at all
                    if ($colProdId === -1 && $colSize === -1 && $colQtyCut === -1 && $colGramasi === -1) {
                        $colProdId = 0;
                        $colSize = 1;
                        $colQtyCut = (count($firstRow) >= 5) ? 3 : 2;
                        $colGramasi = (count($firstRow) >= 5) ? 4 : 3;
                    }

                    // Parse row data (everything below the detected header row).
                    for ($i = $headerIdx + 1; $i < count($sheet); $i++) {
                        $row = $sheet[$i];

                        $prodIdVal = ($colProdId !== -1 && isset($row[$colProdId])) ? trim($row[$colProdId]) : '';
                        $sizeVal = ($colSize !== -1 && isset($row[$colSize])) ? trim($row[$colSize]) : '';
                        $cuttingVal = ($colQtyCut !== -1 && isset($row[$colQtyCut])) ? $row[$colQtyCut] : '';
                        $gramasiVal = ($colGramasi !== -1 && isset($row[$colGramasi])) ? $row[$colGramasi] : '';

                        if (empty($prodIdVal) && empty($sizeVal) && empty($cuttingVal) && empty($gramasiVal)) {
                            continue;
                        }

                        $matchedProdIds = [];

                        // Match by ProdId first
                        if (! empty($prodIdVal)) {
                            foreach ($validProdIds as $vId) {
                                if (strcasecmp($vId, $prodIdVal) === 0) {
                                    $matchedProdIds[] = $vId;
                                    break;
                                }
                            }
                        }

                        // Fallback: match by Size
                        if (empty($matchedProdIds) && ! empty($sizeVal)) {
                            foreach ($productionGroups as $g) {
                                if (! empty($g['lines'])) {
                                    foreach ($g['lines'] as $line) {
                                        if (strcasecmp($line->Size ?: '', $sizeVal) === 0) {
                                            $matchedProdIds[] = $line->ProdId;
                                        }
                                    }
                                }
                            }
                        }

                        $cuttingQty = (is_numeric($cuttingVal) && $cuttingVal >= 0) ? (int) $cuttingVal : null;
                        $gramasi = (is_numeric($gramasiVal) && $gramasiVal >= 0) ? (float) $gramasiVal : null;

                        if (($cuttingQty !== null || $gramasi !== null) && ! empty($matchedProdIds)) {
                            foreach ($matchedProdIds as $matchedProdId) {
                                if (! isset($parsedData[$matchedProdId])) {
                                    $parsedData[$matchedProdId] = [];
                                }
                                if ($cuttingQty !== null) {
                                    $parsedData[$matchedProdId]['cutting_qty'] = $cuttingQty;
                                }
                                if ($gramasi !== null) {
                                    $parsedData[$matchedProdId]['gramasi'] = $gramasi;
                                }
                            }
                        }
                    }
                }
            }

            if (empty($parsedData)) {
                return back()->with('error', 'No valid cutting report or gramasi data could be parsed from the uploaded file.');
            }

            // Persist the fields found in the file.
            $saved = 0;

            \Illuminate\Support\Facades\DB::transaction(function () use ($order, $parsedData, $prodSizes, &$saved) {
                foreach ($parsedData as $prodId => $values) {
                    $updateData = ['size' => $prodSizes[$prodId] ?? '—'];
                    $hasUpdate = false;

                    if (array_key_exists('cutting_qty', $values) && $values['cutting_qty'] !== null && $values['cutting_qty'] !== '') {
                        $updateData['cutting_qty'] = $values['cutting_qty'];
                        $hasUpdate = true;
                    }
                    if (array_key_exists('gramasi', $values) && $values['gramasi'] !== null && $values['gramasi'] !== '') {
                        $updateData['gramasi'] = $values['gramasi'];
                        $hasUpdate = true;
                    }

                    if ($hasUpdate) {
                        SubconCuttingReport::updateOrCreate(
                            ['order_id' => $order->id, 'prod_id' => $prodId],
                            $updateData
                        );
                        $saved++;
                    }
                }
            });

            if ($saved === 0) {
                return back()->with('error', 'No valid data found in the uploaded file.');
            }

            return back()->with('success', $saved.' row(s) imported. Review the values, then submit for approval.');

        } catch (\Throwable $e) {
            report($e);

            return back()->with('error', 'Failed to process file: '.$e->getMessage());
        }
    }

    public function printPackagingLabels(\Illuminate\Http\Request $request, string $id, SubconLabelService $labels)
    {
        $vendorId = Auth::user()->vendor_id;
        $order = SubconOrder::with('vendor')->where('vendor_id', $vendorId)->findOrFail($id);

        if (! $order->canPrintLabels()) {
            return back()->with('error', 'Label printing unlocks after gramasi & blister capacity are approved (current stage: '.$order->stageLabel().').');
        }

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
     * Show subcontractor profile page.
     */
    public function profile()
    {
        $vendorId = Auth::user()->vendor_id;
        $vendor = \App\Models\Vendor::findOrFail($vendorId);

        return view('subcon.vendor.profile', compact('vendor'));
    }

    /**
     * Bahasa Indonesia PDF user guide for the subcon vendor portal.
     */
    public function userGuide()
    {
        $pdf = \Barryvdh\DomPDF\Facade\Pdf::loadView('subcon.pdf.vendor-guide-id')->setPaper('a4');

        return $pdf->stream('Panduan Vendor - Portal Subkontraktor.pdf');
    }

    /**
     * Update subcontractor email contact.
     */
    public function updateProfile(\Illuminate\Http\Request $request)
    {
        $vendorId = Auth::user()->vendor_id;
        $vendor = \App\Models\Vendor::findOrFail($vendorId);

        $request->validate([
            'email' => 'required|email|max:255',
        ]);

        $contactInfo = $vendor->contact_info ?? [];
        $contactInfo['email'] = $request->input('email');

        $vendor->contact_info = $contactInfo;
        $vendor->save();

        return redirect()->back()->with('success', 'Profile details updated successfully.');
    }

    /**
     * Update subcontractor login password.
     */
    public function updatePassword(\Illuminate\Http\Request $request)
    {
        $user = Auth::user();

        $request->validate([
            'current_password' => 'required|string',
            'new_password' => 'required|string|min:8|confirmed',
        ]);

        if (! \Illuminate\Support\Facades\Hash::check($request->current_password, $user->password)) {
            return redirect()->back()->withErrors(['current_password' => 'The provided password does not match your current password.']);
        }

        $user->password = \Illuminate\Support\Facades\Hash::make($request->new_password);
        $user->save();

        return redirect()->back()->with('success', 'Password updated successfully.');
    }
}
