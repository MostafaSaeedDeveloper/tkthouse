import { chromium } from 'playwright-core';
const BASE = process.env.BASE_URL || 'http://localhost:8000';
const b = await chromium.launch({ executablePath: process.env.CHROMIUM_PATH || '/opt/pw-browsers/chromium', args: ['--no-sandbox'] });
let pass=0, fail=0; const t=(n,ok,x='')=>{ok?pass++:fail++;console.log(`${ok?'PASS':'FAIL'}  ${n}${x?'  -> '+x:''}`)};
for (const [name, opts] of [['desk',{viewport:{width:1200,height:760}}],['mob',{viewport:{width:390,height:800},isMobile:true,hasTouch:true}]]) {
  const c = await b.newContext(opts); const p = await c.newPage(); const errs=[]; p.on('pageerror',e=>errs.push(e.message)); await p.route(/^https?:\/\/(?!localhost)/, r=>r.abort());
  await p.goto(BASE+'/login'); await p.fill('input[name=username]','auditadmin'); await p.fill('input[name=password]','Passw0rd!aud'); await Promise.all([p.waitForURL(u=>!u.pathname.startsWith('/login')),p.click('button[type=submit]')]);
  await p.goto(BASE+'/dashboard'); await p.waitForTimeout(500);
  const badge = p.locator('[data-notif-badge]');
  t(`${name}: bell + unread badge in the top bar`, (await badge.isVisible()) && parseInt(await badge.textContent())>=3, await badge.textContent());
  t(`${name}: panel closed by default`, !(await p.locator('#aNotifPanel').isVisible()));
  await p.click('.a-notif__btn'); await p.waitForTimeout(300);
  t(`${name}: click opens the panel with items`, (await p.locator('#aNotifPanel').isVisible()) && (await p.locator('.a-notif__item').count())>=3);
  t(`${name}: aria-expanded true`, (await p.locator('.a-notif__btn').getAttribute('aria-expanded'))==='true');
  await p.screenshot({ path:`bell_${name}.png` });
  await p.keyboard.press('Escape');
  t(`${name}: Esc closes`, !(await p.locator('#aNotifPanel').isVisible()));
  await p.click('.a-notif__btn'); await p.locator('.a-notif__item').first().click(); await p.waitForLoadState('load');
  t(`${name}: clicking an item opens the order`, /\/dashboard\/orders\/\d+/.test(p.url()), p.url());
  const after = parseInt((await p.locator('[data-notif-badge]').isVisible()) ? await p.locator('[data-notif-badge]').textContent() : '0');
  t(`${name}: that notification is now read (badge went down)`, after>=1);
  await p.click('.a-notif__btn'); await p.click('[data-notif-readall]'); await p.waitForTimeout(400);
  t(`${name}: Mark all read clears the badge`, !(await p.locator('[data-notif-badge]').isVisible()));
  t(`${name}: no JS errors`, errs.length===0, errs.join(';'));
  await c.close();
}
console.log(`\n${pass} passed, ${fail} failed`); await b.close();
