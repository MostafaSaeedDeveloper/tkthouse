import { chromium } from 'playwright-core';
const BASE = process.env.BASE_URL || 'http://localhost:8000';
const b = await chromium.launch({ executablePath: process.env.CHROMIUM_PATH || '/opt/pw-browsers/chromium', args: ['--no-sandbox'] });
const ctx = await b.newContext({ viewport: { width: 1366, height: 800 } });
const p = await ctx.newPage(); await p.route(/^https?:\/\/(?!localhost)/, r => r.abort());
const cdp = await ctx.newCDPSession(p);
await p.goto(BASE + '/'); await p.waitForTimeout(500);
const click = async (h) => { const a = p.locator(`.tkt-nav a[href='${BASE}${h === '/' ? '' : h}']`); await a.first().evaluate(e => e.click()); await p.waitForURL(u => u.pathname === h); await p.waitForTimeout(150); };
async function listeners(expr) {
  const { result } = await cdp.send('Runtime.evaluate', { expression: expr });
  if (!result.objectId) return {};
  const { listeners } = await cdp.send('DOMDebugger.getEventListeners', { objectId: result.objectId });
  const by = {}; listeners.forEach(l => by[l.type] = (by[l.type] || 0) + 1); return by;
}
const targets = { window: 'window', document: 'document', html: 'document.documentElement', body: 'document.body', header: 'document.getElementById("tktHeader")', main: 'document.getElementById("tkt-main")' };
const snap = async () => { const o = {}; for (const [k, e] of Object.entries(targets)) o[k] = await listeners(e); return o; };
for (const h of ['/events', '/', '/about']) await click(h);
const a = await snap();
for (let i = 0; i < 5; i++) for (const h of ['/events', '/', '/about', '/contact']) await click(h);
const c = await snap();
for (const k of Object.keys(targets)) console.log(k.padEnd(9), JSON.stringify(a[k]), '=>', JSON.stringify(c[k]));
await b.close();
