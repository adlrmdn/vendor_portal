@extends('layouts.app')

@section('title', 'Edit Preliminary Contract')

@section('content')
@include('admin.pc.partials._tokens')

<div class="container-fluid px-0">
    @include('admin.pc.partials.section-nav', ['pcSection' => 'edit'])

    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h1 class="h3 fw-bold text-dark mb-1">Edit Draft — {{ $contract->contract_number ?? 'Draft' }}</h1>
            <p class="text-muted small mb-0">Modify specifications, delivery schedules, or pricing before submitting for approval</p>
        </div>
    </div>

    @include('admin.pc.contracts.form')
</div>
@endsection
