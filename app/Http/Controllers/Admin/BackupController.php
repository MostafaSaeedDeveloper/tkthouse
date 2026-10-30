<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Orders\OrderAccess;
use App\Services\Backup\DatabaseBackup;
use Illuminate\Http\Request;

/** Database backups: Super Admin only (a backup contains every customer, order and payment record). */
class BackupController extends Controller
{
    public function __construct(private readonly DatabaseBackup $backups)
    {
    }

    private function authorizeSuper(Request $request): void
    {
        abort_unless(OrderAccess::isSuper($request->user()), 403);
    }

    public function index(Request $request)
    {
        $this->authorizeSuper($request);

        return view('admin.backups.index', ['files' => $this->backups->all(), 'keep' => DatabaseBackup::KEEP]);
    }

    public function store(Request $request)
    {
        $this->authorizeSuper($request);
        try {
            $b = $this->backups->create();
        } catch (\Throwable $e) {
            report($e);

            return back()->withErrors(['backup' => 'The backup could not be created. Nothing was changed. Check the server log.']);
        }
        activity('backup')->causedBy($request->user())->log('Created database backup '.$b['name'].' ('.$b['method'].')');

        return redirect()->route('admin.backups.index')->with('success', 'Backup created: '.$b['name']);
    }

    public function download(Request $request, string $name)
    {
        $this->authorizeSuper($request);
        $path = $this->backups->path($name);
        abort_if($path === null, 404);
        activity('backup')->causedBy($request->user())->log('Downloaded database backup '.$name);

        return response()->download($path, $name, ['Content-Type' => 'application/gzip', 'Cache-Control' => 'no-store']);
    }

    public function destroy(Request $request, string $name)
    {
        $this->authorizeSuper($request);
        abort_unless($this->backups->delete($name), 404);
        activity('backup')->causedBy($request->user())->log('Deleted database backup '.$name);

        return redirect()->route('admin.backups.index')->with('success', 'Backup deleted.');
    }
}
