# Scanner browser tests (Chromium, fake camera)

Drives the real scanner page against a LOCAL server + the isolated test database created by `tests/Support/scanner_load.php`
(never production). Chromium's fake video device streams generated QR codes, so camera decode, rear-camera start, overlay states,
debounce, network failure, timeout-after-success and session expiry are exercised end to end.

    # 1. DB + data (see tests/Support/scanner_load.php header), 2. server, 3. run:
    FIXTURE=/tmp/e2e/fixture.json BASE=http://127.0.0.1:8098 node tests/browser/scanner/scanner-e2e.mjs

`fixture.json`: `{ "valid":[…ticket numbers…], "refunded":"…", "other":"…", "used":"…", "pending":"…", "user":"load_s5", "password":"LoadTest!123", "db":"mysql -N -uroot -S /tmp/mdb/s.sock tkt_scanner_test -e" }`

Not covered here: WebKit/iOS Safari (no WebKit build in the CI box) — use the manual checklist on a real iPhone.
