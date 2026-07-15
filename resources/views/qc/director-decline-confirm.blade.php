@extends('layouts.app')

@section('title', 'Confirm Director Rejection')
@section('bare', '1')

@section('content')
<div class="container py-5">
    <div class="row justify-content-center">
        <div class="col-12 col-md-7 col-lg-6">
            <div class="card border-0 shadow-sm" style="border-radius: 16px;">
                <div class="card-body text-center p-5">
                    <div class="mb-3"><i class="fas fa-triangle-exclamation fa-3x text-warning"></i></div>
                    <h4 class="fw-bold mb-2">Reject this inspection?</h4>
                    <p class="text-muted mb-4">You are about to <strong>reject</strong> this packaging inspection at the Director stage. It will be sent <strong>back to MD Production</strong> for re-approval — the factory confirmation is kept.</p>

                    <dl class="row small text-start mb-4">
                        @if($subcon ?? null)
                            <dt class="col-5 text-muted fw-normal">Work Order</dt>
                            <dd class="col-7 fw-semibold">{{ $subcon->order_number }}</dd>
                        @endif
                        @if($productionGroup ?? null)
                            <dt class="col-5 text-muted fw-normal">Production Group</dt>
                            <dd class="col-7">{{ $productionGroup }}</dd>
                        @endif
                        <dt class="col-5 text-muted fw-normal">Project</dt>
                        <dd class="col-7">{{ $row->project_id }}</dd>
                    </dl>

                    <form method="POST" action="{{ route('qc.director-decline.submit', ['token' => $token]) }}">
                        @csrf
                        @if(request('as'))<input type="hidden" name="as" value="{{ request('as') }}">@endif
                        <div class="mb-3 text-start">
                            <label for="reason" class="form-label small fw-semibold">Reason <span class="text-muted fw-normal">(optional — relayed to MD Production)</span></label>
                            <textarea name="reason" id="reason" rows="3" maxlength="1000" class="form-control form-control-sm" placeholder="Why is this being returned?"></textarea>
                        </div>
                        <button type="submit" class="btn btn-danger px-4">
                            <i class="fas fa-circle-xmark me-1"></i> Confirm Rejection
                        </button>
                    </form>
                </div>
            </div>
            <p class="text-center text-muted small mt-3 mb-0">If you didn't mean to reject, just close this window — nothing has been recorded yet.</p>
        </div>
    </div>
</div>
@endsection
