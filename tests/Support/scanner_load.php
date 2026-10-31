<?php

/**
 * Scanner load / concurrency harness. NEVER run it against real data.
 *
 *   SCANNER_LOAD_OK=1 DB_CONNECTION=mysql DB_HOST=127.0.0.1 DB_PORT=3307 DB_DATABASE=tkt_scanner_test … php tests/Support/scanner_load.php
 *
 * It refuses to start unless the database is on localhost, its name contains "test" or "scanner_load", and SCANNER_LOAD_OK=1.
 * It generates its own events / orders / tickets / scanner users, starts its own PHP server (PHP_CLI_SERVER_WORKERS) on
 * 127.0.0.1, logs the scanners in through the real login form (CSRF enforced) and hits the real HTTP endpoints with curl_multi.
 */

use App\Models\Event;
use App\Models\ScanLog;
use App\Models\Ticket;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

require __DIR__.'/../../vendor/autoload.php';
$app = require __DIR__.'/../../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$host = (string) config('database.connections.mysql.host');
$dbName = (string) config('database.connections.mysql.database');
if (getenv('SCANNER_LOAD_OK') !== '1' || ! in_array($host, ['127.0.0.1', 'localhost'], true) || ! preg_match('/test|scanner_load/i', $dbName) || app()->environment('production')) {
    fwrite(STDERR, "Refusing to run: needs SCANNER_LOAD_OK=1, a localhost database whose name contains 'test', and a non-production environment.\n");
    exit(2);
}

$port = (int) (getenv('LOAD_PORT') ?: 8099);
$workers = (int) (getenv('LOAD_WORKERS') ?: 32);
$base = "http://127.0.0.1:$port";
$ticketsPerEvent = (int) (getenv('LOAD_TICKETS') ?: 4000);
$out = [];
$statusTally = [];

function pct(array $v, float $p): float { if (! $v) return 0; sort($v); return round($v[(int) min(count($v) - 1, max(0, ceil($p / 100 * count($v)) - 1))], 1); }
function summarize(array $lat): array { return ['n' => count($lat), 'p50' => pct($lat, 50), 'p95' => pct($lat, 95), 'p99' => pct($lat, 99), 'max' => round(max($lat ?: [0]), 1)]; }

// ───────── 1. generate data ─────────
DB::statement('SET FOREIGN_KEY_CHECKS=0');
foreach (['scan_logs', 'tickets', 'issued_tickets', 'order_items', 'orders', 'user_managed_events', 'customers', 'events'] as $t) { DB::table($t)->truncate(); }
DB::statement('SET FOREIGN_KEY_CHECKS=1');
DB::table('users')->where('username', 'like', 'load_%')->delete();

$mk = fn ($n) => Event::create(['name' => $n, 'event_date' => now()->addDay()->toDateString(), 'event_time' => '21:00:00', 'location' => 'Cairo', 'description' => '', 'status' => 'active', 'requires_booking_approval' => false]);
$fracture = $mk('Fracture Halloween'); $afterParty = $mk('Fracture Halloween - After Party'); $third = $mk('Third Event');
$custId = DB::table('customers')->insertGetId(['first_name' => 'Load', 'last_name' => 'Test', 'email' => 'load@example.test', 'phone' => '+201000000000', 'created_at' => now(), 'updated_at' => now()]);

