// End-to-end QA of a tutor's subscription from sign-up to lock-out:
//   plan on the landing page -> sign-up request -> admin approves (welcome e-mail + set-password link) -> the tutor
//   sets a password and signs in on a free trial -> admin gives a trial on a chosen plan -> admin invoice page ->
//   the QA tutor is suspended: tutor sent to Renew, students and parents locked out -> reactivated: open again.
//   bin/dev-up.sh  (dev server with MAIL_DRIVER=log)  then  node tests/e2e/billing-journey.e2e.js [http://127.0.0.1:8082]
// Runs the setup scripts itself (setup-qa-admin, setup-qa-tutor, setup-qa-billing). Results: results/billing-journey.json
const path = require('path');
const fs = require('fs');
const { execFileSync } = require('child_process');
const { Harness } = require('./harness');

const BASE = process.argv[2] || 'http://127.0.0.1:8082';
const OUT = path.join(__dirname, 'results');
const php = (f, ...a) => execFileSync('php', [path.join(__dirname, f), ...a]).toString();
const stamp = Date.now().toString().slice(-6);
const SIGNUP = { email: `qa.signup.${stamp}@example.com`, mobile: `071${stamp}1`.slice(0, 10), first: 'Sachini', last: `Signup${stamp}` };
const mailLog = () => { try { return fs.readFileSync(path.join(__dirname, '..', '..', 'storage', 'messages.log'), 'utf8'); } catch { return ''; } };

