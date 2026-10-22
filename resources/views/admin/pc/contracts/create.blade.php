@extends('layouts.app')

@section('title', 'New Preliminary Contract')

@section('content')
@include('admin.pc.partials._tokens')

<div class="pc-shell">
    @include('admin.pc.partials.section-nav', ['pcSection' => 'new-contract'])

    <h1 class="pc-h1 mb-4">New Preliminary Contract</h1>
    @include('admin.pc.contracts.form')
</div>
@endsection
