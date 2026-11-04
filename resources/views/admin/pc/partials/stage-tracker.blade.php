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
            'rejected' => ['Rejected', 'Returned to draft', 'capsule-cancelled', 'text-danger'],
            'supplier_queried' => ['Action Required', 'Vendor queried contract', 'capsule-pending', 'text-warning'],
            'superseded' => ['Superseded', 'Revised in newer contract', 'capsule-cancelled', 'text-secondary'],
            'cancelled' => ['Cancelled', 'Contract voided', 'capsule-cancelled', 'text-muted'],
        ];
        [$title, $desc, $capsule, $textColor] = $sideLabels[$status] ?? [ucfirst(str_replace('_', ' ', $status)), '', 'capsule-cancelled', 'text-dark'];
    @endphp
    <div class="d-inline-flex align-items-center gap-2">
        <span class="apple-status-capsule {{ $capsule }} px-3 py-1">
            <span class="capsule-dot"></span>
            {{ $title }}
        </span>
        @if($desc)
            <span class="small text-muted fst-italic">({{ $desc }})</span>
        @endif
    </div>
@else
    <div class="apple-stepper-wrap">
        @foreach ($order as $i => $key)
            @php 
                $isDone = $i < $currentIndex;
                $isCurrent = $i === $currentIndex;
                $nodeState = $isDone ? 'done' : ($isCurrent ? 'active' : 'upcoming');
            @endphp
            <span class="apple-step-node {{ $nodeState }}">
                <span class="apple-step-circle">
                    @if($isDone)
                        <i class="fas fa-check" style="font-size: 0.6rem;"></i>
                    @else
                        {{ $i + 1 }}
                    @endif
                </span>
                <span>{{ $stages[$key] }}</span>
            </span>
            @if (! $loop->last)
                <i class="fas fa-chevron-right apple-step-chevron"></i>
            @endif
        @endforeach
    </div>
@endif
