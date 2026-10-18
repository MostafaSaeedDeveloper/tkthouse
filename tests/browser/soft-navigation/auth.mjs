import { chromium } from 'playwright-core';
const BASE = process.env.BASE_URL || 'http://localhost:8000';
const browser = await chromium.launch({ executablePath: process.env.CHROMIUM_PATH || '/opt/pw-browsers/chromium', args: ['--no-sandbox'] });
const ctx = await browser.newContext({ viewport: { width: 1366, height: 800 } });
let pass = 0, fail = 0; const t = (n, ok, x = '') => { ok ? pass++ : fail++; console.log(`${ok ? 'PASS' : 'FAIL'}  ${n}${x ? '  -> ' + x : ''}`); };
const page = await ctx.newPage(); const errors = [];
page.on('pageerror', e => errors.push(e.message));
page.on('console', m => { if (m.type() === 'error' && !/ERR_FAILED|Failed to load resource|net::/.test(m.text())) errors.push(m.text()); });
await page.route(/^https?:\/\/(?!localhost)/, r => r.abort());
const alive = () => page.evaluate(() => window.__marker === 'alive');
const mark = () => page.evaluate(() => { window.__marker = 'alive'; });

/* login through the real modal, after a soft navigation */
await page.goto(BASE + '/'); await mark();
await page.click(`.tkt-nav a[href='${BASE}/about']`); await page.waitForURL(/about/); await page.waitForTimeout(400);
t('guest: soft nav before login', await alive());
await page.locator('[data-auth-open="login"]:visible').first().click(); await page.waitForTimeout(600);
const form = page.locator('#auth-panel-login form');
await form.locator('input[name=login]').fill('spa-test@example.test');
await form.locator('input[name=password]').fill('Passw0rd!spa');
await Promise.all([page.waitForURL(u => u.pathname.startsWith('/account'), { timeout: 15000 }), form.locator('button[type=submit]').click()]);
await page.waitForLoadState('load');
t('login (AJAX modal -> redirect) is a full navigation to the account area', !(await alive()) && /\/account/.test(page.url()), page.url());
t('logged-in header shows the account menu', (await page.locator('#tktAccount').count()) === 1);
t('login: no console errors', errors.length === 0, errors.join('; '));

/* soft navigation while logged in (public pages are eligible only from public pages: go home first) */
await page.goto(BASE + '/'); await mark();
await page.click(`.tkt-nav a[href='${BASE}/events']`); await page.waitForURL(/events/); await page.waitForTimeout(400);
t('logged in: soft nav works and header stays logged in', (await alive()) && (await page.locator('#tktAccount').count()) === 1);

/* account links in the header are NOT soft */
await page.locator('.tkt-account__toggle').click();
await Promise.all([page.waitForURL(/\/account\/orders/), page.locator(".tkt-account__menu a[href$='/account/orders']").click()]);
await page.waitForLoadState('load');
t('account menu link: full navigation', !(await alive()));
t('on a non-public page, header links to public pages are full navigations too (no half-initialised mix)', await (async () => {
  await mark();
  await Promise.all([page.waitForURL(u => u.pathname === '/about'), page.click(`.tkt-nav a[href='${BASE}/about']`)]);
  await page.waitForLoadState('load');
  return !(await alive());
})());

/* session expires in another tab while this page stays open */
await page.goto(BASE + '/'); await mark();
await ctx.clearCookies(); // simulates expiry / logout elsewhere
const expectedFallbacks = errors.length;
await page.click(`.tkt-nav a[href='${BASE}/about']`); await page.waitForTimeout(2000); await page.waitForLoadState('load');
t('session changed under a soft navigation: falls back to a full load', !(await alive()));
t('...and the header is now the guest header (no stale account menu)', (await page.locator('#tktAccount').count()) === 0 && (await page.locator('[data-auth-open="login"]:visible').count()) > 0);

/* logout is a plain form POST: full navigation, guest header afterwards */
await page.goto(BASE + '/account/login'); await page.waitForTimeout(300);
await page.goto(BASE + '/'); await page.locator('[data-auth-open="login"]:visible').first().click(); await page.waitForTimeout(500);
const f2 = page.locator('#auth-panel-login form');
await f2.locator('input[name=login]').fill('spa-test@example.test'); await f2.locator('input[name=password]').fill('Passw0rd!spa');
await Promise.all([page.waitForURL(u => u.pathname.startsWith('/account')), f2.locator('button[type=submit]').click()]); await page.waitForLoadState('load');
await page.goto(BASE + '/'); await mark();
await page.locator('.tkt-account__toggle').click();
await Promise.all([page.waitForURL(u => u.pathname === '/', { timeout: 8000 }), page.locator('.tkt-account__menu button[type=submit]').click()]);
await page.waitForLoadState('load'); await page.waitForTimeout(300);
t('logout: full navigation, guest header', !(await alive()) && (await page.locator('#tktAccount').count()) === 0);

const unexpected = errors.filter(e => !/Session changed: full page load needed/.test(e));
t('auth flows: no unexpected console errors (the deliberate fallback is logged once by Swup)', unexpected.length === 0, unexpected.join('; '));
console.log(`\n${pass} passed, ${fail} failed`);
await browser.close(); process.exit(fail ? 1 : 0);