$seq = 0; $pools = ['valid' => [], 'refunded' => [], 'canceled' => [], 'pending' => [], 'partial_valid' => [], 'partial_refunded' => [], 'other_event' => [], 'prechecked' => []];
$mkOrder = function (Event $e, string $status, int $qty, callable $perTicket) use (&$seq, $custId) {
    $oid = DB::table('orders')->insertGetId(['customer_id' => $custId, 'order_number' => (string) (3000000000 + ++$seq), 'status' => $status, 'payment_method' => 'fake_card', 'total_amount' => 100 * $qty, 'subtotal_amount' => 100 * $qty, 'paid_at' => now(), 'tickets_generated_at' => now(), 'created_at' => now(), 'updated_at' => now()]);
    $iid = DB::table('order_items')->insertGetId(['order_id' => $oid, 'event_id' => $e->id, 'ticket_name' => $e->name.' - GA', 'ticket_price' => 100, 'quantity' => $qty, 'line_total' => 100 * $qty, 'holder_name' => 'Holder', 'holder_email' => 'h@example.test', 'holder_phone' => '+201000000001', 'holder_gender' => 'male', 'holder_social_profile' => 'h', 'created_at' => now(), 'updated_at' => now()]);
    for ($s = 1; $s <= $qty; $s++) {
        $num = sprintf('%06d%03d%02d', $oid, $iid % 1000, $s);
        $row = ['order_id' => $oid, 'order_item_id' => $iid, 'name' => $e->name.' - GA', 'price' => 100, 'status' => 'not_checked_in', 'ticket_number' => $num, 'qr_payload' => $num, 'holder_name' => 'Holder '.$num, 'holder_email' => "h$num@example.test", 'issued_at' => now(), 'created_at' => now(), 'updated_at' => now()];
        $perTicket($row, $s);
        DB::table('tickets')->insert($row);
    }
};
$t0 = microtime(true);
for ($i = 0; $i < $ticketsPerEvent / 2; $i++) { $mkOrder($fracture, 'paid', 2, function (&$r) use (&$pools) { $pools['valid'][] = $r['ticket_number']; }); }
for ($i = 0; $i < 60; $i++) { $mkOrder($fracture, 'paid', 1, function (&$r) use (&$pools) { $r['status'] = 'canceled'; $r['cancellation_source'] = 'refund'; $r['cancellation_reason'] = 'load'; $r['canceled_at'] = now(); $pools['refunded'][] = $r['ticket_number']; }); }
for ($i = 0; $i < 60; $i++) { $mkOrder($fracture, 'paid', 1, function (&$r) use (&$pools) { $r['status'] = 'canceled'; $r['canceled_at'] = now(); $pools['canceled'][] = $r['ticket_number']; }); }
for ($i = 0; $i < 60; $i++) { $mkOrder($fracture, 'pending_payment', 1, function (&$r) use (&$pools) { $pools['pending'][] = $r['ticket_number']; }); }
for ($i = 0; $i < 60; $i++) { $mkOrder($fracture, 'partially_refunded', 2, function (&$r, $s) use (&$pools) { if ($s === 1) { $r['status'] = 'canceled'; $r['cancellation_source'] = 'refund'; $r['canceled_at'] = now(); $pools['partial_refunded'][] = $r['ticket_number']; } else { $pools['partial_valid'][] = $r['ticket_number']; } }); }
for ($i = 0; $i < 300; $i++) { $mkOrder($afterParty, 'paid', 1, function (&$r) use (&$pools) { $pools['other_event'][] = $r['ticket_number']; }); }
for ($i = 0; $i < 60; $i++) { $mkOrder($fracture, 'paid', 1, function (&$r) use (&$pools) { $r['status'] = 'checked_in'; $r['checked_in_at'] = now()->subMinutes(30); $pools['prechecked'][] = $r['ticket_number']; }); }
$total = DB::table('tickets')->count();
// the original check-in record of the pre-checked-in ones
foreach (DB::table('tickets')->where('status', 'checked_in')->get(['id', 'ticket_number', 'event_id']) as $t) { DB::table('scan_logs')->insert(['ticket_id' => $t->id, 'ticket_number' => $t->ticket_number, 'action' => 'status_update', 'previous_status' => 'not_checked_in', 'new_status' => 'checked_in', 'scanner_name' => 'Gate Zero', 'scanned_at' => now()->subMinutes(30), 'created_at' => now(), 'updated_at' => now()]); }
fwrite(STDERR, sprintf("generated %d tickets in %.1fs (%s)\n", $total, microtime(true) - $t0, json_encode(array_map('count', $pools))));

