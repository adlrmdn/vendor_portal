@extends('layouts.app')

@section('title', 'Execution Logs')

@section('content')
<!-- Google Fonts -->
<link href="https://fonts.googleapis.com/css2?family=Outfit:wght@400;500;600;700&family=Inter:wght@400;500;600&display=swap" rel="stylesheet">

<style>
    .premium-title {
        font-family: 'Outfit', sans-serif;
        font-weight: 700;
        color: #1e293b;
        letter-spacing: -0.02em;
    }
    .premium-card {
        border-radius: 16px;
        border: 1px solid rgba(0, 0, 0, 0.05);
        box-shadow: 0 10px 30px rgba(0, 0, 0, 0.02);
        background: #ffffff;
        overflow: hidden;
    }
    .premium-table th {
        font-family: 'Outfit', sans-serif;
        font-weight: 600;
        text-transform: uppercase;
        font-size: 0.75rem;
        letter-spacing: 0.05em;
        color: #64748b;
        background-color: #f8fafc;
        border-bottom: 2px solid #edf2f7;
        padding: 16px;
    }
    .premium-table td {
        padding: 16px;
        vertical-align: middle;
        font-family: 'Inter', sans-serif;
        color: #334155;
        font-size: 0.875rem;
        border-bottom: 1px solid #f1f5f9;
    }
    .premium-table tr:hover td {
        background-color: #f8fafc;
    }
    .badge-premium {
        font-family: 'Outfit', sans-serif;
        font-weight: 600;
        font-size: 0.75rem;
        padding: 6px 12px;
        border-radius: 9999px;
        display: inline-block;
    }
    .code-pill {
        background-color: #f8fafc;
        color: #0f172a;
        font-family: var(--bs-font-monospace);
        padding: 8px 12px;
        border-radius: 8px;
        font-size: 0.8rem;
        border: 1px solid #e2e8f0;
        max-width: 100%;
        overflow-x: auto;
        white-space: pre-wrap;
    }
    .log-details-btn {
        padding: 4px 8px;
        font-size: 0.75rem;
        border-radius: 6px;
    }
</style>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h2 class="premium-title mb-0">Background Job Logs</h2>
</div>

<div class="card premium-card">
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table premium-table mb-0">
                <thead>
                    <tr>
                        <th style="width: 180px;">Timestamp</th>
                        <th style="width: 180px;">Order #</th>
                        <th style="width: 150px;">Job Type</th>
                        <th style="width: 120px;">Status</th>
                        <th>Details / Messages</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($logs as $log)
                        <tr>
                            <td class="text-nowrap text-muted">
                                {{ $log->created_at->timezone('Asia/Jakarta')->format('Y-m-d H:i:s') }}
                            </td>
                            <td>
                                @if($log->order_id)
                                    <a href="{{ route('subcon.admin.orders.view', $log->order_id) }}" class="fw-semibold text-primary text-decoration-none">
                                        {{ $log->order_number }}
                                    </a>
                                @else
                                    <span class="text-muted">{{ $log->order_number }}</span>
                                @endif
                            </td>
                            <td>
                                @php
                                    $jobTypeMeta = match($log->job_type) {
                                        'cutting'          => ['bg-primary text-white', 'Cutting Sync'],
                                        'gramasi'          => ['bg-info text-dark', 'Gramasi Sync'],
                                        'label_generation' => ['bg-warning text-dark', 'Label Gen'],
                                        default            => ['bg-secondary text-white', ucwords($log->job_type)]
                                    };
                                @endphp
                                <span class="badge-premium {{ $jobTypeMeta[0] }}">
                                    {{ $jobTypeMeta[1] }}
                                </span>
                            </td>
                            <td>
                                @if($log->status === 'success')
                                    <span class="badge bg-success badge-premium text-white">
                                        <i class="fas fa-check-circle me-1"></i> Success
                                    </span>
                                @else
                                    <span class="badge bg-danger badge-premium text-white">
                                        <i class="fas fa-exclamation-circle me-1"></i> Failed
                                    </span>
                                @endif
                            </td>
                            <td>
                                @if($log->status === 'failed' && strlen($log->message) > 120)
                                    <div>
                                        <span class="text-danger fw-semibold">{{ Str::limit($log->message, 120) }}</span>
                                        <button class="btn btn-sm btn-outline-secondary log-details-btn ms-2" type="button" 
                                                data-bs-toggle="collapse" data-bs-target="#logDetails-{{ $log->id }}">
                                            Show Details
                                        </button>
                                        <div class="collapse mt-2" id="logDetails-{{ $log->id }}">
                                            <div class="code-pill text-danger">{{ $log->message }}</div>
                                        </div>
                                    </div>
                                @else
                                    <span class="{{ $log->status === 'failed' ? 'text-danger fw-semibold' : 'text-muted' }}">
                                        {{ $log->message }}
                                    </span>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="text-center p-5 text-muted">
                                <i class="fas fa-cog fa-3x mb-3 text-muted opacity-50"></i>
                                <p class="mb-0 fw-semibold">No execution logs found.</p>
                                <small>Logs will appear here once background sync or label generation jobs run.</small>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        
        @if($logs->hasPages())
            <div class="p-4 border-top">
                {{ $logs->links() }}
            </div>
        @endif
    </div>
</div>
@endsection
