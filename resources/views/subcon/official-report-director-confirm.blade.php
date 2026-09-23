@extends('layouts.app')

@section('title', 'Official Report — Director Authorization')
@section('bare', '1')

@section('content')
<div class="container py-5">
    <div class="row justify-content-center">
        <div class="col-12 col-md-8 col-lg-7">
            <div class="card border-0 shadow-sm" style="border-radius: 16px;">
                <div class="card-body p-4 p-md-5">
                    <h4 class="mb-1">Official Report — Director Authorization</h4>
                    <p class="text-muted mb-4">{{ $report->production_group }} — {{ $report->operation }}</p>

                    <table class="table table-sm">
                        <tr><th>Purchase Order</th><td>{{ $report->matched_po_number ?? '—' }}</td></tr>
                        <tr><th>Approved by (MD Production)</th><td>{{ $report->ho_approval_signature }}</td></tr>
                        <tr><th>Cut / Good / Reject Qty</th><td>{{ number_format($report->cut_qty) }} / {{ number_format($report->good_qty) }} / {{ number_format($report->reject_qty) }}</td></tr>
                        <tr><th>Reject Penalty</th><td>Rp {{ number_format($report->reject_penalty, 0, ',', '.') }}</td></tr>
                        <tr><th>Additional Deduction</th><td>Rp {{ number_format($report->additional_deduction, 0, ',', '.') }}</td></tr>
                        <tr><th>Total Deduction</th><td><strong>Rp {{ number_format($report->totalDeduction(), 0, ',', '.') }}</strong></td></tr>
                        <tr><th>Remarks</th><td>{{ $report->remarks ?: '—' }}</td></tr>
                    </table>

                    <form method="POST" action="{{ $submitUrl }}" class="d-inline">
                        @csrf
                        <input type="text" name="approver" class="form-control mb-2" placeholder="Your Name" required>
                        <button type="submit" class="btn btn-primary">Authorize</button>
                    </form>
                    <a href="{{ $declineUrl }}" class="btn btn-outline-danger">Decline</a>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
