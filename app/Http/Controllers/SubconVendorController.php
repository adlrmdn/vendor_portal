<?php

namespace App\Http\Controllers;

use App\Mail\SubconApprovalRequestMailable;
use App\Models\SubconCuttingReport;
use App\Models\SubconFabricReconciliation;
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

        // Linked fabrics (from VSM fabric POs) + any saved per-fabric reconciliation.
        // Both are best-effort: a VSM/DB hiccup must not take down the order page.
        $fabricLines = $production->fabricLinesForPo($order->order_number);
        try {
            $fabricRecon = \App\Models\SubconFabricReconciliation::where('order_id', $order->id)
                ->get()
                ->keyBy('label');
        } catch (\Throwable $e) {
            report($e);
            $fabricRecon = collect();
        }

        return view('subcon.vendor.orders.show', compact('order', 'productionGroups', 'summary', 'cuttingReports', 'fabricLines', 'fabricRecon'));
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

        if ($order->workflow_stage !== SubconOrder::STAGE_CUTTING && $order->workflow_stage !== SubconOrder::STAGE_CUTTING_REVIEW) {
            return back()->with('error', 'Cutting report can no longer be edited at this stage ('.$order->stageLabel().').');
        }

        $data = $request->validate([
            'blister_capacity' => 'nullable|integer|min:1',
            'sack_capacity' => 'nullable|integer|min:1',
            'fabrics_recon' => 'nullable|array',
            'fabrics_recon.*.label' => 'required_with:fabrics_recon|string|max:500',
            'fabrics_recon.*.short_roll' => 'nullable|numeric|min:0',
            'fabrics_recon.*.sisa_kain' => 'nullable|numeric|min:0',
            'fabrics_recon.*.kepala_kain' => 'nullable|numeric|min:0',
            'fabrics_recon.*.retur_kain' => 'nullable|numeric|min:0',
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

        \Illuminate\Support\Facades\DB::transaction(function () use ($order, $data) {
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

            // Per-fabric reconciliation (leftover fabric measured per fabric type).
            // Upsert by label — updating ONLY the reconciliation fields — so the
            // admin's consumption figures (fabric_sent/consumption_plan/…) on the
            // same fabric row are never wiped by a vendor re-submit. Rows for
            // fabrics the vendor no longer lists are removed.
            $keptLabels = [];
            foreach (($data['fabrics_recon'] ?? []) as $rec) {
                $label = trim((string) ($rec['label'] ?? ''));
                if ($label === '') {
                    continue;
                }
                $keptLabels[] = $label;
                $short = round((float) ($rec['short_roll'] ?? 0), 2);
                $sisa = round((float) ($rec['sisa_kain'] ?? 0), 2);
                $kepala = round((float) ($rec['kepala_kain'] ?? 0), 2);
                SubconFabricReconciliation::updateOrCreate(
                    ['order_id' => $order->id, 'label' => $label],
                    [
                        'short_roll' => $short,
                        'sisa_kain' => $sisa,
                        'kepala_kain' => $kepala,
                        // Retur Kain is LOCKED for the vendor = sum of the other three.
                        // The form field is readonly, but it could be tampered client-side,
                        // so enforce the rule here regardless of the posted value. The
                        // approver can still override retur_kain on the approval form.
                        'retur_kain' => round($short + $sisa + $kepala, 2),
                    ]
                );
            }
            $stale = SubconFabricReconciliation::where('order_id', $order->id);
            if (! empty($keptLabels)) {
                $stale->whereNotIn('label', $keptLabels);
            }
            $stale->delete();

            // Update job_trans_status sequence
            if (empty($order->job_trans_status) || $order->job_trans_status === 'not_saved') {
                $order->job_trans_status = 'first_saved';
            } elseif ($order->job_trans_status === 'first_saved') {
                $order->job_trans_status = 'qty_cutting_saved';
            }

            $order->workflow_stage = SubconOrder::STAGE_CUTTING_REVIEW;
            // status is derived from workflow_stage in the model's saving hook.
            $order->save();
        });

        $this->emailApprover($order, 'cutting');
        $this->notifyAdmins(new SubconApprovalRequested($order, 'cutting'));

        return back()->with('success', 'Cutting report submitted for approval.');
    }

    /**
     * Step 6 → 7: vendor enters gramasi per size + the single blister capacity
     * and submits for approval. Gramasi is synced to D365 on save.
     */
    public function submitGramasi(\Illuminate\Http\Request $request, string $id)
    {
        $vendorId = Auth::user()->vendor_id;
        $order = SubconOrder::with('vendor')->where('vendor_id', $vendorId)->findOrFail($id);

        if ($order->workflow_stage !== SubconOrder::STAGE_GRAMASI && $order->workflow_stage !== SubconOrder::STAGE_GRAMASI_REVIEW) {
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

        \Illuminate\Support\Facades\DB::transaction(function () use ($order, $data) {
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
            $order->save();
        });

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
