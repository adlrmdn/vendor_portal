@extends('layouts.app')

@section('title', 'Action Result')

@section('content')
<div class="container py-5">
    <div class="row justify-content-center">
        <div class="col-md-8 text-center">
            <div class="card shadow-sm">
                <div class="card-body py-5">
                    @if($success)
                        <div class="mb-4 text-success">
                            <i class="fas fa-check-circle fa-5x"></i>
                        </div>
                        <h2 class="h4 mb-3">Action Successful</h2>
                    @else
                        <div class="mb-4 text-danger">
                            <i class="fas fa-exclamation-circle fa-5x"></i>
                        </div>
                        <h2 class="h4 mb-3">Action Failed</h2>
                    @endif
                    
                    <p class="lead mb-4">{{ $message }}</p>
                    
                    <div class="mt-4">
                        <p class="text-muted small">You can now close this window.</p>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
