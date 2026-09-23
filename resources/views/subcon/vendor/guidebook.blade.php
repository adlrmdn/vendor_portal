@extends('layouts.app')

@section('title', __('Vendor Onboarding Guidebook'))

@section('content')
<style>
    .guide-hero {
        background: linear-gradient(135deg, #1e3c72 0%, #2a5298 100%);
        border-radius: 16px;
        color: #ffffff;
        box-shadow: 0 10px 30px rgba(30, 60, 114, 0.15);
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
        font-size: 1.1rem;
    }
    .guide-visualizer-container {
        background: linear-gradient(180deg, #f8fafc 0%, #f1f5f9 100%);
        border-bottom: 1px solid #e2e8f0;
        padding: 24px;
    }
    .feature-pill {
        display: inline-flex;
        align-items: center;
        gap: 6px;
        padding: 6px 12px;
        border-radius: 20px;
        font-size: 0.85rem;
        font-weight: 500;
        background: #f1f5f9;
        color: #334155;
    }
    .mock-flow-card {
        background: #ffffff;
        border-radius: 12px;
        border: 1px solid #e2e8f0;
        box-shadow: 0 4px 12px rgba(0,0,0,0.03);
        transition: transform 0.15s ease;
    }
    .mock-flow-card:hover {
        transform: translateY(-2px);
    }
    .mock-flow-header {
        padding: 10px 14px;
        border-bottom: 1px solid #edf2f7;
        font-weight: 600;
        font-size: 0.85rem;
    }
    .mock-table th {
        font-size: 0.72rem;
        text-transform: uppercase;
        letter-spacing: 0.03em;
        background: #f8fafc;
        padding: 6px 8px;
    }
    .mock-table td {
        font-size: 0.8rem;
        padding: 6px 8px;
    }
</style>

<div class="container-fluid py-2">
    <!-- Hero Banner -->
    <div class="guide-hero p-4 p-md-5 mb-4 position-relative overflow-hidden">
        <div class="row align-items-center">
            <div class="col-lg-8">
                <span class="badge bg-white text-primary px-3 py-1.5 rounded-pill fw-semibold mb-3">
                    <i class="fas fa-book-open me-1"></i> {{ __('Vendor Onboarding Guidebook') }}
                </span>
                <h1 class="display-6 fw-bold mb-2">
                    {{ app()->getLocale() === 'id' ? 'Buku Panduan Mitra Subkontraktor' : 'Subcontractor Partner Guidebook' }}
                </h1>
                <p class="lead mb-3 text-white-50 fs-6">
                    {{ app()->getLocale() === 'id' 
                        ? 'Panduan lengkap dan terstruktur mengenai alur kerja Work Order, pencatatan produksi harian, dan rekonsiliasi bahan.' 
                        : 'Complete structured guide to Work Order workflows, daily production tracking, and material reconciliation.' }}
                </p>
                <div class="d-flex flex-wrap gap-2">
                    <a href="{{ route('subcon.vendor.orders') }}" class="btn btn-light fw-semibold text-primary px-3 shadow-sm">
                        <i class="fas fa-clipboard-list me-1"></i> {{ __('My Work Orders') }}
                    </a>
                    <a href="{{ route('subcon.vendor.daily-production.index') }}" class="btn btn-outline-light fw-semibold px-3">
                        <i class="fas fa-industry me-1"></i> {{ __('Daily Production') }}
                    </a>
                    <a href="{{ route('subcon.vendor.dashboard') }}" class="btn btn-outline-light fw-semibold px-3">
                        <i class="fas fa-tachometer-alt me-1"></i> {{ __('Dashboard') }}
                    </a>
                </div>
            </div>
            <div class="col-lg-4 text-center d-none d-lg-block">
                <i class="fas fa-layer-group fa-8x text-white opacity-25"></i>
            </div>
        </div>
    </div>

    <!-- Quick Navigation Anchor Pills -->
    <div class="d-flex flex-wrap gap-2 mb-4">
        <a href="#sec-lifecycle" class="feature-pill text-decoration-none">
            <i class="fas fa-sync-alt text-primary"></i> 1. {{ app()->getLocale() === 'id' ? 'Alur Utama Order' : 'Work Order Lifecycle' }}
        </a>
        <a href="#sec-daily" class="feature-pill text-decoration-none">
            <i class="fas fa-industry text-warning"></i> 2. {{ app()->getLocale() === 'id' ? 'Produksi Harian' : 'Daily Production Board' }}
        </a>
        <a href="#sec-cutting" class="feature-pill text-decoration-none">
            <i class="fas fa-cut text-danger"></i> 3. {{ app()->getLocale() === 'id' ? 'Laporan Cutting & Prefill' : 'Cutting Report & Prefill' }}
        </a>
        <a href="#sec-recon" class="feature-pill text-decoration-none">
            <i class="fas fa-boxes text-success"></i> 4. {{ app()->getLocale() === 'id' ? 'Rekonsiliasi & Surat Jalan' : 'Material Recon & Delivery Notes' }}
        </a>
        <a href="#sec-guidance" class="feature-pill text-decoration-none">
            <i class="fas fa-info-circle text-info"></i> 5. {{ app()->getLocale() === 'id' ? 'Bantuan & Ikon Informasi' : 'Guidance & Help Icons' }}
        </a>
    </div>

    <!-- SECTION 1: LIFECYCLE OVERVIEW -->
    <div id="sec-lifecycle" class="guide-card mb-5">
        <div class="guide-visualizer-container" id="visualizer-lifecycle">
            <div class="text-center mb-3">
                <span class="badge bg-primary text-white px-3 py-1 rounded-pill small fw-semibold text-uppercase" style="letter-spacing: .05em;">
                    <i class="fas fa-project-diagram me-1"></i> {{ app()->getLocale() === 'id' ? 'Alur Visual 3 Tahapan Produksi' : '3-Stage Visual Production Flow' }}
                </span>
            </div>
            <div class="row g-3 align-items-stretch">
                <!-- Step 1: Cutting -->
                <div class="col-lg-4">
                    <div class="mock-flow-card h-100">
                        <div class="mock-flow-header bg-primary text-white d-flex justify-content-between align-items-center">
                            <span><i class="fas fa-cut me-1"></i> {{ app()->getLocale() === 'id' ? 'Tahap 1: Laporan Cutting' : 'Stage 1: Cutting Report' }}</span>
                            <span class="badge bg-white text-primary rounded-pill small">01</span>
                        </div>
                        <div class="p-3">
                            <div class="small text-secondary mb-2">{{ app()->getLocale() === 'id' ? 'Input Hasil Potong Nyata' : 'Enter Actual Cut Quantities' }}</div>
                            <table class="table table-bordered mock-table text-center mb-2">
                                <thead><tr><th>S</th><th>M</th><th>L</th><th>XL</th></tr></thead>
                                <tbody><tr><td class="bg-primary-subtle fw-bold">120</td><td class="bg-primary-subtle fw-bold">250</td><td class="bg-primary-subtle fw-bold">300</td><td class="bg-primary-subtle fw-bold">150</td></tr></tbody>
                            </table>
                            <div class="d-flex justify-content-between align-items-center small text-muted mb-2">
                                <span><i class="fas fa-magic text-primary me-1"></i>{{ app()->getLocale() === 'id' ? 'Prefill dari Harian' : 'Daily Prefilled' }}</span>
                                <span class="badge bg-light text-dark border">{{ app()->getLocale() === 'id' ? 'Opsi Parsial' : 'Partial Opt.' }}</span>
                            </div>
                            <div class="p-1.5 rounded bg-light border text-center small text-primary fw-semibold">
                                {{ app()->getLocale() === 'id' ? 'Kirim Laporan Cutting ➔' : 'Submit Cutting Report ➔' }}
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Step 2: Gramasi & Blister -->
                <div class="col-lg-4">
                    <div class="mock-flow-card h-100">
                        <div class="mock-flow-header bg-success text-white d-flex justify-content-between align-items-center">
                            <span><i class="fas fa-weight-hanging me-1"></i> {{ app()->getLocale() === 'id' ? 'Tahap 2: Gramasi & Kemasan' : 'Stage 2: Gramasi & Pack' }}</span>
                            <span class="badge bg-white text-success rounded-pill small">02</span>
                        </div>
                        <div class="p-3">
                            <div class="small text-secondary mb-2">{{ app()->getLocale() === 'id' ? 'Berat Gramasi & Kapasitas' : 'Grammage & Capacities' }}</div>
                            <div class="d-flex flex-column gap-1 mb-2">
                                <div class="d-flex justify-content-between align-items-center small p-1.5 rounded bg-light border">
                                    <span><i class="fas fa-box text-primary me-1"></i> {{ __('Blister Capacity') }}:</span>
                                    <strong class="text-dark">20 {{ __('pcs / blister') }}</strong>
                                </div>
                                <div class="d-flex justify-content-between align-items-center small p-1.5 rounded bg-light border">
                                    <span><i class="fas fa-gift text-success me-1"></i> {{ __('Sack (Karung) Capacity') }}:</span>
                                    <strong class="text-dark">80 {{ __('pcs / sack') }}</strong>
                                </div>
                            </div>
                            <div class="small text-muted mb-2">
                                <i class="fas fa-info-circle text-primary me-1"></i>{{ __('Capacity of finished goods that can be loaded into blister pack or sack pack.') }}
                            </div>
                            <div class="p-1.5 rounded bg-light border text-center small text-success fw-semibold">
                                {{ app()->getLocale() === 'id' ? 'Kirim Gramasi & Kemasan ➔' : 'Submit Gramasi & Pack ➔' }}
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Step 3: Labels & Complete -->
                <div class="col-lg-4">
                    <div class="mock-flow-card h-100">
                        <div class="mock-flow-header bg-info text-white d-flex justify-content-between align-items-center">
                            <span><i class="fas fa-barcode me-1"></i> {{ app()->getLocale() === 'id' ? 'Tahap 3: Label & Selesai' : 'Stage 3: Labels & Complete' }}</span>
                            <span class="badge bg-white text-info rounded-pill small">03</span>
                        </div>
                        <div class="p-3">
                            <div class="small text-secondary mb-2">{{ app()->getLocale() === 'id' ? 'Cetak Label & Penyelesaian' : 'Print Labels & Finalize' }}</div>
                            <div class="d-grid gap-1 mb-2">
                                <button class="btn btn-sm btn-outline-primary text-start small py-1" disabled><i class="fas fa-print me-1"></i> {{ __('Print Store Labels') }}</button>
                                <button class="btn btn-sm btn-outline-success text-start small py-1" disabled><i class="fas fa-print me-1"></i> {{ __('Print Online Labels') }}</button>
                            </div>
                            <div class="d-flex align-items-center justify-content-between small text-success mb-2">
                                <span><i class="fas fa-check-double me-1"></i>{{ app()->getLocale() === 'id' ? 'Label Terpasang' : 'Labels Affixed' }}</span>
                                <span class="badge bg-success text-white">{{ app()->getLocale() === 'id' ? 'Siap Selesai' : 'Ready' }}</span>
                            </div>
                            <div class="p-1.5 rounded bg-success text-white border text-center small fw-semibold">
                                <i class="fas fa-check-circle me-1"></i>{{ app()->getLocale() === 'id' ? 'Selesaikan Work Order ✓' : 'Complete Work Order ✓' }}
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="p-4 p-md-5">
            <div class="d-flex align-items-center gap-3 mb-3">
                <div class="guide-step-badge bg-primary text-white">1</div>
                <h3 class="fw-bold mb-0 text-dark">
                    {{ app()->getLocale() === 'id' ? 'Alur 3 Tahap Kerja Subkontraktor' : '3-Step Subcontractor Workflow Lifecycle' }}
                </h3>
            </div>
            <p class="text-secondary">
                {{ app()->getLocale() === 'id' 
                    ? 'Setiap Work Order diproses melalui tiga tahapan terstruktur yang berkesinambungan:' 
                    : 'Every Work Order progresses through three structured, sequential stages:' }}
            </p>

            <div class="row g-4 mt-2">
                <div class="col-md-4">
                    <div class="p-3 rounded-3 bg-light border h-100">
                        <div class="fw-bold text-primary mb-2">
                            <i class="fas fa-cut me-1"></i> {{ app()->getLocale() === 'id' ? 'Tahap 1: Laporan Cutting' : 'Stage 1: Cutting Report' }}
                        </div>
                        <p class="small text-secondary mb-0">
                            {{ app()->getLocale() === 'id'
                                ? 'Vendor mencatat hasil potong nyata per ukuran pakaian. Jika hasil potong dilakukan bertahap, vendor dapat mengaktifkan opsi "Laporan Sebagian" (Partial report).'
                                : 'Record actual cut quantities per garment size. If cutting is carried out in batches, toggle "Partial report" to submit in stages.' }}
                        </p>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="p-3 rounded-3 bg-light border h-100">
                        <div class="fw-bold text-success mb-2">
                            <i class="fas fa-weight-hanging me-1"></i> {{ app()->getLocale() === 'id' ? 'Tahap 2: Gramasi & Blister' : 'Stage 2: Gramasi & Blister' }}
                        </div>
                        <p class="small text-secondary mb-0">
                            {{ app()->getLocale() === 'id'
                                ? 'Setelah cutting disetujui, vendor mengisi berat gramasi (g) per potong baju untuk tiap ukuran, serta menentukan kapasitas isi per kemasan blister (20 pcs/blister) dan karung (80 pcs/sack).'
                                : 'Following cutting approval, enter the garment grammage weight (g) per size and specify the capacity per blister package (20 pcs/blister) and sack (80 pcs/sack).' }}
                        </p>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="p-3 rounded-3 bg-light border h-100">
                        <div class="fw-bold text-info mb-2">
                            <i class="fas fa-tags me-1"></i> {{ app()->getLocale() === 'id' ? 'Tahap 3: Cetak Label & Selesai' : 'Stage 3: Labels & Completion' }}
                        </div>
                        <p class="small text-secondary mb-0">
                            {{ app()->getLocale() === 'id'
                                ? 'Setelah gramasi & blister disetujui, tombol cetak Label Toko dan Label Online terbuka. Tempelkan label kemasan lalu klik "Selesaikan Order".'
                                : 'Once gramasi is approved, Store and Online label printing unlocks. Affix packaging labels and mark the order complete.' }}
                        </p>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- SECTION 2: DAILY PRODUCTION TRACKING -->
    <div id="sec-daily" class="guide-card mb-5">
        <div class="guide-visualizer-container" id="visualizer-daily">
            <div class="card border-0 shadow-sm rounded-3 overflow-hidden">
                <div class="card-header bg-white border-bottom py-2.5 px-3 d-flex justify-content-between align-items-center flex-wrap gap-2">
                    <div class="d-flex align-items-center gap-2">
                        <i class="fas fa-calendar-alt text-warning fs-5"></i>
                        <span class="fw-bold text-dark small">{{ app()->getLocale() === 'id' ? 'Papan Produksi Harian — 23 Sep 2026' : 'Daily Production Board — 23 Sep 2026' }}</span>
                        <span class="badge bg-success-subtle text-success border border-success border-opacity-10 small">Active</span>
                    </div>
                    <div class="d-flex gap-2">
                        <span class="badge bg-light text-secondary border small"><i class="fas fa-filter me-1"></i>PO: CMT-2603-01</span>
                    </div>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover mock-table align-middle mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th class="ps-3">{{ __('Stage') }}</th>
                                    <th class="text-center">S</th>
                                    <th class="text-center">M</th>
                                    <th class="text-center">L</th>
                                    <th class="text-center">XL</th>
                                    <th class="text-end pe-3">{{ __('Total') }}</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr>
                                    <td class="ps-3 fw-semibold"><i class="fas fa-cut text-primary me-1"></i> Cutting</td>
                                    <td class="text-center">50</td>
                                    <td class="text-center">100</td>
                                    <td class="text-center">120</td>
                                    <td class="text-center">80</td>
                                    <td class="text-end pe-3 fw-bold text-primary">350 pcs</td>
                                </tr>
                                <tr>
                                    <td class="ps-3 fw-semibold"><i class="fas fa-tshirt text-success me-1"></i> Sewing</td>
                                    <td class="text-center">40</td>
                                    <td class="text-center">80</td>
                                    <td class="text-center">100</td>
                                    <td class="text-center">70</td>
                                    <td class="text-end pe-3 fw-bold text-success">290 pcs</td>
                                </tr>
                                <tr>
                                    <td class="ps-3 fw-semibold"><i class="fas fa-magic text-warning me-1"></i> Finishing</td>
                                    <td class="text-center">30</td>
                                    <td class="text-center">70</td>
                                    <td class="text-center">70</td>
                                    <td class="text-center">30</td>
                                    <td class="text-end pe-3 fw-bold text-warning">200 pcs</td>
                                </tr>
                                <tr>
                                    <td class="ps-3 fw-semibold"><i class="fas fa-check-circle text-info me-1"></i> QC</td>
                                    <td class="text-center">30</td>
                                    <td class="text-center">70</td>
                                    <td class="text-center">70</td>
                                    <td class="text-center">30</td>
                                    <td class="text-end pe-3 fw-bold text-info">200 pcs</td>
                                </tr>
                                <tr>
                                    <td class="ps-3 fw-semibold"><i class="fas fa-box text-secondary me-1"></i> Packing</td>
                                    <td class="text-center">20</td>
                                    <td class="text-center">50</td>
                                    <td class="text-center">50</td>
                                    <td class="text-center">20</td>
                                    <td class="text-end pe-3 fw-bold text-secondary">140 pcs</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
        <div class="p-4 p-md-5">
            <div class="d-flex align-items-center gap-3 mb-3">
                <div class="guide-step-badge bg-warning text-dark">2</div>
                <h3 class="fw-bold mb-0 text-dark">
                    {{ app()->getLocale() === 'id' ? 'Papan Pencatatan Produksi Harian' : 'Daily Production Tracking Board' }}
                </h3>
            </div>
            <p class="text-secondary">
                {{ app()->getLocale() === 'id'
                    ? 'Papan Produksi Harian memungkinkan pabrik/subcon mencatat hasil output harian per tahapan kerja secara terpisah tanpa harus langsung menyelesaikan pesanan.'
                    : 'The Daily Production Board allows factories to log daily output across production stages without premature work order closure.' }}
            </p>

            <div class="row g-4 mt-2">
                <div class="col-md-6">
                    <h6 class="fw-bold text-dark"><i class="fas fa-tasks text-primary me-2"></i>{{ app()->getLocale() === 'id' ? 'Tahapan Kerja yang Dicatat' : 'Tracked Production Stages' }}</h6>
                    <ul class="text-secondary small">
                        <li><strong>Cutting:</strong> {{ app()->getLocale() === 'id' ? 'Hasil potong per lay/rasio & ukuran.' : 'Output per lay/ratio and size.' }}</li>
                        <li><strong>Sewing:</strong> {{ app()->getLocale() === 'id' ? 'Hasil jahit harian per ukuran.' : 'Daily sewing output per size.' }}</li>
                        <li><strong>Finishing:</strong> {{ app()->getLocale() === 'id' ? 'Hasil setrika & proses finishing.' : 'Ironing and finishing output.' }}</li>
                        <li><strong>QC:</strong> {{ app()->getLocale() === 'id' ? 'Pemeriksaan kualitas akhir.' : 'End-of-line quality control inspection.' }}</li>
                        <li><strong>Packing:</strong> {{ app()->getLocale() === 'id' ? 'Jumlah barang yang telah dikemas ke polybag/blister.' : 'Garments packed into polybags/blisters.' }}</li>
                    </ul>
                </div>
                <div class="col-md-6">
                    <h6 class="fw-bold text-dark"><i class="fas fa-sticky-note text-warning me-2"></i>{{ app()->getLocale() === 'id' ? 'Catatan Harian & Status' : 'Daily Remarks & Status' }}</h6>
                    <ul class="text-secondary small">
                        <li><strong>{{ app()->getLocale() === 'id' ? 'Catatan per Style:' : 'Per-Style Remarks:' }}</strong> {{ app()->getLocale() === 'id' ? 'Tulis kendala atau progres spesifik per model pakaian pada kolom "Catatan Harian (Opsional)".' : 'Log specific notes or progress per garment style.' }}</li>
                        <li><strong>{{ app()->getLocale() === 'id' ? 'Catatan Umum Hari Ini:' : 'Overall Day Remarks:' }}</strong> {{ app()->getLocale() === 'id' ? 'Tuliskan catatan umum vendor untuk hari tersebut pada kartu atas.' : 'Record overarching vendor notes for the selected date.' }}</li>
                        <li><strong>{{ app()->getLocale() === 'id' ? 'Status Style (Active/Closed):' : 'Style Status (Active/Closed):' }}</strong> {{ app()->getLocale() === 'id' ? 'Tutup style jika seluruh proses selesai agar papan kerja tetap rapi, dan buka kembali sewaktu-waktu jika diperlukan.' : 'Close completed styles to keep the board tidy, and reopen anytime.' }}</li>
                    </ul>
                </div>
            </div>
        </div>
    </div>

    <!-- SECTION 3: CUTTING PREFILL & OVERRIDE -->
    <div id="sec-cutting" class="guide-card mb-5">
        <div class="guide-visualizer-container" id="visualizer-cutting">
            <div class="row g-3">
                <div class="col-md-6">
                    <div class="p-3 bg-white rounded-3 border shadow-sm h-100">
                        <div class="d-flex align-items-center gap-2 mb-2">
                            <span class="badge bg-primary rounded-pill small">A</span>
                            <strong class="text-primary small">{{ app()->getLocale() === 'id' ? 'Aliran Otomatis (Prefill Placeholder)' : 'Automated Seeping (Prefill)' }}</strong>
                        </div>
                        <p class="small text-secondary mb-3">
                            {{ app()->getLocale() === 'id'
                                ? 'Total cutting dari Produksi Harian (misal: 250 pcs) otomatis mengalir ke formulir Laporan Cutting sebagai acuan awal.'
                                : 'Daily cutting totals (e.g. 250 pcs) automatically seep into the cutting form as initial suggestion.' }}
                        </p>
                        <div class="p-2.5 rounded-3 bg-light border">
                            <div class="small text-muted mb-1">{{ __('Size M') }} — {{ __('Qty Cut') }}:</div>
                            <div class="input-group input-group-sm mb-1">
                                <input type="text" class="form-control fw-bold border-primary text-primary" value="250" readonly>
                                <span class="input-group-text bg-white small text-muted">pcs</span>
                            </div>
                            <span class="badge bg-primary-subtle text-primary border border-primary border-opacity-10 small">
                                <i class="fas fa-magic me-1"></i> {{ app()->getLocale() === 'id' ? 'Dari Produksi Harian: 250' : 'From Daily: 250' }}
                            </span>
                        </div>
                    </div>
                </div>

                <div class="col-md-6">
                    <div class="p-3 bg-white rounded-3 border shadow-sm h-100">
                        <div class="d-flex align-items-center gap-2 mb-2">
                            <span class="badge bg-warning text-dark rounded-pill small">B</span>
                            <strong class="text-dark small">{{ app()->getLocale() === 'id' ? 'Kebebasan Mengubah (Override Unlocked)' : 'Freedom to Override (Never Locked)' }}</strong>
                        </div>
                        <p class="small text-secondary mb-3">
                            {{ app()->getLocale() === 'id'
                                ? 'Jika ada koreksi atau perbedaan fisik meja potong (misal: 265 pcs), vendor dapat langsung mengetik angka yang benar.'
                                : 'If adjustments or physical discrepancies occur (e.g. 265 pcs), vendors can freely type the corrected value.' }}
                        </p>
                        <div class="p-2.5 rounded-3 bg-light border">
                            <div class="small text-muted mb-1">{{ __('Size M') }} — {{ __('Qty Cut') }}:</div>
                            <div class="input-group input-group-sm mb-1">
                                <input type="text" class="form-control fw-bold border-success text-success" value="265" readonly>
                                <span class="input-group-text bg-white small text-muted">pcs</span>
                            </div>
                            <span class="badge bg-warning-subtle text-dark border border-warning border-opacity-25 small">
                                <i class="fas fa-edit me-1"></i> {{ app()->getLocale() === 'id' ? 'Angka final yang disubmit adalah angka Anda' : 'Your typed value is the final submitted count' }}
                            </span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="p-4 p-md-5">
            <div class="d-flex align-items-center gap-3 mb-3">
                <div class="guide-step-badge bg-danger text-white">3</div>
                <h3 class="fw-bold mb-0 text-dark">
                    {{ app()->getLocale() === 'id' ? 'Sinkronisasi Otomatis Cutting & Kebebasan Edit (Override)' : 'Automated Cutting Prefill & Vendor Override' }}
                </h3>
            </div>
            <p class="text-secondary">
                {{ app()->getLocale() === 'id'
                    ? 'Data hasil cutting dari Papan Produksi Harian secara pintar mengalir ke formulir Laporan Cutting, namun vendor tetap memegang kendali penuh:'
                    : 'Cutting quantities logged on the Daily Production board automatically flow into the Work Order Cutting Report, giving vendors smart convenience with complete control:' }}
            </p>

            <div class="alert alert-primary bg-primary-subtle border-primary border-opacity-10 rounded-3 mb-4">
                <div class="d-flex gap-3 align-items-start">
                    <i class="fas fa-lightbulb text-primary fs-4 mt-1"></i>
                    <div>
                        <strong class="text-dark">{{ app()->getLocale() === 'id' ? 'Fitur Pengisian Otomatis (Prefill Placeholder)' : 'Automated Prefill Feature' }}</strong>
                        <p class="small text-secondary mb-0 mt-1">
                            {{ app()->getLocale() === 'id'
                                ? 'Saat Anda membuka formulir Laporan Cutting, total hasil potong yang telah dicatat di Produksi Harian akan muncul di bawah kolom input dengan tanda "Dari Produksi Harian: X". Nilai ini otomatis menjadi acuan awal.'
                                : 'When opening the Cutting Report form, total cut pieces recorded in Daily Production appear under each input as "From Daily: X", pre-filling the field as an initial suggestion.' }}
                        </p>
                    </div>
                </div>
            </div>

            <div class="alert alert-warning bg-warning-subtle border-warning border-opacity-25 rounded-3 mb-0">
                <div class="d-flex gap-3 align-items-start">
                    <i class="fas fa-pen-nib text-warning fs-4 mt-1"></i>
                    <div>
                        <strong class="text-dark">{{ app()->getLocale() === 'id' ? 'Kebebasan Mengubah Angka (Override Unlocked)' : 'Full Freedom to Override (Never Locked)' }}</strong>
                        <p class="small text-secondary mb-0 mt-1">
                            {{ app()->getLocale() === 'id'
                                ? 'Kolom input jumlah potong TIDAK terkunci. Jika terdapat revisi atau perbedaan fisik, vendor dapat langsung mengetik angka yang benar, atau mengunggah file template Excel. Angka yang disubmit adalah angka final yang Anda ketik.'
                                : 'Input fields are NEVER locked to the suggestion. If adjustments or physical discrepancies exist, vendors can directly edit numbers or upload an updated Excel sheet. The submitted value is always what you type.' }}
                        </p>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- SECTION 4: MATERIAL RECON & DELIVERY NOTES -->
    <div id="sec-recon" class="guide-card mb-5">
        <div class="guide-visualizer-container" id="visualizer-recon">
            <div class="p-3 bg-white rounded-3 border shadow-sm mb-3">
                <div class="d-flex align-items-center gap-2 mb-3 border-bottom pb-2">
                    <span class="badge bg-light text-secondary border">Tab 1: {{ __('Tahap Produksi') }}</span>
                    <span class="badge bg-primary text-white"><i class="fas fa-boxes me-1"></i> Tab 2: {{ __('Rekonsiliasi Bahan & Surat Jalan') }}</span>
                    <span class="badge bg-light text-secondary border">Tab 3: {{ __('Produksi Harian') }}</span>
                </div>
                <div class="row g-3">
                    <div class="col-md-7">
                        <div class="p-2.5 rounded bg-light border">
                            <span class="fw-bold small text-dark d-block mb-1"><i class="fas fa-scroll text-primary me-1"></i> {{ app()->getLocale() === 'id' ? 'Input Sisa Bahan (Kain)' : 'Fabric Remnant Entries' }}</span>
                            <div class="row g-2 small text-secondary">
                                <div class="col-4">Short Roll: <strong>12.50 m</strong></div>
                                <div class="col-4">Sisa Utuh: <strong>8.20 m</strong></div>
                                <div class="col-4">Kepala Kain: <strong>3.10 m</strong></div>
                            </div>
                            <div class="mt-2 pt-1 border-top d-flex justify-content-between align-items-center small">
                                <span class="text-primary fw-bold">Total Retur: 23.80 m</span>
                                <button class="btn btn-xs btn-outline-primary py-0 px-2" disabled>{{ __('Save Reconciliation') }}</button>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-5">
                        <div class="p-2.5 rounded bg-light border h-100 d-flex flex-column justify-content-between">
                            <div>
                                <span class="fw-bold small text-dark d-block mb-1"><i class="fas fa-paperclip text-success me-1"></i> {{ app()->getLocale() === 'id' ? 'Lampiran Surat Jalan' : 'Delivery Note Slips' }}</span>
                                <span class="badge bg-success-subtle text-success border border-success border-opacity-10 small">
                                    <i class="fas fa-file-pdf me-1"></i> SJ-Kain-Vendor-01.pdf
                                </span>
                            </div>
                            <div class="small text-muted mt-2">
                                <i class="fas fa-check-circle text-success me-1"></i>{{ app()->getLocale() === 'id' ? 'Simpan kapan saja tanpa tunggu approval' : 'Save anytime independently' }}
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="p-4 p-md-5">
            <div class="d-flex align-items-center gap-3 mb-3">
                <div class="guide-step-badge bg-success text-white">4</div>
                <h3 class="fw-bold mb-0 text-dark">
                    {{ app()->getLocale() === 'id' ? 'Rekonsiliasi Material & Surat Jalan (Kapan Saja)' : 'Material Reconciliation & Delivery Notes (Anytime)' }}
                </h3>
            </div>
            <p class="text-secondary">
                {{ app()->getLocale() === 'id'
                    ? 'Untuk mempermudah administrasi tanpa menghambat jalannya produksi pakaian, rekonsiliasi bahan dan surat jalan kini terpisah di Tab ke-2 dan dapat disimpan kapan saja:'
                    : 'To simplify administration without stalling garment production, material reconciliation and delivery notes are arranged in Tab 2 and can be saved independently at any time:' }}
            </p>

            <div class="row g-4 mt-2">
                <div class="col-md-6">
                    <div class="border rounded-3 p-3 bg-light h-100">
                        <h6 class="fw-bold text-dark"><i class="fas fa-scroll text-primary me-2"></i>{{ app()->getLocale() === 'id' ? 'Kategori Rekonsiliasi Kain' : 'Fabric Waste Categorization' }}</h6>
                        <ul class="text-secondary small mb-0">
                            <li><strong>Short Roll:</strong> {{ app()->getLocale() === 'id' ? 'Gulungan pendek atau cacat pabrik kain.' : 'Short fabric rolls or mill-damaged fabric.' }}</li>
                            <li><strong>Sisa Kain (Utuh):</strong> {{ app()->getLocale() === 'id' ? 'Kain sisa yang masih dapat digunakan untuk item lain.' : 'Remaining usable full fabric remnants.' }}</li>
                            <li><strong>Kepala Kain:</strong> {{ app()->getLocale() === 'id' ? 'Potongan ujung kain atau perca tidak beraturan.' : 'End pieces, headers, and irregular cuttings.' }}</li>
                            <li><strong>Retur Kain:</strong> {{ app()->getLocale() === 'id' ? 'Total otomatis (Short Roll + Sisa Kain + Kepala Kain).' : 'Auto-calculated sum (Short Roll + Sisa + Kepala).' }}</li>
                        </ul>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="border rounded-3 p-3 bg-light h-100">
                        <h6 class="fw-bold text-dark"><i class="fas fa-file-upload text-success me-2"></i>{{ app()->getLocale() === 'id' ? 'Surat Jalan (Lampiran Pengiriman)' : 'Delivery Notes (Surat Jalan)' }}</h6>
                        <ul class="text-secondary small mb-0">
                            <li>{{ app()->getLocale() === 'id' ? 'Unggah berkas foto atau PDF surat jalan pengiriman kain/sisa bahan.' : 'Upload photo or PDF documents of material delivery slips.' }}</li>
                            <li>{{ app()->getLocale() === 'id' ? 'Dapat diunggah atau dihapus kapan saja selama masa kerja order berlangsung.' : 'Upload or delete attachments anytime while the work order is active.' }}</li>
                            <li>{{ app()->getLocale() === 'id' ? 'Terhubung langsung dengan tim QC dan Head Office untuk verifikasi administrasi.' : 'Directly synced with QC and Head Office for verification.' }}</li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- SECTION 5: INTERFACE GUIDANCE & I IKONS -->
    <div id="sec-guidance" class="guide-card mb-5">
        <div class="p-4 p-md-5">
            <div class="d-flex align-items-center gap-3 mb-3">
                <div class="guide-step-badge bg-info text-white">5</div>
                <h3 class="fw-bold mb-0 text-dark">
                    {{ app()->getLocale() === 'id' ? 'Bantuan Interaktif & Ikon Informasi (i)' : 'Interactive Guidance & Information (i) Icons' }}
                </h3>
            </div>
            <p class="text-secondary">
                {{ app()->getLocale() === 'id'
                    ? 'Di seluruh halaman Portal Subkontraktor, Anda akan menemukan ikon bantuan (i). Cukup arahkan kursor (hover) atau klik untuk membaca petunjuk instan mengenai kolom tersebut:'
                    : 'Throughout the Subcontractor Portal interface, helpful (i) info icons provide instant context. Hover or click to read explanations on each section:' }}
            </p>

            <div class="table-responsive mt-3">
                <table class="table table-bordered align-middle">
                    <thead class="table-light text-secondary small">
                        <tr>
                            <th style="width: 200px;">{{ app()->getLocale() === 'id' ? 'Bagian / Fitur' : 'Section / Feature' }}</th>
                            <th>{{ app()->getLocale() === 'id' ? 'Petunjuk yang Ditampilkan' : 'Guidance Provided' }}</th>
                        </tr>
                    </thead>
                    <tbody class="small text-secondary">
                        <tr>
                            <td class="fw-bold text-dark"><i class="fas fa-cut text-primary me-2"></i>{{ app()->getLocale() === 'id' ? 'Formulir Cutting' : 'Cutting Form' }}</td>
                            <td>{{ __('Cutting Stage Guidance') }}</td>
                        </tr>
                        <tr>
                            <td class="fw-bold text-dark"><i class="fas fa-weight-hanging text-success me-2"></i>{{ app()->getLocale() === 'id' ? 'Gramasi & Blister' : 'Gramasi & Blister' }}</td>
                            <td>{{ __('Gramasi & Blister Guidance') }}</td>
                        </tr>
                        <tr>
                            <td class="fw-bold text-dark"><i class="fas fa-box text-primary me-2"></i>{{ __('Packaging Capacity') }}</td>
                            <td>{{ __('Capacity of finished goods that can be loaded into blister pack or sack pack.') }}</td>
                        </tr>
                        <tr>
                            <td class="fw-bold text-dark"><i class="fas fa-boxes text-info me-2"></i>{{ app()->getLocale() === 'id' ? 'Rekonsiliasi Material' : 'Material Recon' }}</td>
                            <td>{{ __('Material Recon Independent Guidance') }}</td>
                        </tr>
                        <tr>
                            <td class="fw-bold text-dark"><i class="fas fa-industry text-warning me-2"></i>{{ app()->getLocale() === 'id' ? 'Produksi Harian' : 'Daily Production' }}</td>
                            <td>{{ __('Daily Production Sync Guidance') }}</td>
                        </tr>
                        <tr>
                            <td class="fw-bold text-dark"><i class="fas fa-language text-secondary me-2"></i>{{ app()->getLocale() === 'id' ? 'Penggantian Bahasa' : 'Language Switching' }}</td>
                            <td>{{ app()->getLocale() === 'id' ? 'Klik tombol 🇮🇩 ID / 🇬🇧 EN di pojok kanan atas navbar untuk beralih bahasa kapan saja.' : 'Click the 🇮🇩 ID / 🇬🇧 EN button in the top navigation bar to toggle between Indonesian and English anytime.' }}</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
@endsection
