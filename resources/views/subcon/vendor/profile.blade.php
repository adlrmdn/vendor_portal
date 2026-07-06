@extends('layouts.app')

@section('title', 'Profile Settings')

@section('content')
<!-- Google Fonts -->
<link href="https://fonts.googleapis.com/css2?family=Outfit:wght@400;500;600;700&family=Inter:wght@400;500;600&display=swap" rel="stylesheet">

<style>
    .premium-container {
        font-family: 'Inter', sans-serif;
        color: #334155;
    }
    .premium-header {
        background: linear-gradient(135deg, #0f172a 0%, #1e293b 100%);
        border-radius: 20px;
        padding: 35px;
        color: #ffffff;
        box-shadow: 0 10px 25px rgba(15, 23, 42, 0.15);
        position: relative;
        overflow: hidden;
        margin-bottom: 30px;
    }
    .premium-header::after {
        content: '';
        position: absolute;
        right: -50px;
        bottom: -50px;
        width: 220px;
        height: 220px;
        border-radius: 50%;
        background: rgba(255, 255, 255, 0.03);
    }
    .premium-header-title {
        font-family: 'Outfit', sans-serif;
        font-weight: 700;
        font-size: 2.25rem;
        letter-spacing: -0.02em;
    }
    .premium-card {
        border-radius: 16px;
        border: 1px solid rgba(0, 0, 0, 0.05);
        box-shadow: 0 10px 30px rgba(0, 0, 0, 0.02);
        background: #ffffff;
        overflow: hidden;
        margin-bottom: 25px;
    }
    .card-header-premium {
        background-color: #f8fafc;
        border-bottom: 1px solid #f1f5f9;
        padding: 20px 24px;
        font-family: 'Outfit', sans-serif;
        font-weight: 600;
        font-size: 1.15rem;
        color: #0f172a;
        display: flex;
        align-items: center;
    }
    .card-body-premium {
        padding: 24px;
    }
    .form-label-premium {
        font-weight: 500;
        color: #475569;
        margin-bottom: 6px;
        font-size: 0.9rem;
    }
    .form-control-premium {
        border-radius: 10px;
        border: 1px solid #cbd5e1;
        padding: 11px 16px;
        font-size: 0.95rem;
        transition: border-color 0.15s ease-in-out, box-shadow 0.15s ease-in-out;
    }
    .form-control-premium:focus {
        border-color: #3b82f6;
        box-shadow: 0 0 0 4px rgba(59, 130, 246, 0.1);
        outline: none;
    }
    .btn-premium {
        background: #2563eb;
        color: white;
        border-radius: 10px;
        padding: 11px 24px;
        font-weight: 600;
        border: none;
        box-shadow: 0 4px 6px -1px rgba(37, 99, 235, 0.1), 0 2px 4px -1px rgba(37, 99, 235, 0.06);
        transition: all 0.2s;
    }
    .btn-premium:hover {
        background: #1d4ed8;
        transform: translateY(-1px);
        box-shadow: 0 10px 15px -3px rgba(37, 99, 235, 0.2);
    }
    .readonly-badge {
        background-color: #f1f5f9;
        border: 1px solid #e2e8f0;
        color: #64748b;
    }
</style>

<div class="premium-container container-fluid py-4">
    <!-- Header Banner -->
    <div class="premium-header">
        <div class="d-flex align-items-center">
            <div class="me-4 rounded-circle bg-white bg-opacity-10 d-flex align-items-center justify-content-center" style="width: 70px; height: 70px; backdrop-filter: blur(8px);">
                <i class="fas fa-user-cog fs-2 text-white"></i>
            </div>
            <div>
                <h1 class="premium-header-title mb-1">Profile Settings</h1>
                <p class="mb-0 text-white text-opacity-75">Update your subcontractor contact information and security settings</p>
            </div>
        </div>
    </div>

    <!-- Error Alert List -->
    @if ($errors->any())
        <div class="alert alert-danger border-0 shadow-sm rounded-3 mb-4" role="alert">
            <div class="d-flex">
                <i class="fas fa-exclamation-circle fs-4 me-3 mt-1"></i>
                <div>
                    <h5 class="alert-heading fw-bold mb-1">Please correct the following errors:</h5>
                    <ul class="mb-0 ps-3">
                        @foreach ($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            </div>
        </div>
    @endif

    <div class="row">
        <!-- Vendor Profile Info -->
        <div class="col-lg-6">
            <div class="premium-card card">
                <div class="card-header-premium">
                    <i class="fas fa-id-card me-2 text-primary"></i> Subcontractor Information
                </div>
                <div class="card-body-premium">
                    <form action="{{ route('subcon.vendor.profile.update') }}" method="POST">
                        @csrf
                        @method('PUT')

                        <div class="mb-3">
                            <label class="form-label-premium">Vendor Code</label>
                            <input type="text" class="form-control form-control-premium readonly-badge" value="{{ $vendor->vendor_code }}" readonly>
                            <div class="form-text text-muted">Unique ID assigned in Dynamics 365.</div>
                        </div>

                        <div class="mb-3">
                            <label class="form-label-premium">Organization Name</label>
                            <input type="text" class="form-control form-control-premium readonly-badge" value="{{ $vendor->name }}" readonly>
                            <div class="form-text text-muted">Primary entity name in Dynamics 365.</div>
                        </div>

                        <div class="mb-4">
                            <label for="email" class="form-label-premium">Contact Email Address</label>
                            <input type="email" name="email" id="email" class="form-control form-control-premium @error('email') is-invalid @enderror" value="{{ old('email', $vendor->contact_info['email'] ?? '') }}" required>
                            <div class="form-text text-muted">This email is used to send workflow notifications (approval approvals, label requests).</div>
                        </div>

                        <div class="d-flex justify-content-end">
                            <button type="submit" class="btn btn-premium">
                                <i class="fas fa-save me-2"></i> Save Profile Details
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <!-- Password Change -->
        <div class="col-lg-6">
            <div class="premium-card card">
                <div class="card-header-premium">
                    <i class="fas fa-lock-open me-2 text-warning"></i> Security & Password
                </div>
                <div class="card-body-premium">
                    <form action="{{ route('subcon.vendor.profile.password') }}" method="POST">
                        @csrf
                        @method('PUT')

                        <div class="mb-3">
                            <label for="current_password" class="form-label-premium">Current Password</label>
                            <input type="password" name="current_password" id="current_password" class="form-control form-control-premium @error('current_password') is-invalid @enderror" placeholder="Enter current password" required>
                        </div>

                        <div class="mb-3">
                            <label for="new_password" class="form-label-premium">New Password</label>
                            <input type="password" name="new_password" id="new_password" class="form-control form-control-premium @error('new_password') is-invalid @enderror" placeholder="At least 8 characters" required>
                        </div>

                        <div class="mb-4">
                            <label for="new_password_confirmation" class="form-label-premium">Confirm New Password</label>
                            <input type="password" name="new_password_confirmation" id="new_password_confirmation" class="form-control form-control-premium" placeholder="Repeat new password" required>
                        </div>

                        <div class="d-flex justify-content-end">
                            <button type="submit" class="btn btn-premium bg-warning text-dark border-0 hover:bg-warning-dark" style="box-shadow: 0 4px 6px -1px rgba(217, 119, 6, 0.15);">
                                <i class="fas fa-key me-2"></i> Update Password
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
