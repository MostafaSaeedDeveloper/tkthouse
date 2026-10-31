import { createRequire } from 'node:module'; const { chromium, devices } = createRequire(import.meta.url)('playwright');
import { execSync } from 'node:child_process';
import { readFileSync, mkdirSync } from 'node:fs';

const BASE = process.env.BASE || 'http://127.0.0.1:8098';
const fx = JSON.parse(readFileSync(process.env.FIXTURE, 'utf8'));
const SHOTS = process.env.SHOTS || '/tmp/e2e/shots';
mkdirSync(SHOTS, { recursive: true });
const sql = (q) => execSync(`${fx.db} "${q}"`).toString().trim();
const status = (n) => sql(`select status from tickets where ticket_number='${n}'`);
const okLogs = (n) => +sql(`select count(*) from scan_logs where ticket_number='${n}' and action='status_update' and new_status='checked_in'`);

let pass = 0, fail = 0;
const ok = (c, m) => { if (c) { pass++; console.log('  ✓', m); } else { fail++; console.log('  ✗ FAIL:', m); } };
const sleep = (ms) => new Promise((r) => setTimeout(r, ms));

function video(payload, name) {
  const f = `/tmp/e2e/${name}.mjpeg`;
  execSync(`python3 ${new URL('./mkqr.py', import.meta.url).pathname} ${JSON.stringify(payload)} ${f}`);
  return f;
}

async function open(videoFile, { device = 'iPhone 13', cams = true, expireCookies = false } = {}) {
  const args = cams ? ['--use-fake-ui-for-media-stream', '--use-fake-device-for-media-stream', `--use-file-for-fake-video-capture=${videoFile}`] : [];
  const browser = await chromium.launch({ args });
  const ctx = await browser.newContext({ ...devices[device], permissions: cams ? ['camera'] : [] });
  const page = await ctx.newPage();
  const external = [];
  page.on('request', (r) => { if (!r.url().startsWith(BASE) && !r.url().startsWith('data:') && !r.url().startsWith('blob:')) external.push(r.url()); });
  await page.goto(BASE + '/scanner');
  await page.fill('input[name=username]', fx.user); await page.fill('input[name=password]', fx.password);
  await Promise.all([page.waitForURL(/dashboard\/scanner/), page.click('button[type=submit]')]);
  return { browser, ctx, page, external };
}
const title = (p) => p.locator('#rTitle').innerText().catch(() => '');
const waitTitle = async (p, re, t = 15000) => { const end = Date.now() + t; while (Date.now() < end) { const x = await title(p); if (re.test(x) && await p.locator('#result.show').count()) return x; await sleep(150); } return await title(p); };

const [v1, v2, v3, v4] = fx.valid;
console.log('1. page, event, no external requests, camera ready');
{
  const { browser, page, external } = await open(video(v1, 'v1'));
  await page.waitForSelector('#camText');
  await page.waitForFunction(() => /Camera ready/.test(document.getElementById('camText').textContent), null, { timeout: 15000 }).catch(() => {});
  ok(/Camera ready/.test(await page.locator('#camText').innerText()), 'camera starts (rear/environment request)');
  ok((await page.locator('#eventName').innerText()).includes('Fracture Halloween'), 'event name shown');
  ok(await page.locator('#who').innerText().then((t) => t.includes('Load Scanner 5')), 'staff name shown');
  ok(external.length === 0, 'no request left localhost (no CDN): ' + JSON.stringify(external));
  ok(await page.evaluate(() => document.documentElement.scrollWidth <= innerWidth), 'no horizontal scroll');
  await page.screenshot({ path: `${SHOTS}/01-ready-portrait.png` });
  // confirm mode (default): scan shows VALID, nothing is checked in yet
  const t = await waitTitle(page, /VALID TICKET/);
  ok(/VALID TICKET/.test(t), 'confirm mode: scan shows VALID TICKET (not approved)');
  ok(status(v1) === 'not_checked_in', 'DB untouched before the operator confirms');
  await page.screenshot({ path: `${SHOTS}/02-confirm.png` });
  await page.click('#rConfirm');
  ok(/ENTRY APPROVED/.test(await waitTitle(page, /ENTRY APPROVED/)), 'after confirm → ENTRY APPROVED');
  ok(status(v1) === 'checked_in' && okLogs(v1) === 1, 'DB: checked in exactly once');
  await page.screenshot({ path: `${SHOTS}/03-approved.png` });
  await browser.close();
}

