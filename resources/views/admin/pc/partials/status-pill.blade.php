{{--
    Compact single-label status for list contexts (one row among many).
    Expects $status.
--}}
@php
    $map = [
        'draft' => ['Draft', 'pc-pill-draft'],
        'pending_approval' => ['Awaiting approval', 'pc-pill-waiting'],
        'approved' => ['Approved', 'pc-pill-neutral'],
        'sent_to_supplier' => ['Sent to vendor', 'pc-pill-waiting'],
        'confirmed' => ['Confirmed', 'pc-pill-confirmed'],
        'supplier_queried' => ['Vendor queried', 'pc-pill-waiting'],
        'rejected' => ['Rejected', 'pc-pill-dead'],
        'cancelled' => ['Cancelled', 'pc-pill-dead'],
        'superseded' => ['Superseded', 'pc-pill-draft'],
    ];
    [$label, $class] = $map[$status] ?? [ucfirst(str_replace('_', ' ', $status)), 'pc-pill-draft'];
@endphp
<span class="pc-pill {{ $class }}">{{ $label }}</span>
