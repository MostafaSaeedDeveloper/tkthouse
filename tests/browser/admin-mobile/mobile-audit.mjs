import { chromium } from 'playwright-core';
import fs from 'fs';
const BASE = process.env.BASE_URL || 'http://localhost:8000';
const OUT = process.argv[2] || 'shots';
fs.mkdirSync(OUT, { recursive: true });
const b = await chromium.launch({ executablePath: process.env.CHROMIUM_PATH || '/opt/pw-browsers/chromium', args: ['--no-sandbox'] });
const ctx = await b.newContext({ viewport: { width: 390, height: 844 }, isMobile: true, hasTouch: true, deviceScaleFactor: 1 });
const p = await ctx.newPage(); await p.route(/^https?:\/\/(?!localhost)/, r => r.abort());
await p.goto(BASE + '/login'); await p.fill('input[name=username]', 'auditadmin'); await p.fill('input[name=password]', 'Passw0rd!aud');
await Promise.all([p.waitForURL(u => !u.pathname.startsWith('/login')), p.click('button[type=submit]')]);
const pages = (process.argv[3] || '').split(',').filter(Boolean);
const list = pages.length ? pages : ['/dashboard', '/dashboard/orders', '/dashboard/orders/1', '/dashboard/orders/deleted', '/dashboard/events', '/dashboard/events/create', '/dashboard/events/1/edit', '/dashboard/tickets', '/dashboard/tickets/create', '/dashboard/customers', '/dashboard/users', '/dashboard/users/create', '/dashboard/roles', '/dashboard/permissions', '/dashboard/promo-codes', '/dashboard/promo-codes/create', '/dashboard/payments', '/dashboard/payments/transactions', '/dashboard/payments/refunds', '/dashboard/payments/imported', '/dashboard/payment-methods', '/dashboard/reports', '/dashboard/reports/agents', '/dashboard/guest-list', '/dashboard/guest-list/create', '/dashboard/scanner', '/dashboard/scanners', '/dashboard/scanners/create', '/dashboard/affiliates', '/dashboard/affiliates/create', '/dashboard/activity-logs', '/dashboard/settings', '/dashboard/settings/integrations', '/dashboard/settings/payments/providers', '/dashboard/settings/payments/webhooks', '/', '/events', '/events/pulsed', '/about', '/contact'];
const rows = [];
for (const path of list) {
  const errs = []; const h = e => errs.push(e.message); p.on('pageerror', h);
  const res = await p.goto(BASE + path, { waitUntil: 'load' }).catch(e => ({ status: () => 'ERR ' + e.message }));
  await p.waitForTimeout(500);
  const st = typeof res.status === 'function' ? res.status() : '?';
  const m = await p.evaluate(() => {
    const vw = window.innerWidth;
    const sel = el => el.tagName.toLowerCase() + (el.id ? '#' + el.id : '') + (el.className && typeof el.className === 'string' ? '.' + el.className.trim().split(/\s+/).slice(0, 2).join('.') : '');
    const vis = el => { const r = el.getBoundingClientRect(); const cs = getComputedStyle(el); return r.width > 0 && r.height > 0 && cs.visibility !== 'hidden' && cs.display !== 'none'; };
    const clippedAncestor = el => { for (let a = el.parentElement; a && a !== document.body; a = a.parentElement) { const o = getComputedStyle(a).overflowX; if (o === 'auto' || o === 'scroll' || o === 'hidden') return true; } return false; };
    const over = [];
    document.querySelectorAll('body *').forEach(el => { if (!vis(el)) return; const r = el.getBoundingClientRect(); if (r.right > vw + 1 && !clippedAncestor(el) && getComputedStyle(el).position !== 'fixed') over.push(sel(el) + ' r=' + Math.round(r.right)); });
    const small = [];
    document.querySelectorAll('a[href], button, input:not([type=hidden]), select, textarea, [role=button]').forEach(el => { if (!vis(el)) return; const r = el.getBoundingClientRect(); const t = el.type; if (t === 'checkbox' || t === 'radio') return; if (r.height < 32 || r.width < 32) small.push(sel(el) + ' ' + Math.round(r.width) + 'x' + Math.round(r.height)); });
    const tiny = []; const w = document.createTreeWalker(document.body, NodeFilter.SHOW_TEXT);
    while (w.nextNode()) { const n = w.currentNode; if (!n.textContent.trim()) continue; const el = n.parentElement; if (!el || !vis(el)) continue; const fs = parseFloat(getComputedStyle(el).fontSize); if (fs < 11) tiny.push(sel(el) + ' ' + fs + 'px'); }
    return { docOverflow: document.documentElement.scrollWidth - vw, bodyOverflow: document.body.scrollWidth - vw, over: [...new Set(over)].slice(0, 6), overCount: over.length, small: [...new Set(small)].slice(0, 6), smallCount: small.length, tiny: [...new Set(tiny)].slice(0, 4), title: document.title };
  });
  p.off('pageerror', h);
  const name = path.replace(/[^a-z0-9]+/gi, '_').replace(/^_|_$/g, '') || 'home';
  await p.screenshot({ path: `${OUT}/${name}.png`, fullPage: false });
  rows.push({ path, st, ...m, errs });
}
for (const r of rows) {
  const bad = r.docOverflow > 1 || r.bodyOverflow > 1 || r.overCount > 0 || r.errs.length || String(r.st) !== '200';
  console.log(`${bad ? '!!' : 'ok'} ${r.path} [${r.st}] docOverflow=${r.docOverflow} over=${r.overCount} smallTargets=${r.smallCount} tiny=${r.tiny.length}${r.errs.length ? ' ERR:' + r.errs[0] : ''}`);
  if (bad) { r.over.forEach(o => console.log('     over:', o)); }
}
console.log('\n-- small tap targets (<32px) per page, first examples --');
for (const r of rows) if (r.smallCount) console.log(r.path.padEnd(42), r.smallCount, '|', r.small.slice(0, 3).join(' ; '));
await b.close();
