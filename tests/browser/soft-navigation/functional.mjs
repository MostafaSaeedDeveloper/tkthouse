// Functional regression for soft navigation. node functional.mjs [mobile]
import { chromium } from 'playwright-core';

const BASE = process.env.BASE_URL || 'http://localhost:8000';
const mobile = process.argv.includes('mobile');
const browser = await chromium.launch({ executablePath: process.env.CHROMIUM_PATH || '/opt/pw-browsers/chromium', args: ['--no-sandbox'] });
const ctx = await browser.newContext(mobile ? { viewport: { width: 390, height: 844 }, isMobile: true, hasTouch: true } : { viewport: { width: 1366, height: 800 } });

let pass = 0, fail = 0;
const t = (name, ok, extra = '') => { ok ? pass++ : fail++; console.log(`${ok ? 'PASS' : 'FAIL'}  ${name}${extra ? '  -> ' + extra : ''}`); };

async function open(path) {
  const page = await ctx.newPage();
  const errors = [];
  page.on('pageerror', e => errors.push(e.message));
  page.on('console', m => { if (m.type() === 'error' && !/ERR_FAILED|Failed to load resource|net::/.test(m.text())) errors.push(m.text()); });
  await page.route(/^https?:\/\/(?!localhost)/, r => r.abort());
  await page.goto(BASE + path, { waitUntil: 'load' });
  await page.waitForTimeout(250);
  await page.evaluate(() => { window.__marker = 'alive'; });
  page._errors = errors;
  return page;
}
const alive = p => p.evaluate(() => window.__marker === 'alive');
const info = p => p.evaluate(() => ({
  path: location.pathname + location.search,
  title: document.title,
  canonical: document.querySelector('link[rel=canonical]')?.href,
  desc: document.querySelector('meta[name=description]')?.content,
  ogTitle: document.querySelector('meta[property="og:title"]')?.content,
  active: [...document.querySelectorAll('.tkt-nav a.is-active')].map(a => a.textContent.trim()),
  h1: document.querySelector('main h1')?.textContent.trim(),
  scrollY: Math.round(window.scrollY),
}));
async function go(page, href) {
  const sel = (mobile ? '.tkt-mobile-panel nav a' : '.tkt-nav a') + `[href='${BASE}${href === '/' ? '' : href}']`;
  if (mobile) { const b = page.locator('#tktBurger'); if (await b.isVisible() && !(await page.locator('#tktHeader.is-open').count())) await b.click(); }
  await page.click(sel);
}
const settle = p => p.waitForTimeout(450);
const keydownCount = async (page) => {
  const cdp = await ctx.newCDPSession(page);
  const { result } = await cdp.send('Runtime.evaluate', { expression: 'document' });
  const { listeners } = await cdp.send('DOMDebugger.getEventListeners', { objectId: result.objectId });
  const byType = {}; listeners.forEach(l => byType[l.type] = (byType[l.type] || 0) + 1);
  await cdp.detach();
  return byType;
};

/* 1. direct URLs render with SSR and no errors */
for (const path of ['/', '/events', '/events/pulsed', '/about', '/contact', '/terms-and-conditions', '/privacy-policy', '/cookie-policy']) {
  const p = await open(path);
  const i = await info(p);
  t(`direct ${path}: title/canonical/description present`, !!i.title && i.canonical?.endsWith(path === '/' ? ':8000/' : path) && i.desc?.length > 10, `${i.title} | ${i.canonical}`);
  t(`direct ${path}: no console errors`, p._errors.length === 0, p._errors.join('; '));
  await p.close();
}
{ // HTML is server-rendered (no JS needed)
  const html = await (await ctx.request.get(BASE + '/events')).text();
  t('SSR: /events HTML contains event links and h1 without JS', /href="[^"]*\/events\/pulsed"/.test(html) && /<h1/.test(html));
}

