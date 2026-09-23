@extends('layouts.app')

@section('title', 'Official Report — MD Production Approval')
@section('bare', '1')

@section('content')
<div class="container py-5">
    <div class="row justify-content-center">
        <div class="col-12 col-md-8 col-lg-7">
            <div class="card border-0 shadow-sm" style="border-radius: 16px;">
                <div class="card-body p-4 p-md-5">
                    <h4 class="mb-1">Official Report — MD Production Approval</h4>
                    <p class="text-muted mb-4">{{ $report->production_group }} — {{ $report->operation }}</p>

                    @if($report->needsRevision())
                        <div class="alert alert-warning">
                            This is a resend after a Director decline (revision #{{ $report->revision_count }}).
                            <div class="mt-1"><strong>Reason:</strong> {{ $report->director_decline_reason }}</div>
                        </div>
                    @endif

                    <table class="table table-sm">
                        <tr><th>Purchase Order</th><td>{{ $report->matched_po_number ?? '—' }}</td></tr>
                        <tr><th>Cut / Good / Reject Qty</th><td>{{ number_format($report->cut_qty) }} / {{ number_format($report->good_qty) }} / {{ number_format($report->reject_qty) }}</td></tr>
                        <tr><th>Allowed Limit (1%)</th><td>{{ number_format($report->allowed_limit) }}</td></tr>
                        <tr><th>Reject Penalty</th><td>Rp {{ number_format($report->reject_penalty, 0, ',', '.') }}</td></tr>
                    </table>

                    <form method="POST" action="{{ $submitUrl }}">
                        @csrf
                        <div class="mb-3">
                            <label class="form-label">Additional Deduction (Rp)</label>
                            <input type="number" step="0.01" min="0" name="additional_deduction" class="form-control" value="{{ old('additional_deduction', $report->additional_deduction) }}">
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Remarks</label>
                            <textarea name="remarks" class="form-control" rows="3">{{ old('remarks', $report->remarks) }}</textarea>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Your Name</label>
                            <input type="text" name="approver" class="form-control" required>
                        </div>
                        <button type="submit" class="btn btn-primary">Approve &amp; Send to Director</button>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
