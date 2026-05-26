@extends('admin.master')

@section('content')
<div class="content notif-logs-page">

    {{-- Header --}}
    <div class="d-md-flex justify-content-md-between align-items-md-center mb-4">
        <div>
            <h1 class="h3 mb-1">Notification Logs</h1>
            <p class="text-muted mb-0">Track every email and WhatsApp message sent by the system.</p>
        </div>
    </div>

    {{-- Stats --}}
    <div class="notif-stats mb-4">
        <div class="notif-stat-card notif-stat-sent">
            <span class="notif-stat-icon"><i class="fa fa-circle-check"></i></span>
            <div>
                <div class="notif-stat-num">{{ number_format($stats['sent']) }}</div>
                <div class="notif-stat-label">Sent</div>
            </div>
        </div>
        <div class="notif-stat-card notif-stat-failed">
            <span class="notif-stat-icon"><i class="fa fa-circle-xmark"></i></span>
            <div>
                <div class="notif-stat-num">{{ number_format($stats['failed']) }}</div>
                <div class="notif-stat-label">Failed</div>
            </div>
        </div>
        <div class="notif-stat-card notif-stat-skipped">
            <span class="notif-stat-icon"><i class="fa fa-circle-minus"></i></span>
            <div>
                <div class="notif-stat-num">{{ number_format($stats['skipped']) }}</div>
                <div class="notif-stat-label">Skipped</div>
            </div>
        </div>
        <div class="notif-stat-card notif-stat-total">
            <span class="notif-stat-icon"><i class="fa fa-paper-plane"></i></span>
            <div>
                <div class="notif-stat-num">{{ number_format($stats['sent'] + $stats['failed'] + $stats['skipped']) }}</div>
                <div class="notif-stat-label">Total</div>
            </div>
        </div>
    </div>

    {{-- Filters --}}
    <form method="GET" action="{{ route('admin.notification-logs.index') }}" class="notif-filters mb-4">
        {{-- Channel tabs --}}
        <div class="notif-channel-tabs">
            <a href="{{ route('admin.notification-logs.index', request()->except('channel', 'page')) }}"
               class="notif-channel-tab {{ !request('channel') ? 'active' : '' }}">
                <i class="fa fa-list-ul me-1"></i> All
            </a>
            <a href="{{ route('admin.notification-logs.index', array_merge(request()->except('channel', 'page'), ['channel' => 'email'])) }}"
               class="notif-channel-tab {{ request('channel') === 'email' ? 'active' : '' }}">
                <i class="fa fa-envelope me-1"></i> Email
            </a>
            <a href="{{ route('admin.notification-logs.index', array_merge(request()->except('channel', 'page'), ['channel' => 'whatsapp'])) }}"
               class="notif-channel-tab {{ request('channel') === 'whatsapp' ? 'active' : '' }}">
                <i class="fa-brands fa-whatsapp me-1"></i> WhatsApp
            </a>
        </div>

        {{-- Other filters --}}
        <div class="notif-filter-row">
            <select name="status" class="notif-filter-input no-select2" onchange="this.form.submit()">
                <option value="">All Statuses</option>
                <option value="sent"    {{ request('status') === 'sent'    ? 'selected' : '' }}>Sent</option>
                <option value="failed"  {{ request('status') === 'failed'  ? 'selected' : '' }}>Failed</option>
                <option value="skipped" {{ request('status') === 'skipped' ? 'selected' : '' }}>Skipped</option>
            </select>

            <input type="text" name="search" class="notif-filter-input"
                   placeholder="Search recipient or subject…"
                   value="{{ request('search') }}">

            <input type="date" name="from" class="notif-filter-input"
                   value="{{ request('from') }}" title="From date">

            <input type="date" name="to" class="notif-filter-input"
                   value="{{ request('to') }}" title="To date">

            <button type="submit" class="notif-filter-apply">
                <i class="fa fa-filter me-1"></i> Filter
            </button>

            @if(request()->hasAny(['status','search','from','to','channel']))
                <a href="{{ route('admin.notification-logs.index') }}" class="notif-filter-clear">
                    <i class="fa fa-times me-1"></i> Clear
                </a>
            @endif
        </div>
    </form>

    {{-- Table --}}
    <div class="block block-rounded">
        <div class="table-responsive">
            <table class="table notif-table mb-0">
                <thead>
                    <tr>
                        <th style="width:150px">Date</th>
                        <th style="width:100px">Channel</th>
                        <th style="width:160px">Type</th>
                        <th>Recipient</th>
                        <th>Subject / Summary</th>
                        <th style="width:90px">Status</th>
                        <th style="width:40px"></th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($logs as $log)
                    @php
                        $typeLabels = [
                            'admin_ticket_issued' => 'Admin: Ticket Issued',
                            'holder_tickets'      => 'Holder Tickets',
                            'order_tickets'       => 'Order Tickets',
                            'order_invoice'       => 'Invoice',
                            'guest_invitation'    => 'Guest Invitation',
                            'order_status_changed'=> 'Status Changed',
                            'order_approved'      => 'Order Approved',
                            'order_rejected'      => 'Order Rejected',
                            'order_note'          => 'Order Note',
                            'otp'                 => 'OTP',
                            'contact'             => 'Contact Form',
                            'ticket_sent'         => 'Ticket',
                        ];
                        $typeLabel = $typeLabels[$log->type] ?? ucwords(str_replace('_', ' ', $log->type));
                    @endphp
                    <tr class="{{ $log->status === 'failed' ? 'notif-row-failed' : '' }}">
                        <td class="notif-date">{{ $log->created_at->format('d M Y') }}<br><small>{{ $log->created_at->format('H:i:s') }}</small></td>
                        <td>
                            @if($log->channel === 'email')
                                <span class="notif-channel-badge notif-channel-email">
                                    <i class="fa fa-envelope me-1"></i>Email
                                </span>
                            @else
                                <span class="notif-channel-badge notif-channel-wa">
                                    <i class="fa-brands fa-whatsapp me-1"></i>WA
                                </span>
                            @endif
                        </td>
                        <td><span class="notif-type">{{ $typeLabel }}</span></td>
                        <td class="notif-recipient">{{ $log->recipient ?? '—' }}</td>
                        <td class="notif-subject">{{ $log->subject ?? '—' }}</td>
                        <td>
                            @if($log->status === 'sent')
                                <span class="notif-badge notif-badge-sent">Sent</span>
                            @elseif($log->status === 'failed')
                                <span class="notif-badge notif-badge-failed">Failed</span>
                            @else
                                <span class="notif-badge notif-badge-skipped">Skipped</span>
                            @endif
                        </td>
                        <td>
                            @if($log->error || $log->metadata)
                                <button type="button"
                                        class="notif-detail-btn"
                                        data-error="{{ e($log->error ?? '') }}"
                                        data-meta="{{ e(json_encode($log->metadata, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)) }}"
                                        onclick="showNotifDetail(this)"
                                        title="Details">
                                    <i class="fa fa-circle-info"></i>
                                </button>
                            @endif
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="7" class="text-center py-4 text-muted">
                            <i class="fa fa-paper-plane fa-2x mb-2 d-block opacity-25"></i>
                            No notification logs found.
                        </td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    {{-- Pagination --}}
    <div class="mt-3">{{ $logs->links() }}</div>

