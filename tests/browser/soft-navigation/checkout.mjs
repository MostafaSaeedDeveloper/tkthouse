import { chromium } from 'playwright-core';
const BASE = process.env.BASE_URL || 'http://localhost:8000';
const browser = await chromium.launch({ executablePath: process.env.CHROMIUM_PATH || '/opt/pw-browsers/chromium', args: ['--no-sandbox'] });
const ctx = await browser.newContext({ viewport: { width: 1366, height: 800 } });
let pass = 0, fail = 0; const t = (n, ok, x = '') => { ok ? pass++ : fail++; console.log(`${ok ? 'PASS' : 'FAIL'}  ${n}${x ? '  -> ' + x : ''}`); };
const page = await ctx.newPage(); const errors = []; const reqs = [];
page.on('pageerror', e => errors.push(e.message));
page.on('console', m => { if (m.type() === 'error' && !/ERR_FAILED|Failed to load resource|net::/.test(m.text())) errors.push(m.text()); });
page.on('request', r => reqs.push({ m: r.method(), u: r.url().replace(BASE, ''), swup: r.headers()['x-requested-with'] === 'swup' }));
await page.route(/^https?:\/\/(?!localhost)/, r => r.abort());
const alive = () => page.evaluate(() => window.__marker === 'alive');
const settle = () => page.waitForTimeout(450);

await page.goto(BASE + '/'); await page.evaluate(() => window.__marker = 'alive');
await page.click(`.tkt-nav a[href='${BASE}/events']`); await page.waitForURL(/events$/); await settle();
await page.locator("main a[href*='/events/pulsed']").first().evaluate(e => e.click()); await page.waitForURL(/pulsed/); await settle();
await page.locator('.tkt-ticket:not([data-disabled="1"]) [data-step="1"]').first().click();
await Promise.all([page.waitForURL(u => u.pathname.startsWith('/checkout')), page.click('#checkoutBtn')]);
await page.waitForLoadState('load'); await page.waitForTimeout(600);
t('checkout is a full document load', !(await alive()), page.url().slice(0, 60));
t('checkout page scripts initialised (express/otp/checkout modules)', await page.evaluate(() => !!(window.TktExpress && window.TktOtp)));
t('checkout form present', (await page.locator('#expressForm').count()) === 1);
t('checkout: no console errors', errors.length === 0, errors.join('; '));
t('checkout request was a normal navigation (no swup header, no fetch-based swap)', reqs.filter(r => r.u.startsWith('/checkout')).every(r => !r.swup));

// Header links on the checkout page are full navigations
await page.evaluate(() => window.__marker = 'alive');
await Promise.all([page.waitForURL(u => u.pathname === '/about'), page.click(`.tkt-nav a[href='${BASE}/about']`)]); await page.waitForLoadState('load');
t('checkout -> About via header: full navigation', !(await alive()));

// Back across the hard/soft boundary still works
await page.goBack(); await page.waitForLoadState('load'); await page.waitForTimeout(600);
t('back from About lands on checkout', /\/checkout/.test(page.url()));
await page.goBack(); await page.waitForLoadState('load'); await page.waitForTimeout(700);
t('back from checkout lands on the event page and the selector is usable', /pulsed/.test(page.url()) && (await page.locator('#summaryTotal').count()) === 1);
await page.evaluate(() => window.__marker = 'alive');
await page.goBack(); await page.waitForTimeout(900);
t('further back (to /events) works', /\/events$/.test(page.url()), page.url());
await page.goBack(); await page.waitForTimeout(900);
t('further back (to /) works', page.url() === BASE + '/', page.url());
t('boundary navigation: no console errors', errors.length === 0, errors.join('; '));

// Nothing sensitive was fetched through the soft-navigation path
const swupFetches = reqs.filter(r => r.swup).map(r => r.u.split('?')[0]);
t('only public pages were ever requested by the soft router', swupFetches.every(u => /^(\/|\/events|\/events\/[^/]+|\/about|\/contact)$/.test(u)), [...new Set(swupFetches)].join(','));
console.log(`\n${pass} passed, ${fail} failed`);
await browser.close(); process.exit(fail ? 1 : 0);
