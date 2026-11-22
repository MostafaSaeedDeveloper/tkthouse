<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;

/**
 * Sets sequential passwords for scanner accounts (entry1 ... entry10 by default): <base>1, <base>2, ...
 * Only the password column of users that have the scanner role is touched; scan history, roles, e-mails and usernames stay as they are.
 * Dry run unless --apply. The passwords are printed once, in a copy-friendly list, and are not written to any log.
 */
class SetScannerPasswords extends Command
{
    protected $signature = 'scanners:set-passwords
        {--base= : Password prefix; entry N gets "<base>N" (required, at least 6 characters)}
        {--prefix=entry : Username prefix}
        {--from=1 : First number}
        {--to=10 : Last number}
        {--apply : Actually change the passwords (without it nothing changes)}';

    protected $description = 'Set sequential passwords for scanner users entry1..entry10 (dry run unless --apply).';

    public function handle(): int
    {
        $base = (string) $this->option('base');
        if (mb_strlen($base) < 6) {
            $this->error('Pass --base="..." with at least 6 characters, e.g. --base="Gate@7391-" gives Gate@7391-1, Gate@7391-2, ...');

            return self::INVALID;
        }

        $prefix = (string) $this->option('prefix');
        $apply = (bool) $this->option('apply');
        $lines = [];
        $missing = [];

        foreach (range((int) $this->option('from'), (int) $this->option('to')) as $n) {
            $user = User::query()->where('username', $prefix.$n)->first();
            if (! $user || ! $user->hasRole('scanner')) {
                $missing[] = $prefix.$n;

                continue;
            }

            $password = $base.$n;
            if ($apply) {
                // Password column only: no model events, nothing else on the user is touched.
                User::query()->whereKey($user->id)->update(['password' => Hash::make($password), 'remember_token' => null]);
            }
            $lines[] = [$user->username, $password];
        }

        if ($missing) {
            $this->warn('Not found (or not scanner users), skipped: '.implode(', ', $missing));
        }

        $this->newLine();
        $this->line($apply ? 'Passwords changed. Copy them now:' : 'Dry run — these accounts would get these passwords (add --apply):');
        $this->newLine();
        foreach ($lines as [$username, $password]) {
            $this->line("{$username}  {$password}");
        }
        $this->newLine();

        return self::SUCCESS;
    }
}
