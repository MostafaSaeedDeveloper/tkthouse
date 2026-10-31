<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;

/**
 * Read-only: lists the users holding the "scanner" role so the list can be copied before their passwords are changed.
 * Passwords are stored hashed and can never be shown.
 */
class ListScannerUsers extends Command
{
    protected $signature = 'scanners:list {--format=table : table, csv or json} {--only-active : skip inactive users}';

    protected $description = 'List scanner users (id, name, username, email, status) in a copy-friendly format. Read-only.';

    public function handle(): int
    {
        $format = (string) $this->option('format');
        if (! in_array($format, ['table', 'csv', 'json'], true)) {
            $this->error('Unknown format. Use table, csv or json.');

            return self::INVALID;
        }

        $rows = User::query()
            ->whereHas('roles', fn ($q) => $q->where('name', 'scanner'))
            ->when($this->option('only-active'), fn ($q) => $q->where('is_active', true))
            ->withCount(['scanLogs as scans_count' => fn ($q) => $q->where('action', 'status_update')])
            ->orderBy('id')
            ->get()
            ->map(fn (User $u) => [
                'id' => $u->id,
                'name' => $u->name,
                'username' => $u->username,
                'email' => $u->email,
                'active' => $u->is_active ? 'yes' : 'no',
                'scans' => $u->scans_count,
                'created_at' => optional($u->created_at)->toDateTimeString(),
            ])->values();

        if ($rows->isEmpty()) {
            $this->warn('No scanner users found.');

            return self::SUCCESS;
        }

        if ($format === 'json') {
            $this->line($rows->toJson(JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
        } elseif ($format === 'csv') {
            $out = fopen('php://temp', 'w+');
            fputcsv($out, array_keys($rows->first()));
            foreach ($rows as $row) {
                fputcsv($out, $row);
            }
            rewind($out);
            $this->output->write(stream_get_contents($out));
            fclose($out);
        } else {
            $this->table(array_keys($rows->first()), $rows->all());
            $this->line($rows->count().' scanner user(s). Passwords are hashed and cannot be shown.');
        }

        return self::SUCCESS;
    }
}