$scanners = [];
$pw = Hash::make('LoadTest!123');
for ($i = 1; $i <= 20; $i++) {
    $u = User::create(['name' => "Load Scanner $i", 'username' => "load_s$i", 'email' => "load_s$i@example.test", 'password' => $pw, 'is_active' => true]);
    $u->assignRole('scanner');
    $u->managedEvents()->attach($fracture->id);
    $scanners[] = $u;
}
$thirdOnly = User::create(['name' => 'Load Third', 'username' => 'load_third', 'email' => 'load_third@example.test', 'password' => $pw, 'is_active' => true]);
$thirdOnly->assignRole('scanner'); $thirdOnly->managedEvents()->attach($third->id);

// ───────── 2. start own server ─────────
$cmd = sprintf('PHP_CLI_SERVER_WORKERS=%d exec php -S 127.0.0.1:%d -t %s %s', $workers, $port, escapeshellarg(base_path('public')), escapeshellarg(base_path('vendor/laravel/framework/src/Illuminate/Foundation/resources/server.php')));
$proc = proc_open($cmd, [1 => ['file', '/tmp/scanner_load_server.log', 'a'], 2 => ['file', '/tmp/scanner_load_server.log', 'a']], $pipes, base_path('public'), getenv());
register_shutdown_function(function () use ($proc) { $s = proc_get_status($proc); if ($s['running']) { proc_terminate($proc); } });
for ($i = 0; $i < 50; $i++) { if (@file_get_contents("$base/scanner")) break; usleep(200000); }

// ───────── 3. http helpers ─────────
$jar = fn ($name) => "/tmp/scanner_load_jar_$name";
function easy(string $url, string $jar, array $o = []) { $c = curl_init($url); curl_setopt_array($c, [CURLOPT_RETURNTRANSFER => true, CURLOPT_COOKIEFILE => $jar, CURLOPT_COOKIEJAR => $jar, CURLOPT_TIMEOUT => 30, CURLOPT_HEADER => false] + $o); return $c; }
function login(string $base, string $user, string $jarFile): string {
    @unlink($jarFile);
    $html = curl_exec($c = easy("$base/scanner", $jarFile)); curl_close($c);
    preg_match('/name="_token" value="([^"]+)"/', $html, $m);
    $c = easy("$base/scanner", $jarFile, [CURLOPT_POST => true, CURLOPT_POSTFIELDS => http_build_query(['_token' => $m[1], 'username' => $user, 'password' => 'LoadTest!123']), CURLOPT_FOLLOWLOCATION => true]);
    curl_exec($c); $code = curl_getinfo($c, CURLINFO_HTTP_CODE); curl_close($c);
    $c = easy("$base/dashboard/scanner/ping", $jarFile, [CURLOPT_HTTPHEADER => ['Accept: application/json']]);
    $j = json_decode(curl_exec($c), true); curl_close($c);
    if (empty($j['csrf'])) { throw new RuntimeException("login failed for $user (HTTP $code)"); }

    return $j['csrf'];
}
$sessions = [];
foreach ($scanners as $i => $u) { $sessions[$i] = ['jar' => $jar($u->username), 'csrf' => login($base, $u->username, $jar($u->username))]; }
$thirdSession = ['jar' => $jar('third'), 'csrf' => login($base, 'load_third', $jar('third'))];

