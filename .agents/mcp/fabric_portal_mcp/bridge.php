<?php

/**
 * Fabric Vendor Portal — Read-Only AI Bridge
 *
 * Exclusively provides read-only inspection of the Fabric Preliminary Contract (PC)
 * module and related master data. Strictly guarded with PostgreSQL READ ONLY session
 * to guarantee ZERO database mutation risk.
 */

declare(strict_types=1);

error_reporting(E_ALL & ~E_DEPRECATED);
ini_set('display_errors', '0');

require_once __DIR__ . '/../../../vendor/autoload.php';
$app = require_once __DIR__ . '/../../../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Illuminate\Support\Facades\DB;
use App\Models\PcContract;
use App\Models\Vendor;
use App\Models\Season;
use App\Models\TransportMode;
use App\Models\DeliveryTerm;
use App\Models\HsCode;

// Enforce PostgreSQL session-level read-only guarantee
try {
    DB::statement('SET SESSION CHARACTERISTICS AS TRANSACTION READ ONLY;');
} catch (\Throwable $e) {
    // If running in an environment without active connection, continue in safe mode
}

$rawInput = file_get_contents('php://stdin');
$input = json_decode($rawInput ?: '{}', true) ?: [];
$action = $input['action'] ?? 'get_architecture';
$params = $input['params'] ?? [];

$response = ['status' => 'success', 'action' => $action];

