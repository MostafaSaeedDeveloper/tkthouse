<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\NotificationLog;
use Illuminate\Http\Request;

class NotificationLogController extends Controller
{
    public function index(Request $request)
    {
        $channel = $request->input('channel');
        $status  = $request->input('status');
        $search  = $request->input('search');
        $from    = $request->input('from');
        $to      = $request->input('to');

        $base = fn () => NotificationLog::query()
            ->when($channel, fn ($q) => $q->where('channel', $channel))
            ->when($status,  fn ($q) => $q->where('status', $status))
            ->when($search,  fn ($q) => $q->where(fn ($q2) => $q2
                ->where('recipient', 'like', "%{$search}%")
                ->orWhere('subject', 'like', "%{$search}%")
            ))
            ->when($from, fn ($q) => $q->whereDate('created_at', '>=', $from))
            ->when($to,   fn ($q) => $q->whereDate('created_at', '<=', $to));

        $stats = [
            'sent'    => $base()->where('status', 'sent')->count(),
            'failed'  => $base()->where('status', 'failed')->count(),
            'skipped' => $base()->where('status', 'skipped')->count(),
        ];

        $logs = $base()->latest()->paginate(30)->withQueryString();

        return view('admin.notification-logs.index', compact('logs', 'stats'));
    }
}
