{{--
    Delivery Note Attachment — supporting file evidence for material the
    subcon vendor returns to us (fabric or accessory) — NOT our own returns
    to a fabric supplier. Separate from Material Reconciliation's quantity
    table (fabric-reconciliation.blade.php): this is just the file.

    Params:
      $order            SubconOrder
      $materialReturns  Collection<MaterialReturnAttachment>
      $canSubmit        bool — attach window open (also gates the delete button — same window)
      $uploadRoute      string — POST route for the file form
      $viewerRole       ?string — 'admin' or 'vendor': which side is viewing this page. The
                         Remove button only shows on a row this side itself uploaded — see
                         MaterialReturnService::deleteAttachment()'s "own side only" rule.
      $deleteRouteName  ?string — named route for delete, taking [routeParam, attachment->id]
      $deleteRouteParam ?string — the route's other param (order id for in-app, token for the
                         signed HO link)
      $deleteMethod     string — 'DELETE' (in-app, default) or 'POST' (the no-login signed
                         form, which POSTs to a plain /delete path instead of spoofing DELETE)
--}}
@php
    $materialReturns = $materialReturns ?? collect();
    $canSubmit = $canSubmit ?? false;
    $viewerRole = $viewerRole ?? null;
    $deleteRouteName = $deleteRouteName ?? null;
    $deleteRouteParam = $deleteRouteParam ?? null;
    $deleteMethod = $deleteMethod ?? 'DELETE';
    // Random per-render salt: this partial is included more than once on the
    // same page (e.g. once per stage tab on the vendor order view), so the
    // preview collapse ids must not collide across inclusions.
    $previewSalt = \Illuminate\Support\Str::random(8);
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
                    @php
                        $attUrl = \Illuminate\Support\Facades\Storage::disk($att->s3_disk)->temporaryUrl($att->s3_path, now()->addMinutes(10));
                        $attExt = strtolower(pathinfo((string) $att->original_filename, PATHINFO_EXTENSION));
                        $isPdf = $att->mime_type === 'application/pdf' || $attExt === 'pdf';
                        $isImage = str_starts_with((string) $att->mime_type, 'image/') || in_array($attExt, ['jpg', 'jpeg', 'png'], true);
                        $previewId = 'attPreview-'.$previewSalt.'-'.$att->id;
                    @endphp
                    <li class="py-1 {{ ! $loop->last ? 'border-bottom' : '' }}">
                        <div class="d-flex justify-content-between align-items-start gap-2">
                            <div>
                                <a href="{{ $attUrl }}" target="_blank" rel="noopener">{{ $att->original_filename }}</a>
                                <span class="badge {{ $att->uploaded_by_role === 'vendor' ? 'bg-primary-subtle text-primary' : 'bg-secondary-subtle text-secondary' }} border ms-1">{{ $att->uploaded_by_role === 'vendor' ? 'Vendor' : 'MD Prod' }}</span>
                                @if($isPdf || $isImage)
                                    <button type="button" class="btn btn-sm btn-link p-0 ms-1 align-baseline" data-bs-toggle="collapse" data-bs-target="#{{ $previewId }}">
                                        <i class="fas fa-eye me-1"></i>Preview
                                    </button>
                                @endif
                                @if($canSubmit && $deleteRouteName && $viewerRole && $att->uploaded_by_role === $viewerRole)
                                    <form method="POST" action="{{ route($deleteRouteName, [$deleteRouteParam, $att->id]) }}" class="d-inline" onsubmit="return confirm('Remove this delivery note? This cannot be undone.');">
                                        @csrf
                                        @if($deleteMethod === 'DELETE')
                                            @method('DELETE')
                                        @endif
                                        <button type="submit" class="btn btn-sm btn-link text-danger p-0 ms-1 align-baseline">
                                            <i class="fas fa-trash-can me-1"></i>Remove
                                        </button>
                                    </form>
                                @endif
                                @if($att->note)
                                    <div class="text-muted">{{ $att->note }}</div>
                                @endif
                            </div>
                            <div class="text-muted text-nowrap">{{ optional($att->uploaded_at)->format('d M Y H:i') }}</div>
                        </div>
                        @if($isPdf)
                            <div class="collapse mt-2" id="{{ $previewId }}">
                                <iframe src="{{ $attUrl }}" style="width:100%; height:70vh; border:1px solid rgba(0,0,0,0.1); border-radius:8px;"></iframe>
                            </div>
                        @elseif($isImage)
                            <div class="collapse mt-2" id="{{ $previewId }}">
                                <img src="{{ $attUrl }}" alt="{{ $att->original_filename }}" class="img-fluid" style="max-height:70vh; border:1px solid rgba(0,0,0,0.1); border-radius:8px;">
                            </div>
                        @endif
                    </li>
                @endforeach
            </ul>
        @endif
    </div>
</div>
