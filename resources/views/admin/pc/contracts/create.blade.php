@extends('layouts.app')

@section('title', 'New Preliminary Contract')

@section('content')
@include('admin.pc.partials._tokens')

<div class="container-fluid apple-po-wrapper px-3 px-md-4 py-3">
    @include('admin.pc.partials.section-nav', ['pcSection' => 'create'])

    <div class="d-flex justify-content-between align-items-center mb-3">
        <div>
            <div class="d-flex align-items-center gap-2 mb-1">
                <span class="apple-section-eyebrow">Preliminary Contract</span>
                <span class="apple-bullet">&bull;</span>
                <span class="apple-section-eyebrow text-muted">Authoring</span>
            </div>
            <h1 class="apple-page-title mb-1">New Preliminary Contract</h1>
            <p class="text-muted small mb-0">Fill in header commercial terms, lot delivery schedules, and fabric line items</p>
        </div>
    </div>

    @include('admin.pc.contracts.form')
</div>
@endsection
