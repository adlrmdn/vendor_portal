@extends('layouts.app')

@section('title', 'PC Data & System Settings')

@section('content')
@include('admin.pc.partials._tokens')

<div class="container-fluid apple-po-wrapper px-3 px-md-4 py-3">
    @include('admin.pc.partials.section-nav', ['pcSection' => 'data'])

    <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-3">
        <div>
            <div class="d-flex align-items-center gap-2 mb-1">
                <span class="apple-section-eyebrow">Preliminary Contract</span>
                <span class="apple-bullet">&bull;</span>
                <span class="apple-section-eyebrow text-muted">Configuration</span>
            </div>
            <h1 class="apple-page-title mb-1">Master Data &amp; Settings</h1>
            <p class="text-muted small mb-0">Manage lookup tables, D365 synchronization, audit trail, and internal approvers</p>
        </div>
    </div>

    {{-- Sub-Navigation Tabs --}}
    <div class="mb-4">
        <ul class="nav apple-segmented-bar" id="pcDataTab" role="tablist">
            <li class="nav-item" role="presentation">
                <button class="apple-segment-item active" id="master-data-tab" data-bs-toggle="tab" data-bs-target="#master-data" type="button" role="tab">
                    <i class="fas fa-database"></i> Master Data
                </button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="apple-segment-item" id="sync-tab" data-bs-toggle="tab" data-bs-target="#sync" type="button" role="tab">
                    <i class="fas fa-sync-alt"></i> D365 Sync
                </button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="apple-segment-item" id="audit-log-tab" data-bs-toggle="tab" data-bs-target="#audit-log" type="button" role="tab">
                    <i class="fas fa-history"></i> Audit Log
                </button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="apple-segment-item" id="approver-settings-tab" data-bs-toggle="tab" data-bs-target="#approver-settings" type="button" role="tab">
                    <i class="fas fa-user-shield"></i> Approver Settings
                </button>
            </li>
        </ul>
    </div>

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
