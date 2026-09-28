@extends('layouts.app')

@section('title', __('Admin Guidebook'))

@section('content')
<style>
    .admin-guide-hero {
        background: linear-gradient(135deg, #1e3a8a 0%, #1e40af 50%, #2563eb 100%);
        border-radius: 18px;
        color: #ffffff;
        box-shadow: 0 10px 25px rgba(30, 58, 138, 0.15);
    }
    .guide-card {
        border-radius: 14px;
        border: 1px solid rgba(0, 0, 0, 0.08);
        box-shadow: 0 4px 20px rgba(0, 0, 0, 0.03);
        background: #ffffff;
        transition: transform 0.2s ease, box-shadow 0.2s ease;
        overflow: hidden;
    }
    .guide-card:hover {
        transform: translateY(-2px);
        box-shadow: 0 8px 25px rgba(0, 0, 0, 0.06);
    }
    .guide-step-badge {
        width: 38px;
        height: 38px;
        border-radius: 50%;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        font-weight: 700;
        font-size: 1.05rem;
    }
    .formula-box {
        background: #f8fafc;
        border-left: 4px solid #2563eb;
        border-radius: 8px;
        padding: 14px 18px;
        font-family: var(--bs-font-monospace);
        font-size: 0.9rem;
    }
    .system-node-card {
        border: 1px solid #e2e8f0;
        border-radius: 12px;
        padding: 18px;
        background: #ffffff;
        height: 100%;
        box-shadow: 0 2px 8px rgba(0,0,0,0.02);
        position: relative;
    }
    .system-node-card.primary { border-top: 4px solid #2563eb; }
    .system-node-card.success { border-top: 4px solid #10b981; }
    .system-node-card.warning { border-top: 4px solid #f59e0b; }
    .system-node-card.info    { border-top: 4px solid #06b6d4; }
    .system-node-card.danger  { border-top: 4px solid #ef4444; }

    .trouble-card {
        border-left: 5px solid #ef4444;
        border-radius: 12px;
        background: #ffffff;
        border-top: 1px solid #f1f5f9;
        border-right: 1px solid #f1f5f9;
        border-bottom: 1px solid #f1f5f9;
        transition: all 0.2s ease;
    }
    .trouble-card:hover {
        box-shadow: 0 6px 20px rgba(0,0,0,0.06);
    }
    .trouble-card.warning { border-left-color: #f59e0b; }
    .trouble-card.info { border-left-color: #3b82f6; }
    .trouble-card.success { border-left-color: #10b981; }

    .trouble-badge {
        font-size: 0.75rem;
        font-weight: 700;
        text-transform: uppercase;
        letter-spacing: 0.04em;
    }
    .action-step-item {
        position: relative;
        padding-left: 32px;
        margin-bottom: 14px;
    }
    .action-step-num {
        position: absolute;
        left: 0;
        top: 2px;
        width: 22px;
        height: 22px;
        border-radius: 50%;
        background: #2563eb;
        color: #ffffff;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        font-size: 0.75rem;
        font-weight: 700;
    }
    .screen-tag {
        font-family: var(--bs-font-monospace);
        background: #f1f5f9;
        color: #334155;
        padding: 2px 7px;
        border-radius: 4px;
        font-size: 0.8rem;
        border: 1px solid #e2e8f0;
        font-weight: 600;
    }
    .db-badge {
        font-family: var(--bs-font-monospace);
        font-size: 0.75rem;
        background: #e0f2fe;
        color: #0369a1;
        padding: 2px 6px;
        border-radius: 4px;
        border: 1px solid #bae6fd;
    }
</style>

<div class="container-fluid py-2">
    <!-- Hero Banner -->
    <div class="admin-guide-hero p-4 p-md-5 mb-4 position-relative overflow-hidden">
        <div class="row align-items-center">
            <div class="col-lg-8">
                <span class="badge rounded-pill px-3 py-1.5 fw-semibold mb-3" style="background: rgba(255, 255, 255, 0.18); color: #ffffff; border: 1px solid rgba(255, 255, 255, 0.25);">
                    <i class="fas fa-shield-halved me-1"></i> {{ __('Subcon Admin') }} &middot; {{ __('Operations, System Architecture & Troubleshooting Guide') }}
                </span>
                <h1 class="display-6 fw-bold mb-2">
                    {{ app()->getLocale() === 'id' ? 'Buku Panduan Kontrol & Troubleshooting Admin Subkontraktor' : 'Subcontractor Admin Operations & Troubleshooting Manual' }}
                </h1>
                <p class="lead mb-3 text-white-50 fs-6">
                    {{ app()->getLocale() === 'id' 
                        ? 'Panduan onboarding komprehensif bagi Admin: arsitektur integrasi lintas sistem (QC Console, VSM Material Flow, D365 ERP, RPA), kontrol verifikasi bertahap (Cutting, Gramasi, Label, Report Validation, Official Report), serta runbook diagnosa solusi cepat untuk masalah operasional harian.' 
                        : 'Comprehensive onboarding manual for Admins: cross-system architecture (QC Console, VSM Material Flow, D365 ERP, RPA), step-by-step gate controls (Cutting, Gramasi, Labels, Report Validation, Official Report), and rapid diagnostic runbooks for daily production issues.' }}
                </p>
                <div class="d-flex flex-wrap gap-2">
                    <a href="#section-architecture" class="btn btn-light fw-semibold text-primary px-3 shadow-sm">
                        <i class="fas fa-network-wired me-1"></i> {{ __('System Architecture') }}
                    </a>
                    <a href="#section-report-validation" class="btn btn-info fw-semibold text-white px-3 shadow-sm">
                        <i class="fas fa-stamp me-1"></i> {{ __('Report Validation & Gates') }}
                    </a>
                    <a href="#section-troubleshooting" class="btn btn-warning fw-semibold text-dark px-3 shadow-sm">
                        <i class="fas fa-wrench me-1"></i> {{ __('Troubleshooting Runbook') }}
                    </a>
                    <a href="{{ route('subcon.admin.user-guide') }}" target="_blank" class="btn btn-outline-light fw-semibold px-3">
                        <i class="fas fa-file-pdf me-1"></i> {{ __('Download PDF Guide') }}
                    </a>
                </div>
            </div>
            <div class="col-lg-4 text-center text-lg-end mt-4 mt-lg-0 d-none d-lg-block">
                <div class="d-inline-block p-4 rounded-4" style="background: rgba(255, 255, 255, 0.08); backdrop-filter: blur(10px); border: 1px solid rgba(255, 255, 255, 0.15);">
                    <i class="fas fa-server fa-4x text-white-50 mb-2"></i>
                    <div class="text-white fw-bold">{{ __('Enterprise Ecosystem') }}</div>
                    <div class="text-white-50 small">QC Console &middot; VSM &middot; D365 &middot; RPA</div>
                    <div class="badge bg-success rounded-pill mt-2"><i class="fas fa-circle-check me-1"></i> Systems Integrated</div>
                </div>
            </div>
        </div>
    </div>

    <!-- Quick Jump Navigation -->
    <div class="d-flex flex-wrap gap-2 mb-4">
        <a href="#section-architecture" class="btn btn-sm btn-outline-primary rounded-pill"><i class="fas fa-sitemap me-1"></i> Part I: {{ __('Ecosystem Architecture') }}</a>
        <a href="#control-sync" class="btn btn-sm btn-outline-primary rounded-pill"><i class="fas fa-rotate me-1"></i> 1. {{ __('PO Sync') }}</a>
        <a href="#control-cutting" class="btn btn-sm btn-outline-primary rounded-pill"><i class="fas fa-scissors me-1"></i> 2. {{ __('Cutting & Math') }}</a>
        <a href="#control-gramasi" class="btn btn-sm btn-outline-primary rounded-pill"><i class="fas fa-weight-scale me-1"></i> 3. {{ __('Gramasi') }}</a>
        <a href="#control-labels" class="btn btn-sm btn-outline-primary rounded-pill"><i class="fas fa-print me-1"></i> 4. {{ __('D365 Labels') }}</a>
        <a href="#section-report-validation" class="btn btn-sm btn-outline-primary rounded-pill"><i class="fas fa-clipboard-check me-1"></i> 5. {{ __('Report Validation & Material Flow') }}</a>
        <a href="#control-addprocess" class="btn btn-sm btn-outline-primary rounded-pill"><i class="fas fa-industry me-1"></i> 6. {{ __('Add Process & 70% Penalty') }}</a>
        <a href="#control-berita-acara" class="btn btn-sm btn-outline-primary rounded-pill"><i class="fas fa-file-contract me-1"></i> 7. {{ __('Official Report') }}</a>
        <a href="#section-troubleshooting" class="btn btn-sm btn-danger text-white rounded-pill"><i class="fas fa-triangle-exclamation me-1"></i> Part III: {{ __('Troubleshooting Runbook') }}</a>
        <a href="#section-glossary" class="btn btn-sm btn-outline-dark rounded-pill"><i class="fas fa-book me-1"></i> Part IV: {{ __('Glossary & "i" Index') }}</a>
    </div>

    <!-- ============================================================= -->
    <!-- PART I: SYSTEM ARCHITECTURE & CROSS-SYSTEM INTEGRATIONS       -->
    <!-- ============================================================= -->
    <div class="d-flex align-items-center gap-2 mb-3" id="section-architecture">
        <span class="badge bg-primary text-white rounded-pill px-3 py-1 fw-bold">PART I</span>
        <h4 class="fw-bold mb-0 text-dark">{{ __('Cross-System Architecture & Data Bridges') }}</h4>
    </div>

    <div class="guide-card mb-5">
        <div class="card-header bg-white py-3 border-bottom">
            <h5 class="mb-0 fw-bold"><i class="fas fa-diagram-project text-primary me-2"></i>{{ __('How Vendor Portal Integrates with External Systems') }}</h5>
            <small class="text-muted">{{ __('Understanding where approvals originate, where returns are checked, and how D365 RPA synchronizes') }}</small>
        </div>
        <div class="card-body p-4">
            <p class="text-muted small mb-4">
                {{ app()->getLocale() === 'id'
                    ? 'Portal Vendor Subkon tidak berdiri sendiri. Sistem ini terhubung langsung dengan 3 sistem eksternal utama melalui database bersama (shared PostgreSQL) dan engine RPA untuk memastikan verifikasi kualitas, audit material, dan pembukuan finansial berjalan tanpa manipulasi data.'
                    : 'The Subcon Vendor Portal operates within an enterprise web of 4 interconnected systems via shared PostgreSQL databases and RPA workers, ensuring end-to-end quality inspection, physical material return reconciliation, and automated ERP debiting.' }}
            </p>

            <div class="row g-4 mb-4">
                <!-- System 1: Vendor Portal -->
                <div class="col-md-6 col-lg-3">
                    <div class="system-node-card primary">
                        <div class="d-flex align-items-center justify-content-between mb-2">
                            <span class="badge bg-primary-subtle text-primary fw-bold">Core Portal</span>
                            <span class="db-badge">MySQL Primary</span>
                        </div>
                        <h6 class="fw-bold text-dark mb-1">Vendor Portal</h6>
                        <div class="small text-muted mb-2"><code>vendor-portal.megaperintis.co.id</code></div>
                        <p class="small text-muted mb-0">
                            <strong>{{ __('Role') }}:</strong> {{ __('The web host and provider. Vendors enter cutting reports, gramasi, and material returns. Admin supervises gates and approves workflows.') }}
                        </p>
                    </div>
                </div>

                <!-- System 2: QC Console -->
                <div class="col-md-6 col-lg-3">
                    <div class="system-node-card info">
                        <div class="d-flex align-items-center justify-content-between mb-2">
                            <span class="badge bg-info-subtle text-info fw-bold">Quality Control</span>
                            <span class="db-badge">Postgres: qms</span>
                        </div>
                        <h6 class="fw-bold text-dark mb-1">QC Console</h6>
                        <div class="small text-muted mb-2">Tauri / Windows App</div>
                        <p class="small text-muted mb-0">
                            <strong>{{ __('Role') }}:</strong> {{ __('Factory inspectors inspect finished garments on-site. Creates packaging sessions, pulls fabric lines by production_group, and generates the Stage-1 digital approval token.') }}
                        </p>
                    </div>
                </div>

                <!-- System 3: VSM Material Flow -->
                <div class="col-md-6 col-lg-3">
                    <div class="system-node-card warning">
                        <div class="d-flex align-items-center justify-content-between mb-2">
                            <span class="badge bg-warning-subtle text-warning fw-bold">Warehouse Flow</span>
                            <span class="db-badge">Postgres: wms</span>
                        </div>
                        <h6 class="fw-bold text-dark mb-1">VSM Material Flow</h6>
                        <div class="small text-muted mb-2"><code>value_stream_ops</code> App</div>
                        <p class="small text-muted mb-0">
                            <strong>{{ __('Role') }}:</strong> {{ __('Warehouse staff physically counts returned fabric and accessories (Surat Jalan Retur). Enters actual received quantities (qty_actual) and marks tasks "Checked".') }}
                        </p>
                    </div>
                </div>

                <!-- System 4: D365 & RPA -->
                <div class="col-md-6 col-lg-3">
                    <div class="system-node-card success">
                        <div class="d-flex align-items-center justify-content-between mb-2">
                            <span class="badge bg-success-subtle text-success fw-bold">ERP & Automation</span>
                            <span class="db-badge">D365 / S3 rpa-lake</span>
                        </div>
                        <h6 class="fw-bold text-dark mb-1">Dynamics 365 & RPA</h6>
                        <div class="small text-muted mb-2">Microsoft D365 &middot; RPA Engine</div>
                        <p class="small text-muted mb-0">
                            <strong>{{ __('Role') }}:</strong> {{ __('Master ERP issuing CMT and AddPro POs. RPA bots poll approved milestones to generate carton Packing Instructions and post Debit Notes / Invoices.') }}
                        </p>
                    </div>
                </div>
            </div>

            <!-- Inter-System Communication Table -->
            <div class="table-responsive border rounded-3">
                <table class="table table-hover align-middle mb-0 small">
                    <thead class="table-light">
                        <tr>
                            <th style="width: 22%;">{{ __('Interaction / Bridge') }}</th>
                            <th style="width: 18%;">{{ __('Source -> Destination') }}</th>
                            <th style="width: 22%;">{{ __('Data Channel & Tables') }}</th>
                            <th>{{ __('Operational Purpose & Admin Impact') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td class="fw-bold text-primary">
                                <i class="fas fa-file-contract me-1"></i> Fabric Consumption Publish
                            </td>
                            <td>Vendor Portal &rarr; QC Console</td>
                            <td><span class="db-badge">qms</span> <code>packaging_project_fabric_lines</code></td>
                            <td>
                                {{ app()->getLocale() === 'id'
                                    ? 'Saat admin menyetujui cutting, sistem menerbitkan konsumsi kain dengan kunci production_group. Saat inspektur QC memulai inspeksi di QC Console, data kain ditarik otomatis (PULL).'
                                    : 'When admin approves cutting, fabric consumption is published keyed by production_group. When QC inspector opens project on QC Console, fabric figures are automatically pulled.' }}
                            </td>
                        </tr>
                        <tr>
                            <td class="fw-bold text-info">
                                <i class="fas fa-certificate me-1"></i> Final Approval Origin
                            </td>
                            <td>QC Console &rarr; Vendor Portal</td>
                            <td><span class="db-badge">qms</span> <code>packaging_project_sessions</code></td>
                            <td>
                                {{ app()->getLocale() === 'id'
                                    ? 'Inspektur QC menyelesaikan inspeksi fisik di QC Console -> membuat approval_token (UUID) -> mengirim email/link verifikasi Stage-1 ke Vendor/Pabrik.'
                                    : 'QC inspector finishes physical inspection on QC Console -> generates random-UUID approval_token -> triggers Stage-1 approval email to factory representative.' }}
                            </td>
                        </tr>
                        <tr>
                            <td class="fw-bold text-warning">
                                <i class="fas fa-truck-ramp-box me-1"></i> Material Return Audit (VSM)
                            </td>
                            <td>Vendor Portal &harr; VSM Material Flow</td>
                            <td><span class="db-badge">wms</span> <code>material_return_tasks</code>, <code>material_return_lines</code></td>
                            <td>
                                {{ app()->getLocale() === 'id'
                                    ? 'Admin mengirim tugas retur ke VSM ("Send to Material Flow"). Tim gudang di VSM wajib memvalidasi fisik (qty_actual) dan menandai "Checked" sebelum laporan bisa dikirim ke Direktur!'
                                    : 'Admin dispatches return task to VSM ("Send to Material Flow"). Warehouse staff in VSM must verify physical counts (qty_actual) and mark "Checked" before Director send is unlocked!' }}
                            </td>
                        </tr>
                        <tr>
                            <td class="fw-bold text-success">
                                <i class="fas fa-barcode me-1"></i> Packing Instruction & Labels
                            </td>
                            <td>Vendor Portal &harr; D365 RPA</td>
                            <td><span class="db-badge">primary</span> <code>rpa_queues</code> &rarr; S3 <code>rpa-lake</code></td>
                            <td>
                                {{ app()->getLocale() === 'id'
                                    ? 'Admin klik "Generate Labels" -> antrean RPA memanggil D365 untuk membuat instruksi packing (PI) -> menghasilkan PDF label toko (Store) dan karung gudang (WH).'
                                    : 'Admin clicks "Generate Labels" -> RPA calls D365 to generate Packing Instruction (PI) -> builds Store carton labels and WH replenish bulk sack barcodes.' }}
                            </td>
                        </tr>
                        <tr>
                            <td class="fw-bold text-danger">
                                <i class="fas fa-money-bill-transfer me-1"></i> Debit Note / Deduction Post
                            </td>
                            <td>Vendor Portal &rarr; D365 Finance</td>
                            <td><span class="db-badge">primary</span> <code>rpa_queues</code> &rarr; D365 AP</td>
                            <td>
                                {{ app()->getLocale() === 'id'
                                    ? 'Setelah Direktur menyetujui Report Validation atau Berita Acara, RPA otomatis memotong tagihan vendor via Debit Note di D365 atas kelebihan pemakaian kain / reject.'
                                    : 'Once Director signs Report Validation or Official Report, RPA automatically posts Debit Notes to D365 Accounts Payable to deduct vendor invoices.' }}
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- ============================================================= -->
    <!-- PART II: HOW TO OPERATE & CONTROL AS ADMIN (SOP)              -->
    <!-- ============================================================= -->
    <div class="d-flex align-items-center gap-2 mb-3">
        <span class="badge bg-primary text-white rounded-pill px-3 py-1 fw-bold">PART II</span>
        <h4 class="fw-bold mb-0 text-dark">{{ __('Admin Operational Control Manual (SOP)') }}</h4>
    </div>

    <!-- 1. PO Sync & Gate Lifecycle -->
    <div class="guide-card mb-4" id="control-sync">
        <div class="card-header bg-white py-3 border-bottom d-flex align-items-center gap-2">
            <span class="guide-step-badge bg-primary text-white">1</span>
            <div>
                <h5 class="mb-0 fw-bold">{{ __('PO Synchronization & Gate Lifecycle Architecture') }}</h5>
                <small class="text-muted">{{ __('How work orders enter the portal and advance through gates') }}</small>
            </div>
        </div>
        <div class="card-body p-4">
            <div class="row g-4 mb-4">
                <div class="col-lg-6">
                    <h6 class="fw-bold text-dark"><i class="fas fa-diagram-project text-primary me-1"></i> {{ __('How Work Orders Arrive in the Portal') }}</h6>
                    <p class="text-muted small">
                        {{ app()->getLocale() === 'id'
                            ? 'Portal Subkon tersinkronisasi langsung dengan Microsoft Dynamics 365 (D365). Sistem mengambil seluruh Purchase Order yang memiliki PurchPoolId = "CMT" dan status Confirmed.'
                            : 'The Subcon Portal synchronizes directly with Microsoft Dynamics 365 (D365). The system imports all Purchase Orders with PurchPoolId = "CMT" and Confirmed status.' }}
                    </p>
                    <div class="list-group list-group-flush border rounded-3 small">
                        <div class="list-group-item d-flex justify-content-between align-items-center">
                            <span><strong>{{ __('Automatic Sync') }}:</strong> Background cron job polls D365 hourly.</span>
                            <span class="badge bg-success-subtle text-success">Automated</span>
                        </div>
                        <div class="list-group-item d-flex justify-content-between align-items-center">
                            <span><strong>{{ __('Manual Sync') }}:</strong> Click <span class="screen-tag">Sync POs</span> on Dashboard anytime.</span>
                            <span class="badge bg-primary-subtle text-primary">On Demand</span>
                        </div>
                    </div>
                </div>
                <div class="col-lg-6">
                    <h6 class="fw-bold text-dark"><i class="fas fa-traffic-light text-warning me-1"></i> {{ __('The 4 Mandatory Governance Gates') }}</h6>
                    <div class="small">
                        <div class="p-2 border rounded-2 mb-2 bg-light">
                            <strong class="text-warning">Gate 1: Cutting Review</strong> &mdash; Vendor submits cut quantities by size. Admin reviews consumption vs Cutt Plan.
                        </div>
                        <div class="p-2 border rounded-2 mb-2 bg-light">
                            <strong class="text-info">Gate 2: Gramasi Review</strong> &mdash; Vendor submits garment piece weights (grams) and packaging capacity (blister & sack).
                        </div>
                        <div class="p-2 border rounded-2 mb-2 bg-light">
                            <strong class="text-primary">Gate 3: Waiting Distribution & Labels</strong> &mdash; RPA triggers Packing Instruction (PI) in D365 to allocate cartons/boxes.
                        </div>
                        <div class="p-2 border rounded-2 bg-light">
                            <strong class="text-success">Gate 4: Completed</strong> &mdash; Vendor prints box labels, ships goods to warehouse, and marks the PO finished.
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- 2. Cutting Approval & Consumption Math -->
    <div class="guide-card mb-4" id="control-cutting">
        <div class="card-header bg-white py-3 border-bottom d-flex align-items-center gap-2">
            <span class="guide-step-badge bg-warning text-dark">2</span>
            <div>
                <h5 class="mb-0 fw-bold">{{ __('Controlling Gate 1: Cutting Approval & Consumption Math') }}</h5>
                <small class="text-muted">{{ __('Screen: Approvals -> Order View (Material Reconciliation & Consumption)') }}</small>
            </div>
        </div>
        <div class="card-body p-4">
            <div class="row g-4 mb-4">
                <div class="col-lg-6">
                    <h6 class="fw-bold text-primary mb-2"><i class="fas fa-calculator me-1"></i> {{ __('The Core Mathematical Equations') }}</h6>
                    <div class="formula-box mb-3">
                        <div class="text-muted small mb-1">// 1. Net Fabric Consumed (Meters / Yards)</div>
                        <strong>Net Fabric</strong> = Fabric Sent - Retur Kain
                    </div>
                    <div class="formula-box mb-3">
                        <div class="text-muted small mb-1">// 2. Computed Cut Plan (Expected Garment Yield)</div>
                        <strong>Cutt Plan</strong> = Net Fabric / Cons. Plan
                    </div>
                    <div class="formula-box">
                        <div class="text-muted small mb-1">// 3. Measured Actual Consumption</div>
                        <strong>Actual Cons.</strong> = Net Fabric / Total Qty Cut
                    </div>
                </div>
                <div class="col-lg-6">
                    <h6 class="fw-bold text-dark mb-2"><i class="fas fa-user-check text-success me-1"></i> {{ __('Admin Action Checklist at Cutting Gate') }}</h6>
                    <div class="action-step-item">
                        <span class="action-step-num">1</span>
                        <strong>{{ __('Inspect Cut Qty vs Cut Plan') }}:</strong> Compare the vendor's total cut against Cutt Plan. If variance exceeds allowed tolerance (±2% to 5%), review material return.
                    </div>
                    <div class="action-step-item">
                        <span class="action-step-num">2</span>
                        <strong>{{ __('Verify Retur Kain & Sisa Kain') }}:</strong> Verify that leftover fabric returned to warehouse is correctly entered in <span class="screen-tag">Retur Kain</span>. This directly reduces net fabric and protects vendor from unjustified penalties.
                    </div>
                    <div class="action-step-item">
                        <span class="action-step-num">3</span>
                        <strong>{{ __('Check for Partial Flag') }}:</strong> If vendor checked <span class="badge text-bg-danger">Partial</span>, approving allows them to continue cutting subsequent batches without locking the stage.
                    </div>
                    <div class="action-step-item">
                        <span class="action-step-num">4</span>
                        <strong>{{ __('Handling Cutting Revisions') }}:</strong> If vendor made an error and submitted a revision, review the diff and click <span class="screen-tag">{{ __('Approve') }}</span>.
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- 3. Gramasi & Packaging Capacity -->
    <div class="guide-card mb-4" id="control-gramasi">
        <div class="card-header bg-white py-3 border-bottom d-flex align-items-center gap-2">
            <span class="guide-step-badge bg-info text-white">3</span>
            <div>
                <h5 class="mb-0 fw-bold">{{ __('Controlling Gate 2: Gramasi Verification & Packaging Capacities') }}</h5>
                <small class="text-muted">{{ __('Screen: Approvals -> Order View (Order Info & Blister Input)') }}</small>
            </div>
        </div>
        <div class="card-body p-4">
            <div class="row g-4">
                <div class="col-lg-6">
                    <h6 class="fw-bold text-dark mb-2"><i class="fas fa-weight-hanging text-info me-1"></i> {{ __('What is Gramasi?') }}</h6>
                    <p class="text-muted small">
                        {{ app()->getLocale() === 'id'
                            ? 'Gramasi adalah berat timbangan baju per potong dalam satuan gram (g) untuk setiap ukuran. Nilai ini menjadi acuan Quality Control (QC) untuk memastikan densitas kain rajut / woven seragam dan tidak terjadi penyusutan di luar toleransi.'
                            : 'Gramasi is the physical weight per piece in grams (g) per size. QC utilizes this metric to verify knit/woven density uniformity and prevent unauthorized shrinkage.' }}
                    </p>
                    <div class="alert alert-light border small">
                        <strong>{{ __('Admin Rule') }}:</strong> Ensure gramasi values are positive and realistic (e.g. 150g - 250g for t-shirts; 350g - 600g for jackets). Gramasi cannot be zero or empty when generating labels in D365!
                    </div>
                </div>
                <div class="col-lg-6">
                    <h6 class="fw-bold text-dark mb-2"><i class="fas fa-box-open text-primary me-1"></i> {{ __('Blister Capacity vs Sack Capacity') }}</h6>
                    <div class="p-3 bg-light rounded-3 border small mb-3">
                        <div class="mb-2">
                            <strong class="text-primary">{{ __('Blister Capacity') }} (e.g. 20 pcs/blister):</strong>
                            {{ __('Quantity of garments packed into an inner polybag/blister pack. Applies uniformly across all sizes.') }}
                        </div>
                        <div>
                            <strong class="text-success">{{ __('Sack (Karung) Capacity') }} (e.g. 80 pcs/sack):</strong>
                            {{ __('Quantity of garments packed into an outer bulk sack for warehouse replenish shipments.') }}
                        </div>
                    </div>
                    <div class="text-muted small">
                        <i class="fas fa-lightbulb text-warning me-1"></i>
                        {{ __('Admin can adjust capacities anytime on Order View -> "Save capacity". Then use "Recalculate Labels" without re-triggering D365 RPA.') }}
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- 4. D365 Label Distribution & Printing -->
    <div class="guide-card mb-4" id="control-labels">
        <div class="card-header bg-white py-3 border-bottom d-flex align-items-center gap-2">
            <span class="guide-step-badge bg-secondary text-white">4</span>
            <div>
                <h5 class="mb-0 fw-bold">{{ __('Controlling Gate 3: D365 Packing Instruction & Label Generation') }}</h5>
                <small class="text-muted">{{ __('Screen: Generate Labels (Waiting Distribution) / Order Detail') }}</small>
            </div>
        </div>
        <div class="card-body p-4">
            <div class="row g-4 mb-3">
                <div class="col-lg-4">
                    <div class="p-3 border rounded-3 bg-light h-100">
                        <h6 class="fw-bold text-primary"><i class="fas fa-bolt me-1"></i> Generate Labels</h6>
                        <p class="text-muted small mb-0">
                            {{ __('Triggers the automated RPA service to create the Packing Instruction (PI) in D365. Takes 30-60 seconds. Button displays "Generating…" while background job runs.') }}
                        </p>
                    </div>
                </div>
                <div class="col-lg-4">
                    <div class="p-3 border rounded-3 bg-light h-100">
                        <h6 class="fw-bold text-success"><i class="fas fa-calculator me-1"></i> Recalculate Labels</h6>
                        <p class="text-muted small mb-0">
                            {{ __('Fast local calculation! Appears after labels already exist. Use this when you only changed Blister or Sack capacity to update carton counts without re-calling D365.') }}
                        </p>
                    </div>
                </div>
                <div class="col-lg-4">
                    <div class="p-3 border rounded-3 bg-light h-100">
                        <h6 class="fw-bold text-warning"><i class="fas fa-rotate-right me-1"></i> Retry Generation</h6>
                        <p class="text-muted small mb-0">
                            {{ __('Appears in yellow if label creation failed. Inspect the red/yellow alert box on the order detail to identify the D365 error, fix it, then click Retry.') }}
                        </p>
                    </div>
                </div>
            </div>

            <div class="d-flex align-items-center gap-3 p-3 bg-light border rounded-3 small">
                <i class="fas fa-barcode fa-2x text-secondary"></i>
                <div>
                    <strong>{{ __('Store Labels vs Warehouse (WH) Labels') }}:</strong>
                    {{ __('Store Labels print carton barcodes routed directly to retail branches. WH Labels print bulk pallet/sack barcodes routed to central distribution warehouses.') }}
                </div>
            </div>
        </div>
    </div>

    <!-- 5. CRITICAL: The Main Official Report, Deep-Dive Report Analysis & Director Lifecycle -->
    <div class="guide-card mb-4" id="section-report-validation">
        <div class="card-header bg-white py-3 border-bottom d-flex align-items-center gap-2">
            <span class="guide-step-badge bg-danger text-white">5</span>
            <div>
                <h5 class="mb-0 fw-bold">{{ __('The Main Official Report: Deep-Dive Analysis, Two-Phase Sign-Off & Director Lifecycle') }}</h5>
                <small class="text-muted">{{ __('Screen: Report Validation (/subcon/admin/report-validations), Form (/qc/ho-approve/{token}), & Director (/subcon/admin/director-approvals)') }}</small>
            </div>
        </div>
        <div class="card-body p-4">
            <!-- Origin & Scope Callout -->
            <div class="alert alert-primary border-0 mb-4">
                <div class="d-flex gap-3">
                    <i class="fas fa-file-signature fa-2x text-primary"></i>
                    <div>
                        <h6 class="fw-bold mb-1">{{ __('What is the Main Official Report and Where Does it Originate?') }}</h6>
                        <p class="mb-0 small">
                            {{ app()->getLocale() === 'id'
                                ? 'Laporan Resmi Utama (Main Official Report / Berita Acara Rekonsiliasi) adalah dokumen resmi penutupan surat perintah kerja (PO CMT) yang menggabungkan hasil audit fisik pakaian jadi, rekonsiliasi pemakaian kain bersih, audit retur bahan di gudang, dan klaim pemotongan finansial (Debit Note). Dokumen ini TIDAK dibuat secara manual oleh vendor di portal; dokumen ini berakar dari aplikasi eksternal QC Console (Tauri) di pabrik saat inspektur QC menyelesaikan pemeriksaan fisik garmen jadi.'
                                : 'The Main Official Report (Berita Acara Rekonsiliasi) is the authoritative contractual settlement document closing each CMT Work Order. It merges physical garment inspection findings, net fabric consumption audits, warehouse remnant checks, and financial deduction claims (Debit Notes). It does NOT originate manually from the vendor; it is birthed from the external QC Console (Tauri desktop app) when on-site QC inspectors finalize their physical inspection.' }}
                        </p>
                    </div>
                </div>
            </div>

            <!-- The Complete End-to-End Lifecycle Diagram -->
            <h6 class="fw-bold text-dark mb-3"><i class="fas fa-arrows-split-up-and-left text-primary me-2"></i>{{ __('The Full Lifecycle: From QC Inspection to D365 Financial Settlement') }}</h6>
            <div class="row g-3 mb-4">
                <div class="col-lg-3 col-md-6">
                    <div class="p-3 border rounded-3 bg-light h-100">
                        <div class="d-flex justify-content-between align-items-center mb-1">
                            <span class="badge bg-secondary">Step 1 &middot; Origin</span>
                            <span class="db-badge">QC Console</span>
                        </div>
                        <h6 class="fw-bold text-dark mb-1">{{ __('Physical Inspection') }}</h6>
                        <p class="small text-muted mb-0">
                            QC Inspector audits finished garments at the factory. Factory representative verifies counts and signs digitally. Generates UUID token.
                        </p>
                    </div>
                </div>
                <div class="col-lg-3 col-md-6">
                    <div class="p-3 border rounded-3 bg-light h-100" style="border-top: 3px solid #2563eb !important;">
                        <div class="d-flex justify-content-between align-items-center mb-1">
                            <span class="badge bg-primary">Step 2 &middot; MD Review</span>
                            <span class="db-badge">Phase A</span>
                        </div>
                        <h6 class="fw-bold text-dark mb-1">{{ __('Review & Approve') }}</h6>
                        <p class="small text-muted mb-0">
                            MD Production conducts initial audit of fabric math, sets fabric price, signs <code>ho_approval_signature</code>, and queues the background RAF run.
                        </p>
                    </div>
                </div>
                <div class="col-lg-3 col-md-6">
                    <div class="p-3 border rounded-3 bg-light h-100" style="border-top: 3px solid #f59e0b !important;">
                        <div class="d-flex justify-content-between align-items-center mb-1">
                            <span class="badge bg-warning text-dark">Step 3 &middot; Validation</span>
                            <span class="db-badge">Phase B &middot; VSM Gate</span>
                        </div>
                        <h6 class="fw-bold text-dark mb-1">{{ __('Validate & Send') }}</h6>
                        <p class="small text-muted mb-0">
                            MD Production audits VSM warehouse returns, enters mandatory remarks, signs <code>ho_validation_signature</code>, and forwards to Director.
                        </p>
                    </div>
                </div>
                <div class="col-lg-3 col-md-6">
                    <div class="p-3 border rounded-3 bg-light h-100" style="border-top: 3px solid #10b981 !important;">
                        <div class="d-flex justify-content-between align-items-center mb-1">
                            <span class="badge bg-success">Step 4 &middot; Settlement</span>
                            <span class="db-badge">Director &middot; D365</span>
                        </div>
                        <h6 class="fw-bold text-dark mb-1">{{ __('Director Authorization') }}</h6>
                        <p class="small text-muted mb-0">
                            Director executes digital sign-off. Locks project as completed; RPA automatically registers Debit Notes & invoices into D365 AP.
                        </p>
                    </div>
                </div>
            </div>

            <!-- Deep-Dive: How to Analyze the Report -->
            <div class="card border border-primary-subtle rounded-3 mb-4 overflow-hidden">
                <div class="card-header bg-primary-subtle py-3">
                    <h6 class="fw-bold mb-0 text-primary-emphasis">
                        <i class="fas fa-magnifying-glass-chart me-2"></i>{{ __('Admin Deep-Dive: How to Analyze the Report During Validation') }}
                    </h6>
                    <small class="text-muted">{{ __('Follow these 6 critical analytical dimensions before approving or sending to the Director') }}</small>
                </div>
                <div class="card-body p-4">
                    <div class="row g-4">
                        <!-- Dimension 1: Output Yield & Defect Rates -->
                        <div class="col-lg-6">
                            <div class="p-3 border rounded-3 bg-white h-100">
                                <h6 class="fw-bold text-dark mb-2">
                                    <span class="badge bg-primary me-1">1</span> {{ __('Garment Yield & Defect Analysis') }}
                                </h6>
                                <p class="text-muted small mb-2">
                                    Compare <strong>Total Qty Cut</strong> against <strong>Inspected Passed Garments</strong>.
                                </p>
                                <ul class="small text-muted ps-3 mb-0">
                                    <li><strong>Shortage / Loss:</strong> If finished passed pieces are substantially below cut quantity (&gt;1% variance), check QC remarks for sewing defects, oil stains, or sewing rejects.</li>
                                    <li><strong>Inspection Result:</strong> Check the status pill. If <span class="badge bg-danger-subtle text-danger border border-danger">FAILED</span>, inspect QC defect tallies before agreeing to release the batch.</li>
                                </ul>
                            </div>
                        </div>

                        <!-- Dimension 2: Fabric Consumption & Remnant Math -->
                        <div class="col-lg-6">
                            <div class="p-3 border rounded-3 bg-white h-100">
                                <h6 class="fw-bold text-dark mb-2">
                                    <span class="badge bg-primary me-1">2</span> {{ __('Fabric Consumption & Remnant Math Analysis') }}
                                </h6>
                                <p class="text-muted small mb-2">
                                    Analyze fabric lines in the consumption table using the core formula:
                                </p>
                                <div class="formula-box p-2 mb-2 small">
                                    <strong>Net Fabric</strong> = Fabric Sent - Retur Kain<br>
                                    <strong>Actual Cons.</strong> = Net Fabric / Total Qty Cut
                                </div>
                                <ul class="small text-muted ps-3 mb-0">
                                    <li><strong>Overconsumption Check:</strong> If <code>Actual Cons. &gt; Cons. Plan</code>, overconsumption is flagged in red.</li>
                                    <li><strong>Remnant Verification:</strong> Ensure vendor logged all returned rolls in <span class="screen-tag">Retur Kain</span>. If Retur Kain is missing, actual consumption will appear falsely inflated!</li>
                                </ul>
                            </div>
                        </div>

                        <!-- Dimension 3: Financial Deductions & D365 Price Hints -->
                        <div class="col-lg-6">
                            <div class="p-3 border rounded-3 bg-white h-100">
                                <h6 class="fw-bold text-dark mb-2">
                                    <span class="badge bg-primary me-1">3</span> {{ __('Financial Deductions & Price Hint Resolution') }}
                                </h6>
                                <p class="text-muted small mb-2">
                                    The system calculates fabric overconsumption penalties automatically:
                                </p>
                                <div class="formula-box p-2 mb-2 small">
                                    <strong>Fabric Deduction (IDR)</strong> = Overconsumption (Meters) &times; Fabric Unit Price
                                </div>
                                <ul class="small text-muted ps-3 mb-0">
                                    <li><strong>D365 Price Hint:</strong> System pre-fills the unit price from historical fabric POs in D365. Admin can override if contract pricing was updated.</li>
                                    <li><strong>Additional Deductions:</strong> Add manual deduction rows for transit damages, delayed delivery penalties, or missing packaging trims.</li>
                                </ul>
                            </div>
                        </div>

                        <!-- Dimension 4: VSM Physical Audit & Material Flow -->
                        <div class="col-lg-6">
                            <div class="p-3 border rounded-3 bg-white h-100">
                                <h6 class="fw-bold text-dark mb-2">
                                    <span class="badge bg-primary me-1">4</span> {{ __('Accessories & VSM Physical Return Audit') }}
                                </h6>
                                <p class="text-muted small mb-2">
                                    Cross-examine physical delivery notes (Surat Jalan Retur) with warehouse physical logs:
                                </p>
                                <ul class="small text-muted ps-3 mb-0">
                                    <li><strong>Delivery Note Attachments:</strong> Click and inspect the uploaded PDF/image scans under "Delivery Note Attachment".</li>
                                    <li><strong>Accessory Quantities:</strong> Verify returned quantities of zippers, buttons, labels, and thread in PCS.</li>
                                    <li><strong>VSM Status:</strong> Check the Material Flow badge. If <span class="badge bg-warning-subtle text-warning-emphasis">Pending</span>, warehouse has not verified physical weight in VSM yet.</li>
                                </ul>
                            </div>
                        </div>

                        <!-- Dimension 5: RAF Automation Status -->
                        <div class="col-lg-6">
                            <div class="p-3 border rounded-3 bg-white h-100">
                                <h6 class="fw-bold text-dark mb-2">
                                    <span class="badge bg-primary me-1">5</span> {{ __('RAF (Robot Automated Financials) Analysis') }}
                                </h6>
                                <p class="text-muted small mb-2">
                                    In Validate Mode, check the RAF status callout banner:
                                </p>
                                <ul class="small text-muted ps-3 mb-0">
                                    <li><span class="badge bg-success-subtle text-success me-1">RAF Completed</span> Production run is synchronized and document reflects final financial figures.</li>
                                    <li><span class="badge bg-warning-subtle text-warning-emphasis me-1">RAF Pending</span> Background automation is calculating. Refresh after 30 seconds.</li>
                                    <li><span class="badge bg-danger-subtle text-danger me-1">RAF Failed</span> Automation failed to post to ERP. Contact RPA support before sending to Director.</li>
                                </ul>
                            </div>
                        </div>

                        <!-- Dimension 6: PDF Document Cross-Examination -->
                        <div class="col-lg-6">
                            <div class="p-3 border rounded-3 bg-white h-100">
                                <h6 class="fw-bold text-dark mb-2">
                                    <span class="badge bg-primary me-1">6</span> {{ __('PDF Document Tab Cross-Examination') }}
                                </h6>
                                <p class="text-muted small mb-2">
                                    On the approval screen, click the <strong><i class="fas fa-file-pdf me-1"></i> PDF Document</strong> tab:
                                </p>
                                <ul class="small text-muted ps-3 mb-0">
                                    <li>Renders the exact formatted contractual document in real time from live database figures.</li>
                                    <li>Verify inspector name, factory representative signature, and itemized deduction breakdown before applying your digital sign-off.</li>
                                </ul>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Step-by-Step Walkthrough: Two-Phase Sign-Off -->
            <h6 class="fw-bold text-dark mb-3"><i class="fas fa-signature text-primary me-2"></i>{{ __('The Two-Phase Sign-Off Workflow on /qc/ho-approve/{token}') }}</h6>
            <div class="p-3 bg-light rounded-3 border mb-4">
                <div class="row g-3">
                    <div class="col-lg-6">
                        <div class="p-3 bg-white rounded-3 border h-100">
                            <div class="d-flex align-items-center gap-2 mb-2">
                                <span class="badge bg-primary">Phase A</span>
                                <strong class="text-dark">{{ __('Review & Approve (hoApprove)') }}</strong>
                            </div>
                            <p class="text-muted small mb-2">
                                When the report first arrives from QC Console:
                            </p>
                            <ol class="small text-muted ps-3 mb-0">
                                <li>Review consumption numbers, enter fabric prices, and record initial deductions.</li>
                                <li>Click the green button: <span class="screen-tag text-success">Review &amp; Approve</span>.</li>
                                <li>System writes <code>ho_approval_signature</code> and queues the RAF background robot run.</li>
                                <li>Page switches into <strong>Validate Mode</strong>.</li>
                            </ol>
                        </div>
                    </div>
                    <div class="col-lg-6">
                        <div class="p-3 bg-white rounded-3 border h-100">
                            <div class="d-flex align-items-center gap-2 mb-2">
                                <span class="badge bg-success">Phase B</span>
                                <strong class="text-dark">{{ __('Validate & Send Approval (hoSendApproval)') }}</strong>
                            </div>
                            <p class="text-muted small mb-2">
                                Finalizing and transmitting to Commercial Director:
                            </p>
                            <ol class="small text-muted ps-3 mb-2">
                                <li>Verify RAF status shows <em>Completed</em>.</li>
                                <li>Verify Material Flow shows <em>Checked</em> on VSM.</li>
                                <li>Enter mandatory <strong>Head Office Remarks</strong> explaining variances.</li>
                                <li>Click <span class="screen-tag text-primary">Validate &amp; Send Approval</span> to notify Director.</li>
                            </ol>
                            <div class="p-2 bg-light rounded border small text-danger">
                                <strong><i class="fas fa-lock me-1"></i> {{ __('Hard Gate Rule') }}:</strong>
                                {{ app()->getLocale() === 'id'
                                    ? 'Tombol "Validate & Send Approval" TIDAK AKAN BISA dikirim ke Direktur selama tugas di VSM (value_stream_ops) belum diverifikasi fisik oleh staf gudang! Staf gudang di VSM harus menimbang/menghitung kain retur, memasukkan qty_actual, dan menekan "Mark as Checked". Jika ada retur yang belum dicek, sistem otomatis menolak pengiriman untuk mencegah kebocoran finansial.'
                                    : 'The "Validate & Send Approval" button is HARD-BLOCKED from sending to the Director as long as the dispatched task in VSM (value_stream_ops) has not been physically verified by warehouse staff! Warehouse staff in VSM must count returned materials, enter qty_actual, and click "Mark as Checked". Un-checked tasks prevent financial leakage.' }}
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Admin Controls: Recall Button & Director Rejection Loop -->
            <div class="row g-3">
                <div class="col-lg-6">
                    <div class="p-3 border rounded-3 bg-light h-100">
                        <h6 class="fw-bold text-warning mb-2">
                            <i class="fas fa-rotate-left me-1"></i> {{ __('Emergency Safety Valve: The "Recall" Action') }}
                        </h6>
                        <p class="small text-muted mb-2">
                            What if MD Production clicked "Validate & Send Approval" but immediately realized a wrong deduction figure was entered?
                        </p>
                        <div class="p-2 bg-white rounded border small text-dark mb-2">
                            <strong>{{ __('How to Recall') }}:</strong> Navigate to <span class="screen-tag">Director Authorizations</span> (/subcon/admin/director-approvals). Locate the row and click the orange <span class="badge bg-warning-subtle text-dark border border-warning"><i class="fas fa-rotate-left me-1"></i> Recall</span> button.
                        </div>
                        <p class="small text-muted mb-0">
                            <em>Quiet correction path:</em> No rejection email is sent to anyone. The project instantly re-opens in Report Validation, unlocking the form for MD Production to adjust figures and re-send.
                        </p>
                    </div>
                </div>

                <div class="col-lg-6">
                    <div class="p-3 border rounded-3 bg-light h-100">
                        <h6 class="fw-bold text-danger mb-2">
                            <i class="fas fa-ban me-1"></i> {{ __('Director Rejection & The Revision Loop') }}
                        </h6>
                        <p class="small text-muted mb-2">
                            If the Commercial Director discovers a discrepancy and clicks <strong>Reject</strong>:
                        </p>
                        <ul class="small text-muted ps-3 mb-0">
                            <li>The Director enters a mandatory rejection reason (e.g. <em>"Fabric penalty must be recalculated"</em>).</li>
                            <li>The project reverts to <code>pending_ho</code> in MD Production's <span class="screen-tag">Report Validations</span> queue with <code>revision_count + 1</code>.</li>
                            <li>A red banner highlights the Director's reason. MD Production adjusts figures, updates HO Remarks, and resubmits to the Director.</li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- 6. Add Process & 70% Rejection Governance -->
    <div class="guide-card mb-4" id="control-addprocess">
        <div class="card-header bg-white py-3 border-bottom d-flex align-items-center gap-2">
            <span class="guide-step-badge bg-dark text-white">6</span>
            <div>
                <h5 class="mb-0 fw-bold">{{ __('Controlling Add Process & 70% Rejection Penalties') }}</h5>
                <small class="text-muted">{{ __('Screen: Add Process (/subcon/admin/add-process)') }}</small>
            </div>
        </div>
        <div class="card-body p-4">
            <p class="text-muted small mb-3">
                {{ app()->getLocale() === 'id'
                    ? 'Add Process mengelola proses sekunder yang dikerjakan oleh vendor embellishment (Washing, Bordir, Sablon, Pleating). PO ini terpisah dari PO CMT utama dan menggunakan PurchPoolId "AddPro".'
                    : 'Add Process governs secondary outsourced operations performed by embellishment vendors (Washing, Embroidery, Screen Printing, Pleating). These use separate commercial POs under PurchPoolId "AddPro".' }}
            </p>
            <div class="row g-3 mb-3">
                <div class="col-md-3">
                    <div class="p-3 bg-light border rounded text-center">
                        <strong class="text-primary font-monospace">ADD-Was</strong>
                        <div class="small text-muted">Washing / Garment Dye</div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="p-3 bg-light border rounded text-center">
                        <strong class="text-success font-monospace">ADD-Embro</strong>
                        <div class="small text-muted">Embroidery / Bordir</div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="p-3 bg-light border rounded text-center">
                        <strong class="text-warning font-monospace">ADD-Sab</strong>
                        <div class="small text-muted">Screen Print / Sablon</div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="p-3 bg-light border rounded text-center">
                        <strong class="text-danger font-monospace">ADD-Pleat</strong>
                        <div class="small text-muted">Pleating / Lipit Kain</div>
                    </div>
                </div>
            </div>

            <div class="alert alert-danger border-0 small">
                <i class="fas fa-gavel me-1"></i>
                <strong>{{ __('The 70% Rejection Penalty Formula') }}:</strong>
                <div class="mt-2 font-monospace bg-white p-2 rounded border">
                    Allowed Limit = floor(Cut Qty &times; 1%)<br>
                    Exceeding Rejects = max(0, Reject Qty - Allowed Limit)<br>
                    Penalty Price = Benchmark Sales Price &times; 70%<br>
                    <strong>Total Reject Penalty = Exceeding Rejects &times; Penalty Price</strong>
                </div>
                <div class="mt-2">
                    {{ app()->getLocale() === 'id'
                        ? 'Toleransi kerusakan standar adalah 1% dari jumlah potong. Jika reject melebihi 1%, setiap potong baju yang rusak dikenakan penalti 70% dari harga patokan baju untuk mengganti biaya kain dan jasa cutting.'
                        : 'Standard damage tolerance is 1% of cut qty. Rejects exceeding 1% incur a 70% penalty against benchmark sales price to recover fabric and cutting costs.' }}
                </div>
            </div>
        </div>
    </div>

    <!-- 7. Official Report (Berita Acara) Multi-Tier Authorizations -->
    <div class="guide-card mb-4" id="control-berita-acara">
        <div class="card-header bg-white py-3 border-bottom d-flex align-items-center gap-2">
            <span class="guide-step-badge bg-primary text-white">7</span>
            <div>
                <h5 class="mb-0 fw-bold">{{ __('Controlling Official Report (Berita Acara) & Director Authorizations') }}</h5>
                <small class="text-muted">{{ __('Screen: Official Reports (/subcon/admin/official-reports)') }}</small>
            </div>
        </div>
        <div class="card-body p-4">
            <p class="text-muted small mb-3">
                {{ app()->getLocale() === 'id'
                    ? 'Berita Acara resmi diterbitkan per operasi Add Process (ADD-Was, ADD-Embro, dll) untuk mengklaim penalti penolakan barang dan pengurangan biaya. Berita Acara menggunakan otorisasi 2 tingkat:'
                    : 'Official Reports (Berita Acara) are generated per Add Process operation (ADD-Was, ADD-Embro, etc.) to claim reject penalties and deductions. Follows 2-tier authorization:' }}
            </p>
            <div class="row g-3">
                <div class="col-md-6">
                    <div class="p-3 border rounded-3 bg-light h-100 small">
                        <span class="badge bg-primary mb-2">Stage 1</span>
                        <div class="fw-bold text-dark mb-1">{{ __('MD Production Validation') }}</div>
                        <div class="text-muted">{{ __('Admin reviews good vs reject counts, verifies benchmark sales price, adds optional manual deductions, and signs digitally.') }}</div>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="p-3 border rounded-3 bg-light h-100 small">
                        <span class="badge bg-danger mb-2">Stage 2</span>
                        <div class="fw-bold text-dark mb-1">{{ __('Commercial Director Sign-off') }}</div>
                        <div class="text-muted">{{ __('Executive digital signature. Once approved, Debit Notes are posted to Finance via RPA automatically. If declined, reverts to MD Production for revision.') }}</div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- 8. Vendor Governance & Security -->
    <div class="guide-card mb-5" id="control-vendors">
        <div class="card-header bg-white py-3 border-bottom d-flex align-items-center gap-2">
            <span class="guide-step-badge bg-info text-white">8</span>
            <div>
                <h5 class="mb-0 fw-bold">{{ __('Vendor Account Administration & Security') }}</h5>
                <small class="text-muted">{{ __('Screen: Vendors (/subcon/admin/vendors) & Workflow (/subcon/admin/workflow)') }}</small>
            </div>
        </div>
        <div class="card-body p-4">
            <div class="row g-4">
                <div class="col-lg-6">
                    <h6 class="fw-bold text-dark"><i class="fas fa-key text-warning me-1"></i> {{ __('Managing Vendor Accounts & Password Resets') }}</h6>
                    <ul class="small text-muted ps-3 mb-0">
                        <li><strong>{{ __('Reset Password') }}:</strong> In <span class="screen-tag">Vendors</span> menu, click <span class="badge bg-warning text-dark">Reset Password</span>. Password resets immediately to standard default: <code>password</code>.</li>
                        <li><strong>{{ __('Deactivating Vendors') }}:</strong> Click the toggle switch to disable access immediately for inactive or retired contractors.</li>
                        <li><strong>{{ __('New Vendor Setup') }}:</strong> Click "Add Subcon Vendor", link their vendor code from D365, and provide their operational email.</li>
                    </ul>
                </div>
                <div class="col-lg-6">
                    <h6 class="fw-bold text-dark"><i class="fas fa-envelope-open-text text-primary me-1"></i> {{ __('Workflow Email Routing') }}</h6>
                    <p class="text-muted small mb-2">
                        In <span class="screen-tag">Workflow Settings</span>, you configure who receives approval emails:
                    </p>
                    <div class="list-group list-group-flush border rounded-3 small">
                        <div class="list-group-item"><strong>Cutting Approver:</strong> Receives cutting submission emails with signed links.</div>
                        <div class="list-group-item"><strong>Gramasi Approver:</strong> Receives unit weight & blister capacity approval requests.</div>
                        <div class="list-group-item"><strong>Final Approver (HO):</strong> Receives QC Stage-1 completion links for Report Validation.</div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- ============================================================= -->
    <!-- PART III: ADMIN TROUBLESHOOTING RUNBOOK & DIAGNOSTICS         -->
    <!-- ============================================================= -->
    <div class="d-flex align-items-center justify-content-between mb-3" id="section-troubleshooting">
        <div class="d-flex align-items-center gap-2">
            <span class="badge bg-danger text-white rounded-pill px-3 py-1 fw-bold">PART III</span>
            <h4 class="fw-bold mb-0 text-dark">{{ __('Admin Troubleshooting Runbook & Diagnostics') }}</h4>
        </div>
        <span class="text-muted small"><i class="fas fa-stethoscope me-1"></i>{{ __('10 Real-World Production Scenarios & Fixes') }}</span>
    </div>

    <!-- Troubleshooting Grid -->
    <div class="row g-3 mb-5">
        <!-- Issue 1: Material Flow VSM Gate Block -->
        <div class="col-lg-6">
            <div class="trouble-card p-4 h-100">
                <div class="d-flex align-items-center justify-content-between mb-2">
                    <span class="trouble-badge text-danger"><i class="fas fa-circle-exclamation me-1"></i> Scenario 1</span>
                    <span class="badge bg-danger-subtle text-danger">Material Flow &middot; Gate Lock</span>
                </div>
                <h6 class="fw-bold text-dark mb-2">{{ __('Can\'t Send to Director: "Material Flow still needs to check the returned material"') }}</h6>
                <div class="small text-muted mb-3">
                    <strong>Symptom:</strong> MD Production clicks "Validate & Send Approval", but the page redirects back with a red warning banner: <em>"Report Validation can't be sent to the Director yet &mdash; Material Flow still needs to check the returned material."</em>
                </div>
                <div class="p-3 bg-light rounded-3 small border">
                    <strong class="text-primary"><i class="fas fa-wrench me-1"></i> Step-by-Step Resolution:</strong>
                    <ol class="ps-3 mb-0 mt-1 text-muted">
                        <li><strong>Identify the Cause:</strong> You dispatched a material return task to warehouse, but warehouse staff has not completed their physical count in VSM yet. Alternatively, delivery notes were attached without clicking "Send to Material Flow".</li>
                        <li><strong>Action in VSM:</strong> Contact warehouse inventory staff. Have them open <strong>VSM (<code>value_stream_ops</code>) &rarr; Material Flow tab</strong>.</li>
                        <li><strong>Physical Count:</strong> Warehouse staff inspects the returned physical fabric rolls or accessory packages, inputs the actual received count into <span class="screen-tag">Qty Actual</span>, and clicks <span class="screen-tag">Mark as Checked</span>.</li>
                        <li><strong>Return & Submit:</strong> Once VSM marks the task as <code>checked</code>, refresh the Report Validation page on the portal. The gate is now unlocked; click <span class="screen-tag">Validate & Send Approval</span>.</li>
                    </ol>
                </div>
            </div>
        </div>

        <!-- Issue 2: QC Console Not Showing Fabric Lines -->
        <div class="col-lg-6">
            <div class="trouble-card warning p-4 h-100">
                <div class="d-flex align-items-center justify-content-between mb-2">
                    <span class="trouble-badge text-warning"><i class="fas fa-triangle-exclamation me-1"></i> Scenario 2</span>
                    <span class="badge bg-warning-subtle text-warning">QC Console &middot; QMS DB</span>
                </div>
                <h6 class="fw-bold text-dark mb-2">{{ __('QC Console Shows Empty Fabric Consumption or "Not yet in QC console"') }}</h6>
                <div class="small text-muted mb-3">
                    <strong>Symptom:</strong> On the Order Detail page, status badge displays "Not yet in QC console" or inspector on QC Console desktop app says fabric consumption table is completely blank.
                </div>
                <div class="p-3 bg-light rounded-3 small border">
                    <strong class="text-primary"><i class="fas fa-wrench me-1"></i> Step-by-Step Resolution:</strong>
                    <ol class="ps-3 mb-0 mt-1 text-muted">
                        <li><strong>Understand Architecture:</strong> The portal publishes fabric lines to the shared QMS table <code>packaging_project_fabric_lines</code> keyed by <code>production_group</code> with <code>project_id = NULL</code> (staged).</li>
                        <li><strong>Check Cutting Approval:</strong> Ensure Gate 1 (Cutting Review) has been formally <strong>Approved</strong>. Fabric lines are ONLY published upon cutting approval!</li>
                        <li><strong>Check Production Group:</strong> In QC Console, verify the inspector selected the exact matching Production Group code (e.g. <code>MPG/PRG/2601/000070</code>). If the group code differs, the pull query returns 0 rows.</li>
                        <li><strong>Force Re-Publish:</strong> In Subcon Admin &rarr; Order View &rarr; Material Reconciliation, make a minor edit and click <span class="screen-tag">Save Reconciliation</span>. This immediately re-executes <code>SubconFabricLinePublisher::publish()</code> to QMS.</li>
                    </ol>
                </div>
            </div>
        </div>

        <!-- Issue 3: Director Declined / Rejected the Report -->
        <div class="col-lg-6">
            <div class="trouble-card warning p-4 h-100">
                <div class="d-flex align-items-center justify-content-between mb-2">
                    <span class="trouble-badge text-warning"><i class="fas fa-rotate-left me-1"></i> Scenario 3</span>
                    <span class="badge bg-warning-subtle text-warning">Director Decline &middot; Revision</span>
                </div>
                <h6 class="fw-bold text-dark mb-2">{{ __('Director Declined the Report: How to Revise & Resubmit') }}</h6>
                <div class="small text-muted mb-3">
                    <strong>Symptom:</strong> The Commercial Director clicked "Decline" on their authorization link, providing a rejection reason (e.g. <em>"Fabric penalty is too low, check Short Roll"</em>).
                </div>
                <div class="p-3 bg-light rounded-3 small border">
                    <strong class="text-primary"><i class="fas fa-wrench me-1"></i> Step-by-Step Resolution:</strong>
                    <ol class="ps-3 mb-0 mt-1 text-muted">
                        <li><strong>Project State:</strong> The inspection automatically reverts to <code>pending_ho</code> in MD Production's queue. A yellow banner displays the Director's decline note.</li>
                        <li><strong>Open Report Validation:</strong> Open <span class="screen-tag">Report Validations</span> and click into the flagged order.</li>
                        <li><strong>Adjust Figures:</strong> Review the Director's note. Adjust Fabric Price, Short Roll, or add manual deduction lines as requested.</li>
                        <li><strong>Update HO Remarks:</strong> In <span class="screen-tag">HO Remarks</span>, explain the corrections made in response to the Director's feedback.</li>
                        <li><strong>Re-Send:</strong> Click <span class="screen-tag">Validate & Send Approval</span>. A new fresh review link is emailed to the Director with incremented revision counter.</li>
                    </ol>
                </div>
            </div>
        </div>

        <!-- Issue 4: Label Generation Stuck or Error -->
        <div class="col-lg-6">
            <div class="trouble-card p-4 h-100">
                <div class="d-flex align-items-center justify-content-between mb-2">
                    <span class="trouble-badge text-danger"><i class="fas fa-circle-exclamation me-1"></i> Scenario 4</span>
                    <span class="badge bg-danger-subtle text-danger">D365 RPA &middot; Packing Instruction</span>
                </div>
                <h6 class="fw-bold text-dark mb-2">{{ __('Label Generation Stuck on "Generating…" or Throws Yellow Retry') }}</h6>
                <div class="small text-muted mb-3">
                    <strong>Symptom:</strong> Generate Labels button stays on "Generating…" for over 2 minutes or changes into yellow "Retry Generation".
                </div>
                <div class="p-3 bg-light rounded-3 small border">
                    <strong class="text-primary"><i class="fas fa-wrench me-1"></i> Step-by-Step Resolution:</strong>
                    <ol class="ps-3 mb-0 mt-1 text-muted">
                        <li><strong>Inspect the Alert Banner:</strong> Look at the red/yellow callout above the button. If it says <em>"Can't generate &mdash; needs a fix in D365"</em>, a destination retail store is closed or inactive in D365. Contact IT/Merchandiser to update the store code in D365, then click <span class="screen-tag">Retry After D365 Fix</span>.</li>
                        <li><strong>Check Gramasi:</strong> Verify gramasi was entered for ALL sizes. Zero gramasi causes D365 Packing Instruction calculation to crash.</li>
                        <li><strong>Capacity Change:</strong> If you only changed Blister or Sack capacity, do <strong>NOT</strong> click Generate Labels; click <span class="screen-tag">Recalculate Labels</span> for instant local updates!</li>
                    </ol>
                </div>
            </div>
        </div>

        <!-- Issue 5: Abnormal Deduction / Overconsumption Dispute -->
        <div class="col-lg-6">
            <div class="trouble-card warning p-4 h-100">
                <div class="d-flex align-items-center justify-content-between mb-2">
                    <span class="trouble-badge text-warning"><i class="fas fa-calculator me-1"></i> Scenario 5</span>
                    <span class="badge bg-warning-subtle text-warning">Financial &middot; Penalty Dispute</span>
                </div>
                <h6 class="fw-bold text-dark mb-2">{{ __('Vendor Disputes Huge Overconsumption Deduction in IDR') }}</h6>
                <div class="small text-muted mb-3">
                    <strong>Symptom:</strong> Deduction shows millions of IDR in red. The vendor complains that the system is unfairly penalizing them.
                </div>
                <div class="p-3 bg-light rounded-3 small border">
                    <strong class="text-primary"><i class="fas fa-wrench me-1"></i> Step-by-Step Resolution:</strong>
                    <ol class="ps-3 mb-0 mt-1 text-muted">
                        <li><strong>Check Retur Kain:</strong> Did the vendor return leftover fabric rolls but forgot to enter them in the portal? Formula: <code>Net Fabric = Fabric Sent - Retur Kain</code>. If Retur Kain is zero, Net Fabric is inflated!</li>
                        <li><strong>Check Cons. Plan:</strong> Verify if Engineering revised marker efficiency. If Cons. Plan is outdated, enter the approved Cons. Plan and click <span class="screen-tag">Save Reconciliation</span>.</li>
                        <li><strong>Check VSM Actuals:</strong> Verify if VSM warehouse counted less returned fabric than vendor declared. Discrepancies in VSM directly affect Net Fabric.</li>
                    </ol>
                </div>
            </div>
        </div>

        <!-- Issue 6: PO Missing After D365 Sync -->
        <div class="col-lg-6">
            <div class="trouble-card info p-4 h-100">
                <div class="d-flex align-items-center justify-content-between mb-2">
                    <span class="trouble-badge text-primary"><i class="fas fa-database me-1"></i> Scenario 6</span>
                    <span class="badge bg-primary-subtle text-primary">ERP Sync &middot; PO Ingestion</span>
                </div>
                <h6 class="fw-bold text-dark mb-2">{{ __('Work Order Missing or Not Found on Dashboard After Sync') }}</h6>
                <div class="small text-muted mb-3">
                    <strong>Symptom:</strong> Merchandiser gave a PO number from D365, but searching on Subcon Admin yields zero results even after clicking "Sync POs".
                </div>
                <div class="p-3 bg-light rounded-3 small border">
                    <strong class="text-primary"><i class="fas fa-wrench me-1"></i> Step-by-Step Resolution:</strong>
                    <ol class="ps-3 mb-0 mt-1 text-muted">
                        <li><strong>PO Status:</strong> Open D365 &rarr; <code>PurchTable</code>. Confirm status is <strong>Confirmed</strong> (Draft or Open POs are filtered out).</li>
                        <li><strong>Purchase Pool:</strong> The PO must have PurchPoolId set strictly to <code>CMT</code>. If set to fabric or generic pool, the portal sync query skips it.</li>
                        <li><strong>Vendor Code:</strong> Verify the vendor code exists in <span class="screen-tag">Vendors</span> menu. If it's a new vendor, add them first.</li>
                        <li><strong>Run Sync:</strong> Click <span class="screen-tag">Sync POs</span> on Dashboard and inspect <span class="screen-tag">Logs</span> for any API errors.</li>
                    </ol>
                </div>
            </div>
        </div>

        <!-- Issue 7: Vendor Cutting Mistake & Revision -->
        <div class="col-lg-6">
            <div class="trouble-card warning p-4 h-100">
                <div class="d-flex align-items-center justify-content-between mb-2">
                    <span class="trouble-badge text-warning"><i class="fas fa-scissors me-1"></i> Scenario 7</span>
                    <span class="badge bg-warning-subtle text-warning">Vendor Mistake &middot; Revision</span>
                </div>
                <h6 class="fw-bold text-dark mb-2">{{ __('Vendor Submitted Wrong Cutting Quantity After Approval') }}</h6>
                <div class="small text-muted mb-3">
                    <strong>Symptom:</strong> Vendor accidentally typed 300 pcs instead of 450 pcs on Size XL, and cutting report is already approved.
                </div>
                <div class="p-3 bg-light rounded-3 small border">
                    <strong class="text-primary"><i class="fas fa-wrench me-1"></i> Step-by-Step Resolution:</strong>
                    <ol class="ps-3 mb-0 mt-1 text-muted">
                        <li><strong>Vendor Action:</strong> Instruct vendor to open the order on their portal and click <span class="screen-tag">Revise Cutting Report</span>.</li>
                        <li><strong>Correct Numbers:</strong> Vendor edits the quantities by size and submits with an explanatory revision note.</li>
                        <li><strong>Admin Re-Approval:</strong> The order re-enters <span class="screen-tag">Approvals</span> &rarr; Cutting Review on your dashboard. Inspect the revised figures and click <span class="screen-tag">Approve</span>.</li>
                    </ol>
                </div>
            </div>
        </div>

        <!-- Issue 8: Add Process Missing Matched Commercial PO -->
        <div class="col-lg-6">
            <div class="trouble-card info p-4 h-100">
                <div class="d-flex align-items-center justify-content-between mb-2">
                    <span class="trouble-badge text-primary"><i class="fas fa-industry me-1"></i> Scenario 8</span>
                    <span class="badge bg-primary-subtle text-primary">Add Process &middot; Commercial PO</span>
                </div>
                <h6 class="fw-bold text-dark mb-2">{{ __('Add Process Says "Awaiting Confirmed Commercial PO in D365"') }}</h6>
                <div class="small text-muted mb-3">
                    <strong>Symptom:</strong> In Add Process menu, operation (ADD-Was / ADD-Embro) is blocked with yellow warning that no confirmed PO matches.
                </div>
                <div class="p-3 bg-light rounded-3 small border">
                    <strong class="text-primary"><i class="fas fa-wrench me-1"></i> Step-by-Step Resolution:</strong>
                    <ol class="ps-3 mb-0 mt-1 text-muted">
                        <li><strong>Understand Matching:</strong> Add Process requires a separate commercial PO in D365 issued to the embellishment vendor under PurchPoolId <code>AddPro</code>.</li>
                        <li><strong>Check Line Group:</strong> In D365, ensure the PO line has the exact <code>Production Group</code> string assigned.</li>
                        <li><strong>Confirm PO:</strong> Ensure Purchasing has changed the PO status to <strong>Confirmed</strong> in D365. Once confirmed, refresh the Add Process page to proceed.</li>
                    </ol>
                </div>
            </div>
        </div>

        <!-- Issue 9: Discrepancy Between Vendor Declared vs VSM Actual Return -->
        <div class="col-lg-6">
            <div class="trouble-card warning p-4 h-100">
                <div class="d-flex align-items-center justify-content-between mb-2">
                    <span class="trouble-badge text-warning"><i class="fas fa-boxes-stacked me-1"></i> Scenario 9</span>
                    <span class="badge bg-warning-subtle text-warning">VSM Audit &middot; Quantity Variance</span>
                </div>
                <h6 class="fw-bold text-dark mb-2">{{ __('Discrepancy: Vendor Declared 50m Return, But VSM Counted 35m') }}</h6>
                <div class="small text-muted mb-3">
                    <strong>Symptom:</strong> Vendor claimed in delivery note that they returned 50 meters of fabric, but warehouse recorded 35 meters in VSM.
                </div>
                <div class="p-3 bg-light rounded-3 small border">
                    <strong class="text-primary"><i class="fas fa-wrench me-1"></i> Step-by-Step Resolution:</strong>
                    <ol class="ps-3 mb-0 mt-1 text-muted">
                        <li><strong>Audit Delivery Slips:</strong> Open the delivery note attachment scan on the Order View. Inspect physical warehouse receiving stamps.</li>
                        <li><strong>Warehouse Confirmation:</strong> Contact VSM warehouse team. If physical fabric was damaged or short, warehouse's <code>qty_actual</code> (35m) is authoritative.</li>
                        <li><strong>Net Fabric Adjustment:</strong> In Report Validation, Retur Kain will be based on physical verified return, automatically adjusting overconsumption penalties.</li>
                    </ol>
                </div>
            </div>
        </div>

        <!-- Issue 10: Vendor Account Locked or Forgot Password -->
        <div class="col-lg-6">
            <div class="trouble-card success p-4 h-100">
                <div class="d-flex align-items-center justify-content-between mb-2">
                    <span class="trouble-badge text-success"><i class="fas fa-key me-1"></i> Scenario 10</span>
                    <span class="badge bg-success-subtle text-success">Account Security &middot; Quick Reset</span>
                </div>
                <h6 class="fw-bold text-dark mb-2">{{ __('Subcontractor Vendor Forgot Password or Account Locked') }}</h6>
                <div class="small text-muted mb-3">
                    <strong>Symptom:</strong> Vendor partner calls saying their cutting staff cannot log into the portal.
                </div>
                <div class="p-3 bg-light rounded-3 small border">
                    <strong class="text-primary"><i class="fas fa-wrench me-1"></i> Step-by-Step Resolution:</strong>
                    <ol class="ps-3 mb-0 mt-1 text-muted">
                        <li>Go to <span class="screen-tag">Vendors</span> menu (/subcon/admin/vendors).</li>
                        <li>Locate vendor by name or code.</li>
                        <li>Click <span class="badge bg-warning text-dark">Reset Password</span>. Password resets immediately to: <code>password</code>.</li>
                        <li>Instruct vendor to log in with <code>password</code> and immediately update it in their profile.</li>
                    </ol>
                </div>
            </div>
        </div>
    </div>

    <!-- ============================================================= -->
    <!-- PART IV: SYSTEM GLOSSARY & TOOLTIP INDEX                      -->
    <!-- ============================================================= -->
    <div class="d-flex align-items-center gap-2 mb-3" id="section-glossary">
        <span class="badge bg-dark text-white rounded-pill px-3 py-1 fw-bold">PART IV</span>
        <h4 class="fw-bold mb-0 text-dark">{{ __('Quick Reference Glossary & Tooltip "i" Cross-Reference Index') }}</h4>
    </div>

    <div class="guide-card mb-4">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th style="width: 200px;">{{ __('Term / Symbol') }}</th>
                            <th style="width: 140px;">{{ __('System / DB') }}</th>
                            <th>{{ __('Indonesian Explanation') }}</th>
                            <th>{{ __('English Explanation') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td><span class="fw-bold text-primary">Cutt Plan</span></td>
                            <td><span class="db-badge">Portal Core</span></td>
                            <td>Estimasi hasil potong baju yang dihitung dari kain bersih: <code>(Fabric Sent - Retur Kain) / Cons. Plan</code>.</td>
                            <td>Expected garment piece yield computed from net fabric: <code>(Fabric Sent - Retur Kain) / Cons. Plan</code>.</td>
                        </tr>
                        <tr>
                            <td><span class="fw-bold text-dark">Retur Kain</span></td>
                            <td><span class="db-badge">Portal & VSM</span></td>
                            <td>Kain fisik utuh maupun sisa yang dikembalikan oleh subkon ke gudang pabrik dan diverifikasi di VSM.</td>
                            <td>Actual physical fabric rolls or remnants returned by subcontractor to warehouse and checked in VSM.</td>
                        </tr>
                        <tr>
                            <td><span class="fw-bold text-warning">VSM Material Flow</span></td>
                            <td><span class="db-badge">Postgres: wms</span></td>
                            <td>Aplikasi gudang tempat staf memvalidasi fisik penerimaan retur kain & aksesoris (Surat Jalan Retur).</td>
                            <td>Warehouse app where staff physically inspects and audits returned fabric and accessories.</td>
                        </tr>
                        <tr>
                            <td><span class="fw-bold text-info">QC Console</span></td>
                            <td><span class="db-badge">Postgres: qms</span></td>
                            <td>Aplikasi desktop Tauri di pabrik tempat inspektur mengaudit baju jadi dan menerbitkan token persetujuan Stage-1.</td>
                            <td>Tauri desktop app where factory inspectors audit finished garments and trigger Stage-1 approval tokens.</td>
                        </tr>
                        <tr>
                            <td><span class="fw-bold text-danger">Report Validation</span></td>
                            <td><span class="db-badge">Portal Stage 2</span></td>
                            <td>Tahap audit Head Office (MD Production) untuk memverifikasi konsumsi, denda, dan audit fisik VSM sebelum ke Direktur.</td>
                            <td>Head Office audit stage where MD Production verifies consumption math, penalties, and VSM checks before Director send.</td>
                        </tr>
                        <tr>
                            <td><span class="fw-bold text-dark">Short Roll</span></td>
                            <td><span class="db-badge">Portal Core</span></td>
                            <td>Kekurangan panjang kain dari roll cacat yang langsung dipotong dari data issue awal.</td>
                            <td>Fabric shortage or defect roll allowance deducted from original issue meters.</td>
                        </tr>
                        <tr>
                            <td><span class="fw-bold text-dark">Kepala Kain</span></td>
                            <td><span class="db-badge">Portal Core</span></td>
                            <td>Potongan sisa ujung kain / sisa perca hasil proses spreading dan cutting.</td>
                            <td>End remnants and perimeter scrap pieces created during spreading and cutting.</td>
                        </tr>
                        <tr>
                            <td><span class="fw-bold text-info">Gramasi</span></td>
                            <td><span class="db-badge">Portal & QC</span></td>
                            <td>Berat baju per potong dalam satuan gram (g) untuk verifikasi densitas kain dan pencegahan penyusutan.</td>
                            <td>Garment piece weight in grams (g) used to verify fabric density and knit quality.</td>
                        </tr>
                        <tr>
                            <td><span class="fw-bold text-success">Packing Instruction (PI)</span></td>
                            <td><span class="db-badge">D365 ERP</span></td>
                            <td>Instruksi kemasan di Dynamics 365 yang memecah alokasi baju ke dalam karton Coli.</td>
                            <td>Dynamics 365 packing directive allocating finished pieces into carton Coli units.</td>
                        </tr>
                        <tr>
                            <td><span class="fw-bold text-dark">Add Process</span></td>
                            <td><span class="db-badge">D365 AddPro</span></td>
                            <td>Pengerjaan proses tambahan (Washing, Bordir, Sablon, Pleating) dengan PO komersial terpisah.</td>
                            <td>Outsourced embellishment operations (Washing, Embroidery, Print, Pleating) with separate POs.</td>
                        </tr>
                        <tr>
                            <td><span class="fw-bold text-danger">70% Rejection Rule</span></td>
                            <td><span class="db-badge">Official Report</span></td>
                            <td>Penalti 70% dari harga patokan baju atas reject Add Process di atas toleransi 1% untuk mengganti biaya kain & cutting.</td>
                            <td>70% benchmark price penalty on Add Process rejects exceeding 1% tolerance to recover material & cutting loss.</td>
                        </tr>
                        <tr>
                            <td><span class="fw-bold text-success">Recalculate Labels</span></td>
                            <td><span class="db-badge">Local Engine</span></td>
                            <td>Perhitungan cepat jumlah Coli/karton di portal saat kapasitas diubah tanpa pemicuan ulang RPA D365.</td>
                            <td>Fast local recalculation of Coli carton counts when capacity changes without re-calling D365 RPA.</td>
                        </tr>
                        <tr>
                            <td><span class="fw-bold text-warning">Retry Generation</span></td>
                            <td><span class="db-badge">RPA Service</span></td>
                            <td>Memicu ulang pekerjaan RPA ke D365 bila terjadi kegagalan jaringan atau kesalahan bisnis D365.</td>
                            <td>Re-triggers RPA job to D365 if previous generation encountered network or ERP business errors.</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
@endsection
