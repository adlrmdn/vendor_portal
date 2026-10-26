@extends('layouts.app')

@section('title', 'New Preliminary Contract')

@section('content')
@include('admin.pc.partials._tokens')

<div class="container-fluid px-0">
    @include('admin.pc.partials.section-nav', ['pcSection' => 'create'])

    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h1 class="h3 fw-bold text-dark mb-1">New Preliminary Contract</h1>
            <p class="text-muted small mb-0">Fill in header commercial terms, lot delivery schedules, and fabric line items</p>
        </div>
    </div>

    @include('admin.pc.contracts.form')
</div>
@endsection