console.log('2. fast mode + debounce (QR stays in view for ~8 s → one request)');
{
  const { browser, page } = await open(video(v2, 'v2'));
  await page.evaluate(() => localStorage.setItem('tkt.fast', '1')); await page.reload();
  let posts = 0; page.on('request', (r) => { if (r.url().endsWith('/scanner/check-in')) posts++; });
  ok(/ENTRY APPROVED/.test(await waitTitle(page, /ENTRY APPROVED/)), 'fast mode: scan → server → ENTRY APPROVED');
  await sleep(8000);
  ok(posts === 1, `only one check-in request while the same QR stays in view (saw ${posts})`);
  ok(okLogs(v2) === 1 && status(v2) === 'checked_in', 'DB: once');
  ok(+await page.locator('#cOk').innerText() >= 1, 'operator counter incremented');
  await browser.close();
}

console.log('3. already used / refunded / other event / invalid / unpaid');
for (const [pay, re, label, shot] of [[v1, /ALREADY USED/, 'already checked in', '04-used'], [fx.refunded, /REFUNDED/, 'refunded ticket', '05-refunded'], [fx.other, /WRONG EVENT/, 'ticket of another event', '06-wrong-event'], ['HELLO-NOT-A-TICKET', /INVALID TICKET/, 'invalid QR', '07-invalid'], [fx.pending, /NEEDS REVIEW/, 'unpaid order', '08-review']]) {
  const { browser, page } = await open(video(pay, 'x'));
  await page.evaluate(() => localStorage.setItem('tkt.fast', '1')); await page.reload();
  const t = await waitTitle(page, re);
  ok(re.test(t), `${label} → "${t}"`);
  const cls = await page.locator('#result').getAttribute('class');
  ok(/red|amber/.test(cls), `${label}: red/amber panel, not green`);
  await page.screenshot({ path: `${SHOTS}/${shot}.png` });
  await browser.close();
}
ok(status(fx.refunded) === 'canceled' && status(fx.pending) === 'not_checked_in' && status(fx.other) === 'not_checked_in', 'DB: refused tickets unchanged');

console.log('4. network failure: no false approval; verify afterwards');
{
  const { browser, page } = await open(video(v3, 'v3'));
  await page.evaluate(() => localStorage.setItem('tkt.fast', '1')); await page.reload();
  await page.route('**/scanner/check-in', (r) => r.abort('connectionfailed'));
  const t = await waitTitle(page, /UNVERIFIED|NETWORK|NO CONNECTION/);
  ok(/UNVERIFIED/.test(t), `network error → "${t}" (amber, not green)`);
  ok(/amber/.test(await page.locator('#result').getAttribute('class')), 'amber panel');
  ok(status(v3) === 'not_checked_in', 'DB: not checked in');
  await page.screenshot({ path: `${SHOTS}/09-network.png` });
  await page.unroute('**/scanner/check-in');
  await page.click('#rVerify');
  ok(/VALID TICKET/.test(await waitTitle(page, /VALID TICKET/)), 'Verify (read-only lookup) shows the true state: valid, not yet in');
  await page.click('#rConfirm');
  ok(/ENTRY APPROVED/.test(await waitTitle(page, /ENTRY APPROVED/)) && okLogs(v3) === 1, 'explicit re-check then approves exactly once');
  await browser.close();
}

console.log('5. timeout AFTER the server checked the ticket in');
{
  const { browser, page } = await open(video(v4, 'v4'));
  await page.evaluate(() => localStorage.setItem('tkt.fast', '1')); await page.reload();
  await page.route('**/scanner/check-in', async (r) => { await r.fetch(); await r.abort('timedout'); }); // server did the work, client never hears
  ok(/UNVERIFIED/.test(await waitTitle(page, /UNVERIFIED/)), 'response lost → UNVERIFIED (never green)');
  ok(status(v4) === 'checked_in' && okLogs(v4) === 1, 'DB: the check-in did happen, once');
  await page.unroute('**/scanner/check-in');
  await page.click('#rVerify');
  const t = await waitTitle(page, /ALREADY USED/);
  const meta = await page.locator('#rMeta').innerText() + await page.locator('#rHint').innerText();
  ok(/ALREADY USED/.test(t) && /you/i.test(meta), `verify tells the operator it was their own scan moments ago ("${meta.replace(/\n/g, ' ')}")`);
  ok(okLogs(v4) === 1, 'no second success logged');
  await browser.close();
}

