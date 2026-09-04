@extends('layouts.app')

@section('title', 'Finance Admin Dashboard')

@php
    $statusMeta = [
        'processing' => ['label' => 'Processing RPA', 'color' => 'info'],
        'completed' => ['label' => 'Completed', 'color' => 'success'],
        'failed' => ['label' => 'Failed', 'color' => 'danger'],
        'waiting' => ['label' => 'Waiting', 'color' => 'secondary'],
    ];
@endphp

@section('content')
<div class="d-flex justify-content-between align-items-center mb-4">
    <h4 class="mb-0"><i class="fas fa-file-invoice-dollar me-2"></i>Finance Dashboard</h4>
    <span class="text-muted small">As of {{ now('Asia/Jakarta')->format('d M Y H:i') }} WIB</span>
</div>

<div class="row g-3">
    <div class="col-lg-6">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-header bg-white d-flex justify-content-between align-items-center">
                <span class="fw-bold"><i class="fas fa-file-invoice me-2 text-primary"></i>Invoices</span>
                <a href="{{ route('finance.admin.invoices') }}" class="small text-decoration-none">View all &rarr;</a>
            </div>
            <div class="card-body">
                <div class="row text-center g-2">
                    @foreach ($statusMeta as $key => $meta)
                        <div class="col">
                            <div class="border rounded p-3">
                                <div class="fs-4 fw-bold text-{{ $meta['color'] }}">{{ $invoiceStats[$key] }}</div>
                                <div class="small text-muted">{{ $meta['label'] }}</div>
                            </div>
                        </div>
                    @endforeach
                </div>
                <div class="text-center mt-3 text-muted small">{{ $invoiceStats['total'] }} total</div>
            </div>
        </div>
    </div>

    <div class="col-lg-6">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-header bg-white d-flex justify-content-between align-items-center">
                <span class="fw-bold"><i class="fas fa-file-circle-minus me-2 text-danger"></i>Debit Notes</span>
                <a href="{{ route('finance.admin.debit-notes') }}" class="small text-decoration-none">View all &rarr;</a>
            </div>
            <div class="card-body">
                <div class="row text-center g-2">
                    @foreach ($statusMeta as $key => $meta)
                        <div class="col">
                            <div class="border rounded p-3">
                                <div class="fs-4 fw-bold text-{{ $meta['color'] }}">{{ $debitNoteStats[$key] }}</div>
                                <div class="small text-muted">{{ $meta['label'] }}</div>
                            </div>
                        </div>
                    @endforeach
                </div>
                <div class="text-center mt-3 text-muted small">{{ $debitNoteStats['total'] }} total</div>
            </div>
        </div>
    </div>
</div>
@endsection
