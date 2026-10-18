// Navigation benchmark: node measure.mjs <label> [mobile] [slow]
import { chromium } from 'playwright-core';

const BASE = process.env.BASE_URL || 'http://localhost:8000';
const label = process.argv[2] || 'run';
const mobile = process.argv.includes('mobile');
const slow = process.argv.includes('slow');
const RUNS = 5;

const browser = await chromium.launch({ executablePath: process.env.CHROMIUM_PATH || '/opt/pw-browsers/chromium', args: ['--no-sandbox'] });
const ctx = await browser.newContext(mobile
  ? { viewport: { width: 390, height: 844 }, isMobile: true, hasTouch: true }
  : { viewport: { width: 1366, height: 800 } });

const steps = [
  { name: 'Home -> Events', from: '/', href: BASE + '/events', card: false },
  { name: 'Events -> Event details', from: '/events', href: BASE + '/events/pulsed', card: true },
  { name: 'Event details -> Home', from: '/events/pulsed', href: BASE },
  { name: 'Home -> About', from: '/', href: BASE + '/about' },
  { name: 'About -> Contact', from: '/about', href: BASE + '/contact' },
];

const results = [];
const errors = new Set();

for (const step of steps) {
  const runs = [];
  for (let i = 0; i < RUNS + 1; i++) { // first run is warm-up
    const page = await ctx.newPage();
    const cdp = await ctx.newCDPSession(page);
    await cdp.send('Network.enable');
    if (slow) await cdp.send('Network.emulateNetworkConditions', { offline: false, latency: 400, downloadThroughput: 200 * 1024, uploadThroughput: 100 * 1024 });
    // external hosts are unreachable in the sandbox: abort them quickly so timings are deterministic
    await page.route(/^https?:\/\/(?!localhost)/, r => r.abort());
    page.on('pageerror', e => errors.add(step.name + ': ' + e.message));
    page.on('console', m => { if (m.type() === 'error' && !/ERR_FAILED|Failed to load resource/.test(m.text())) errors.add(step.name + ': ' + m.text()); });

    await page.goto(BASE + step.from, { waitUntil: 'load' });
    await page.waitForTimeout(300);
    if (mobile) {
      const burger = await page.$('#tktBurger');
      if (burger && await burger.isVisible()) { await burger.click(); }
    }
    await page.evaluate(() => { window.__marker = 'alive'; });

    const reqs = []; let bytes = 0;
    cdp.on('Network.requestWillBeSent', e => { if (e.request.url.startsWith(BASE)) reqs.push(e.requestId); });
    cdp.on('Network.loadingFinished', e => { if (reqs.includes(e.requestId)) bytes += e.encodedDataLength; });

    const sel = step.card ? "a[href='" + step.href + "']" : (mobile ? '.tkt-mobile-panel nav a' : '.tkt-nav a') + "[href='" + step.href + "']";
    const target = await page.$(sel);
    if (!target) { runs.push(null); await page.close(); continue; }
    const targetHref = await target.getAttribute('href');
    const expectedTitle = await (await ctx.request.get(new URL(targetHref, BASE).href)).text().then(h => (h.match(/<title>([^<]*)<\/title>/) || [])[1]);
    const t0 = Date.now();
    await target.click();
    await page.waitForURL(u => u.href.replace(/\/$/, '') === new URL(targetHref, BASE).href.replace(/\/$/, ''), { timeout: 20000 });
    await page.waitForFunction(t => document.readyState === 'complete' && document.title.replace(/&amp;/g,'&') === t.replace(/&amp;/g,'&'), expectedTitle, { timeout: 20000 });
    const dt = Date.now() - t0;
    await page.waitForTimeout(800); // settle so late requests are counted
    const alive = await page.evaluate(() => window.__marker === 'alive');
    const heap = await page.evaluate(() => performance.memory ? performance.memory.usedJSHeapSize : 0);
    const listeners = await cdp.send('Runtime.evaluate', { expression: 'document' }).then(async r => {
      const c = await cdp.send('DOMDebugger.getEventListeners', { objectId: r.result.objectId });
      return c.listeners.length;
    }).catch(() => -1);
    runs.push({ ms: dt, requests: reqs.length, kb: Math.round(bytes / 1024 * 10) / 10, fullReload: !alive, heapMB: Math.round(heap / 1048576 * 10) / 10, docListeners: listeners });
    await page.close();
  }
  const good = runs.slice(1).filter(Boolean);
  const avg = k => good.length ? Math.round(good.reduce((a, r) => a + r[k], 0) / good.length * 10) / 10 : null;
  results.push({ step: step.name, ms: avg('ms'), requests: avg('requests'), kb: avg('kb'), fullReload: good.every(r => r.fullReload) ? 'yes' : good.some(r => r.fullReload) ? 'sometimes' : 'no', heapMB: avg('heapMB'), docListeners: good[good.length - 1]?.docListeners });
}

console.log(`\n== ${label} (${mobile ? 'mobile' : 'desktop'}${slow ? ', slow-3G-ish' : ''}) ==`);
console.table(results);
if (errors.size) { console.log('Console/page errors:'); errors.forEach(e => console.log(' -', e)); } else console.log('No console/page errors');
await browser.close();
