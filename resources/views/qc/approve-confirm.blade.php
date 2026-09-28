@extends('layouts.app')

@section('title', 'Konfirmasi Persetujuan')
@section('bare', '1')

@section('content')
<style>
    .qc-confirm-icon {
        width: 72px;
        height: 72px;
        border-radius: 50%;
        background: rgba(255, 193, 7, 0.15);
        display: flex;
        align-items: center;
        justify-content: center;
        margin: 0 auto;
        position: relative;
    }
    .qc-confirm-icon::before {
        content: '';
        position: absolute;
        inset: -6px;
        border-radius: 50%;
        border: 2px solid rgba(255, 193, 7, 0.45);
        animation: qc-confirm-pulse 1.8s ease-out infinite;
    }
    @keyframes qc-confirm-pulse {
        0%   { transform: scale(0.9); opacity: 0.9; }
        70%  { transform: scale(1.3); opacity: 0; }
        100% { transform: scale(1.3); opacity: 0; }
    }
    .qc-confirm-btn {
        animation: qc-confirm-glow 2s ease-in-out infinite;
    }
    @keyframes qc-confirm-glow {
        0%, 100% { box-shadow: 0 0 0 0 rgba(25, 135, 84, 0.35); }
        50%      { box-shadow: 0 0 0 8px rgba(25, 135, 84, 0); }
    }
</style>
<div class="container py-3 py-md-4">
    <div class="row justify-content-center">
        <div class="col-12 col-sm-10 col-md-8 col-lg-6">
            <div class="card border-0 shadow-sm" style="border-radius: 16px;">
                <div class="card-body p-4 text-center">
                    <div class="qc-confirm-icon mb-3">
                        <i class="fas fa-hourglass-half fa-lg text-warning"></i>
                    </div>

                    <h4 class="fw-bold mb-2">Satu langkah lagi, setujui inspeksi ini?</h4>
                    <p class="text-muted mb-3">
                        Anda menyetujui inspeksi ini sebagai Perwakilan Pabrik. Setelah dikonfirmasi, proses lanjut ke
                        persetujuan MD Production dan <strong>tidak dapat dibatalkan</strong>.
                    </p>

                    <div class="bg-light rounded-3 px-3 py-2 mb-3 text-start">
                        @if($styleName)
                            <div class="text-muted small">Style</div>
                            <div class="fw-semibold text-break mb-2">{{ $styleName }}</div>
                        @endif
                        <div class="text-muted small">Proyek</div>
                        <div class="fw-semibold text-break">{{ $row->project_id }}</div>
                    </div>

                    <div class="alert alert-warning fw-semibold mb-4">
                        <i class="fas fa-triangle-exclamation me-1"></i> Belum berubah — tekan tombol di bawah untuk menyetujui.
                    </div>

                    <form method="POST" action="{{ route('qc.approve.submit', ['token' => $token]) }}">
                        @csrf
                        <button type="submit" class="qc-confirm-btn btn btn-success btn-lg px-4 w-100">
                            <i class="fas fa-check me-1"></i> Ya, Konfirmasi &amp; Setujui
                        </button>
                    </form>
                </div>
            </div>
            <p class="text-center text-muted small mt-3 mb-0">Tidak bermaksud menyetujui? Tutup jendela ini — belum ada yang tercatat.</p>
        </div>
    </div>
</div>
@endsection
