@extends('layouts.app')

@section('title', 'Daily Production')

@push('styles')
    @include('subcon.partials.daily-production-styles')
@endpush

@section('content')

@php
    $dateCarbon = \Illuminate\Support\Carbon::parse($date);
    $prevDate = $dateCarbon->copy()->subDay()->toDateString();
    $nextDate = $dateCarbon->copy()->addDay()->toDateString();
    $isToday = $date === now()->toDateString();
@endphp

<div class="d-flex justify-content-between align-items-start gap-3 mb-3">
    <div>
        <h2 class="mb-1">Daily Production</h2>
        <p class="text-muted mb-0">
            Read-only view of every vendor's daily output. Each style shows its own Cutting columns — one per ratio/marker it uses.
        </p>
    </div>
    <a href="{{ route('subcon.admin.daily-production.export', ['vendor_id' => request('vendor_id')]) }}" class="btn btn-outline-success text-nowrap">
        <i class="fas fa-file-excel me-1"></i> Export to Excel
    </a>
</div>

<div class="card mb-4">
    <div class="card-body p-3">
        <div class="row g-4">
            <div class="col-12 col-lg-6">
                <div class="dp-toolbar-label">Select a date</div>
                <div class="d-flex align-items-center gap-2">
                    <a href="{{ route('subcon.admin.daily-production.index', ['date' => $prevDate, 'vendor_id' => request('vendor_id')]) }}" class="btn btn-sm btn-outline-secondary" title="Previous day">
                        <i class="fas fa-chevron-left"></i>
                    </a>
                    <form method="GET" action="{{ route('subcon.admin.daily-production.index') }}" class="d-inline d-flex gap-2">
                        <input type="hidden" name="vendor_id" value="{{ request('vendor_id') }}">
                        <input type="date" name="date" value="{{ $date }}" max="{{ now()->toDateString() }}"
                               class="form-control form-control-sm" style="width:170px;" onchange="this.form.submit()">
                    </form>
                    <a href="{{ route('subcon.admin.daily-production.index', ['date' => $nextDate, 'vendor_id' => request('vendor_id')]) }}" class="btn btn-sm btn-outline-secondary {{ $nextDate > now()->toDateString() ? 'disabled' : '' }}" title="Next day">
                        <i class="fas fa-chevron-right"></i>
                    </a>
                    @unless($isToday)
                        <a href="{{ route('subcon.admin.daily-production.index', ['vendor_id' => request('vendor_id')]) }}" class="btn btn-sm btn-link">Jump to Today</a>
                    @endunless
                </div>
            </div>

            <div class="col-12 col-lg-6">
                <div class="dp-toolbar-label">Filter by vendor</div>
                <form method="GET" action="{{ route('subcon.admin.daily-production.index') }}" class="d-flex gap-2">
                    <input type="hidden" name="date" value="{{ $date }}">
                    <select name="vendor_id" class="form-select form-select-sm" onchange="this.form.submit()">
                        <option value="">All Vendors</option>
                        @foreach($vendors as $vendor)
                            <option value="{{ $vendor->id }}" {{ request('vendor_id') == $vendor->id ? 'selected' : '' }}>{{ $vendor->name }}</option>
                        @endforeach
                    </select>
                </form>
            </div>
        </div>
    </div>
</div>

@if(empty($groups))
    <div class="card">
        <div class="card-body text-center text-muted py-5">
            <i class="fas fa-industry fa-2x mb-3 opacity-50"></i>
            <p class="mb-0">No styles on the board yet.</p>
        </div>
    </div>
@else
    @foreach($groups as $group)
        @include('subcon.partials.style-progress-rows', [
            'group' => $group,
            'date' => $date,
            'flatStages' => $flatStages,
            'finishingStages' => $finishingStages,
            'mode' => 'view',
            'showWorkOrderLink' => true,
            'workOrderRouteName' => 'subcon.admin.orders.view',
            'showVendorName' => true,
        ])
    @endforeach
@endif

@push('scripts')
    @include('subcon.partials.daily-production-scripts')
@endpush

@endsection
