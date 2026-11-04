@extends('layouts.app')

@section('title', 'Preliminary Contracts — Overview & Pipeline')

@section('content')
@include('admin.pc.partials._tokens')

<div class="container-fluid apple-po-wrapper px-3 px-md-4 py-3">
    @include('admin.pc.partials.section-nav', ['pcSection' => 'dashboard'])
    @include('admin.pc.partials.dashboard')
</div>

@include('partials.apple-theme')
@endsection
