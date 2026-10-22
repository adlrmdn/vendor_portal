{{--
    Renders the contract's position in the real 4-step sequence
    (draft -> pending_vendor_review -> pending_final_approval -> confirmed)
    as connected segments, or a flat strip for the two dead-end states
    (cancelled/superseded) where "progress" no longer means anything.
    Expects $status.
--}}
@php
    $stages = [
        'draft' => 'Draft',
        'pending_vendor_review' => 'Vendor Review',
        'pending_final_approval' => 'Approval',
        'confirmed' => 'Confirmed',
    ];
    $order = array_keys($stages);
    $currentIndex = array_search($status, $order, true);
@endphp

@if ($currentIndex === false)
    <span class="pc-stage-tracker pc-stage-flat {{ $status === 'superseded' ? 'is-superseded' : '' }}">
        {{ $status === 'superseded' ? 'Superseded by a later revision' : 'Cancelled' }}
    </span>
@else
    <div class="pc-stage-tracker">
        @foreach ($order as $i => $key)
            @php $state = $i < $currentIndex ? 'done' : ($i === $currentIndex ? 'current' : ''); @endphp
            <div class="seg {{ $state }}"><span class="dot"></span>{{ $stages[$key] }}</div>
            @if (! $loop->last)
                <div class="bar {{ $i < $currentIndex ? 'done' : '' }}"></div>
            @endif
        @endforeach
    </div>
@endif