/* 2. soft navigation chain */
{
  const p = await open('/');
  const chain = [['/events', 'Events'], ['/events/pulsed', null], ['/', 'Home'], ['/about', 'About'], ['/contact', 'Contact']];
  let last = await info(p);
  for (const [href, nav] of chain) {
    if (href === '/events/pulsed') { await p.click(`a[href='${BASE}/events/pulsed']`); } else { await go(p, href); }
    await p.waitForURL(u => u.pathname === href, { timeout: 8000 });
    await settle(p);
    const i = await info(p);
    t(`soft ${last.path} -> ${href}: no full reload`, await alive(p));
    t(`soft -> ${href}: title changed & canonical/og updated`, i.title !== last.title && i.canonical.endsWith(href === '/' ? ':8000/' : href) && i.ogTitle === i.title, `${i.title}`);
    if (nav) t(`soft -> ${href}: active nav = ${nav}`, i.active.includes(nav) && i.active.length === 1, JSON.stringify(i.active));
    t(`soft -> ${href}: scrolled to top`, i.scrollY === 0, String(i.scrollY));
    last = i;
  }
  const ev = await p.evaluate(() => 0);
  t('chain: no console errors', p._errors.length === 0, p._errors.join('; '));

  /* 3. back / forward */
  const trail = [];
  for (let k = 0; k < 5; k++) { await p.goBack(); await settle(p); trail.push((await info(p)).path); }
  t('back x5 stays soft & ends at /', (await alive(p)) && trail.join(',') === '/about,/,/events/pulsed,/events,/', trail.join(','));
  const titleBack = (await info(p)).title;
  for (let k = 0; k < 2; k++) { await p.goForward(); await settle(p); }
  const f = await info(p);
  t('forward x2 -> /events/pulsed with matching title/h1', f.path === '/events/pulsed' && (await alive(p)) && /Pulsed/.test(f.title) && /pulsed/i.test(f.h1 || ''), `${f.path} ${f.title}`);
  await p.close();
}

/* 4. scroll restoration */
{
  const p = await open('/events');
  await p.evaluate(() => window.scrollTo(0, 300)); await settle(p);
  const before = (await info(p)).scrollY;
  // click without Playwright's scroll-into-view so the saved position is exactly `before`
  await p.locator(`a[href='${BASE}/events/pulsed']`).first().evaluate(e => e.click()); await p.waitForURL(/pulsed/); await settle(p);
  const top = (await info(p)).scrollY;
  await p.goBack(); await settle(p);
  const restored = (await info(p)).scrollY;
  t('scroll: new page starts at top, back restores position', top === 0 && before > 0 && Math.abs(restored - before) < 40, `before=${before} top=${top} restored=${restored}`);
  await p.close();
}

/* 5. no listener / timer duplication + booking works after soft navigation */
{
  const p = await open('/events');
  const base = await keydownCount(p);
  for (let k = 0; k < 6; k++) {
    await p.click(`a[href='${BASE}/events/pulsed']`); await p.waitForURL(/pulsed/); await settle(p);
    await go(p, '/events'); await p.waitForURL(u => u.pathname === '/events'); await settle(p);
  }
  const after = await keydownCount(p);
  t('leak: document keydown listeners identical after 6 visits to the event page', JSON.stringify(base) === JSON.stringify(after), `${JSON.stringify(base)} vs ${JSON.stringify(after)}`);
  t('leak: body has no stale tkt-has-bar', !(await p.evaluate(() => document.body.classList.contains('tkt-has-bar'))));
  await p.click(`a[href='${BASE}/events/pulsed']`); await p.waitForURL(/pulsed/); await settle(p);
  const onDetail = await keydownCount(p);
  t('leak: exactly 2 booking keydown listeners while on the event page', (onDetail.keydown || 0) - (base.keydown || 0) === 2 || (onDetail.keydown || 0) - (after.keydown || 0) === 2, JSON.stringify(onDetail));
  const plus = p.locator('.tkt-ticket:not([data-disabled="1"]) [data-step="1"]').first();
  if (await plus.count()) {
    await plus.click(); await plus.click();
    const total = await p.locator('#summaryTotal').textContent();
    const href = await p.locator('#checkoutBtn').getAttribute('href');
    t('booking: selector updates summary + checkout link after a soft visit', /[1-9]/.test(total) && /cart=/.test(href), `${total} ${href?.slice(0, 60)}`);
    // Leaving and coming back must give a clean selector
    await go(p, '/'); await p.waitForURL(u => u.pathname === '/'); await settle(p);
    await p.click(`a[href*='/events/pulsed']`).catch(async () => { await p.goto(BASE + '/events/pulsed'); });
    await p.waitForURL(/pulsed/); await settle(p);
    const total2 = await p.locator('#summaryTotal').textContent();
    t('booking: fresh state on re-entering the event page', /^0\.00/.test(total2.trim()), total2);
  } else t('booking: ticket selector present for test event', false, 'no purchasable ticket in seed data');
  t('lifecycle: no console errors', p._errors.length === 0, p._errors.join('; '));
  await p.close();
}

