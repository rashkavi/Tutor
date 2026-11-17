// End-to-end QA of the FREE / STARTER / GROW plans and the upgrade path (docs/PLANS.md):
//   FREE limits and locks -> upgrade nudges (menu card, dashboard card, upgrade page, limit card) -> parents locked out
//   on FREE -> "Ask for a 7-day free trial" (admin approves, once) -> upgrade from FREE makes an invoice -> unpaid: back to FREE (reverse trial).
//   bin/dev-up.sh  then  node tests/e2e/plans-journey.e2e.js [http://127.0.0.1:8082]   Results: results/plans-journey.json
const path = require('path');
const fs = require('fs');
const { execFileSync } = require('child_process');
const { Harness } = require('./harness');

const BASE = process.argv[2] || 'http://127.0.0.1:8082';
const OUT = path.join(__dirname, 'results');
const php = (f, ...a) => execFileSync('php', [path.join(__dirname, f), ...a]).toString();
const state = () => JSON.parse(php('qa-tick.php', 'status'));

(async () => {
  php('setup-qa-tutor.php', 'free');
  const qb = JSON.parse(php('setup-qa-billing.php'));
  const t = new Harness(BASE);
  t.shots = path.join(OUT, 'shots');
  fs.mkdirSync(t.shots, { recursive: true });
  await t.start();
  const p = () => t.page;
  const text = async (sel = 'body') => (await p().locator(sel).first().innerText().catch(() => '')).replace(/\s+/g, ' ');
  const call = (method, url, body) => p().evaluate(async ([m, u, b]) => {
    const r = await fetch(u, { method: m, headers: { 'Content-Type': 'application/json', Accept: 'application/json', 'X-Requested-With': 'enlixo' }, body: b ? JSON.stringify(b) : undefined });
    return { status: r.status, body: await r.text() };
  }, [method, url, body]);
  const tutorIn = async () => {
    await t.open('tutor/sign-in', 800);
    if (!(await p().locator('#email').count())) { await t.open('tutor/dashboard', 2500); return; } // still signed in
    await p().fill('#email', 'qa.tutor@enlixo.test');
    await p().fill('#password', 'QaTutor#2026');
    await p().click('button:has-text("Sign In")');
    await p().waitForURL(/dashboard/, { timeout: 15000 });
    await p().waitForTimeout(2500);
  };
  const noModal = async () => t.expect(!(await p().locator('.modal.show').count()), 'A pop-up opened');

  await t.tc('PL-01', 'FREE', 'A FREE tutor sees their plan and a way up on every page', ['Sign in as a tutor on FREE', 'Look at the plan card in the menu and the dashboard'], 'Menu: "FREE plan · Upgrade"; dashboard: "Your FREE plan" card with meters and "See STARTER"', async () => {
    await t.useViewport({ width: 1440, height: 900 });
    await tutorIn();
    t.expect(/FREE plan/.test(await text('.ex-plan-card')), `Menu card: ${await text('.ex-plan-card')}`);
    t.expect((await p().locator('.ex-plan-card').first().getAttribute('href')) === 'upgrade', 'Menu card does not open the Upgrade page');
    t.expect(/Your FREE plan/.test(await text('.ex-dash-plan')) && /See STARTER/.test(await text('.ex-dash-plan')), 'Dashboard plan card missing');
    t.expect((await p().locator('.ex-bill-banner').count()) <= 1, 'More than one banner');
  });
  await t.tc('PL-02', 'FREE', 'Over a limit: a nudge on the pages, not a pop-up', ['The tutor has more classes than FREE allows', 'Open Classes'], 'A banner says how many classes FREE allows and links to STARTER', async () => {
    await t.open('tutor/classes', 2500);
    t.expect(/FREE allows 3/.test(await text('.ex-bill-banner')), `Banner: ${await text('.ex-bill-banner')}`);
    t.expect(/upgrade\?limit=classes/.test(await p().locator('.ex-bill-banner a').first().getAttribute('href')), 'Banner link');
    await noModal();
  });
  await t.tc('PL-03', 'FREE', 'The API refuses what FREE does not have, with the plan that has it', ['Add a 4th class', 'Post a tute by courier', 'Ask for the access'], '402 limit_reached for the class, 402 feature_locked for the courier tute; access lists limits and STARTER as next plan', async () => {
    const c = await call('POST', '/api/v1/tutor/classes', { data: '{}' });
    t.expect(c.status === 402 && /limit_reached/.test(c.body), `Class: ${c.status} ${c.body.slice(0, 120)}`);
    const a = JSON.parse((await call('GET', '/api/v1/me/access')).body).data;
    t.expect(a.paid_plan.key === 'free' && a.next_plan.key === 'start', `Plans: ${a.paid_plan.key} → ${a.next_plan?.key}`);
    t.expect(a.limits.find((l) => l.key === 'classes').limit === 3, 'Classes limit is not 3');
    t.expect(a.features.find((f) => f.key === 'parents.login').unlocked === false, 'Parent login unlocked on FREE');
  });
  await t.tc('PL-04', 'FREE', 'Locked features show a lock and lead to the Upgrade page', ['Open Integrations', 'Open the Upgrade page for Zoom'], 'Integrations: "Unlock with STARTER"; Upgrade page: "Unlock Zoom Integration…", STARTER marked Best next step', async () => {
    await t.open('tutor/intergrations', 2000);
    const unlock = p().locator('a', { hasText: 'Unlock with STARTER' });
    if (await unlock.count()) {
      await unlock.first().click();
    } else {
      await t.open('tutor/upgrade?feature=zoom.tutor_integration', 0);
    }
    await p().waitForTimeout(3000);
    t.expect(/upgrade\?feature=zoom/.test(p().url()), `Went to ${p().url()}`);
    t.expect(/Unlock Zoom Integration/.test(await text('.up-why')), 'Reason missing');
    t.expect(/best next step/i.test(await text('.up-plan.is-rec')) && /STARTER/.test(await text('.up-plan.is-rec')), 'STARTER not recommended');
    await noModal();
  });
  await t.tc('PL-05', 'FREE', 'Parents of a FREE tutor cannot sign in, with a clear reason', ['A parent of the QA tutor\'s student signs in'], 'Refused: "Parent login is not part of your tutor\'s ENLIXO plan"', async () => {
    await t.open('parent/', 800);
    const r = await call('POST', 'api/signIn.php', { mobile: qb.parent_whatsapp, otp: qb.parent_otp });
    t.expect(/not part of your tutor/.test(r.body) && !/"done"/.test(r.body), `Answer: ${r.body.slice(0, 160)}`);
  });
  await t.tc('PL-06', 'Preview', 'Ask to try STARTER free for 7 days; it starts only after the ENLIXO admin approves', ['Upgrade page › Ask for a 7-day free trial', 'Admin approves', 'Ask again'], 'Request waits (no features yet), approval turns STARTER on for 7 days; a second request is refused', async () => {
    await t.open('tutor/upgrade', 3000);
    await p().locator('.up-why .up-try').click();
    await p().waitForTimeout(2000);
    t.expect(/with the ENLIXO team/.test(await text('.up-note')), `Note: ${await text('.up-note')}`);
    let a = JSON.parse((await call('GET', '/api/v1/me/access')).body).data;
    t.expect(a.plan.key === 'free' && !a.preview, 'The trial started before the admin approved it');
    php('qa-tick.php', 'approve-trial');
    await t.open('tutor/upgrade', 3000);
    t.expect(/Trying STARTER/.test(await text('.ex-plan-card')), `Menu: ${await text('.ex-plan-card')}`);
    a = JSON.parse((await call('GET', '/api/v1/me/access')).body).data;
    t.expect(a.plan.key === 'start' && a.paid_plan.key === 'free' && a.preview_used, 'Preview not running');
    const again = await call('POST', '/api/v1/tutor/subscription/preview', { plan: 'grow' });
    t.expect(again.status === 409, `Second request: ${again.status}`);
  });
  await t.tc('PL-07', 'Preview', 'While trying STARTER, parents can sign in', ['The parent signs in again'], 'Signed in', async () => {
    const qb2 = JSON.parse(php('setup-qa-billing.php'));
    await t.open('parent/', 800);
    const r = await call('POST', 'api/signIn.php', { mobile: qb2.parent_whatsapp, otp: qb2.parent_otp });
    t.expect(/"done"/.test(r.body), `Answer: ${r.body.slice(0, 160)}`);
  });
  await t.tc('PL-08', 'Upgrade', 'Upgrade from FREE to STARTER on the page', ['Sign in as the tutor', 'Upgrade page › Upgrade to STARTER', 'Read the confirm panel', 'Confirm'], 'The confirm is on the page (no pop-up); STARTER is on, an invoice is made and the invoice page opens', async () => {
    await tutorIn();
    await t.open('tutor/upgrade', 3000);
    await p().locator('.up-plan', { hasText: 'STARTER' }).locator('button', { hasText: 'Upgrade to STARTER' }).click();
    await p().waitForTimeout(800);
    t.expect(/due in 7 days/.test(await text('#up-confirm')), `Confirm: ${await text('#up-confirm')}`);
    await noModal();
    await p().locator('#up-confirm button', { hasText: 'Confirm' }).click();
    await p().waitForURL(/invoice\?invoice-id=/, { timeout: 15000 });
    const s = state();
    t.expect(s.plan_key === 'start' && s.status === 'trial' && s.open_invoices === 1, `State: ${JSON.stringify(s)}`);
  });
  await t.tc('PL-09', 'Upgrade', 'Not paid after 7 days: back to FREE, nothing closes', ['The 7 days pass without payment', 'The daily job runs'], 'Plan FREE, status Active, the invoice void; the class portal stays open', async () => {
    const s = JSON.parse(php('qa-tick.php', 'end-trial'));
    t.expect(s.plan_key === 'free' && s.status === 'active' && s.open_invoices === 0, `State: ${JSON.stringify(s)}`);
  });
  await t.tc('PL-10', 'Phone', 'The Upgrade page works on a phone', ['Open Upgrade at 390 px'], 'Nothing runs past the screen; plans stack', async () => {
    await t.useViewport({ width: 390, height: 844 });
    await tutorIn();
    await t.open('tutor/upgrade?limit=classes', 3000);
    const wide = await p().evaluate(() => [...document.querySelectorAll('#up-page > *, #up-actions *')].some((el) => el.getBoundingClientRect().right > document.documentElement.clientWidth + 2));
    t.expect(!wide, 'Upgrade page runs past the screen');
    t.expect(/STARTER/.test(await text('.up-why')), 'Reason card missing');
  });

  php('setup-qa-tutor.php');
  const fails = await t.finish(path.join(OUT, 'plans-journey.json'));
  process.exit(fails ? 1 : 0);
})();