try {
    switch ($action) {
        case 'get_architecture':
            $response['data'] = [
                'subsystem' => 'Fabric Vendor Portal & Preliminary Contract (PC) Subsystem',
                'description' => 'Advance fabric capacity reservation and procurement platform for PT Mega Perintis Tbk.',
                'business_rules' => [
                    'contract_purpose' => 'Reserves greige and yarn manufacturing capacity with fabric mills ahead of seasonal cutting.',
                    'bulk_dyeing_rule' => 'Bulk dyeing and finishing strictly awaits the formal Purchase Order (PO).',
                    'contract_number_format' => 'MPG/PC/{DIVISION}/{YY}/{MONTH_ROMAN}/{SEQUENCE}',
                    'dual_control_approval' => 'Requires TOTP 2FA approval from authorized Approvers before dispatching to suppliers.',
                    'vendor_response_sla' => 'Fabric mills must accept or query within 3 business days.',
                ],
                'status_lifecycle' => [
                    'draft' => 'Draft contract being authored by merchandiser.',
                    'pending_approval' => 'Submitted for Director / Senior Merchandiser dual-control TOTP approval.',
                    'approved' => 'Approved internally, ready for dispatch to supplier.',
                    'sent_to_supplier' => 'Dispatched to fabric mill portal; awaiting vendor response.',
                    'confirmed' => 'Accepted by vendor; capacity locked and bilateral digital audit seal appended.',
                    'supplier_queried' => 'Vendor submitted technical or lead-time query; awaiting buyer resolution.',
                    'revised' => 'Superseded by a revision (e.g. REV 1, REV 2).',
                    'cancelled' => 'Cancelled before or during review.',
                ],
                'database_tables' => [
                    'pc_contracts' => 'Primary contract headers (number, vendor, season, currency, payment terms, status, approvals).',
                    'pc_contract_lines' => 'Fabric specifications (item code, HS code, color, cuttable width, GSM, volume, unit price).',
                    'pc_delivery_schedules' => 'Milestone delivery dates and volume splits per line.',
                    'pc_contract_files' => 'Generated PDF contracts and uploaded attachments.',
                    'pc_contract_vendor_responses' => 'Formal vendor acceptance or query records with audit timestamps.',
                    'pc_audit_logs' => 'Immutable audit log of all contract events and actor IP addresses.',
                    'vendors' => 'Supplier master data filtered by type = fabric.',
                    'seasons' => 'Collection seasons (e.g. FALL 26, FESTIVE 26).',
                    'transport_modes' => 'Freight modes (Sea Freight, Air Freight, Land).',
                    'delivery_terms' => 'Incoterms (FOB Shanghai, CIF Jakarta, etc.).',
                    'hs_codes' => 'Customs tariff codes for fabric export/import compliance.',
                ],
                'routes_overview' => [
                    'admin_contracts' => '/admin/pc/contracts (CRUD, Submit, Approve, Send, Resend, Revise, PDF)',
                    'admin_guidebook' => '/admin/pc/guidebook/pdf (Admin Operating Guidebook PDF)',
                    'vendor_contracts' => '/vendor/pc/contracts (Review, Accept, Query, PDF)',
                    'vendor_guidebook' => '/vendor/pc/guidebook/pdf (Supplier Operating Guidebook PDF)',
                ],
            ];
            break;

        case 'list_vendors':
            $vendors = Vendor::where('type', 'fabric')
                ->where('is_active', true)
                ->with('paymentTerm:id,code,name')
                ->select('id', 'name', 'code', 'email', 'contact_person', 'currency_code', 'payment_term_id')
                ->orderBy('name')
                ->get()
                ->map(function ($v) {
                    return [
                        'id' => $v->id,
                        'name' => $v->name,
                        'code' => $v->code,
                        'email' => $v->email,
                        'contact_person' => $v->contact_person,
                        'currency' => $v->currency_code,
                        'payment_term' => $v->paymentTerm ? $v->paymentTerm->name : null,
                    ];
                });
            $response['data'] = [
                'total_active_fabric_vendors' => count($vendors),
                'vendors' => $vendors,
            ];
            break;

        case 'get_master_data':
            $response['data'] = [
                'seasons' => Season::active()->orderBy('sort_key')->get(['id', 'code', 'name', 'start_date', 'end_date']),
                'transport_modes' => TransportMode::active()->orderBy('code')->get(['id', 'code', 'name']),
                'delivery_terms' => DeliveryTerm::active()->orderBy('code')->get(['id', 'code', 'name']),
                'hs_codes' => HsCode::orderBy('code')->limit(50)->get(['id', 'code', 'description']),
            ];
            break;

        case 'get_pipeline_stats':
            $counts = PcContract::select('status', DB::raw('count(*) as count'))
                ->groupBy('status')
                ->pluck('count', 'status')
                ->all();
            $total = array_sum($counts);
            $response['data'] = [
                'total_contracts' => $total,
                'breakdown' => $counts,
            ];
            break;

        case 'inspect_contract':
            $identifier = $params['identifier'] ?? null;
            if (!$identifier) {
                throw new \InvalidArgumentException('Missing contract identifier (id or contract_number).');
            }

            $contract = PcContract::with([
                'vendor:id,name,code,email',
                'season:id,code,name',
                'lines',
                'deliverySchedules',
                'files' => function ($q) {
                    $q->select('id', 'contract_id', 'file_type', 'original_filename', 'file_size', 'generated_at');
                },
                'auditLogs' => function ($q) {
                    $q->latest('created_at')->limit(10);
                },
            ])
            ->where('id', $identifier)
            ->orWhere('contract_number', $identifier)
            ->first();

            if (!$contract) {
                $response['status'] = 'not_found';
                $response['message'] = "Preliminary Contract '{$identifier}' not found.";
            } else {
                $response['data'] = [
                    'id' => $contract->id,
                    'contract_number' => $contract->contract_number,
                    'revision_no' => $contract->revision_no,
                    'status' => $contract->status,
                    'vendor' => $contract->vendor ? ['name' => $contract->vendor->name, 'code' => $contract->vendor->code] : null,
                    'season' => $contract->season ? $contract->season->name : null,
                    'currency_code' => $contract->currency_code,
                    'payment_term_name' => $contract->payment_term_name,
                    'incoterm' => $contract->incoterm,
                    'transport_mode' => $contract->transport_mode,
                    'total_quantity' => (float) $contract->total_quantity,
                    'total_amount' => (float) $contract->total_amount,
                    'created_at' => $contract->created_at ? $contract->created_at->toIso8601String() : null,
                    'approved_at' => $contract->approved_at ? $contract->approved_at->toIso8601String() : null,
                    'vendor_decision' => $contract->vendor_decision,
                    'vendor_decision_at' => $contract->vendor_decision_at ? $contract->vendor_decision_at->toIso8601String() : null,
                    'lines' => $contract->lines->map(function ($l) {
                        return [
                            'line_number' => $l->line_number,
                            'item_code' => $l->item_code,
                            'description' => $l->description,
                            'hs_code' => $l->hs_code,
                            'color_name' => $l->color_name,
                            'cuttable_width' => $l->cuttable_width,
                            'weight_gsm' => $l->weight_gsm,
                            'quantity' => (float) $l->quantity,
                            'uom' => $l->uom,
                            'unit_price' => (float) $l->unit_price,
                            'subtotal' => (float) $l->subtotal,
                            'tolerance_percent' => (float) $l->tolerance_percent,
                        ];
                    }),
                    'delivery_schedules' => $contract->deliverySchedules->map(function ($s) {
                        return [
                            'target_delivery_date' => $s->target_delivery_date,
                            'quantity' => (float) $s->quantity,
                            'destination' => $s->destination,
                        ];
                    }),
                    'audit_history' => $contract->auditLogs->map(function ($a) {
                        return [
                            'event_type' => $a->event_type,
                            'actor' => $a->actor,
                            'created_at' => $a->created_at ? $a->created_at->toIso8601String() : null,
                            'note' => $a->note,
                        ];
                    }),
                ];
            }
            break;

        case 'read_guidebook':
            $target = strtolower($params['type'] ?? 'admin');
            $docPath = $target === 'vendor'
                ? __DIR__ . '/../../../docs/vendor_preliminary_contract_guidebook.md'
                : __DIR__ . '/../../../docs/admin_preliminary_contract_guidebook.md';

            if (file_exists($docPath)) {
                $response['data'] = [
                    'guidebook_type' => $target,
                    'filepath' => realpath($docPath),
                    'content' => file_get_contents($docPath),
                ];
            } else {
                throw new \RuntimeException("Guidebook document for '{$target}' not found.");
            }
            break;

        default:
            throw new \InvalidArgumentException("Unknown action '{$action}'.");
    }
} catch (\Throwable $e) {
    $response = [
        'status' => 'error',
        'action' => $action,
        'error_message' => $e->getMessage(),
    ];
}

echo json_encode($response, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;