/* 6. rapid clicks */
{
  const p = await open('/');
  const aboutReqs = []; p.on('request', r => { if (/\/about$/.test(r.url())) aboutReqs.push(r.url()); });
  const sel = (mobile ? '.tkt-mobile-panel nav a' : '.tkt-nav a') + `[href='${BASE}/about']`;
  if (mobile) await p.locator('#tktBurger').click();
  for (let k = 0; k < 6; k++) await p.locator(sel).click({ noWaitAfter: true, force: true }).catch(() => {});
  await p.waitForURL(u => u.pathname === '/about'); await settle(p);
  t('rapid: 6 clicks on one link send a single request', aboutReqs.length === 1, `requests=${aboutReqs.length}`);
  t('rapid: result is consistent (no reload, correct page)', (await alive(p)) && (await info(p)).path === '/about');
  // different links back to back: last click wins
  const links = ['/contact', '/events', '/'];
  for (const h of links) { const s = (mobile ? '.tkt-mobile-panel nav a' : '.tkt-nav a') + `[href='${BASE}${h === '/' ? '' : h}']`; await p.locator(s).evaluate(e => e.click()).catch(() => {}); }
  await p.waitForTimeout(1200);
  const i = await info(p);
  t('rapid: different links in a burst end on the last one with matching content', i.path === '/' && /Find your next/.test(i.title), `${i.path} | ${i.title}`);
  await p.close();
}

/* 7. mobile menu / account menu close on navigation */
if (mobile) {
  const p = await open('/');
  await p.locator('#tktBurger').click();
  await p.locator(`.tkt-mobile-panel nav a[href='${BASE}/about']`).click(); await p.waitForURL(/about/); await settle(p);
  t('mobile: menu panel closes after navigation', !(await p.locator('#tktHeader.is-open').count()) && (await alive(p)));
  await p.close();
}

/* 8. auth modal still opens after soft navigation (jQuery + Bootstrap kept alive) */
{
  const p = await open('/');
  await go(p, '/about'); await p.waitForURL(/about/); await settle(p);
  if (mobile) await p.locator('#tktBurger').click();
  await p.locator('[data-auth-open="login"]:visible').first().click(); await p.waitForTimeout(600);
  t('auth modal opens after soft navigation', await p.locator('#login-register1').isVisible());
  await p.close();
}

/* 9. things that must stay a full navigation */
{
  const p = await open('/events/pulsed');
  const plus = p.locator('.tkt-ticket:not([data-disabled="1"]) [data-step="1"]').first();
  if (await plus.count()) {
    await plus.click();
    await Promise.all([p.waitForURL(u => u.pathname.startsWith('/checkout'), { timeout: 8000 }), p.click('#checkoutBtn')]);
    t('checkout link: full navigation (no soft swap)', !(await alive(p)), (await info(p)).path.slice(0, 40));
  } else t('checkout link: full navigation', false, 'no ticket in seed');
  await p.close();
  const q = await open('/');
  const hrefs = await q.evaluate(() => [...document.querySelectorAll('a[href]')].map(a => a.getAttribute('href')));
  const logout = await q.locator('form[action*="logout"]').count();
  t('login/logout/account/checkout URLs are not soft-navigable', await q.evaluate(() => ['/checkout', '/account/login', '/account/orders', '/payments/paymob/callback', '/dashboard', '/t/ABC'].every(h => !window.TktSpa.isSoftUrl(h))));
  await q.close();
}

