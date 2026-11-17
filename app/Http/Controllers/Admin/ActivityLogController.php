<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Spatie\Activitylog\Models\Activity;

class ActivityLogController extends Controller
{
    public function index(Request $request)
    {
        $log = trim((string) $request->query('log', ''));
        $activities = Activity::with('causer')
            ->when($log !== '', fn ($q) => $q->where('log_name', $log))
            ->latest()->paginate(30)->withQueryString();

        return view('admin.activity-logs.index', compact('activities', 'log'));
    }
}
