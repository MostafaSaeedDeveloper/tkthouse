import { chromium } from 'playwright-core';
const BASE = process.env.BASE_URL || 'http://localhost:8000';
const b = await chromium.launch({ executablePath: process.env.CHROMIUM_PATH || '/opt/pw-browsers/chromium', args: ['--no-sandbox'] });
let pass=0, fail=0; const t=(n,ok,x='')=>{ok?pass++:fail++;console.log(`${ok?'PASS':'FAIL'}  ${n}${x?'  -> '+x:''}`)};
async function login(ctx){ const p=await ctx.newPage(); await p.route(/^https?:\/\/(?!localhost)/, r=>r.abort()); await p.goto(BASE+'/login'); await p.fill('input[name=username]','auditadmin'); await p.fill('input[name=password]','Passw0rd!aud'); await Promise.all([p.waitForURL(u=>!u.pathname.startsWith('/login')),p.click('button[type=submit]')]); return p; }
// phone
{
  const ctx = await b.newContext({ viewport:{width:390,height:844}, isMobile:true, hasTouch:true });
  const p = await login(ctx); const errs=[]; p.on('pageerror',e=>errs.push(e.message));
  await p.goto(BASE+'/dashboard/customers'); await p.waitForTimeout(400);
  const body = p.locator('.a-filters__body');
  t('phone: filter fields are collapsed by default', !(await body.isVisible()));
  t('phone: toggle visible, aria-expanded=false', (await p.locator('.a-filters__toggle').isVisible()) && (await p.locator('.a-filters__toggle').getAttribute('aria-expanded'))==='false');
  await p.screenshot({path:'f1.png'});
  await p.click('.a-filters__toggle'); await p.waitForTimeout(200);
  t('phone: tap opens the fields and Apply/Reset', (await body.isVisible()) && (await p.locator('.a-filters__actions .a-btn--dark').isVisible()));
  t('phone: aria-expanded=true after opening', (await p.locator('.a-filters__toggle').getAttribute('aria-expanded'))==='true');
  await p.screenshot({path:'f2.png'});
  await p.click('.a-filters__toggle'); await p.waitForTimeout(150);
  t('phone: tap again collapses', !(await body.isVisible()));
  // applied filter shows a badge and still works
  await p.goto(BASE+'/dashboard/customers?search=Customer1'); await p.waitForTimeout(300);
  t('phone: applied filters show a count badge', (await p.locator('.a-filters__count').textContent()).trim()==='1');
  await p.click('.a-filters__toggle'); await p.fill('.a-filters input[name=search]','Customer2'); await Promise.all([p.waitForURL(/search=Customer2/),p.click('.a-filters__actions .a-btn--dark')]);
  t('phone: Apply submits the filters', /search=Customer2/.test(p.url()));
  t('phone: no JS errors', errs.length===0, errs.join(';'));
  await ctx.close();
}
// desktop unchanged
{
  const ctx = await b.newContext({ viewport:{width:1366,height:800} });
  const p = await login(ctx);
  await p.goto(BASE+'/dashboard/customers'); await p.waitForTimeout(400);
  t('desktop: no toggle, fields always visible', !(await p.locator('.a-filters__toggle').isVisible()) && (await p.locator('.a-filters input[name=search]').isVisible()) && (await p.locator('.a-filters__actions .a-btn--dark').isVisible()));
  await p.screenshot({path:'f3.png'});
  await ctx.close();
}
console.log(`\n${pass} passed, ${fail} failed`); await b.close();
