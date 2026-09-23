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
    $dateHuman = $isToday ? 'Today, '.$dateCarbon->format('d M Y') : $dateCarbon->format('l, d M Y');
@endphp

<div class="d-flex justify-content-between align-items-start gap-3 mb-3">
    <div>
        <h2 class="mb-1">Daily Production</h2>
        <p class="text-muted mb-0">
            Pick a date, type how many pieces each size completed at every step, then hit Save.
            Each style has its own <strong>Cutting</strong> columns — one per ratio/marker that style uses — click the <i class="fas fa-plus-circle text-primary"></i> in its Cutting header to add another.
        </p>
    </div>
    <a href="{{ route('subcon.vendor.daily-production.export') }}" class="btn btn-outline-success text-nowrap">
        <i class="fas fa-file-excel me-1"></i> Export to Excel
    </a>
</div>

@if(session('success'))
    <div class="alert alert-success py-2">{{ session('success') }}</div>
@endif
@if(session('error'))
    <div class="alert alert-danger py-2">{{ session('error') }}</div>
@endif

<div class="card mb-4">
    <div class="card-body p-3">
        <div class="row g-4">
            <div class="col-12 col-lg-6">
                <div class="dp-toolbar-label">Choose a date</div>
                <div class="d-flex align-items-center gap-2">
                    <a href="{{ route('subcon.vendor.daily-production.index', ['date' => $prevDate]) }}" class="btn btn-sm btn-outline-secondary" title="Previous day">
                        <i class="fas fa-chevron-left"></i>
                    </a>
                    <form method="GET" action="{{ route('subcon.vendor.daily-production.index') }}" class="d-inline">
                        <input type="date" name="date" value="{{ $date }}" max="{{ now()->toDateString() }}"
                               class="form-control form-control-sm" style="width:170px;" onchange="this.form.submit()">
                    </form>
                    <a href="{{ route('subcon.vendor.daily-production.index', ['date' => $nextDate]) }}" class="btn btn-sm btn-outline-secondary {{ $nextDate > now()->toDateString() ? 'disabled' : '' }}" title="Next day">
                        <i class="fas fa-chevron-right"></i>
                    </a>
                    @unless($isToday)
                        <a href="{{ route('subcon.vendor.daily-production.index') }}" class="btn btn-sm btn-link">Jump to Today</a>
                    @endunless
                </div>
            </div>

            <div class="col-12 col-lg-6">
                <div class="dp-toolbar-label">Add a style to track</div>
                <form method="POST" action="{{ route('subcon.vendor.daily-production.activate') }}" class="d-flex gap-2">
                    @csrf
                    <input type="hidden" name="date" value="{{ $date }}">
                    <select name="order_id" class="form-select form-select-sm searchable" data-no-clear data-no-empty-option
                            placeholder="{{ $addable->isEmpty() ? 'No more styles' : 'Select a style…' }}"
                            required @if($addable->isEmpty()) disabled @endif>
                        <option value="" selected hidden></option>
                        @foreach($addable as $o)
                            <option value="{{ $o->id }}">{{ $o->title ?: $o->order_number }} ({{ $o->order_number }})</option>
                        @endforeach
                    </select>
                    <button type="submit" class="btn btn-sm btn-primary" @if($addable->isEmpty()) disabled @endif>
                        <i class="fas fa-plus me-1"></i> Add
                    </button>
                </form>
            </div>
        </div>
    </div>
</div>

@if(empty($groups))
    <div class="card">
        <div class="card-body text-center text-muted py-5">
            <i class="fas fa-industry fa-2x mb-3 opacity-50"></i>
            <p class="mb-1">No styles yet.</p>
            <p class="mb-0 small">Pick a style above and click <strong>Add</strong> to start tracking its daily output.</p>
        </div>
    </div>
@else
    <form method="POST" action="{{ route('subcon.vendor.daily-production.store-all') }}" id="dpForm">
        @csrf
        <input type="hidden" name="entry_date" value="{{ $date }}">

        <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-3">
            <div>
                <div class="fw-bold fs-5">{{ $dateHuman }}</div>
                <div class="text-muted small">{{ count($groups) }} {{ \Illuminate\Support\Str::plural('style', count($groups)) }} on the board</div>
            </div>
            <button type="submit" class="btn btn-success dp-save-btn">
                <i class="fas fa-check me-2"></i>Save
            </button>
        </div>

        <div class="card mb-3 border-0 shadow-sm">
            <div class="card-body p-2 px-3 bg-white rounded border">
                <div class="d-flex align-items-center gap-2">
                    <label for="vendor-day-note" class="form-label mb-0 text-muted small fw-semibold text-nowrap">
                        <i class="fas fa-calendar-day text-primary me-1"></i> Overall Day Remarks (Optional):
                    </label>
                    <input type="text" id="vendor-day-note" name="day_note"
                           class="form-control form-control-sm"
                           placeholder="General notes for {{ $dateCarbon->format('d M Y') }} (e.g. shift schedule, overall factory conditions, overtime)"
                           value="{{ $dayNote ?? '' }}" maxlength="2000">
                </div>
            </div>
        </div>

        @foreach($groups as $group)
            @include('subcon.partials.style-progress-rows', [
                'group' => $group,
                'date' => $date,
                'flatStages' => $flatStages,
                'finishingStages' => $finishingStages,
                'mode' => 'edit',
                'showWorkOrderLink' => true,
                'showAddRatioColumn' => true,
            ])
        @endforeach
    </form>
@endif

@push('scripts')
    @include('subcon.partials.daily-production-scripts')
@endpush

@include('partials.searchable-select')

@endsection