/** Fire all [sessionIdx, code, eventId?] concurrently; returns list of [status, json, ms]. */
function blast(string $base, array $sessions, array $jobs, string $path = '/dashboard/scanner/check-in', int $timeoutMs = 30000, array $extra = []): array {
    $mh = curl_multi_init(); $handles = [];
    foreach ($jobs as $k => [$si, $code, $ev]) {
        $s = $sessions[$si];
        $c = easy($base.$path, $s['jar'], [CURLOPT_POST => true, CURLOPT_POSTFIELDS => json_encode(['code' => $code, 'event_id' => $ev]), CURLOPT_TIMEOUT_MS => $timeoutMs,
            CURLOPT_HTTPHEADER => ['Accept: application/json', 'Content-Type: application/json', 'X-Requested-With: XMLHttpRequest', 'X-CSRF-TOKEN: '.$s['csrf']]] + $extra);
        curl_multi_add_handle($mh, $c); $handles[$k] = $c;
    }
    $t0 = microtime(true);
    do { curl_multi_exec($mh, $running); if ($running) curl_multi_select($mh, 0.05); } while ($running);
    $wall = (microtime(true) - $t0) * 1000;
    $res = [];
    foreach ($handles as $k => $c) {
        $body = curl_multi_getcontent($c);
        $res[$k] = ['status' => curl_getinfo($c, CURLINFO_HTTP_CODE), 'json' => json_decode((string) $body, true), 'ms' => curl_getinfo($c, CURLINFO_TOTAL_TIME) * 1000, 'err' => curl_errno($c)];
        curl_multi_remove_handle($mh, $c); curl_close($c);
    }
    curl_multi_close($mh);
    foreach ($res as $x) { $GLOBALS['statusTally'][$x['status'].'/'.($x['json']['code'] ?? 'none')] = ($GLOBALS['statusTally'][$x['status'].'/'.($x['json']['code'] ?? 'none')] ?? 0) + 1; }

    return [$res, $wall];
}
$take = function (string $pool, int $n) use (&$pools) { return array_splice($pools[$pool], 0, $n); };
$fail = [];
$assert = function (bool $ok, string $what) use (&$fail) { if (! $ok) { $fail[] = $what; fwrite(STDERR, "ASSERTION FAILED: $what\n"); } };
$count = fn ($r, $code) => count(array_filter($r, fn ($x) => ($x['json']['code'] ?? null) === $code));

// warm-up (opcache / autoload / sessions)
blast($base, $sessions, array_map(fn ($i) => [$i, '000000000', $fracture->id], range(0, 19)));

// ───────── 0. baseline: one scanner, one request at a time ─────────
$lat = []; foreach ($take('valid', 150) as $num) { [$r] = blast($base, $sessions, [[3, $num, $fracture->id]]); $lat[] = $r[0]['ms']; $assert(($r[0]['json']['code'] ?? '') === 'approved', 'baseline: not approved'); }
$out['baseline_sequential_single_scanner'] = ['latency_ms' => summarize($lat)];

// ───────── A. same ticket, K simultaneous scanners ─────────
$allLat = [];
foreach ([2, 5, 10, 20] as $K) {
    $rounds = 60; $okTotal = 0; $dupTotal = 0; $bad = 0; $lat = [];
    foreach ($take('valid', $rounds) as $num) {
        [$r] = blast($base, $sessions, array_map(fn ($i) => [$i, $num, $fracture->id], range(0, $K - 1)));
        $ap = $count($r, 'approved'); $du = $count($r, 'already_checked_in');
        if ($ap !== 1 || $du !== $K - 1) { $bad++; }
        $okTotal += $ap; $dupTotal += $du; foreach ($r as $x) { $lat[] = $x['ms']; }
        $logs = ScanLog::where('ticket_number', $num)->where('action', 'status_update')->where('new_status', 'checked_in')->count();
        $assert($logs === 1 && Ticket::where('ticket_number', $num)->value('status') === 'checked_in', "K=$K ticket $num: $logs success logs");
    }
    $assert($bad === 0, "K=$K: $bad of $rounds rounds did not yield exactly 1 approval");
    $out['same_ticket_K'.$K] = ['rounds' => $rounds, 'rounds_with_exactly_one_approval' => $rounds - $bad, 'approvals' => $okTotal, 'duplicates' => $dupTotal, 'latency_ms' => summarize($lat)];
    $allLat = array_merge($allLat, $lat);
}