console.log('6. session expiry (real 401 from the server)');
{
  const { browser, ctx, page } = await open(null, { cams: false });
  const n = fx.valid[4];
  await sleep(2500);                              // let the page's own in-flight requests finish (their Set-Cookie would revive the session)
  await ctx.clearCookies();                       // the server no longer knows this device
  await page.fill('#manualCode', n); await page.press('#manualCode', 'Enter');
  const t = await waitTitle(page, /SESSION EXPIRED/);
  ok(/SESSION EXPIRED/.test(t), `expired session → "${t}"`);
  ok(await page.locator('#rLogin').isVisible(), 'login link offered');
  ok(/amber/.test(await page.locator('#result').getAttribute('class')), 'amber, never green');
  ok(status(n) === 'not_checked_in', 'nothing checked in');
  await page.screenshot({ path: `${SHOTS}/12-session-expired.png` });
  await browser.close();
}

console.log('7. camera denied → manual entry works');
{
  const { browser, page } = await open(null, { cams: false });
  await page.waitForFunction(() => document.getElementById('camFail').classList.contains('show'), null, { timeout: 15000 }).catch(() => {});
  ok(await page.locator('#camFail').evaluate((e) => e.classList.contains('show')), 'camera failure message shown');
  await page.screenshot({ path: `${SHOTS}/10-camera-denied.png` });
  const n = fx.valid[5];
  await page.fill('#manualCode', n); await page.press('#manualCode', 'Enter');
  ok(/VALID TICKET/.test(await waitTitle(page, /VALID TICKET/)), 'manual number → lookup');
  await page.click('#rConfirm');
  ok(/ENTRY APPROVED/.test(await waitTitle(page, /ENTRY APPROVED/)) && status(n) === 'checked_in', 'manual check-in works without camera');
  await page.click('#rNext');
  await page.fill('#manualCode', n); await page.press('#manualCode', 'Enter');
  ok(/ALREADY USED/.test(await waitTitle(page, /ALREADY USED/)), 'refresh/re-enter → consistent ALREADY USED');
  await browser.close();
}

console.log('8. layouts: small phone, landscape, tablet');
for (const [name, vp] of [['iphone-se', { width: 320, height: 568 }], ['landscape', { width: 844, height: 390 }], ['pixel', { width: 412, height: 915 }]]) {
  const browser = await chromium.launch({ args: ['--use-fake-ui-for-media-stream', '--use-fake-device-for-media-stream'] });
  const ctx = await browser.newContext({ viewport: vp, hasTouch: true, isMobile: true, permissions: ['camera'] });
  const page = await ctx.newPage();
  await page.goto(BASE + '/scanner'); await page.fill('input[name=username]', fx.user); await page.fill('input[name=password]', fx.password);
  await Promise.all([page.waitForURL(/dashboard\/scanner/), page.click('button[type=submit]')]);
  await sleep(2500);
  ok(await page.evaluate(() => document.documentElement.scrollWidth <= innerWidth + 1), `${name}: no horizontal scroll`);
  const camBox = await page.locator('#camBox').boundingBox();
  ok(camBox && camBox.width >= 200 && camBox.height >= 150, `${name}: camera viewport ${Math.round(camBox.width)}×${Math.round(camBox.height)}`);
  const tiny = await page.evaluate(() => [...document.querySelectorAll('button,input,select')].filter((e) => e.offsetParent && (e.getBoundingClientRect().height < 40)).map((e) => e.id || e.tagName));
  ok(tiny.length === 0, `${name}: touch targets ≥ 40px ${JSON.stringify(tiny)}`);
  await page.screenshot({ path: `${SHOTS}/11-${name}.png` });
  await browser.close();
}

console.log(`\n${pass} passed, ${fail} failed`);
process.exit(fail ? 1 : 0);
