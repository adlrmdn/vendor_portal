@extends('layouts.app')

@section('title', 'Preliminary Contract')

@section('content')
@include('admin.pc.partials._tokens')

<div class="pc-shell">
    @include('admin.pc.partials.section-nav', ['pcSection' => 'dashboard'])
    @include('admin.pc.partials.dashboard')
</div>
@endsection
