// End-to-end QA of Settings → Staff & Roles (tutor/staff.php) and staff sign-in:
//   STARTER sees the GROW lock -> GROW tutor makes a role and invites a staff member -> the staff member signs in on the
//   tutor sign-in page, sees only their role's pages, never billing, and the API refuses the rest -> switched off = out.
//   bin/dev-up.sh  then  node tests/e2e/staff-journey.e2e.js [http://127.0.0.1:8082]   Results: results/staff-journey.json
const path = require('path');
const fs = require('fs');
const { execFileSync } = require('child_process');
const { Harness } = require('./harness');

const BASE = process.argv[2] || 'http://127.0.0.1:8082';
const OUT = path.join(__dirname, 'results');
const php = (f, ...a) => execFileSync('php', [path.join(__dirname, f), ...a]).toString();

(async () => {
  php('setup-qa-tutor.php');
  php('setup-qa-staff.php');
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
  const signIn = async (email, password) => {
    await p().context().clearCookies();
    await t.open('tutor/sign-in', 800);
    await p().fill('#email', email);
    await p().fill('#password', password);
    await p().click('button:has-text("Sign In")');
    await p().waitForURL(/dashboard/, { timeout: 15000 });
    await p().waitForTimeout(2000);
  };

  await t.tc('SF-01', 'Plans', 'STARTER: Staff & Roles shows the GROW lock', ['Sign in as a STARTER tutor', 'Open Settings → Staff & Roles'], 'A "Staff accounts are part of GROW" card with See GROW, no invite form', async () => {
    await t.useViewport({ width: 1440, height: 900 });
    await signIn('qa.tutor@enlixo.test', 'QaTutor#2026');
    await t.open('tutor/staff', 2500);
    t.expect(/part of GROW/.test(await text('#st-page')), `Page: ${(await text('#st-page')).slice(0, 160)}`);
  });

  php('setup-qa-tutor.php', 'grow');
  await t.tc('SF-02', 'Roles', 'GROW tutor makes a role', ['Open Staff & Roles → Roles', 'New Role "QA Cashier" with View payments only', 'Create Role'], 'Role saved and listed with its permissions', async () => {
    await t.open('tutor/staff?tab=roles', 2500);
    await p().click('#st-actions button:has-text("New Role")');
    await p().fill('[data-key="name"]', 'QA Cashier');
    await p().locator('.st-perm input').evaluateAll((els) => els.forEach((e) => { if (e.checked) e.click(); }));
    await p().locator('.st-perm', { hasText: 'View payments' }).locator('input').check();
    await p().locator('.st-perm', { hasText: 'View students' }).locator('input').check();
    await p().click('button:has-text("Create Role")');
    await p().waitForTimeout(1500);
    t.expect(/QA Cashier/.test(await text('.st-roles')), 'Role not in the list');
  });

  await t.tc('SF-03', 'Staff', 'Invite with an empty form shows the field errors', ['Staff tab → Invite Staff', 'Send Invitation with nothing filled'], 'Errors under first name, last name, e-mail and role', async () => {
    await t.open('tutor/staff', 2500);
    await p().click('#st-actions button:has-text("Invite Staff")');
    await p().click('button:has-text("Send Invitation")');
    await p().waitForTimeout(1200);
    t.expect((await p().locator('.st-invite .is-invalid').count()) >= 4, `Invalid fields: ${await p().locator('.st-invite .is-invalid').count()}`);
  });

  await t.tc('SF-04', 'Staff', 'Invite a staff member', ['Fill name, e-mail qa.staff@enlixo.test, mobile, role QA Cashier', 'Send Invitation'], 'Listed as Invited; the set-password link shows for WhatsApp', async () => {
    await p().fill('[data-key="first_name"]', 'Qa');
    await p().fill('[data-key="last_name"]', 'Staff');
    await p().fill('[data-key="email"]', 'qa.staff@enlixo.test');
    await p().selectOption('[data-key="role_id"]', { label: 'QA Cashier' });
    await p().click('button:has-text("Send Invitation")');
    await p().waitForTimeout(2500);
    t.expect(/qa\.staff@enlixo\.test/.test(await text('#st-page table')), 'Not in the table');
    t.expect(/setup-password\?token=/.test(await text('.st-link')), 'No link shown');
    t.expect(/Invited/.test(await text('#st-page table')), 'Status is not Invited');
    await p().screenshot({ path: path.join(t.shots, 'staff-invited.png') });
  });

  php('setup-qa-staff.php', 'password');
  await t.tc('SF-05', 'Sign-in', 'The staff member signs in and sees only their pages', ['Sign in on the tutor sign-in page as qa.staff@enlixo.test', 'Look at the menu'], 'Dashboard opens; menu has Students and Student Fees, no Classes, no My Subscription, no plan card; user card says Staff', async () => {
    await signIn('qa.staff@enlixo.test', 'QaStaff#2026');
    const menu = await text('.ex-sidenav');
    const links = await p().locator('.ex-sidenav').first().locator('a[href]').evaluateAll((as) => as.map((a) => a.getAttribute('href')));
    t.expect(links.includes('fees') && links.includes('students'), `Links: ${links.join(', ')}`);
    t.expect(!links.some((h) => ['subscription', 'upgrade', 'staff', 'create-class', 'classes', 'message-center'].includes(h)), `Links outside the role: ${links.join(', ')}`);
    t.expect(!(await p().locator('.ex-plan-card').count()), 'Plan card shown to staff');
    t.expect(/Staff ·/.test(menu), 'User card does not say Staff');
    t.expect(/Your pages/.test(await text('#tutor-dashboard')) && !/Couldn/.test(await text('#tutor-dashboard')), 'Staff dashboard did not load');
    await p().screenshot({ path: path.join(t.shots, 'staff-home.png') });
  });

  await t.tc('SF-06', 'Sign-in', 'Pages and API outside the role are refused', ['Open tutor/subscription and tutor/create-class', 'Call the subscription and class API'], 'Pages send the staff member to the dashboard; API answers 403', async () => {
    await t.open('tutor/subscription', 2000);
    t.expect(/dashboard/.test(p().url()), `Subscription page stayed: ${p().url()}`);
    await t.open('tutor/create-class', 2000);
    t.expect(/dashboard/.test(p().url()), `Create class stayed: ${p().url()}`);
    const sub = await call('GET', '/api/v1/tutor/subscription');
    t.expect(sub.status === 403, `Subscription API: ${sub.status}`);
    const cls = await call('POST', '/api/v1/tutor/classes', { data: '{}' });
    t.expect(cls.status === 403, `Class API: ${cls.status}`);
    const st = await call('GET', '/api/v1/tutor/staff');
    t.expect(st.status === 403, `Staff API: ${st.status}`);
  });

  await t.tc('SF-07', 'Staff', 'Switched off: the staff member is signed out', ['The tutor switches the staff member off', 'The staff member opens a page / signs in again'], 'Sent to sign-in; sign-in says the staff access is switched off', async () => {
    const staffCtx = p();
    const tutor = await t.browser.newContext();
    const tp = await tutor.newPage();
    await tp.goto(`${BASE}/tutor/sign-in`);
    await tp.fill('#email', 'qa.tutor@enlixo.test');
    await tp.fill('#password', 'QaTutor#2026');
    await tp.click('button:has-text("Sign In")');
    await tp.waitForURL(/dashboard/, { timeout: 15000 });
    await tp.goto(`${BASE}/tutor/staff`);
    await tp.waitForTimeout(2500);
    await tp.locator('tr', { hasText: 'qa.staff@enlixo.test' }).locator('button:has-text("Switch Off")').click();
    await tp.waitForTimeout(1500);
    t.expect(/Switched off/.test(await tp.locator('table').innerText()), 'Status not switched off');
    await tutor.close();
    await staffCtx.goto(`${BASE}/tutor/students`);
    await staffCtx.waitForTimeout(2500);
    t.expect(!/students/.test(staffCtx.url()) || /sign-in|index/.test(staffCtx.url()), `Still on: ${staffCtx.url()}`);
    await t.open('tutor/sign-in', 800);
    if (await p().locator('#email').count()) {
      await p().fill('#email', 'qa.staff@enlixo.test');
      await p().fill('#password', 'QaStaff#2026');
      await p().click('button:has-text("Sign In")');
      await p().waitForTimeout(2000);
      t.expect(/switched off/i.test(await text('body')), 'No switched-off message');
    }
  });

  await t.tc('SF-08', 'Layout', 'Staff & Roles on a phone: no sideways scroll', ['Tutor on a 390 px phone', 'Open Staff & Roles'], 'Page fits the screen', async () => {
    await t.useViewport({ width: 390, height: 844 });
    await signIn('qa.tutor@enlixo.test', 'QaTutor#2026');
    await t.open('tutor/staff', 2500);
    const over = await p().evaluate(() => document.documentElement.scrollWidth - window.innerWidth);
    t.expect(over <= 1, `Overflow ${over}px`);
    await p().screenshot({ path: path.join(t.shots, 'staff-phone.png') });
  });

  php('setup-qa-staff.php');
  php('setup-qa-tutor.php');
  await t.finish(path.join(OUT, 'staff-journey.json'));
})();