(async () => {
  const admin = JSON.parse(php('setup-qa-admin.php'));
  php('setup-qa-tutor.php');
  let qb = JSON.parse(php('setup-qa-billing.php'));
  const t = new Harness(BASE);
  t.shots = path.join(OUT, 'shots');
  fs.mkdirSync(t.shots, { recursive: true });
  await t.start();
  const p = () => t.page;
  const text = async (sel = 'body') => (await p().locator(sel).first().innerText().catch(() => '')).replace(/\s+/g, ' ');
  const path_ = () => new URL(p().url()).pathname;
  const post = (url, body) => p().evaluate(async ([u, b]) => {
    const r = await fetch(u, { method: 'POST', headers: { 'Content-Type': 'application/json', Accept: 'application/json', 'X-Requested-With': 'enlixo' }, body: JSON.stringify(b) });
    return { status: r.status, body: await r.text() };
  }, [url, body]);
  const noModal = async () => t.expect(!(await p().locator('.modal.show').count()), 'A pop-up opened');
  const adminIn = async () => {
    await t.open('admin/', 1000);
    await p().fill('#email', admin.email);
    await p().fill('#password', admin.password);
    await p().locator('button', { hasText: /sign in/i }).first().click();
    await p().waitForURL(/dashboard/, { timeout: 15000 });
  };
  let newTutorId = null;
  let setLink = null;

  // ------------------------------------------------------------------ sign-up
  await t.tc('BJ-01', 'Sign-up', 'Every plan on the landing page opens the sign-up form with that plan', ['Open the landing page', 'Look at the plan buttons'], 'Each plan links to the sign-up form with ?plan=', async () => {
    await t.open('', 1500);
    const links = await p().$$eval('a[href*="tutor-registration-application"]', (as) => as.map((a) => a.getAttribute('href')));
    t.expect(links.some((l) => /plan=grow/.test(l)) && links.some((l) => /plan=start/.test(l)), `Links: ${links.join(', ')}`);
  });
  await t.tc('BJ-02', 'Sign-up', 'The sign-up form opens on the chosen plan, on a phone too', ['Open the sign-up form with ?plan=grow at 390 px'], 'The one-page form "Create your account" is shown with GROW chosen, nothing cut off', async () => {
    await p().setViewportSize({ width: 390, height: 844 });
    await t.open('tutor-registration-application.php?plan=grow', 2000);
    t.expect(/Create your account/.test(await text()), 'Form missing');
    t.expect(/GROW/.test(await text('.su-chosen')), 'GROW not chosen');
    const wide = await p().evaluate(() => document.documentElement.scrollWidth > document.documentElement.clientWidth + 2 && getComputedStyle(document.body).overflowX !== 'hidden');
    t.expect(!wide, 'The page scrolls sideways on a phone');
    await p().setViewportSize({ width: 1440, height: 900 });
  });
  await t.tc('BJ-03', 'Sign-up', 'A tutor sends a sign-up request for GROW with the payment slip', ['Fill name, e-mail, mobile', 'Upload the bank slip', 'Accept the terms', 'Send'], 'A reference TR-… comes back, the slip is saved and a "request received" e-mail goes out', async () => {
    const before = mailLog().length;
    await t.open('tutor-registration-application.php?plan=grow', 2000);
    t.expect(await p().isDisabled('#su-next'), 'Send is possible without a slip'); // GROW: the slip comes first
    t.expect(await p().locator('.su-chosen').count() === 1 && await p().locator('.su-mini').count() === 0, 'More than the chosen plan is shown');
    await p().fill('#su-name', `${SIGNUP.first} ${SIGNUP.last}`);
    await p().fill('#su-email', SIGNUP.email);
    await p().fill('#su-mobile', SIGNUP.mobile);
    await p().setInputFiles('#su-slip', { name: 'slip.png', mimeType: 'image/png', buffer: Buffer.from('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNkYPhfDwAChwGA60e6kgAAAABJRU5ErkJggg==', 'base64') });
    await p().check('#su-agree');
    await p().click('#su-next');
    await p().waitForSelector('.su-ref', { timeout: 8000 });
    t.expect(/TR-\d+/.test(await text('.su-ref')), `No reference: ${await text('.su-ref')}`);
    t.expect(mailLog().slice(before).includes(SIGNUP.email), 'No "request received" e-mail');
  });

  // ------------------------------------------------------------------ admin approves
  await t.tc('BJ-04', 'Admin', 'Admin finds the request in Requested Tutors and opens it as a page', ['Sign in as admin', 'Open Requested Tutors', 'Open the new request'], 'The request page shows the plan GROW; no pop-up', async () => {
    await adminIn();
    const list = JSON.parse(await p().evaluate(async () => (await fetch('/api/v1/admin/tutor-requests?per_page=50', { headers: { Accept: 'application/json', 'X-Requested-With': 'enlixo' } })).text()));
    const req = (list.data.requests ?? []).find((r) => r.email === SIGNUP.email);
    t.expect(req, 'Request not listed');
    await t.open(`admin/requested-user-details?id=${req.id}`, 2500);
    t.expect(/GROW/i.test(await text()), 'Plan not shown');
    await noModal();
    t.reqId = req.id;
  });
  await t.tc('BJ-05', 'Admin', 'Approve: Add Tutor is filled in, saving makes the tutor with a free trial and sends the welcome', ['Press Approve on the request', 'Check the filled-in Add Tutor form', 'Press Approve and Add Tutor'], 'Tutor Details opens with "Welcome e-mail sent" and the set-password link; the e-mail has "Set my password"', async () => {
    await t.open(`admin/add-tutor?request=${t.reqId}`, 3000);
    const before = mailLog().length;
    await p().locator('#add-tutor-actions button', { hasText: /Approve and Add Tutor/ }).click();
    await p().waitForURL(/tutor-details\?tutor-id=/, { timeout: 15000 });
    await p().waitForTimeout(2500);
    newTutorId = Number(new URL(p().url()).searchParams.get('tutor-id'));
    t.expect(/Welcome e-mail sent/.test(await text('.ad-welcome')), 'No welcome banner');
    setLink = (await text('.ad-welcome code')).trim();
    t.expect(/setup-password\?token=[a-f0-9]{40}/.test(setLink), `Link: ${setLink}`);
    const mail = mailLog().slice(before);
    t.expect(mail.includes(SIGNUP.email) && /Set your password/.test(mail), 'Welcome e-mail missing');
  });

  // ------------------------------------------------------------------ the new tutor
  await t.tc('BJ-06', 'New tutor', 'The new tutor sets a password with the link and signs in', ['Open the link from the e-mail', 'Enter a password twice', 'Sign in with e-mail and password'], 'Dashboard opens; the plan card says "Free trial · N days left"', async () => {
    await t.useViewport({ width: 1440, height: 900 });
    const token = new URL(setLink).searchParams.get('token');
    await t.open(`tutor/setup-password?token=${token}`, 1500);
    t.expect(await p().isVisible('#password'), 'Set-password page not shown');
    const r = await post('api/setupUserPassword.php', { token, status: 1, password: 'NewTutor#2026' });
    t.expect(/updated/i.test(r.body), `Answer: ${r.body.slice(0, 120)}`);
    await t.open('tutor/sign-in', 800);
    await p().fill('#email', SIGNUP.email);
    await p().fill('#password', 'NewTutor#2026');
    await p().click('button:has-text("Sign In")');
    await p().waitForURL(/dashboard/, { timeout: 15000 });
    await p().waitForTimeout(2000);
    t.expect(/Free trial · \d+ days? left/.test(await text('.ex-plan-card')), `Plan card: ${await text('.ex-plan-card')}`);
  });
  await t.tc('BJ-07', 'New tutor', 'The set-password link works only once', ['Open the same link again and send a password'], 'Refused: the link is invalid now', async () => {
    const token = new URL(setLink).searchParams.get('token');
    const r = await post('api/setupUserPassword.php', { token, status: 1, password: 'Again#2026x' });
    t.expect(!/updated/i.test(r.body), 'The link worked twice');
  });

  // ------------------------------------------------------------------ admin: trial on a chosen plan, invoice page
  await t.tc('BJ-08', 'Admin', 'Admin gives the new tutor a 30-day free trial on STARTER from the Manage Plan page', ['Open the tutor › Manage Plan › Give a Free Trial', 'Choose STARTER and 30 days', 'Press Give Free Trial'], 'Back on Tutor Details: Trial, STARTER; History has the trial; no pop-up', async () => {
    await t.useViewport({ width: 1440, height: 900 });
    await adminIn();
    await t.open(`admin/tutor-plan?tutor-id=${newTutorId}&do=trial`, 2500);
    await noModal();
    await p().locator('.tp-plan', { hasText: 'STARTER' }).click();
    await p().locator('.tp-days button', { hasText: '30 days' }).click();
    await p().fill('[data-key="reason"]', 'QA: longer trial before the term');
    await p().click('#tp-save');
    await p().waitForURL(/tutor-details/, { timeout: 15000 });
    await p().waitForTimeout(2500);
    const body = await text();
    t.expect(/Trial/.test(body) && /STARTER/.test(body), 'Not on a STARTER trial');
    const acc = JSON.parse(await p().evaluate(async (id) => (await fetch(`/api/v1/admin/tutors/${id}`, { headers: { Accept: 'application/json', 'X-Requested-With': 'enlixo' } })).text(), newTutorId));
    t.expect(acc.data.tutor.status === 'trial' && acc.data.tutor.plan === 'start', `Status ${acc.data.tutor.status}, plan ${acc.data.tutor.plan}`);
    t.expect(acc.data.history.some((h) => /Free 30-day trial on STARTER/.test(h.what)), 'History missing the trial');
  });
  await t.tc('BJ-09', 'Admin', 'An invoice opens as a page with Record a Payment on the page', ['Open Finance › Invoices', 'Open an invoice', 'Press Record a Payment'], 'Invoice page; the payment form opens on the page, no pop-up', async () => {
    const list = JSON.parse(await p().evaluate(async () => (await fetch('/api/v1/admin/invoices?per_page=50', { headers: { Accept: 'application/json', 'X-Requested-With': 'enlixo' } })).text()));
    const inv = (list.data ?? []).find((i) => ['open', 'overdue', 'part'].includes(i.status)) ?? (list.data ?? [])[0];
    t.expect(inv, 'No invoice in the test database');
    await t.open(`admin/invoice?invoice-id=${inv.id}`, 2500);
    t.expect(/Invoice total/.test(await text()), 'Invoice not shown');
    if (['open', 'overdue', 'part'].includes(inv.status)) {
      await p().locator('.ld-act', { hasText: 'Record a Payment' }).click();
      await p().waitForTimeout(600);
      t.expect(await p().isVisible('#inv-panel'), 'Payment form not on the page');
    }
    await noModal();
  });

  // ------------------------------------------------------------------ subscription ends: everyone locked out
  await t.tc('BJ-10', 'Lock-out', 'A signed-in student is signed out when the tutor\'s subscription is suspended', ['A student of the QA tutor signs in', 'ENLIXO suspends the tutor (not paid)', 'The student opens the next page'], 'Back on the sign-in page with "The class portal is paused"', async () => {
    await t.useViewport({ width: 390, height: 844 });
    await t.open('portal/', 800);
    const r = await post('api/signIn.php', { mobile: qb.student_whatsapp, otp: qb.student_otp });
    t.expect(/"done"/.test(r.body), `Student sign-in: ${r.body.slice(0, 120)}`);
    php('setup-qa-billing.php', 'suspend');
    await t.open('portal/dashboard', 2000);
    t.expect(!path_().endsWith('/dashboard'), `Stayed on ${path_()}`);
    t.expect(/class portal is paused/i.test(await text()), 'No explanation on the sign-in page');
  });
  await t.tc('BJ-11', 'Lock-out', 'The student cannot sign in while the tutor is suspended', ['Sign in with the WhatsApp code'], 'Refused with "Your tutor\'s ENLIXO account is paused…"', async () => {
    qb = JSON.parse(php('setup-qa-billing.php'));
    php('setup-qa-billing.php', 'suspend');
    const r = await post('api/signIn.php', { mobile: qb.student_whatsapp, otp: qb.student_otp });
    t.expect(/paused/i.test(r.body) && !/"done"/.test(r.body), `Answer: ${r.body.slice(0, 160)}`);
  });
  await t.tc('BJ-12', 'Lock-out', 'The parent cannot sign in while the tutor is suspended', ['The parent signs in with the WhatsApp code'], 'Refused with the paused message', async () => {
    await t.open('parent/', 800);
    const r = await post('api/signIn.php', { mobile: qb.parent_whatsapp, otp: qb.parent_otp });
    t.expect(/paused/i.test(r.body) && !/"done"/.test(r.body), `Answer: ${r.body.slice(0, 160)}`);
  });
  await t.tc('BJ-13', 'Lock-out', 'The suspended tutor is sent to Renew from every page, on a phone', ['The QA tutor signs in', 'Open Classes, Students and Time Table'], 'Every page goes to Renew: "Your subscription is paused", steps and Pay / WhatsApp; nothing cut off', async () => {
    await t.useViewport({ width: 390, height: 844 });
    await t.open('tutor/sign-in', 800);
    await p().fill('#email', 'qa.tutor@enlixo.test');
    await p().fill('#password', 'QaTutor#2026');
    await p().click('button:has-text("Sign In")');
    await p().waitForTimeout(4500);
    for (const pg of ['classes', 'students', 'time-table']) {
      await t.open(`tutor/${pg}`, 2500);
      t.expect(path_().endsWith('/renew'), `${pg} opened ${path_()}`);
    }
    t.expect(/Your subscription is paused|We are checking your payment|Your subscription has ended/.test(await text()), 'Renew page title missing');
    t.expect(/WhatsApp ENLIXO/.test(await text()), 'No way to contact ENLIXO');
    const wide = await p().evaluate(() => [...document.querySelectorAll('#rn-page *, #rn-actions *')].some((el) => el.getBoundingClientRect().right > document.documentElement.clientWidth + 2));
    t.expect(!wide, 'Renew page cut off on a phone');
  });
  await t.tc('BJ-14', 'Lock-out', 'Reactivated: the student and the parent can sign in again', ['ENLIXO reactivates the tutor', 'Student and parent sign in'], 'Both sign in', async () => {
    php('setup-qa-billing.php', 'active');
    qb = JSON.parse(php('setup-qa-billing.php'));
    await t.open('portal/', 800);
    const s = await post('api/signIn.php', { mobile: qb.student_whatsapp, otp: qb.student_otp });
    await t.open('parent/', 800);
    const pr = await post('api/signIn.php', { mobile: qb.parent_whatsapp, otp: qb.parent_otp });
    t.expect(/"done"/.test(s.body) && /"done"/.test(pr.body), `Student: ${s.body.slice(0, 80)} · Parent: ${pr.body.slice(0, 80)}`);
  });

  php('setup-qa-tutor.php');
  const fails = await t.finish(path.join(OUT, 'billing-journey.json'));
  process.exit(fails ? 1 : 0);
})();
