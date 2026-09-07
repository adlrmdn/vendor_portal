{{-- Debit note + QC inspection report combined, one PDF for the deduction RPA
     job's D365 attachment. Same single-Blade-view + CSS page-break pattern as
     every other multi-page PDF in this app (no PDF-merge library needed). --}}
@php
    // Same deduction-line derivation inspection-report-body.blade.php computes
    // for BA Section 4 — duplicated here (not shared state) so the debit-note
    // page's claim table lists the *same* line items instead of one lump sum.
    // Keep this in sync with the @php block at the top of that partial.
    $debitFabricDeductionLines = $fabricLines->filter(fn ($f) => ($f->deduction ?? 0) > 0)->values();
    $debitHasAnyDeduction = $deductions['exceedingRejectQty'] > 0 || $deductions['sumBarangHilang'] > 0
        || $deductionLines->isNotEmpty() || $debitFabricDeductionLines->isNotEmpty();
@endphp
<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    @include('qc.pdf.partials.inspection-report-styles')
</head>
<body>
@include('qc.pdf.debit-note-page')
<div style="page-break-before: always;"></div>
@include('qc.pdf.partials.inspection-report-body')
</body>
</html>
