@extends('layouts.app')

@section('title', 'Preliminary Contracts — Overview & Pipeline')

@section('content')
@include('admin.pc.partials._tokens')

<div class="container-fluid px-0">
    @include('admin.pc.partials.section-nav', ['pcSection' => 'dashboard'])
    @include('admin.pc.partials.dashboard')
</div>
@endsection
