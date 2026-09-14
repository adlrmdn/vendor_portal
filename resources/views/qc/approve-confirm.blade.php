@extends('layouts.app')

@section('title', 'Confirm Approval')
@section('bare', '1')

@section('content')
<div class="container py-5">
    <div class="row justify-content-center">
        <div class="col-12 col-md-7 col-lg-6">
            <div class="card border-0 shadow-sm" style="border-radius: 16px;">
                <div class="card-body text-center p-5">
                    <div class="mb-3"><i class="fas fa-circle-check fa-3x text-success"></i></div>
                    <h4 class="fw-bold mb-2">Approve this inspection?</h4>
                    <p class="text-muted mb-4">You are about to <strong>approve</strong> this packaging inspection as the Factory Representative. This is recorded immediately and moves it on to MD Production approval.</p>

                    <dl class="row small text-start mb-4">
                        <dt class="col-5 text-muted fw-normal">Project</dt>
                        <dd class="col-7 fw-semibold">{{ $row->project_id }}</dd>
                    </dl>

                    <form method="POST" action="{{ route('qc.approve.submit', ['token' => $token]) }}">
                        @csrf
                        <button type="submit" class="btn btn-success px-4">
                            <i class="fas fa-check me-1"></i> Confirm Approval
                        </button>
                    </form>
                </div>
            </div>
            <p class="text-center text-muted small mt-3 mb-0">If you didn't mean to approve, just close this window — nothing has been recorded yet.</p>
        </div>
    </div>
</div>
@endsection
