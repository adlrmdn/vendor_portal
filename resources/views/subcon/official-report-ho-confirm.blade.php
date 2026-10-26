@extends('layouts.app')

@section('title', 'Official Report — MD Production Approval')
@section('bare', '1')

@section('content')
<div class="container py-5">
    <div class="row justify-content-center">
        <div class="col-12 col-md-8 col-lg-7">
            <div class="card border-0 shadow-sm" style="border-radius: 16px;">
                <div class="card-body p-4 p-md-5">
                    <h4 class="mb-0">Official Report — MD Production Approval</h4>
                    <p class="text-muted mb-4">{{ $report->production_group }} — {{ $report->operation }}</p>

                    @if($report->needsRevision())
                        <div class="alert alert-warning">
                            This is a resend after a Director decline (revision #{{ $report->revision_count }}).
                            <div class="mt-1"><strong>Reason:</strong> {{ $report->director_decline_reason }}</div>
                        </div>
                    @endif

                    <table class="table table-sm">
                        <tr><th>Vendor</th><td>{{ $report->matched_po_vendor_name ?? $report->matched_po_vendor_code ?? '—' }}</td></tr>
                        <tr><th>Style</th><td>{{ $style ?? '—' }}</td></tr>
                        <tr><th>Purchase Order</th><td>{{ $report->matched_po_number ?? '—' }}</td></tr>
                    </table>

                    <h6 class="mt-4 mb-2">Yield Details</h6>
                    <table class="table table-sm table-bordered">
                        <thead class="table-light">
                            <tr><th>Size</th><th class="text-end">Order Qty</th><th class="text-end">Cut Qty</th><th class="text-end">Good Qty</th><th class="text-end">Reject Qty</th><th class="text-end">Balance</th></tr>
                        </thead>
                        <tbody>
                            @foreach($yield['rows'] as $row)
                                <tr>
                                    <td>{{ $row['size'] }}</td>
                                    <td class="text-end">{{ number_format($row['order_qty']) }}</td>
                                    <td class="text-end">{{ number_format($row['cut_qty']) }}</td>
                                    <td class="text-end">{{ number_format($row['good_qty']) }}</td>
                                    <td class="text-end">{{ number_format($row['reject_qty']) }}</td>
                                    <td class="text-end">{{ number_format($row['balance']) }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                        <tfoot class="table-light fw-bold">
                            <tr>
                                <td>TOTAL</td>
                                <td class="text-end">{{ number_format($yield['total']['order_qty']) }}</td>
                                <td class="text-end">{{ number_format($yield['total']['cut_qty']) }}</td>
                                <td class="text-end">{{ number_format($yield['total']['good_qty']) }}</td>
                                <td class="text-end">{{ number_format($yield['total']['reject_qty']) }}</td>
                                <td class="text-end">{{ number_format($yield['total']['balance']) }}</td>
                            </tr>
                        </tfoot>
                    </table>
                    <table class="table table-sm">
                        <tr><th>Allowed Limit (1%)</th><td>{{ number_format($report->allowed_limit) }}</td>
                            <th>Reject Penalty</th><td>Rp {{ number_format($report->reject_penalty, 0, ',', '.') }}</td></tr>
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
                        <div class="text-end mb-2">
                            <a href="{{ route('subcon.admin.official-reports.document', $report->id) }}" class="btn btn-sm btn-outline-primary" target="_blank" rel="noopener">
                                <i class="fas fa-file-pdf me-1"></i> PDF Document
                            </a>
                        </div>
                        @if($autoApprover)
                            <p class="text-muted small mb-3">Signing as <strong>{{ $autoApprover }}</strong></p>
                        @else
                            <div class="mb-3">
                                <label class="form-label">Your Name</label>
                                <input type="text" name="approver" class="form-control" required>
                            </div>
                        @endif
                        <button type="submit" class="btn btn-primary">Approve &amp; Send to Director</button>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
