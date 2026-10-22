{{--
    Compact single-label status for list contexts (one row among many).
    The full stage-tracker is the right component for a single contract's
    own page, where there's room and it's genuinely about that one
    contract's journey — repeating it down every row of a table just adds
    visual noise and slows scanning, so lists get this instead.
    Expects $status.
--}}
@php
    $map = [
        'draft' => ['Draft', 'pc-pill-draft'],
        'pending_vendor_review' => ['Awaiting vendor', 'pc-pill-waiting'],
        'pending_final_approval' => ['Awaiting approval', 'pc-pill-waiting'],
        'confirmed' => ['Confirmed', 'pc-pill-confirmed'],
        'cancelled' => ['Cancelled', 'pc-pill-dead'],
        'superseded' => ['Superseded', 'pc-pill-neutral'],
    ];
    [$label, $class] = $map[$status] ?? [ucfirst($status), 'pc-pill-draft'];
@endphp
<span class="pc-pill {{ $class }}">{{ $label }}</span>
