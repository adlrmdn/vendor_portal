@extends('layouts.app')

@section('title', 'Edit Preliminary Contract')

@section('content')
@include('admin.pc.partials._tokens')

<div class="pc-shell">
    @include('admin.pc.partials.section-nav', ['pcSection' => 'contracts'])

    <h1 class="pc-h1 pc-mono mb-4">Edit Draft — {{ $contract->contract_number ?? 'Draft' }}</h1>
    @include('admin.pc.contracts.form')
</div>
@endsection
