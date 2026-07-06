{{-- Workflow-stage badge. Param: $order --}}
@php
    $stageMeta = match($order->workflow_stage) {
        'cutting'              => ['secondary', 'fa-scissors',        'Cutting Entry'],
        'cutting_review'       => ['warning',   'fa-hourglass-half',  'Cutting Review'],
        'gramasi'              => ['info',      'fa-weight-hanging',  'Gramasi Entry'],
        'gramasi_review'       => ['warning',   'fa-hourglass-half',  'Gramasi Review'],
        'waiting_distribution' => ['info',      'fa-truck-ramp-box',  'Distribution'],
        'labels'               => ['primary',   'fa-tags',            'Ready to Print'],
        'completed'            => ['success',   'fa-flag-checkered',  'Completed'],
        default                => ['secondary', 'fa-circle',          ucwords(str_replace('_', ' ', (string) $order->workflow_stage))],
    };
@endphp
<span class="badge bg-{{ $stageMeta[0] }}-subtle text-{{ $stageMeta[0] }} border border-{{ $stageMeta[0] }} border-opacity-25 fw-semibold">
    <i class="fas {{ $stageMeta[1] }} me-1"></i> {{ $stageMeta[2] }}
</span>
