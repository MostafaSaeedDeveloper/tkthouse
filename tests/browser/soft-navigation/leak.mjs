import { chromium } from 'playwright-core';
const BASE = process.env.BASE_URL || 'http://localhost:8000';
const b = await chromium.launch({ executablePath: process.env.CHROMIUM_PATH || '/opt/pw-browsers/chromium', args: ['--no-sandbox', '--js-flags=--expose-gc'] });
const ctx = await b.newContext({ viewport: { width: 1366, height: 800 } });
const p = await ctx.newPage(); await p.route(/^https?:\/\/(?!localhost)/, r => r.abort());
const cdp = await ctx.newCDPSession(p); await cdp.send('Performance.enable');
await p.goto(BASE + '/'); await p.waitForTimeout(500); await p.evaluate(() => window.__marker = 'alive');
const sample = async () => { await cdp.send('HeapProfiler.collectGarbage'); const { metrics } = await cdp.send('Performance.getMetrics'); const m = Object.fromEntries(metrics.map(x => [x.name, x.value])); return { heapKB: Math.round(m.JSHeapUsedSize / 1024), nodes: m.Nodes, listeners: m.JSEventListeners }; };
const cycle = ['/events', '/events/pulsed', '/', '/about', '/contact', '/events/sideral'];
const click = async (h) => { const a = p.locator(`.tkt-nav a[href='${BASE}${h === '/' ? '' : h}']`); if (await a.count()) await a.first().evaluate(e => e.click()); else await p.locator(`a[href*='${h}']`).first().evaluate(e => e.click()); await p.waitForURL(u => u.pathname === h); await p.waitForTimeout(120); };
// events/sideral reachable only from the listing: route through /events
async function lap() { for (const h of ['/events', '/events/pulsed', '/', '/about', '/contact', '/events', '/events/sideral', '/']) await click(h); }
await lap(); const s1 = await sample();
for (let i = 0; i < 12; i++) await lap();
const s2 = await sample();
console.log('after 1 lap (8 navigations):', JSON.stringify(s1));
console.log('after 13 laps (104 navigations):', JSON.stringify(s2));
console.log('still a single document (no reload):', await p.evaluate(() => window.__marker === 'alive'));
const grow = { heapKB: s2.heapKB - s1.heapKB, nodes: s2.nodes - s1.nodes, listeners: s2.listeners - s1.listeners };
console.log('growth over 96 extra navigations:', JSON.stringify(grow));
await b.close();
process.exit(grow.listeners > 5 || grow.nodes > 400 || grow.heapKB > 1500 ? 1 : 0);