/* 10. failures: server error -> safe full-load fallback; offline -> retry bar */
{
  const p = await open('/');
  let first = true;
  await p.route('**/about', route => {
    if (first && route.request().headers()['x-requested-with'] === 'swup') { first = false; return route.fulfill({ status: 500, body: 'boom' }); }
    return route.continue();
  });
  await go(p, '/about');
  await p.waitForURL(u => u.pathname === '/about'); await p.waitForLoadState('load'); await settle(p);
  const i = await info(p);
  t('error 500 on soft request: falls back to a full page load that renders /about', !(await alive(p)) && /About/.test(i.title), `${i.path} | ${i.title}`);
  await p.close();

  const o = await open('/');
  await ctx.setOffline(true);
  await go(o, '/about'); await o.waitForTimeout(500);
  const barVisible = await o.locator('.tkt-navbar-error.is-visible').count();
  t('offline: stays on page and shows a retry bar', (await info(o)).path === '/' && barVisible === 1 && (await alive(o)));
  await ctx.setOffline(false);
  await o.locator('.tkt-navbar-error__retry').click(); await o.waitForURL(/about/); await settle(o);
  t('offline -> retry works as a soft navigation', (await alive(o)) && (await info(o)).path === '/about');
  await o.close();

  // a public URL that redirects to a non-public page (e.g. login/checkout) -> full navigation, never a half swap
  const r = await open('/');
  await r.route('**/contact', route => route.request().headers()['x-requested-with'] === 'swup'
    ? route.fulfill({ status: 302, headers: { location: '/account/login' } })
    : route.continue());
  await go(r, '/contact');
  await r.waitForTimeout(2200); await r.waitForLoadState('load');
  t('redirect to a non-public page: full navigation fallback', !(await alive(r)) && /\/contact$/.test(r.url()), r.url());
  await r.close();

  // non-layout response (e.g. maintenance page) -> full navigation
  const m = await open('/');
  await m.route('**/contact', route => route.request().headers()['x-requested-with'] === 'swup'
    ? route.fulfill({ status: 503, contentType: 'text/html', body: '<html><head><title>Maintenance</title></head><body>Back soon</body></html>' })
    : route.continue());
  await go(m, '/contact');
  await m.waitForURL(u => u.pathname === '/contact'); await m.waitForLoadState('load'); await settle(m);
  t('non-layout (503/maintenance-like) response: full navigation fallback', !(await alive(m)));
  await m.close();
}

/* 11. accessibility: focus + announcement */
{
  const p = await open('/');
  await go(p, '/about'); await p.waitForURL(/about/); await p.waitForTimeout(700);
  const a = await p.evaluate(() => ({
    focusInsideMain: !!document.activeElement && !!document.activeElement.closest('main'),
    focusTag: document.activeElement?.tagName,
    live: [...document.querySelectorAll('[aria-live]')].map(e => e.textContent.trim()).filter(Boolean),
  }));
  t('a11y: focus moves into the new main content', a.focusInsideMain, a.focusTag);
  t('a11y: route change is announced to screen readers', a.live.some(x => /About/.test(x)), JSON.stringify(a.live));
  await p.close();
}


