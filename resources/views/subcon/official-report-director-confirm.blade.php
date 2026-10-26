@extends('layouts.app')

@section('title', 'Official Report — Director Authorization')
@section('bare', '1')

@section('content')
<div class="container py-5">
    <div class="row justify-content-center">
        <div class="col-12 col-md-8 col-lg-7">
            <div class="card border-0 shadow-sm" style="border-radius: 16px;">
                <div class="card-body p-4 p-md-5">
                    <h4 class="mb-0">Official Report — Director Authorization</h4>
                    <p class="text-muted mb-4">{{ $report->production_group }} — {{ $report->operation }}</p>

                    <table class="table table-sm">
                        <tr><th>Vendor</th><td>{{ $report->matched_po_vendor_name ?? $report->matched_po_vendor_code ?? '—' }}</td></tr>
                        <tr><th>Style</th><td>{{ $style ?? '—' }}</td></tr>
                        <tr><th>Purchase Order</th><td>{{ $report->matched_po_number ?? '—' }}</td></tr>
                        <tr><th>Approved by (MD Production)</th><td>{{ $report->ho_approval_signature }}</td></tr>
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
                            <th>Exceeding Qty</th><td>{{ number_format($report->exceeding_reject_qty) }}</td></tr>
                    </table>

                    <h6 class="mt-4 mb-2">Deduction</h6>
                    <table class="table table-sm">
                        <tr><th>Reject Penalty</th><td>Rp {{ number_format($report->reject_penalty, 0, ',', '.') }}</td></tr>
                        <tr><th>Additional Deduction</th><td>Rp {{ number_format($report->additional_deduction, 0, ',', '.') }}</td></tr>
                        <tr><th>Total Deduction</th><td><strong>Rp {{ number_format($report->totalDeduction(), 0, ',', '.') }}</strong></td></tr>
                        <tr><th>Remarks</th><td>{{ $report->remarks ?: '—' }}</td></tr>
                    </table>

                    <div class="text-end mb-2">
                        <a href="{{ route('subcon.admin.official-reports.document', $report->id) }}" class="btn btn-sm btn-outline-primary" target="_blank" rel="noopener">
                            <i class="fas fa-file-pdf me-1"></i> PDF Document
                        </a>
                    </div>

                    <form method="POST" action="{{ $submitUrl }}" class="d-inline">
                        @csrf
                        @if($autoApprover)
                            <p class="text-muted small mb-2">Signing as <strong>{{ $autoApprover }}</strong></p>
                        @else
                            <input type="text" name="approver" class="form-control mb-2" placeholder="Your Name" required>
                        @endif
                        <button type="submit" class="btn btn-primary">Authorize</button>
                    </form>
                    <a href="{{ $declineUrl }}" class="btn btn-outline-danger">Decline</a>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
