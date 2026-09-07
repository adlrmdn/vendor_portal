@extends('layouts.app')

@section('title', 'Workflow Settings')

@section('content')
<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h2 class="mb-0">Workflow Settings</h2>
        <small class="text-muted">Configure who receives fabric tolerance &amp; partial-shipment approval requests</small>
    </div>
    <a href="{{ route('admin.approvals') }}" class="btn btn-outline-secondary">
        <i class="fas fa-gavel me-1"></i> Approvals
    </a>
</div>

@if ($errors->any())
    <div class="alert alert-danger">
        <ul class="mb-0">
            @foreach ($errors->all() as $error)
                <li>{{ $error }}</li>
            @endforeach
        </ul>
    </div>
@endif

<div class="card">
    <div class="card-header bg-primary text-white">
        <i class="fas fa-envelope-open-text me-2"></i>Approval Email Routing
    </div>
    <div class="card-body">
        <form action="{{ route('admin.workflow.update') }}" method="POST">
            @csrf

            <div class="mb-3">
                <label for="fabric_approver_email" class="form-label fw-bold">Fabric Approver Email Address(es)</label>
                <input type="text" name="fabric_approver_email" id="fabric_approver_email"
                       class="form-control @error('fabric_approver_email') is-invalid @enderror"
                       value="{{ old('fabric_approver_email', $fabricApproverEmail) }}"
                       placeholder="a@mail.com, b@mail.com" required>
                @error('fabric_approver_email')<div class="invalid-feedback">{{ $message }}</div>@enderror
                <div class="form-text">
                    Receives the signed-link approval email whenever a fabric vendor requests a delivery-tolerance
                    amendment or a partial shipment. Separate multiple recipients with commas. Requests can also be
                    approved or declined in-app from the <a href="{{ route('admin.approvals') }}">Approvals</a> tab.
                </div>
            </div>

            <div class="d-flex justify-content-end border-top pt-3">
                <button type="submit" class="btn btn-primary">
                    <i class="fas fa-save me-2"></i>Save Workflow Settings
                </button>
            </div>
        </form>
    </div>
</div>
@endsection
