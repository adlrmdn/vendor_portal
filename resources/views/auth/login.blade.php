@extends('layouts.app')

@section('content')
    <div class="container">
        <div class="row justify-content-center align-items-center" style="height: calc(100vh - 76px);">
            <div class="col-xl-9 col-lg-10">
                <div class="card shadow-lg border-0">
                    <div class="card-body p-0">
                        <div class="row g-0">
                            <!-- Left Side: Welcome/Branding -->
                            <div
                                class="col-md-5 d-none d-md-flex flex-column justify-content-center align-items-center bg-light p-5 border-end">
                                <div class="mb-4">
                                    <img src="{{ asset('images/mpg_logo_final.png') }}" alt="Logo" class="img-fluid"
                                        style="max-height: 100px;">
                                </div>
                                <h3 class="fw-bold mb-2 text-center text-dark">Welcome Back</h3>
                                <p class="text-muted text-center small mb-0">Access your dashboard</p>
                            </div>

                            <!-- Right Side: Login Form -->
                            <div class="col-md-7 p-5">
                                <div class="d-md-none text-center mb-4">
                                    <!-- Cropped Logo Container -->
                                    <div style="height: 60px; overflow: hidden; display: inline-block;">
                                        <img src="{{ asset('images/mpg_logo_final.png') }}" alt="Logo" class="img-fluid"
                                            style="max-height: 80px;">
                                    </div>
                                    <h3 class="fw-bold mt-3">Welcome Back</h3>
                                </div>

                                <form method="POST" action="{{ route('login') }}">
                                    @csrf

                                    <div class="mb-4">
                                        <label for="email" class="form-label text-muted small fw-bold">EMAIL ADDRESS</label>
                                        <div class="input-group">
                                            <span class="input-group-text bg-light border-end-0"><i
                                                    class="fas fa-envelope text-muted"></i></span>
                                            <input id="email" type="email"
                                                class="form-control bg-light border-start-0 @error('email') is-invalid @enderror"
                                                name="email" value="{{ old('email') }}" required autocomplete="email"
                                                autofocus tabindex="1">
                                        </div>
                                        @error('email')
                                            <span class="invalid-feedback d-block mt-1" role="alert">
                                                <strong>{{ $message }}</strong>
                                            </span>
                                        @enderror
                                    </div>

                                    <div class="mb-4">
                                        <div class="d-flex justify-content-between">
                                            <label for="password"
                                                class="form-label text-muted small fw-bold">PASSWORD</label>
                                            @if (Route::has('password.request'))
                                                <a class="text-decoration-none small" href="{{ route('password.request') }}">
                                                    Forgot Password?
                                                </a>
                                            @endif
                                        </div>
                                        <div class="input-group">
                                            <span class="input-group-text bg-light border-end-0"><i
                                                    class="fas fa-lock text-muted"></i></span>
                                            <input id="password" type="password"
                                                class="form-control bg-light border-start-0 @error('password') is-invalid @enderror"
                                                name="password" required autocomplete="current-password" tabindex="2">
                                        </div>
                                        @error('password')
                                            <span class="invalid-feedback d-block mt-1" role="alert">
                                                <strong>{{ $message }}</strong>
                                            </span>
                                        @enderror
                                    </div>

                                    <div class="mb-4">
                                        <div class="form-check">
                                            <input class="form-check-input" type="checkbox" name="remember" id="remember" {{ old('remember') ? 'checked' : '' }}>
                                            <label class="form-check-label small text-muted" for="remember">
                                                Keep me logged in
                                            </label>
                                        </div>
                                    </div>

                                    <div class="d-grid shadow-sm">
                                        <button type="submit" class="btn btn-primary py-2 fw-bold" tabindex="3">
                                            LOG IN
                                        </button>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="text-center mt-3 text-muted small">
                    &copy; {{ date('Y') }} PT. Mega Putra Garment. All rights reserved.
                </div>
            </div>
        </div>
    </div>
    <style>
        body {
            overflow: hidden;
        }
    </style>
@endsection