// ───────── B. different tickets simultaneously (throughput) ─────────
foreach ([5, 10, 20] as $K) {
    $per = (int) (getenv('LOAD_PER_SCANNER') ?: 40);
    $jobs = []; $nums = $take('valid', $K * $per);
    foreach ($nums as $j => $num) { $jobs[] = [$j % $K, $num, $fracture->id]; }
    // K scanners, each firing its tickets in waves of one in-flight request per scanner
    $lat = []; $okc = 0; $t0 = microtime(true);
    for ($w = 0; $w < $per; $w++) {
        $wave = array_filter($jobs, fn ($x, $idx) => intdiv($idx, $K) === $w, ARRAY_FILTER_USE_BOTH);
        [$r] = blast($base, $sessions, array_values($wave));
        foreach ($r as $x) { $lat[] = $x['ms']; $okc += (($x['json']['code'] ?? '') === 'approved') ? 1 : 0; }
    }
    $secs = microtime(true) - $t0;
    $assert($okc === count($nums), "B K=$K: only $okc of ".count($nums).' distinct tickets approved');
    $out['distinct_tickets_K'.$K] = ['tickets' => count($nums), 'approved' => $okc, 'wall_s' => round($secs, 2), 'checkins_per_sec' => round($okc / $secs, 1), 'latency_ms' => summarize($lat)];
    $allLat = array_merge($allLat, $lat);
}

// ───────── C. rapid repeated scans of one ticket by one scanner (same session) ─────────
$num = $take('valid', 1)[0];
[$r] = blast($base, $sessions, array_map(fn () => [0, $num, $fracture->id], range(1, 20)));
$assert($count($r, 'approved') === 1 && $count($r, 'already_checked_in') === 19, 'C rapid repeat: not exactly one approval');
$out['rapid_repeat_same_scanner_20x'] = ['approved' => $count($r, 'approved'), 'duplicate' => $count($r, 'already_checked_in'), 'latency_ms' => summarize(array_column($r, 'ms'))];

// ───────── D. mixed negative cases, concurrently, across many scanners ─────────
$jobs = []; $expect = [];
$add = function (string $pool, int $n, string $code, ?int $ev = null) use (&$jobs, &$expect, &$pools, $fracture) { foreach (array_splice($pools[$pool], 0, $n) as $i => $num) { $jobs[] = [$i % 20, $num, $ev ?? $fracture->id]; $expect[] = $code; } };
$add('refunded', 40, 'refunded'); $add('canceled', 40, 'canceled'); $add('pending', 40, 'order_not_paid'); $add('other_event', 40, 'wrong_event'); $add('prechecked', 40, 'already_checked_in'); $add('partial_refunded', 40, 'refunded');
foreach (range(1, 20) as $i) { $jobs[] = [$i % 20, '99'.str_pad((string) $i, 10, '0'), $fracture->id]; $expect[] = 'not_found'; }
$pv = array_splice($pools['partial_valid'], 0, 40); foreach ($pv as $i => $num) { $jobs[] = [$i % 20, $num, $fracture->id]; $expect[] = 'approved'; }
$r = []; foreach (array_chunk($jobs, 20, true) as $chunk) { [$part] = blast($base, $sessions, $chunk); $r = $r + $part; } ksort($r);
$wrong = 0; foreach ($r as $k => $x) { if (($x['json']['code'] ?? null) !== $expect[$k]) { $wrong++; } }
$assert($wrong === 0, "D mixed: $wrong responses had the wrong verdict");
$approvedNeg = ScanLog::whereIn('ticket_number', array_merge($pools['refunded'] ?? [], []))->count();
$out['mixed_cases'] = ['requests' => count($jobs), 'wrong_verdicts' => $wrong, 'latency_ms' => summarize(array_column($r, 'ms'))];
$allLat = array_merge($allLat, array_column($r, 'ms'));
$leaked = DB::table('tickets')->whereIn('status', ['checked_in'])->whereNotNull('cancellation_source')->count() + DB::table('tickets')->join('orders', 'orders.id', '=', 'tickets.order_id')->where('tickets.status', 'checked_in')->whereNotIn('orders.status', ['paid', 'partially_refunded'])->count();
$assert($leaked === 0, "D: $leaked refused tickets ended up checked in");

