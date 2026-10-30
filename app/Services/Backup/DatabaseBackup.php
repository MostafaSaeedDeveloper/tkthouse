<?php

namespace App\Services\Backup;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\Process\ExecutableFinder;
use Symfony\Component\Process\Process;

/**
 * Database backups as gzip-compressed SQL files in PRIVATE storage (never under public/). The native dump tool is used when the server
 * has one; otherwise a built-in PHP dumper writes the same kind of file. Nothing here changes the database.
 */
final class DatabaseBackup
{
    public const DIR = 'backups';
    public const KEEP = 10;
    private const NAME = '/^tkthouse-[A-Za-z0-9_]+-\d{8}-\d{6}\.sql\.gz$/';

    public function disk()
    {
        return Storage::disk('local');
    }

    /** @return array{name: string, size: int, created_at: \Illuminate\Support\Carbon, method: string} */
    public function create(): array
    {
        $this->disk()->makeDirectory(self::DIR);
        $conn = DB::connection();
        $name = 'tkthouse-'.preg_replace('/[^A-Za-z0-9_]/', '_', basename((string) $conn->getDatabaseName(), '.sqlite')).'-'.now()->format('Ymd-His').'.sql.gz';
        $path = $this->disk()->path(self::DIR.'/'.$name);

        $method = 'php';
        $done = false;
        if (in_array($conn->getDriverName(), ['mysql', 'mariadb'], true) && ($bin = $this->binary())) {
            $done = $this->nativeDump($bin, $path);
            $method = $done ? basename($bin) : 'php';
        }
        if (! $done) {
            $this->phpDump($path);
        }
        @chmod($path, 0600);
        $this->prune();

        return ['name' => $name, 'size' => (int) filesize($path), 'created_at' => now(), 'method' => $method];
    }

    /** @return list<array{name: string, size: int, created_at: \Illuminate\Support\Carbon}> newest first */
    public function all(): array
    {
        $out = [];
        foreach ($this->disk()->files(self::DIR) as $f) {
            $n = basename($f);
            if (preg_match(self::NAME, $n)) {
                $out[] = ['name' => $n, 'size' => $this->disk()->size($f), 'created_at' => \Illuminate\Support\Carbon::createFromTimestamp($this->disk()->lastModified($f))];
            }
        }
        usort($out, fn ($a, $b) => $b['created_at'] <=> $a['created_at'] ?: strcmp($b['name'], $a['name']));

        return $out;
    }

    /** Absolute path of a backup by name, or null. Only names this class creates are accepted (no path traversal). */
    public function path(string $name): ?string
    {
        if (! preg_match(self::NAME, $name) || ! $this->disk()->exists(self::DIR.'/'.$name)) {
            return null;
        }

        return $this->disk()->path(self::DIR.'/'.$name);
    }

    public function delete(string $name): bool
    {
        return $this->path($name) !== null && $this->disk()->delete(self::DIR.'/'.$name);
    }

    public function prune(int $keep = self::KEEP): void
    {
        foreach (array_slice($this->all(), $keep) as $old) {
            $this->disk()->delete(self::DIR.'/'.$old['name']);
        }
    }

    private function binary(): ?string
    {
        $finder = new ExecutableFinder();

        return $finder->find('mariadb-dump') ?? $finder->find('mysqldump');
    }

    private function nativeDump(string $bin, string $path): bool
    {
        $c = config('database.connections.'.config('database.default'));
        $cnf = tempnam(sys_get_temp_dir(), 'tkt');
        // credentials go through a 0600 option file, never the command line (visible in the process list)
        file_put_contents($cnf, "[client]\nuser=\"".addcslashes((string) $c['username'], '"\\')."\"\npassword=\"".addcslashes((string) $c['password'], '"\\')."\"\n");
        chmod($cnf, 0600);
        $gz = gzopen($path, 'wb6');
        try {
            $args = [$bin, '--defaults-extra-file='.$cnf, '--single-transaction', '--quick', '--routines', '--triggers', '--no-tablespaces', '--default-character-set=utf8mb4'];
            if (! empty($c['unix_socket'])) {
                $args[] = '--socket='.$c['unix_socket'];
            } else {
                array_push($args, '--host='.$c['host'], '--port='.($c['port'] ?? 3306));
            }
            $args[] = $c['database'];
            $p = new Process($args, null, null, null, 600);
            $p->run(function ($type, $buf) use ($gz) {
                if ($type === Process::OUT) {
                    gzwrite($gz, $buf);
                }
            });
            gzclose($gz);

            return $p->isSuccessful() && filesize($path) > 100;
        } catch (\Throwable $e) {
            report($e);
            @gzclose($gz);

            return false;
        } finally {
            @unlink($cnf);
        }
    }

    private function phpDump(string $path): void
    {
        $db = DB::connection();
        $pdo = $db->getPdo();
        $gz = gzopen($path, 'wb6');
        $driver = $db->getDriverName();
        $w = fn (string $s) => gzwrite($gz, $s);
        $w("-- TKT House database backup (PHP dumper) · ".now()->toDateTimeString()."\n");
        if ($driver === 'sqlite') {
            $tables = collect($db->select("select name, sql from sqlite_master where type = 'table' and name not like 'sqlite_%' order by name"));
            foreach ($tables as $t) {
                $w("DROP TABLE IF EXISTS \"{$t->name}\";\n{$t->sql};\n");
                $this->rows($db, $pdo, $t->name, '"', $w);
            }
        } else {
            $w("SET FOREIGN_KEY_CHECKS=0;\nSET NAMES utf8mb4;\n");
            foreach ($db->select("SHOW FULL TABLES WHERE Table_type = 'BASE TABLE'") as $row) {
                $name = array_values((array) $row)[0];
                $create = array_values((array) $db->selectOne('SHOW CREATE TABLE `'.str_replace('`', '``', $name).'`'))[1];
                $w("DROP TABLE IF EXISTS `{$name}`;\n{$create};\n");
                $this->rows($db, $pdo, $name, '`', $w);
            }
            $w("SET FOREIGN_KEY_CHECKS=1;\n");
        }
        gzclose($gz);
    }

    private function rows($db, \PDO $pdo, string $table, string $q, \Closure $w): void
    {
        $quoted = $q.str_replace($q, $q.$q, $table).$q;
        foreach ($db->table($table)->cursor() as $r) {
            $vals = array_map(fn ($v) => $v === null ? 'NULL' : (is_int($v) || is_float($v) ? (string) $v : $pdo->quote((string) $v)), (array) $r);
            $w("INSERT INTO {$quoted} VALUES (".implode(',', $vals).");\n");
        }
    }
}
