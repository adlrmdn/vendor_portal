{{--
    Renders the contract's position in the 4-step sequence:
    draft -> pending_approval -> approved -> sent_to_supplier -> confirmed
    or a flat strip for side/terminal states (rejected, cancelled, superseded, supplier_queried).
    Expects $status.
--}}
@php
    $stages = [
        'draft' => 'Draft',
        'pending_approval' => 'Approval',
        'approved' => 'Approved',
        'sent_to_supplier' => 'Vendor Review',
        'confirmed' => 'Confirmed',
    ];
    $order = array_keys($stages);
    $currentIndex = array_search($status, $order, true);
@endphp

@if ($currentIndex === false)
    @php
        $sideLabels = [
            'rejected' => ['Rejected (returned to draft)', ''],
            'supplier_queried' => ['Vendor queried (action required)', ''],
            'superseded' => ['Superseded by a later revision', 'is-superseded'],
            'cancelled' => ['Cancelled', ''],
        ];
        [$sideLabel, $sideClass] = $sideLabels[$status] ?? [ucfirst(str_replace('_', ' ', $status)), ''];
    @endphp
    <span class="pc-stage-tracker pc-stage-flat {{ $sideClass }}">
        {{ $sideLabel }}
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
