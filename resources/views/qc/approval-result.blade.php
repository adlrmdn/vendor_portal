@extends('layouts.app')

@section('title', 'Packaging Approval')
@section('bare', '1')

@section('content')
<div class="container py-5">
    <div class="row justify-content-center">
        <div class="col-12 col-md-7 col-lg-6">
            <div class="card border-0 shadow-sm" style="border-radius: 16px;">
                <div class="card-body text-center p-5">
                    @if($state === 'success')
                        <div class="mb-3"><i class="fas fa-circle-check fa-3x text-success"></i></div>
                        <h4 class="fw-bold mb-2">Approved &amp; Signed</h4>
                    @elseif($state === 'rejected')
                        <div class="mb-3"><i class="fas fa-circle-xmark fa-3x text-danger"></i></div>
                        <h4 class="fw-bold mb-2">Inspection Rejected</h4>
                    @elseif($state === 'already')
                        <div class="mb-3"><i class="fas fa-circle-info fa-3x text-primary"></i></div>
                        <h4 class="fw-bold mb-2">Already Approved</h4>
                    @elseif($state === 'blocked')
                        <div class="mb-3"><i class="fas fa-hourglass-half fa-3x text-warning"></i></div>
                        <h4 class="fw-bold mb-2">Waiting on Material Flow</h4>
                    @else
                        <div class="mb-3"><i class="fas fa-circle-exclamation fa-3x text-danger"></i></div>
                        <h4 class="fw-bold mb-2">Link Not Valid</h4>
                    @endif

                    <p class="text-muted mb-4">{{ $message }}</p>

                    @if(! empty($signature))
                        <div class="alert alert-light border text-start small mb-0">
                            <span class="text-muted d-block mb-1">Signature</span>
                            <span class="fw-semibold">{{ $signature }}</span>
                        </div>
                    @endif
                </div>
            </div>
            <p class="text-center text-muted small mt-3 mb-0">You may close this window.</p>
        </div>
    </div>
</div>
@endsection
