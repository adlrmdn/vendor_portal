{{--
    Material Flow dispatch — "Send to Material Flow" button + status badge.
    Material the SUBCON VENDOR returns to us (fabric or accessory) — NOT our
    own returns to a fabric supplier. The actual quantities live in Material
    Reconciliation (fabric-reconciliation.blade.php, Fabric + Accessory
    tables) and the file lives in delivery-note-attachment.blade.php — this
    partial is just the dispatch action + status, shown once per order page.

    Params:
      $order              SubconOrder
      $materialReturnTask ?MaterialReturnTask
      $dispatchRoute      ?string — POST route for "Send to Material Flow" (admin/HO only, null hides everything)
--}}
@php
    $materialReturnTask = $materialReturnTask ?? null;
    $dispatchRoute = $dispatchRoute ?? null;
@endphp

@if($dispatchRoute || $materialReturnTask)
    <div class="card mb-3 border-0 shadow-sm" style="border-radius:12px; border:1px solid rgba(0,0,0,0.08) !important;">
        <div class="card-body p-3 d-flex justify-content-between align-items-center flex-wrap gap-2">
            <div class="fw-semibold small text-secondary text-uppercase" style="letter-spacing:.05em;">
                <i class="fas fa-truck-ramp-box me-1"></i> Material Flow
            </div>
            <div class="d-flex align-items-center gap-2">
                @if($materialReturnTask)
                    <span class="badge {{ $materialReturnTask->isChecked() ? 'bg-success' : 'bg-warning text-dark' }}">
                        {{ $materialReturnTask->isChecked() ? 'Checked by Material Flow' : 'Awaiting Material Flow' }}
                    </span>
                @endif
                @if($dispatchRoute)
                    <form method="POST" action="{{ $dispatchRoute }}"
                          onsubmit="return confirm('Send to Material Flow? Report Validation cannot be sent to the Director until inventory checks the returned material.');" class="m-0">
                        @csrf
                        <button type="submit" class="btn btn-sm btn-outline-warning" {{ $materialReturnTask && ! $materialReturnTask->isChecked() ? 'disabled' : '' }}>
                            <i class="fas fa-paper-plane me-1"></i> Send to Material Flow
                        </button>
                    </form>
                @endif
            </div>
        </div>
    </div>
@endif
