@extends('layouts.app')

@section('title', __('Dashboard'))

@section('content')
<!-- Google Fonts -->
<link href="https://fonts.googleapis.com/css2?family=Outfit:wght@400;500;600;700&family=Inter:wght@400;500;600&display=swap" rel="stylesheet">

<style>
    .premium-container {
        font-family: 'Inter', sans-serif;
    }
    .premium-header {
        background: linear-gradient(135deg, #1e3c72 0%, #2a5298 100%);
        border-radius: 20px;
        padding: 30px;
        color: #ffffff;
        box-shadow: 0 10px 30px rgba(30, 60, 114, 0.15);
        position: relative;
        overflow: hidden;
    }
    .premium-header::after {
        content: '';
        position: absolute;
        right: -50px;
        bottom: -50px;
        width: 200px;
        height: 200px;
        border-radius: 50%;
        background: rgba(255, 255, 255, 0.05);
    }
    .premium-header-title {
        font-family: 'Outfit', sans-serif;
        font-weight: 700;
        font-size: 2rem;
        letter-spacing: -0.02em;
    }
    .premium-title {
        font-family: 'Outfit', sans-serif;
        font-weight: 700;
        color: #1e293b;
        letter-spacing: -0.02em;
    }
    .premium-card {
        border-radius: 16px;
        border: 1px solid rgba(0, 0, 0, 0.06);
        box-shadow: 0 8px 24px rgba(0, 0, 0, 0.03);
        background: #ffffff;
        overflow: hidden;
        transition: transform 0.25s, box-shadow 0.25s;
    }
    .premium-card:hover {
        transform: translateY(-3px);
        box-shadow: 0 12px 30px rgba(0, 0, 0, 0.06);
    }
    .guide-spotlight-card {
        background: linear-gradient(135deg, #eff6ff 0%, #f8fafc 100%);
        border: 1px solid #bfdbfe;
        border-radius: 16px;
        box-shadow: 0 6px 20px rgba(37, 99, 235, 0.06);
    }
    .stat-number {
        font-family: 'Outfit', sans-serif;
        font-weight: 700;
        font-size: 2.25rem;
        line-height: 1;
        color: #1e293b;
    }
    .stat-label {
        font-family: 'Outfit', sans-serif;
        font-weight: 600;
        font-size: 0.8rem;
        text-transform: uppercase;
        letter-spacing: 0.05em;
        color: #64748b;
    }
    .icon-box {
        width: 52px;
        height: 52px;
        border-radius: 12px;
        display: flex;
        align-items: center;
        justify-content: center;
    }
    .icon-box-primary {
        background-color: #eff6ff;
        color: #2563eb;
    }
    .icon-box-warning {
        background-color: #fffbef;
        color: #d97706;
    }
    .icon-box-success {
        background-color: #f0fdf4;
        color: #16a34a;
    }
    .premium-table th {
        font-family: 'Outfit', sans-serif;
        font-weight: 600;
        text-transform: uppercase;
        font-size: 0.75rem;
        letter-spacing: 0.05em;
        color: #64748b;
        background-color: #f8fafc;
        border-bottom: 2px solid #edf2f7;
        padding: 14px 16px;
    }
    .premium-table td {
        padding: 14px 16px;
        vertical-align: middle;
        font-family: 'Inter', sans-serif;
        color: #334155;
        font-size: 0.875rem;
        border-bottom: 1px solid #f1f5f9;
    }
    .premium-table tr:hover td {
        background-color: #f8fafc;
    }
    .step-card {
        background-color: #ffffff;
        border-radius: 12px;
        padding: 18px 16px;
        border: 1px solid #e2e8f0;
        box-shadow: 0 2px 8px rgba(0,0,0,0.02);
        height: 100%;
        display: flex;
        flex-direction: column;
        transition: all 0.2s ease;
    }
    .step-card:hover {
        border-color: #cbd5e1;
        transform: translateY(-2px);
    }
    .step-badge-num {
        width: 28px;
        height: 28px;
        border-radius: 50%;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        font-family: 'Outfit', sans-serif;
        font-weight: 700;
        font-size: 0.85rem;
    }
    .code-pill {
        background-color: #f1f5f9;
        color: #475569;
        font-family: var(--bs-font-monospace);
        padding: 4px 8px;
        border-radius: 6px;
        font-size: 0.8rem;
        border: 1px solid #e2e8f0;
    }
</style>

<div class="premium-container">
    <!-- Header banner -->
    <div class="premium-header mb-4">
        <div class="d-flex flex-wrap justify-content-between align-items-center gap-3">
            <div>
                <h1 class="premium-header-title mb-1">
                    {{ __('Welcome back') }}, {{ auth()->user()->vendor?->name ?? __('Subcontractor Vendor') }}
                </h1>
                <p class="mb-0 text-white text-opacity-75">
                    <i class="fas fa-industry me-2"></i>{{ __('Portal Subkontraktor') }}
                </p>
            </div>
            <div class="d-flex flex-wrap gap-2">
                <a href="{{ route('subcon.vendor.guidebook') }}" class="btn btn-warning fw-semibold shadow-sm px-3">
                    <i class="fas fa-book-open me-2"></i>{{ __('Vendor Onboarding Guidebook') }}
                </a>
                <a href="{{ route('subcon.vendor.orders') }}" class="btn btn-light fw-semibold text-primary shadow-sm px-3">
                    <i class="fas fa-clipboard-list me-2"></i>{{ __('My Work Orders') }}
                </a>
                <a href="{{ route('subcon.vendor.daily-production.index') }}" class="btn btn-outline-light fw-semibold px-3">
                    <i class="fas fa-industry me-2"></i>{{ __('Daily Production') }}
                </a>
            </div>
        </div>
    </div>

    <!-- Guidebook Spotlight Banner -->
    <div class="guide-spotlight-card p-4 mb-4">
        <div class="row align-items-center g-3">
            <div class="col-lg-8">
                <div class="d-flex align-items-center gap-2 mb-2">
                    <span class="badge bg-primary text-white rounded-pill px-3 py-1 small fw-semibold">
                        <i class="fas fa-sparkles me-1"></i> {{ __('Panduan Interaktif') }}
                    </span>
                    <span class="badge bg-white text-primary border rounded-pill px-2 py-0.5 small">
                        🇮🇩 ID &amp; 🇬🇧 EN
                    </span>
                </div>
                <h4 class="fw-bold text-dark mb-1">
                    {{ app()->getLocale() === 'id' ? 'Buku Panduan Alur Kerja Mitra Subkontraktor' : 'Subcontractor Partner Interactive Guidebook' }}
                </h4>
                <p class="text-secondary small mb-3">
                    {{ app()->getLocale() === 'id'
                        ? 'Pelajari alur kerja 3 tahap Work Order, sinkronisasi otomatis Laporan Cutting dari Produksi Harian, serta rekonsiliasi bahan dan surat jalan kapan saja.'
                        : 'Explore the 3-step Work Order workflow, automated Daily Production sync to Cutting Reports, and independent material reconciliation.' }}
                </p>
                <div class="d-flex flex-wrap gap-2 align-items-center">
                    <a href="{{ route('subcon.vendor.guidebook') }}" class="btn btn-primary btn-sm px-3 fw-semibold shadow-sm">
                        <i class="fas fa-book-reader me-1"></i> {{ app()->getLocale() === 'id' ? 'Buka Buku Panduan Sekarang ➔' : 'Open Interactive Guidebook ➔' }}
                    </a>
                    <a href="{{ route('subcon.vendor.user-guide') }}" target="_blank" class="btn btn-outline-secondary btn-sm px-3 fw-semibold">
                        <i class="fas fa-file-pdf me-1"></i> {{ app()->getLocale() === 'id' ? 'Unduh PDF Panduan' : 'Download PDF Guide' }}
                    </a>
                </div>
            </div>
            <div class="col-lg-4 text-lg-end d-none d-lg-block">
                <div class="d-inline-flex gap-2 p-3 bg-white rounded-3 border shadow-sm text-start">
                    <i class="fas fa-chalkboard-teacher fa-2x text-primary mt-1"></i>
                    <div>
                        <div class="fw-bold small text-dark">{{ app()->getLocale() === 'id' ? 'Panduan Terstruktur' : 'Structured Guidance' }}</div>
                        <div class="text-muted small" style="font-size: 0.78rem;">
                            {{ app()->getLocale() === 'id' ? '3 Tahap • Produksi Harian • Rekonsiliasi' : '3 Stages • Daily Logs • Recon' }}
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Stats Cards -->
    <div class="row g-4 mb-4">
        <div class="col-12 col-md-4">
            <div class="card premium-card">
                <div class="card-body p-4 d-flex align-items-center justify-content-between">
                    <div>
                        <div class="stat-label">{{ __('Total Work Orders') }}</div>
                        <div class="stat-number mt-1">{{ number_format($stats['total_orders']) }}</div>
                    </div>
                    <div class="icon-box icon-box-primary">
                        <i class="fas fa-clipboard-list fa-lg"></i>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-12 col-md-4">
            <div class="card premium-card">
                <div class="card-body p-4 d-flex align-items-center justify-content-between">
                    <div>
                        <div class="stat-label">{{ __('Active Orders') }}</div>
                        <div class="stat-number mt-1">{{ number_format($stats['active_orders']) }}</div>
                    </div>
                    <div class="icon-box icon-box-warning">
                        <i class="fas fa-tasks fa-lg"></i>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-12 col-md-4">
            <div class="card premium-card">
                <div class="card-body p-4 d-flex align-items-center justify-content-between">
                    <div>
                        <div class="stat-label">{{ __('Completed Orders') }}</div>
                        <div class="stat-number mt-1">{{ number_format($stats['completed']) }}</div>
                    </div>
                    <div class="icon-box icon-box-success">
                        <i class="fas fa-check-double fa-lg"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Workflow Overview Cards (Updated 3-Stage + Daily + Recon) -->
    <div class="mb-5">
        <div class="d-flex justify-content-between align-items-center mb-3">
            <h5 class="premium-title mb-0">
                <i class="fas fa-project-diagram me-2 text-primary"></i>{{ app()->getLocale() === 'id' ? 'Alur Kerja Utama Subkontraktor' : 'Subcontractor Core Workflow' }}
            </h5>
            <a href="{{ route('subcon.vendor.guidebook') }}" class="btn btn-sm btn-link text-decoration-none fw-semibold">
                {{ app()->getLocale() === 'id' ? 'Lihat Panduan Bergambar ➔' : 'View Illustrated Guidebook ➔' }}
            </a>
        </div>
        <div class="row g-3">
            <div class="col-12 col-sm-6 col-lg-4">
                <div class="step-card">
                    <div class="d-flex align-items-center justify-content-between mb-2">
                        <span class="step-badge-num bg-primary text-white">1</span>
                        <i class="fas fa-cut text-primary"></i>
                    </div>
                    <div class="fw-bold small text-dark mb-1">{{ app()->getLocale() === 'id' ? 'Tahap 1: Laporan Cutting' : 'Stage 1: Cutting Report' }}</div>
                    <p class="text-secondary small mb-0" style="font-size: 0.8rem;">
                        {{ app()->getLocale() === 'id' ? 'Catat hasil potong nyata per ukuran. Mengalir otomatis dari Produksi Harian dan bebas diubah jika ada revisi.' : 'Log actual cut quantities. Daily production totals auto-seep as suggestions; freely override.' }}
                    </p>
                </div>
            </div>

            <div class="col-12 col-sm-6 col-lg-4">
                <div class="step-card">
                    <div class="d-flex align-items-center justify-content-between mb-2">
                        <span class="step-badge-num bg-success text-white">2</span>
                        <i class="fas fa-weight-hanging text-success"></i>
                    </div>
                    <div class="fw-bold small text-dark mb-1">{{ app()->getLocale() === 'id' ? 'Tahap 2: Gramasi & Kemasan' : 'Stage 2: Gramasi & Pack' }}</div>
                    <p class="text-secondary small mb-0" style="font-size: 0.8rem;">
                        {{ app()->getLocale() === 'id' ? 'Isi gramasi per ukuran pakaian, serta masukkan kapasitas blister (20 pcs) dan kapasitas karung (80 pcs).' : 'Enter grammage per size, plus blister capacity (20 pcs) & sack capacity (80 pcs).' }}
                    </p>
                </div>
            </div>

            <div class="col-12 col-sm-6 col-lg-4">
                <div class="step-card">
                    <div class="d-flex align-items-center justify-content-between mb-2">
                        <span class="step-badge-num bg-info text-white">3</span>
                        <i class="fas fa-barcode text-info"></i>
                    </div>
                    <div class="fw-bold small text-dark mb-1">{{ app()->getLocale() === 'id' ? 'Tahap 3: Cetak Label & Selesai' : 'Stage 3: Labels & Complete' }}</div>
                    <p class="text-secondary small mb-0" style="font-size: 0.8rem;">
                        {{ app()->getLocale() === 'id' ? 'Cetak Label Toko dan Label Online setelah disetujui, tempelkan pada kemasan dan selesaikan order.' : 'Print Store & Online labels once approved, affix to packaging, and mark order completed.' }}
                    </p>
                </div>
            </div>

            <div class="col-12 col-sm-6 col-lg-6">
                <div class="step-card bg-light">
                    <div class="d-flex align-items-center justify-content-between mb-2">
                        <span class="badge bg-warning text-dark">{{ __('Daily Production') }}</span>
                        <i class="fas fa-industry text-warning"></i>
                    </div>
                    <div class="fw-bold small text-dark mb-1">{{ app()->getLocale() === 'id' ? 'Papan Pencatatan Produksi Harian' : 'Daily Production Tracking Board' }}</div>
                    <p class="text-secondary small mb-2" style="font-size: 0.8rem;">
                        {{ app()->getLocale() === 'id' ? 'Catat pergerakan harian Cutting, Sewing, Finishing, QC, dan Packing secara bertahap tanpa harus menutup pesanan.' : 'Log day-to-day progress across cutting, sewing, finishing, QC, and packing without premature closure.' }}
                    </p>
                    <a href="{{ route('subcon.vendor.daily-production.index') }}" class="btn btn-sm btn-outline-warning text-dark fw-semibold mt-auto align-self-start" style="font-size: 0.78rem;">
                        <i class="fas fa-external-link-alt me-1"></i> {{ __('Open Daily Production Board') }}
                    </a>
                </div>
            </div>

            <div class="col-12 col-sm-6 col-lg-6">
                <div class="step-card bg-light">
                    <div class="d-flex align-items-center justify-content-between mb-2">
                        <span class="badge bg-success text-white">{{ __('Material Recon') }}</span>
                        <i class="fas fa-boxes text-success"></i>
                    </div>
                    <div class="fw-bold small text-dark mb-1">{{ app()->getLocale() === 'id' ? 'Rekonsiliasi Bahan & Surat Jalan Kapan Saja' : 'Material Recon & Delivery Notes (Anytime)' }}</div>
                    <p class="text-secondary small mb-2" style="font-size: 0.8rem;">
                        {{ app()->getLocale() === 'id' ? 'Tersedia di Tab ke-2 halaman order. Simpan sisa kain dan lampirkan file surat jalan kapan saja secara mandiri.' : 'Available in Tab 2 of work orders. Save remnants and attach delivery note documents independently at any time.' }}
                    </p>
                    <a href="{{ route('subcon.vendor.orders') }}" class="btn btn-sm btn-outline-success fw-semibold mt-auto align-self-start" style="font-size: 0.78rem;">
                        <i class="fas fa-arrow-right me-1"></i> {{ __('Daftar Order Saya') }}
                    </a>
                </div>
            </div>
        </div>
    </div>

    <!-- Recent Work Orders Table -->
    <div class="card premium-card mb-4">
        <div class="card-header bg-white border-bottom py-3 d-flex justify-content-between align-items-center">
            <h5 class="premium-title mb-0" style="font-size: 1.1rem;">
                <i class="fas fa-history me-2 text-muted"></i>{{ __('Recent Work Orders') }}
            </h5>
            <a href="{{ route('subcon.vendor.orders') }}" class="btn btn-sm btn-outline-primary rounded-pill px-3">
                {{ __('View All Orders') }}
            </a>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table premium-table mb-0">
                    <thead>
                        <tr>
                            <th>{{ __('Order #') }}</th>
                            <th>{{ __('Production Group') }}</th>
                            <th>{{ __('Style Name') }}</th>
                            <th>{{ __('Due Date') }}</th>
                            <th>{{ __('Workflow Stage') }}</th>
                            <th>{{ __('Status') }}</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($recentOrders as $order)
                        <tr>
                            <td><span class="code-pill">{{ $order->order_number }}</span></td>
                            <td>
                                @if($order->production_group)
                                    <span class="code-pill text-primary" style="background-color: #eff6ff; border-color: #dbeafe;">{{ $order->production_group }}</span>
                                @else
                                    <span class="text-muted small">—</span>
                                @endif
                            </td>
                            <td class="fw-semibold">{{ $order->title }}</td>
                            <td>{{ $order->due_date?->format('d M Y') ?? '—' }}</td>
                            <td>@include('subcon.partials.stage-badge', ['order' => $order])</td>
                            <td>
                                @php 
                                    $badge = match($order->status) { 
                                        'pending' => 'secondary', 
                                        'in_progress' => 'warning text-dark', 
                                        'completed' => 'success', 
                                        'cancelled' => 'danger', 
                                        default => 'secondary' 
                                    }; 
                                @endphp
                                <span class="badge bg-{{ $badge }} rounded-pill px-2.5 py-1 small">{{ ucfirst(str_replace('_', ' ', $order->status)) }}</span>
                            </td>
                            <td class="text-end">
                                <a href="{{ route('subcon.vendor.orders.view', $order->id) }}" class="btn btn-sm btn-outline-primary rounded-pill px-3">
                                    <i class="fas fa-eye me-1"></i> {{ __('View') }}
                                </a>
                            </td>
                        </tr>
                        @empty
                        <tr>
                            <td colspan="7" class="text-center text-muted py-5">
                                <i class="fas fa-folder-open fa-2x mb-3 text-muted opacity-50"></i>
                                <p class="mb-0">{{ __('No work orders assigned yet.') }}</p>
                            </td>
                        </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
@endsection
