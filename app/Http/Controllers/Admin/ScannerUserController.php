<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ScanLog;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ScannerUserController extends Controller
{
    public function index(Request $request)
    {
        $search = trim((string) $request->string('search'));

        $scannerUsers = User::query()
            ->with(['roles'])
            ->withCount(['scanLogs as scans_count' => function ($query) {
                $query->where('action', 'status_update');
            }])
            ->whereHas('roles', fn ($query) => $query->where('name', 'scanner'))
            ->when($search !== '', function ($query) use ($search) {
                $query->where(function ($subQuery) use ($search) {
                    $subQuery
                        ->where('name', 'like', "%{$search}%")
                        ->orWhere('username', 'like', "%{$search}%")
                        ->orWhere('email', 'like', "%{$search}%");
                });
            })
            ->latest()
            ->paginate(15)
            ->withQueryString();

        return view('admin/scanners/index', compact('scannerUsers', 'search'));
    }

    public function create()
    {
        return view('admin/scanners/create');
    }


    public function show(User $user)
    {
        abort_unless($user->hasRole('scanner'), 404);

        $scanLogs = ScanLog::query()
            ->where('scanned_by_user_id', $user->id)
            ->where('action', '!=', 'lookup_success')
            ->latest('scanned_at')
            ->paginate(20)
            ->withQueryString();

        $stats = [
            'total_scans' => ScanLog::query()->where('scanned_by_user_id', $user->id)->where('action', 'status_update')->count(),
            'successful_lookups' => ScanLog::query()->where('scanned_by_user_id', $user->id)->where('action', 'lookup_success')->count(),
            'failed_lookups' => ScanLog::query()->where('scanned_by_user_id', $user->id)->where('action', 'lookup_failed')->count(),
            'checkins' => ScanLog::query()->where('scanned_by_user_id', $user->id)->where('action', 'status_update')->where('new_status', 'checked_in')->count(),
            'logins' => ScanLog::query()->where('scanned_by_user_id', $user->id)->where('action', 'scanner_login')->count(),
        ];

        return view('admin/scanners/show', compact('user', 'scanLogs', 'stats'));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'username' => ['required', 'string', 'max:255', 'unique:users,username'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'string', 'min:8'],
        ]);

        $scannerRole = Role::query()->where('name', 'scanner')->firstOrFail();

        $user = User::create([
            'name' => $validated['name'],
            'username' => $validated['username'],
            'email' => $validated['email'],
            'password' => Hash::make($validated['password']),
        ]);

        $user->syncRoles([$scannerRole->name]);
        app(PermissionRegistrar::class)->forgetCachedPermissions();


        return redirect()->route('admin.scanners.index')->with('success', 'Scanner user added successfully.');
    }

    public function destroy(User $user)
    {
        abort_unless($user->hasRole('scanner'), 404);

        $user->delete();

        return back()->with('success', 'Scanner user deleted successfully.');
    }

    public function exportHistory(Request $request): StreamedResponse
    {
        $from = $request->date('from');
        $to = $request->date('to');

        $logs = ScanLog::query()
            ->with(['ticket', 'scannerUser'])
            ->when($from, fn ($query) => $query->whereDate('scanned_at', '>=', $from))
            ->when($to, fn ($query) => $query->whereDate('scanned_at', '<=', $to))
            ->latest('scanned_at')
            ->get();

        return response()->streamDownload(function () use ($logs) {
            $handle = fopen('php://output', 'w');

            fputcsv($handle, ['scanned_at', 'action', 'ticket_number', 'event_name', 'previous_status', 'new_status', 'scanner_username', 'scanner_name', 'ip_address']);

            foreach ($logs as $log) {
                fputcsv($handle, [
                    optional($log->scanned_at)->toDateTimeString(),
                    $log->action,
                    $log->ticket_number,
                    $log->event_name,
                    $log->previous_status,
                    $log->new_status,
                    $log->scannerUser?->username,
                    $log->scanner_name,
                    $log->ip_address,
                ]);
            }

            fclose($handle);
        }, 'scan-history.csv', ['Content-Type' => 'text/csv']);
    }
}