</div>

{{-- Detail modal --}}
<div id="notifDetailModal" class="notif-modal-backdrop" onclick="closeNotifDetail()">
    <div class="notif-modal" onclick="event.stopPropagation()">
        <div class="notif-modal-header">
            <h6 class="mb-0">Details</h6>
            <button type="button" onclick="closeNotifDetail()" class="notif-modal-close">&times;</button>
        </div>
        <div class="notif-modal-body">
            <div id="notifDetailError" style="display:none">
                <div class="notif-detail-label">Error</div>
                <pre id="notifDetailErrorText" class="notif-detail-pre notif-detail-pre-error"></pre>
            </div>
            <div id="notifDetailMeta" style="display:none">
                <div class="notif-detail-label">Metadata</div>
                <pre id="notifDetailMetaText" class="notif-detail-pre"></pre>
            </div>
        </div>
    </div>
</div>

<style>
.notif-logs-page .block { background: var(--surface2); border: 1px solid var(--border); border-radius: 14px; }

/* Stats */
.notif-stats { display: grid; grid-template-columns: repeat(4, 1fr); gap: 12px; }
@media (max-width: 640px) { .notif-stats { grid-template-columns: repeat(2, 1fr); } }
.notif-stat-card {
    border-radius: 12px;
    padding: 16px;
    display: flex;
    align-items: center;
    gap: 14px;
    border: 1px solid var(--border);
    background: var(--surface2);
}
.notif-stat-icon { font-size: 22px; width: 40px; text-align: center; }
.notif-stat-num { font-size: 22px; font-weight: 800; font-family: var(--font-num, monospace); line-height: 1; }
.notif-stat-label { font-size: 12px; color: var(--muted); margin-top: 3px; }
.notif-stat-sent   .notif-stat-icon { color: #22c55e; }
.notif-stat-failed .notif-stat-icon { color: #ef4444; }
.notif-stat-skipped .notif-stat-icon { color: #f59e0b; }
.notif-stat-total  .notif-stat-icon { color: #3b82f6; }

/* Filters */
.notif-channel-tabs { display: flex; gap: 6px; flex-wrap: wrap; margin-bottom: 10px; }
.notif-channel-tab {
    text-decoration: none !important;
    color: var(--text) !important;
    border: 1px solid var(--border);
    border-radius: 999px;
    padding: 6px 14px;
    font-size: 13px;
    background: var(--surface2);
    transition: background 0.15s;
}
.notif-channel-tab:hover { background: rgba(255,255,255,0.06); }
.notif-channel-tab.active { background: var(--gold); border-color: var(--gold); color: #111 !important; font-weight: 700; }
.notif-filter-row { display: flex; flex-wrap: wrap; gap: 8px; align-items: center; }
.notif-filter-input {
    border: 1px solid var(--border);
    border-radius: 8px;
    background: var(--surface2);
    color: var(--text);
    padding: 7px 10px;
    font-size: 13px;
    min-width: 0;
}
.notif-filter-input::placeholder { color: var(--muted); }
input.notif-filter-input[type="text"] { flex: 1; min-width: 180px; }
.notif-filter-apply {
    border: 0; border-radius: 8px; background: var(--gold); color: #111;
    padding: 7px 14px; font-size: 13px; font-weight: 700; white-space: nowrap;
}
.notif-filter-clear {
    text-decoration: none; color: var(--muted); font-size: 13px; white-space: nowrap;
}
.notif-filter-clear:hover { color: var(--text); }

/* Table */
.notif-table th { font-size: 11px; text-transform: uppercase; letter-spacing: .5px; color: var(--muted); border-bottom: 1px solid var(--border) !important; }
.notif-table td { border-color: var(--border) !important; vertical-align: middle; font-size: 13px; }
.notif-row-failed td { background: rgba(239,68,68,0.04); }
.notif-date { color: var(--text); font-size: 12px; white-space: nowrap; }
.notif-date small { color: var(--muted); }
.notif-recipient { font-family: monospace; font-size: 12px; color: var(--muted); max-width: 180px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
.notif-subject { font-size: 13px; max-width: 260px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
.notif-type { font-size: 11px; color: var(--muted); background: rgba(255,255,255,0.06); border-radius: 999px; padding: 2px 8px; white-space: nowrap; }

/* Channel badges */
.notif-channel-badge { font-size: 11px; border-radius: 999px; padding: 3px 8px; font-weight: 600; white-space: nowrap; }
.notif-channel-email { background: rgba(59,130,246,0.15); color: #93c5fd; }
.notif-channel-wa { background: rgba(34,197,94,0.15); color: #86efac; }

/* Status badges */
.notif-badge { font-size: 11px; border-radius: 999px; padding: 3px 10px; font-weight: 700; white-space: nowrap; }
.notif-badge-sent    { background: rgba(34,197,94,0.15);  color: #86efac; }
.notif-badge-failed  { background: rgba(239,68,68,0.15);  color: #fca5a5; }
.notif-badge-skipped { background: rgba(245,158,11,0.15); color: #fcd34d; }

/* Detail button */
.notif-detail-btn {
    background: none; border: none; color: var(--muted); font-size: 15px; cursor: pointer; padding: 0 4px;
}
.notif-detail-btn:hover { color: var(--gold); }

/* Modal */
.notif-modal-backdrop {
    display: none;
    position: fixed; inset: 0; background: rgba(0,0,0,0.6); z-index: 9999;
    align-items: center; justify-content: center;
}
.notif-modal-backdrop.is-open {
    display: flex;
}
.notif-modal {
    background: var(--surface2, #1a1a24);
    border: 1px solid var(--border);
    border-radius: 14px;
    width: 100%;
    max-width: 560px;
    margin: 16px;
    max-height: 80vh;
    overflow-y: auto;
    box-shadow: 0 24px 60px rgba(0,0,0,0.6);
}
.notif-modal-header {
    display: flex; justify-content: space-between; align-items: center;
    padding: 16px 20px; border-bottom: 1px solid var(--border);
}
.notif-modal-close { background: none; border: none; color: var(--muted); font-size: 20px; cursor: pointer; line-height: 1; }
.notif-modal-close:hover { color: var(--text); }
.notif-modal-body { padding: 20px; }
.notif-detail-label { font-size: 11px; text-transform: uppercase; letter-spacing: .5px; color: var(--muted); margin-bottom: 6px; }
.notif-detail-pre {
    background: rgba(0,0,0,0.35); border: 1px solid var(--border); border-radius: 8px;
    padding: 12px; font-size: 12px; color: var(--text); white-space: pre-wrap; word-break: break-word;
    margin: 0 0 16px;
}
.notif-detail-pre-error { color: #fca5a5; }
</style>

<script>
function showNotifDetail(btn) {
    var error = btn.dataset.error;
    var meta  = btn.dataset.meta;

    var errEl  = document.getElementById('notifDetailError');
    var metaEl = document.getElementById('notifDetailMeta');

    if (error) {
        errEl.style.display = '';
        document.getElementById('notifDetailErrorText').textContent = error;
    } else {
        errEl.style.display = 'none';
    }

    if (meta && meta !== 'null') {
        metaEl.style.display = '';
        document.getElementById('notifDetailMetaText').textContent = meta;
    } else {
        metaEl.style.display = 'none';
    }

    document.getElementById('notifDetailModal').classList.add('is-open');
}

function closeNotifDetail() {
    document.getElementById('notifDetailModal').classList.remove('is-open');
}

document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') closeNotifDetail();
});
</script>
@endsection