// ───────── E. scanner restrictions through direct HTTP ─────────
[$r] = blast($base, [$thirdSession] + $sessions, [[0, $take('valid', 1)[0], null], [0, $take('valid', 1)[0], $fracture->id]]);
$assert(($r[0]['json']['code'] ?? '') === 'wrong_event', 'E: third-event scanner admitted a Fracture ticket (no event id sent)');
$assert(($r[1]['json']['code'] ?? '') === 'event_not_allowed', 'E: third-event scanner could target the Fracture event id');
[$r] = blast($base, $sessions, [[0, $take('valid', 1)[0], $afterParty->id]]);
$assert(($r[0]['json']['code'] ?? '') === 'event_not_allowed', 'E: Fracture scanner could target another event id');
$out['direct_request_restrictions'] = 'verified';

// ───────── F. slow DB: a competing transaction holds the ticket row ─────────
$pdo = DB::connection()->getPdo();
$lockNum = $take('valid', 1)[0];
$holder = new PDO(sprintf('mysql:host=%s;port=%s;dbname=%s', $host, config('database.connections.mysql.port'), $dbName), config('database.connections.mysql.username'), config('database.connections.mysql.password'), [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
$holder->beginTransaction(); $holder->query("SELECT id FROM tickets WHERE ticket_number='$lockNum' FOR UPDATE");
$t0 = microtime(true);
$mh = curl_multi_init(); $c = easy($base.'/dashboard/scanner/check-in', $sessions[1]['jar'], [CURLOPT_POST => true, CURLOPT_POSTFIELDS => json_encode(['code' => $lockNum, 'event_id' => $fracture->id]), CURLOPT_HTTPHEADER => ['Accept: application/json', 'Content-Type: application/json', 'X-CSRF-TOKEN: '.$sessions[1]['csrf']]]);
curl_multi_add_handle($mh, $c); curl_multi_exec($mh, $run); usleep(1500000); curl_multi_exec($mh, $run);
$stillWaiting = $run > 0; $holder->commit(); // release after 1.5 s
do { curl_multi_exec($mh, $run); if ($run) curl_multi_select($mh, 0.05); } while ($run);
$slow = json_decode(curl_multi_getcontent($c), true); $slowMs = (microtime(true) - $t0) * 1000;
$assert($stillWaiting && ($slow['code'] ?? '') === 'approved', 'F: request did not wait for the row lock and then approve');
$out['slow_db_row_lock_1.5s'] = ['request_waited' => $stillWaiting, 'verdict' => $slow['code'] ?? null, 'ms' => round($slowMs)];

// lock held past innodb_lock_wait_timeout → must NOT approve, and must say the result is unknown
DB::statement('SET GLOBAL innodb_lock_wait_timeout=2');
$lockNum2 = $take('valid', 1)[0];
$holder->beginTransaction(); $holder->query("SELECT id FROM tickets WHERE ticket_number='$lockNum2' FOR UPDATE");
[$r] = blast($base, $sessions, [[2, $lockNum2, $fracture->id]]);
$holder->rollBack();
$assert(($r[0]['json']['ok'] ?? true) === false && ($r[0]['json']['code'] ?? '') !== 'approved' && Ticket::where('ticket_number', $lockNum2)->value('status') === 'not_checked_in', 'F2: lock timeout produced a false approval');
$out['lock_wait_timeout'] = ['http' => $r[0]['status'], 'verdict' => $r[0]['json']['code'] ?? null, 'ticket_still_not_checked_in' => Ticket::where('ticket_number', $lockNum2)->value('status') === 'not_checked_in'];
DB::statement('SET GLOBAL innodb_lock_wait_timeout=50');
[$r] = blast($base, $sessions, [[2, $lockNum2, $fracture->id]]);
$assert(($r[0]['json']['code'] ?? '') === 'approved', 'F3: safe re-check after the uncertain result did not approve a valid ticket');

// ───────── G. timeouts / dropped connections, then re-verification ─────────
$aborted = $take('valid', 60); $ok1 = 0; $unknown = 0;
foreach (array_chunk($aborted, 20) as $chunk) {
    // client gives up after 2 ms: the server may or may not have finished
    blast($base, $sessions, array_map(fn ($num, $i) => [$i, $num, $fracture->id], $chunk, array_keys($chunk)), '/dashboard/scanner/check-in', 2);
}
usleep(1500000);
foreach ($aborted as $num) {
    $st = Ticket::where('ticket_number', $num)->value('status');
    $logs = ScanLog::where('ticket_number', $num)->where('action', 'status_update')->where('new_status', 'checked_in')->count();
    $assert(($st === 'checked_in' && $logs === 1) || ($st === 'not_checked_in' && $logs === 0), "G: ticket $num inconsistent after aborted request (status=$st logs=$logs)");
    $st === 'checked_in' ? $ok1++ : $unknown++;
}
// operator re-verifies via read-only lookup, then rechecks only what is still unchecked
$recheck = array_values(array_filter($aborted, fn ($n) => Ticket::where('ticket_number', $n)->value('status') === 'not_checked_in'));
[$r] = blast($base, $sessions, array_map(fn ($n, $i) => [$i % 20, $n, $fracture->id], $recheck, array_keys($recheck)));
$assert($count($r, 'approved') === count($recheck), 'G: re-check after timeout failed');
[$r2] = blast($base, $sessions, array_map(fn ($n, $i) => [$i % 20, $n, $fracture->id], $aborted, array_keys($aborted)));
$assert($count($r2, 'already_checked_in') === count($aborted), 'G: second pass should be all duplicates');
$out['client_timeout_after_server_work'] = ['aborted_requests' => count($aborted), 'server_had_committed' => $ok1, 'server_had_not' => $unknown, 'after_recheck_all_checked_in_exactly_once' => true];

// ───────── H. global invariants ─────────
$multi = DB::table('scan_logs')->where('action', 'status_update')->where('new_status', 'checked_in')->select('ticket_id', DB::raw('count(*) c'))->groupBy('ticket_id')->having('c', '>', 1)->count();
$mismatch = DB::table('tickets')->where('status', 'checked_in')->whereNotExists(fn ($q) => $q->select(DB::raw(1))->from('scan_logs')->whereColumn('scan_logs.ticket_id', 'tickets.id')->where('scan_logs.action', 'status_update')->where('scan_logs.new_status', 'checked_in'))->count();
$assert($multi === 0, "H: $multi tickets have more than one successful check-in log");
$assert($mismatch === 0, "H: $mismatch checked-in tickets without a check-in log");
$out['invariants'] = ['tickets_with_more_than_one_success_log' => $multi, 'checked_in_without_log' => $mismatch, 'total_checked_in' => DB::table('tickets')->where('status', 'checked_in')->count(), 'tickets_generated' => $total];
$out['overall_http_latency_ms'] = summarize($allLat);
$out['env'] = ['cpus' => (int) shell_exec('nproc'), 'php_server_workers' => $workers, 'db' => 'MariaDB '.DB::selectOne('select version() v')->v, 'note' => 'client, PHP server workers and MariaDB share this one machine; php -S boots the framework on every request'];

// ───────── I. query counts (in-process, one request) ─────────
$svc = app(App\Services\Scanner\CheckInService::class);
$qc = function (string $code) use ($svc, $scanners, $fracture) { $u = User::find($scanners[0]->id); DB::flushQueryLog(); DB::enableQueryLog(); $svc->checkIn($u, $code, $fracture->id); $n = count(DB::getQueryLog()); DB::disableQueryLog(); return $n; };
$out['queries_per_request'] = ['approved' => $qc($take('valid', 1)[0]), 'duplicate' => $qc($pools['prechecked'][0] ?? $take('valid', 1)[0]), 'refunded' => $qc($pools['refunded'][0] ?? '1'), 'not_found' => $qc('999999999999')];

ksort($statusTally); $out['http_status_code_tally'] = $statusTally;
$out['assertion_failures'] = $fail;
echo json_encode($out, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)."\n";
exit($fail ? 1 : 0);
