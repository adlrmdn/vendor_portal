@extends('layouts.app')

@section('title', 'Official Report — Decline')
@section('bare', '1')

@section('content')
<div class="container py-5">
    <div class="row justify-content-center">
        <div class="col-12 col-md-7 col-lg-6">
            <div class="card border-0 shadow-sm" style="border-radius: 16px;">
                <div class="card-body p-4 p-md-5">
                    <h4 class="mb-4">Decline Official Report</h4>
                    <p class="text-muted">{{ $report->production_group }} — {{ $report->operation }}</p>
                    <form method="POST" action="{{ $submitUrl }}">
                        @csrf
                        <div class="mb-3">
                            <label class="form-label">Reason</label>
                            <textarea name="reason" class="form-control" rows="4" required></textarea>
                        </div>
                        <button type="submit" class="btn btn-danger">Decline &amp; Send Back for Revision</button>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
