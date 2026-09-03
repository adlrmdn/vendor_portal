{{--
    Delivery Note Attachment — supporting file evidence for material the
    subcon vendor returns to us (fabric or accessory) — NOT our own returns
    to a fabric supplier. Separate from Material Reconciliation's quantity
    table (fabric-reconciliation.blade.php): this is just the file.

    Params:
      $order          SubconOrder
      $materialReturns Collection<MaterialReturnAttachment>
      $canSubmit      bool — attach window open
      $uploadRoute    string — POST route for the file form
--}}
@php
    $materialReturns = $materialReturns ?? collect();
    $canSubmit = $canSubmit ?? false;
@endphp

<div class="card mt-3 shadow-sm border-0" style="border-radius:12px; border:1px solid rgba(0,0,0,0.08);">
    <div class="card-body p-3">
        <div class="fw-semibold small text-secondary text-uppercase mb-2" style="letter-spacing:.05em;">
            <i class="fas fa-paperclip me-1"></i> Delivery Note Attachment
        </div>

        @if($canSubmit)
            <form method="POST" action="{{ $uploadRoute }}" enctype="multipart/form-data" class="row g-2 align-items-end mb-3">
                @csrf
                <div class="col-12 col-md-5">
                    <input type="file" name="file" class="form-control form-control-sm" accept=".pdf,.jpg,.jpeg,.png" required>
                </div>
                <div class="col-12 col-md-5">
                    <input type="text" name="note" class="form-control form-control-sm" placeholder="Note (optional)" maxlength="1000">
                </div>
                <div class="col-12 col-md-2">
                    <button type="submit" class="btn btn-sm btn-outline-primary w-100"><i class="fas fa-paperclip me-1"></i> Attach</button>
                </div>
            </form>
        @else
            <div class="text-muted small mb-2"><i class="fas fa-lock me-1"></i> Attaching is only open while this window is active.</div>
        @endif

        @if($materialReturns->isEmpty())
            <div class="text-muted small">No delivery notes attached yet.</div>
        @else
            <ul class="list-unstyled mb-0 small">
                @foreach($materialReturns as $att)
                    <li class="d-flex justify-content-between align-items-start gap-2 py-1 {{ ! $loop->last ? 'border-bottom' : '' }}">
                        <div>
                            <a href="{{ \Illuminate\Support\Facades\Storage::disk($att->s3_disk)->temporaryUrl($att->s3_path, now()->addMinutes(10)) }}" target="_blank" rel="noopener">{{ $att->original_filename }}</a>
                            <span class="badge {{ $att->uploaded_by_role === 'vendor' ? 'bg-primary-subtle text-primary' : 'bg-secondary-subtle text-secondary' }} border ms-1">{{ $att->uploaded_by_role === 'vendor' ? 'Vendor' : 'MD Prod' }}</span>
                            @if($att->note)
                                <div class="text-muted">{{ $att->note }}</div>
                            @endif
                        </div>
                        <div class="text-muted text-nowrap">{{ optional($att->uploaded_at)->format('d M Y H:i') }}</div>
                    </li>
                @endforeach
            </ul>
        @endif
    </div>
</div>
