@extends('layouts.app')

@section('title', 'Cutting Plan — ' . $order->order_number)

@section('content')
@php
    $sizes = array_keys($orderQtyBySize);
    $n = fn ($v) => number_format((float) $v);
    $n2 = fn ($v) => number_format((float) $v, 2);
    $n4 = fn ($v) => number_format((float) $v, 4);
@endphp

<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h2 class="mb-0">Cutting Plan</h2>
        <small class="text-muted">{{ $order->order_number }}@if($order->title) <span class="mx-1">·</span> {{ $order->title }}@endif</small>
    </div>
    <a href="{{ route('subcon.admin.orders.view', $order->id) }}" class="btn btn-outline-secondary">
        <i class="fas fa-arrow-left me-1"></i> Back to Work Order
    </a>
</div>

@if(session('success'))
    <div class="alert alert-success py-2 small">{{ session('success') }}</div>
@endif

@if(empty($sizes))
    <div class="alert alert-warning small">
        No production sizes found for this style in VSM yet — the ratio-group table needs a size list to plan against. You can still save marker-type blocks, but the qty cascade won't compute until sizes are available.
    </div>
@endif

<datalist id="fabric-labels">
    @foreach(array_keys($fabricLines) as $label)
        <option value="{{ $label }}">
    @endforeach
</datalist>

<form id="cutting-plan-form" method="POST" action="{{ route('subcon.admin.orders.cutting-plan.save', $order->id) }}">
    @csrf

    <div class="table-responsive mb-4">
        <table class="table table-sm table-bordered mb-0" style="max-width: 700px;">
            <thead class="table-light"><tr><th>Size</th>@foreach($sizes as $size)<th class="text-end">{{ $size }}</th>@endforeach<th class="text-end">Total</th></tr></thead>
            <tbody><tr><td class="fw-semibold">Order Qty</td>@foreach($sizes as $size)<td class="text-end">{{ $n($orderQtyBySize[$size]) }}</td>@endforeach<td class="text-end fw-semibold">{{ $n(array_sum($orderQtyBySize)) }}</td></tr></tbody>
        </table>
    </div>

    <div id="plan-blocks">
        @foreach($blocks as $bi => $entry)
            @include('subcon.partials.cutting-plan-block', ['bi' => $bi, 'block' => $entry['block'], 'computed' => $entry['computed'], 'sizes' => $sizes])
        @endforeach
    </div>

    <button type="button" class="btn btn-outline-secondary mb-4" id="add-block">
        <i class="fas fa-plus me-1"></i> Add Marker Type Block
    </button>

    <div>
        <button type="submit" class="btn btn-primary">
            <i class="fas fa-save me-1"></i> Save Cutting Plan
        </button>
    </div>
</form>

{{-- Server-rendered block template placeholders, used by JS to build new
     blocks/groups client-side without duplicating the Blade markup by hand. --}}
<template id="block-template">
    @include('subcon.partials.cutting-plan-block', ['bi' => '__BI__', 'block' => null, 'computed' => null, 'sizes' => $sizes])
</template>
<template id="group-row-template">
    @include('subcon.partials.cutting-plan-group-row', ['bi' => '__BI__', 'gi' => '__GI__', 'group' => null, 'computedGroup' => null, 'sizes' => $sizes])
</template>

