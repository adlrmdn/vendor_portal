{{--
    One (ProductionGroup, Operation) row from SubconAdminController's
    add-process row builder — a plain array, not a SubconOrder — used both
    by the "Add Process Only" view mode and (search-only) inline within the
    plain "All Work Orders" results. Expects $order (the row array) and
    $ordersByGroup (ProductionGroup -> SubconOrder, for display fallback).
--}}
@php
    $linkedOrder = $ordersByGroup->get($order['production_group']);
    // The PO matched to THIS row's own operation (not just any PO the group
    // happens to have) — see SubconAdminController::buildAddProcessRows().
    // Every row reaching this partial already has one: rows without a
    // confirmed match are filtered out before pagination/display.
    $addproPo = $order['matched_po'] ?? null;
    $extraPos = max(0, count($order['addpro_pos'] ?? []) - 1);
@endphp
<tr>
    <td>
        {{-- Always label what's shown here — a bare unlabeled PO pill is
             indistinguishable from an "Add Process PO" pill at a glance, but
             the two can be genuinely different POs, or (for a
             standalone/non-PLM group with only one PO on record) the very
             same PO number showing up two ways: once as the vendor's synced
             work order, once tagged AddPro-pool in D365. --}}
        @if($addproPo)
            <span class="code-pill" style="background-color: #fff4e6; border-color: #ffe0b3;">{{ $addproPo['po_number'] }}</span>
            <div class="text-muted small mt-1">Add Process PO{{ $extraPos > 0 ? ' (+'.$extraPos.' more)' : '' }}</div>
        @elseif($linkedOrder)
            <span class="code-pill">{{ $linkedOrder->order_number }}</span>
            <div class="text-muted small mt-1">Work Order</div>
        @else
            <span class="text-muted small">—</span>
        @endif
    </td>
    <td><span class="code-pill text-primary" style="background-color: #eff6ff; border-color: #dbeafe;">{{ $order['production_group'] }}</span></td>
    <td class="fw-semibold">
        @if($addproPo)
            {{ $addproPo['vendor_name'] ?? $addproPo['vendor_code'] ?? '—' }}
        @else
            {{ $linkedOrder?->vendor?->name ?? '— (in-house / unregistered)' }}
        @endif
    </td>
    <td>{{ $order['style_name'] ?? $linkedOrder?->title ?? '—' }}</td>
    <td><span class="badge bg-light text-dark border">{{ $order['sizes_count'] ?? 0 }} sizes</span></td>
    <td>
        @php
            // The matched PO's own D365 RequestedDeliveryDate first (this
            // row's actual Add Process PO) — falls back to the linked CMT
            // work order's due_date when the PO doesn't carry one.
            $addProcessDueDate = $addproPo['due_date'] ?? null;
            $addProcessDueDate = $addProcessDueDate ? \Illuminate\Support\Carbon::parse($addProcessDueDate) : $linkedOrder?->due_date;
        @endphp
        {{ $addProcessDueDate?->format('d M Y') ?? '—' }}
    </td>
    <td><span class="badge bg-primary-subtle text-primary border">{{ $order['operation'] }}</span></td>
    <td>
        <span class="badge-workflow bg-{{ $order['reported'] ? 'success' : 'secondary' }}">{{ $order['reported'] ? 'Reported' : 'Not reported' }}</span>
        {{-- "Reported" = Official Report (Berita Acara) actually sent; qty being
             filled in is only a precondition for that, shown separately so the
             two don't get read as the same thing. --}}
        <div class="text-muted small mt-1">{{ $order['qty_filled'] ? 'Qty filled' : 'Qty not filled' }}</div>
    </td>
    <td class="text-end">
        <a href="{{ route('subcon.admin.add-process.index', ['prg' => $order['production_group']]) }}" class="btn btn-sm btn-outline-primary rounded-pill px-3">
            <i class="fas fa-eye me-1"></i> View
        </a>
    </td>
</tr>
