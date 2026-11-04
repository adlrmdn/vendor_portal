@extends('layouts.app')

@section('title', 'Edit Preliminary Contract')

@section('content')
@include('admin.pc.partials._tokens')

<div class="container-fluid apple-po-wrapper px-3 px-md-4 py-3">
    @include('admin.pc.partials.section-nav', ['pcSection' => 'edit'])

    <div class="d-flex justify-content-between align-items-center mb-3">
        <div>
            <div class="d-flex align-items-center gap-2 mb-1">
                <span class="apple-section-eyebrow">Preliminary Contract</span>
                <span class="apple-bullet">&bull;</span>
                <span class="apple-section-eyebrow text-muted">Revision</span>
            </div>
            <h1 class="apple-page-title mb-1">Edit Draft — {{ $contract->contract_number ?? 'Draft' }}</h1>
            <p class="text-muted small mb-0">Modify specifications, delivery schedules, or pricing before submitting for approval</p>
        </div>
    </div>

    @include('admin.pc.contracts.form')
</div>
@endsection