<script>
document.addEventListener('DOMContentLoaded', function () {
    const ALLW_MARKER = 1 / 36;
    const SIZES = @json($sizes);
    const ORDER_QTY = @json($orderQtyBySize);
    const FABRIC_LINES = @json($fabricLines);

    const blocksEl = document.getElementById('plan-blocks');
    const addBlockBtn = document.getElementById('add-block');
    const blockTpl = document.getElementById('block-template').innerHTML;
    const groupTpl = document.getElementById('group-row-template').innerHTML;

    let blockIdx = {{ count($blocks) }};

    function fmt(v) { return isFinite(v) ? (Math.round(v * 100) / 100).toLocaleString('en-US', {maximumFractionDigits: 2}) : '0'; }
    function fmt4(v) { return isFinite(v) ? (Math.round(v * 10000) / 10000).toLocaleString('en-US', {maximumFractionDigits: 4}) : '0'; }

    function nextGroupIndex(blockCard) {
        return blockCard.querySelectorAll('.plan-group').length;
    }

    function addGroupRow(blockCard) {
        const bi = blockCard.dataset.blockIndex;
        const gi = nextGroupIndex(blockCard);
        const html = groupTpl.replaceAll('__BI__', bi).replaceAll('__GI__', gi);
        const tbody = blockCard.querySelector('.plan-groups-body');
        const wrap = document.createElement('tbody');
        wrap.innerHTML = html;
        Array.from(wrap.children).forEach(function (row) { tbody.appendChild(row); });
        recomputeBlock(blockCard);
    }

    function addBlock() {
        const bi = blockIdx++;
        const html = blockTpl.replaceAll('__BI__', bi);
        const wrap = document.createElement('div');
        wrap.innerHTML = html;
        const card = wrap.firstElementChild;
        blocksEl.appendChild(card);
        recomputeBlock(card);
    }

    function resolveFabricAvailable(blockCard) {
        const overrideInput = blockCard.querySelector('.block-fabric-override');
        const override = parseFloat(overrideInput.value);
        if (! isNaN(override) && overrideInput.value !== '') {
            return {yds: override, source: 'override'};
        }
        const markerType = blockCard.querySelector('.block-marker-type').value.trim();
        const line = FABRIC_LINES[markerType];
        if (! line) return {yds: null, source: null};
        if (line.goods_receive !== null && line.goods_receive !== undefined) {
            return {yds: parseFloat(line.goods_receive), source: 'goods_receive'};
        }
        if (line.fabric_sent !== null && line.fabric_sent !== undefined) {
            return {yds: parseFloat(line.fabric_sent), source: 'fabric_sent'};
        }
        return {yds: null, source: null};
    }

    function recomputeBlock(blockCard) {
        const groupRows = Array.from(blockCard.querySelectorAll('.plan-group'));
        let remaining = Object.assign({}, ORDER_QTY);
        let totalQty = 0, totalFabricYds = 0;

        groupRows.forEach(function (tr, idx) {
            const letter = String.fromCharCode(65 + idx);
            tr.querySelector('.group-letter').textContent = letter;

            const jmlLayer = parseFloat(tr.querySelector('.group-jml-layer').value) || 0;
            const markerLength = parseFloat(tr.querySelector('.group-marker-length').value) || 0;

            let ratioTotal = 0;
            const qtyMarker = {};
            tr.querySelectorAll('.group-rasio').forEach(function (inp) {
                const size = inp.dataset.size;
                const rasio = parseFloat(inp.value) || 0;
                ratioTotal += rasio;
                qtyMarker[size] = rasio * jmlLayer;
            });
            tr.querySelector('.group-ratio-total').textContent = fmt(ratioTotal);

            const kebFabric = jmlLayer * (markerLength + ALLW_MARKER);
            tr.querySelector('.group-keb-fabric').textContent = fmt(kebFabric);
            totalFabricYds += kebFabric;

            const qtyRow = tr.nextElementSibling;
            const sisaRow = qtyRow.nextElementSibling;
            let groupQtyTotal = 0;
            SIZES.forEach(function (size) {
                const qm = qtyMarker[size] || 0;
                groupQtyTotal += qm;
                const sisa = (remaining[size] || 0) - qm;
                const qmCell = qtyRow.querySelector('.group-qty-marker[data-size="' + size + '"]');
                const sisaCell = sisaRow.querySelector('.group-sisa-marker[data-size="' + size + '"]');
                if (qmCell) qmCell.textContent = fmt(qm);
                if (sisaCell) sisaCell.textContent = fmt(sisa);
                remaining[size] = sisa;
            });
            totalQty += groupQtyTotal;
        });

        const totalQtyEl = blockCard.querySelector('.block-total-qty');
        const totalFabricEl = blockCard.querySelector('.block-total-fabric');
        const avgConsEl = blockCard.querySelector('.block-avg-cons');
        const tolFabricEl = blockCard.querySelector('.block-tol-fabric');
        const estQtyEl = blockCard.querySelector('.block-est-qty');
        const fabricHintEl = blockCard.querySelector('.block-fabric-hint');

        if (totalQtyEl) totalQtyEl.textContent = fmt(totalQty);
        if (totalFabricEl) totalFabricEl.textContent = fmt(totalFabricYds);
        const avgCons = totalQty > 0 ? totalFabricYds / totalQty : 0;
        if (avgConsEl) avgConsEl.textContent = fmt4(avgCons);
        const tolPct = parseFloat(blockCard.querySelector('.block-tolerance').value) || 0;
        if (tolFabricEl) tolFabricEl.textContent = fmt(totalFabricYds * (1 + tolPct / 100));

        const avail = resolveFabricAvailable(blockCard);
        if (fabricHintEl) {
            fabricHintEl.textContent = avail.yds !== null
                ? 'Auto: ' + fmt(avail.yds) + ' yds (' + avail.source.replace('_', ' ') + ') — leave blank to use it'
                : 'No linked fabric-available figure yet for this marker type — enter one above, or leave blank.';
        }
        if (estQtyEl) {
            estQtyEl.textContent = (avail.yds !== null && avgCons > 0) ? fmt(Math.floor(avail.yds / avgCons)) + ' pcs' : '—';
        }
    }

    function recomputeAll() {
        document.querySelectorAll('.plan-block').forEach(recomputeBlock);
    }

    addBlockBtn.addEventListener('click', addBlock);

    blocksEl.addEventListener('click', function (e) {
        const addGroupBtn = e.target.closest('.add-group');
        if (addGroupBtn) {
            addGroupRow(addGroupBtn.closest('.plan-block'));
            return;
        }
        const removeGroupBtn = e.target.closest('.remove-group');
        if (removeGroupBtn) {
            const blockCard = removeGroupBtn.closest('.plan-block');
            const tr = removeGroupBtn.closest('.plan-group');
            const qtyRow = tr.nextElementSibling;
            const sisaRow = qtyRow.nextElementSibling;
            sisaRow.remove();
            qtyRow.remove();
            tr.remove();
            recomputeBlock(blockCard);
            return;
        }
        const removeBlockBtn = e.target.closest('.remove-block');
        if (removeBlockBtn) {
            removeBlockBtn.closest('.plan-block').remove();
        }
    });

    blocksEl.addEventListener('input', function (e) {
        const blockCard = e.target.closest('.plan-block');
        if (blockCard) recomputeBlock(blockCard);
    });

    recomputeAll();

    // Reindex `name="blocks[i]..."` / group indices right before submit, since
    // removing a block/group in the middle leaves gaps that would otherwise
    // post as sparse/misordered arrays.
    document.getElementById('cutting-plan-form').addEventListener('submit', function () {
        document.querySelectorAll('.plan-block').forEach(function (blockCard, bi) {
            blockCard.dataset.blockIndex = bi;
            blockCard.querySelectorAll('[name]').forEach(function (el) {
                el.name = el.name.replace(/^blocks\[\d+\]/, 'blocks[' + bi + ']');
            });
            blockCard.querySelectorAll('.plan-group').forEach(function (tr, gi) {
                tr.querySelectorAll('[name]').forEach(function (el) {
                    el.name = el.name.replace(/\[groups\]\[\d+\]/, '[groups][' + gi + ']');
                });
            });
        });
    });
});
</script>
@endsection