/* 12. search, filters and in-page widgets after a soft visit */
{
  const p = await open('/');
  // header search (desktop bar / mobile panel)
  if (mobile) await p.locator('#tktBurger').click();
  const input = p.locator(mobile ? '.tkt-mobile-panel .tkt-header__search input' : '.tkt-header__bar .tkt-header__search input[name=q]').first();
  await input.fill('pulsed'); await input.press('Enter');
  await p.waitForURL(u => u.pathname === '/events' && u.searchParams.get('q') === 'pulsed'); await settle(p);
  let i = await info(p);
  t('search (header): soft navigation to /events?q=pulsed', (await alive(p)) && i.path === '/events?q=pulsed' && /Events/.test(i.title), i.path);
  t('search results are filtered by the server', (await p.locator("main a[href*='/events/pulsed']").count()) > 0 && (await p.locator("main a[href*='/events/sideral']").count()) === 0);
  t('canonical ignores the search query', i.canonical.endsWith('/events'), i.canonical);
  // page search form
  const pageSearch = p.locator('main form[role=search] input[name=q]');
  await pageSearch.fill('sideral'); await pageSearch.press('Enter');
  await p.waitForURL(u => u.searchParams.get('q') === 'sideral'); await settle(p);
  t('search (events page form): soft + results change', (await alive(p)) && (await p.locator("main a[href*='/events/sideral']").count()) > 0 && (await p.locator("main a[href*='/events/pulsed']").count()) === 0);
  // filter tabs (links)
  await p.locator("main nav[aria-label='Filter events'] a", { hasText: 'Previous' }).evaluate(e => e.click()); await settle(p);
  t('filter tab: soft navigation, active state follows', (await alive(p)) && /when=previous/.test((await info(p)).path) && (await p.locator("main nav[aria-label='Filter events'] a.is-active").textContent()).trim() === 'Previous');
  await p.goBack(); await settle(p);
  t('back from filter restores the previous search results', (await alive(p)) && (await info(p)).path === '/events?q=sideral');
  // home hero search
  const h = await open('/');
  const hero = h.locator('.tkt-hero__search');
  const nameField = hero.locator('input[type=search], input[type=text]').first();
  await nameField.fill('pulsed'); await nameField.press('Enter');
  await h.waitForURL(/\/events/); await settle(h);
  t('search (home hero): soft navigation', await alive(h), (await info(h)).path);
  await h.close();
  await p.close();

  // tabs + lightbox on an event page reached by soft navigation
  const q = await open('/events');
  await q.locator("a[href*='/events/pulsed']").first().evaluate(e => e.click()); await q.waitForURL(/pulsed/); await settle(q);
  const tabs = q.locator('[data-tkt-tabs] [role=tab]');
  const n = await tabs.count();
  if (n > 1) {
    await tabs.nth(1).click();
    const sel = await tabs.nth(1).getAttribute('aria-selected');
    const panel = await q.locator('#' + (await tabs.nth(1).getAttribute('aria-controls'))).evaluate(e => e.classList.contains('is-active'));
    t('tabs work after a soft visit', sel === 'true' && panel);
    await tabs.nth(1).focus(); await q.keyboard.press('ArrowRight');
    t('tabs keyboard navigation (ArrowRight) works', (await tabs.nth(2 % n).getAttribute('aria-selected')) === 'true');
  } else t('tabs present on event page', false, 'tabs=' + n);
  // leaving and re-entering must not double-bind tabs: one click = one state change
  await q.locator('a.tkt-logo').evaluate(e => e.click()); await q.waitForURL(u => u.pathname === '/'); await settle(q);
  await q.locator("a[href*='/events/pulsed']").first().evaluate(e => e.click()); await q.waitForURL(/pulsed/); await settle(q);
  t('event page re-entered: first tab active again', (await q.locator('[data-tkt-tabs] [role=tab]').first().getAttribute('aria-selected')) === 'true');
  t('widgets: no console errors', q._errors.length === 0, q._errors.join('; '));
  await q.close();
}

console.log(`\n${pass} passed, ${fail} failed (${mobile ? 'mobile' : 'desktop'})`);
await browser.close();
process.exit(fail ? 1 : 0);
