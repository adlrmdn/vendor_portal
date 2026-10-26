@extends('layouts.app')

@section('title', 'PC Data & System Settings')

@section('content')
@include('admin.pc.partials._tokens')

<div class="container-fluid px-0">
    @include('admin.pc.partials.section-nav', ['pcSection' => 'data'])

    <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-3">
        <div>
            <h1 class="h3 fw-bold text-dark mb-1">Master Data & Settings</h1>
            <p class="text-muted small mb-0">Manage lookup tables, D365 entity synchronization, audit trail, and internal approvers</p>
        </div>
    </div>

    {{-- Sub-Navigation Tabs --}}
    <ul class="nav nav-tabs mb-4" id="pcDataTab" role="tablist">
        <li class="nav-item" role="presentation">
            <button class="nav-link active fw-semibold" id="master-data-tab" data-bs-toggle="tab" data-bs-target="#master-data" type="button" role="tab">
                <i class="fas fa-database me-1 text-primary"></i> Master Data
            </button>
        </li>
        <li class="nav-item" role="presentation">
            <button class="nav-link fw-semibold" id="sync-tab" data-bs-toggle="tab" data-bs-target="#sync" type="button" role="tab">
                <i class="fas fa-sync-alt me-1 text-success"></i> D365 Sync
            </button>
        </li>
        <li class="nav-item" role="presentation">
            <button class="nav-link fw-semibold" id="audit-log-tab" data-bs-toggle="tab" data-bs-target="#audit-log" type="button" role="tab">
                <i class="fas fa-history me-1 text-info"></i> Audit Log
            </button>
        </li>
        <li class="nav-item" role="presentation">
            <button class="nav-link fw-semibold" id="approver-settings-tab" data-bs-toggle="tab" data-bs-target="#approver-settings" type="button" role="tab">
                <i class="fas fa-user-shield me-1 text-warning"></i> Approver Settings
            </button>
        </li>
    </ul>

    <div class="tab-content" id="pcDataTabContent">
        <div class="tab-pane fade show active" id="master-data" role="tabpanel">
            @include('admin.pc.partials.master-data')
        </div>
        <div class="tab-pane fade" id="sync" role="tabpanel">
            @include('admin.pc.partials.sync')
        </div>
        <div class="tab-pane fade" id="audit-log" role="tabpanel">
            @include('admin.pc.partials.audit-log')
        </div>
        <div class="tab-pane fade" id="approver-settings" role="tabpanel">
            @include('admin.pc.partials.approver-settings')
        </div>
    </div>
</div>

@push('scripts')
<script>
    (function () {
        function activateTab(id) {
            const btn = document.getElementById(id + '-tab');
            if (btn && window.bootstrap) {
                new bootstrap.Tab(btn).show();
            }
        }

        if (window.location.hash) {
            const raw = window.location.hash.replace('#', '');
            if (['master-data', 'sync', 'audit-log', 'approver-settings'].includes(raw)) {
                activateTab(raw);
            }
        }

        const tabButtons = document.querySelectorAll('#pcDataTab button[data-bs-toggle="tab"]');
        tabButtons.forEach(function (btn) {
            btn.addEventListener('shown.bs.tab', function (e) {
                const target = e.target.getAttribute('data-bs-target');
                if (target && history.replaceState) {
                    history.replaceState(null, '', target);
                }
            });
        });
    })();
</script>
@endpush
@endsection
