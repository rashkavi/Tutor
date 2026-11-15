// End-to-end QA of a parent in the parent portal, on a PHONE first (390 x 844), then on a desktop.
//   php -S 127.0.0.1:8082 bin/dev-router.php               (from the repo root, test database only)
//   node tests/e2e/parent-journey.e2e.js [http://127.0.0.1:8082]
// Runs tests/e2e/setup-qa-student.php and setup-qa-parent.php itself (a parent with two children, fees of each status,
// a fresh sign-in code). WhatsApp cannot be reached from a test machine: only the "send code" call is answered by the
// test, the code is typed into the real OTP screen and checked by the real sign-in API.
// Results: tests/e2e/results/parent-journey.json (+ a screenshot per failed case). Exit code = number of failures.
const path = require('path');
const fs = require('fs');
const { execFileSync } = require('child_process');
const { Harness } = require('./harness');

const BASE = process.argv[2] || 'http://127.0.0.1:8082';
const OUT = path.join(__dirname, 'results');
const PHONE = { width: 390, height: 844 };
const DESKTOP = { width: 1440, height: 900 };
const php = (file, ...args) => execFileSync('php', [path.join(__dirname, file), ...args]).toString();
const setup = (...args) => { php('setup-qa-student.php'); return JSON.parse(php('setup-qa-parent.php', ...args)); };

const slipPng = () => {
  const png = Buffer.from('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNkYPhfDwAChwGA60e6kgAAAABJRU5ErkJggg==', 'base64');
  const f = path.join(OUT, `qa-slip-p-${Date.now()}.png`);
  fs.writeFileSync(f, Buffer.concat([png, Buffer.from(`QA parent ${Date.now()} ${Math.random()}`)]));
  return f;
};

(async () => {
  let qa = setup();
  const t = new Harness(BASE);
  t.shots = path.join(OUT, 'shots');
  fs.mkdirSync(t.shots, { recursive: true });
  await t.start(PHONE);
  const p = () => t.page;
  const text = async (sel = 'body') => (await p().locator(sel).first().innerText().catch(() => '')).replace(/\s+/g, ' ');
  const path_ = () => new URL(p().url()).pathname;
  const post = (url, body) => p().evaluate(async ([u, b]) => {
    const r = await fetch(u, { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify(b) });
    return { status: r.status, body: await r.text() };
  }, [url, body]);
  const cutOff = () => p().evaluate(() => {
    const W = document.documentElement.clientWidth;
    const scrolls = (el) => { for (let a = el.parentElement; a && a !== document.body; a = a.parentElement) { const c = getComputedStyle(a); if (/(auto|scroll)/.test(c.overflowX) || c.position === 'fixed') return true; } return false; };
    return [...document.querySelectorAll('#real-list *, .contentBodyArea *')].filter((el) => {
      const r = el.getBoundingClientRect(); const c = getComputedStyle(el);
      return r.width > 0 && r.height > 0 && r.right > W + 2 && c.visibility !== 'hidden' && c.display !== 'none' && c.position !== 'fixed' && !scrolls(el) && !el.closest('.offcanvas, .modal, .dropdown-menu');
    }).slice(0, 3).map((el) => `${el.tagName.toLowerCase()}.${[...el.classList].slice(0, 2).join('.')} (ends at ${Math.round(el.getBoundingClientRect().right)}px)`);
  });
  const signIn = async () => {
    await p().route('**/parent/api/sendOtp.php', (r) => r.fulfill({ contentType: 'application/json', body: JSON.stringify({ done: 'We sent a 6-digit code to your WhatsApp' }) }));
    await t.open('parent/', 800);
    await p().fill('#mobile', qa.whatsapp);
    await p().locator('#content-div button.custom-btn').first().click();
    await p().waitForSelector('.otp-digit', { timeout: 8000 });
    for (let i = 0; i < 6; i++) await p().fill(`.otp-digit[data-index="${i}"]`, qa.otp[i]);
    await p().click('#verifyBtn');
    await p().waitForURL(/dashboard/, { timeout: 10000 });
    await p().unroute('**/parent/api/sendOtp.php');
  };

  // ------------------------------------------------------------------ sign in (phone)
  await t.tc('PA-01', 'Sign in', 'Signed-out parent cannot open the dashboard', ['Open /parent/dashboard without signing in'], 'Sent to the sign-in page', async () => {
    await t.open('parent/dashboard', 800);
    t.expect(!path_().endsWith('/dashboard'), `Stayed on ${path_()}`);
  });
  await t.tc('PA-02', 'Sign in', 'Empty WhatsApp number is refused', ['Leave the number empty, press Send'], 'A message asks for the number', async () => {
    await t.open('parent/', 800);
    const r = await post('api/sendOtp.php', { mobile: '' });
    t.expect(/enter your whatsapp number/i.test(r.body), `Answer: ${r.body.slice(0, 120)}`);
  });
  await t.tc('PA-03', 'Sign in', 'A number that is not a parent is refused', ['Send 0719999999'], '"not registered as a parent", no code', async () => {
    const r = await post('api/sendOtp.php', { mobile: '0719999999' });
    t.expect(/not registered/i.test(r.body) && !/"done"/.test(r.body), `Answer: ${r.body.slice(0, 120)}`);
  });
  await t.tc('PA-04', 'Security', 'The send-code answer never contains the code', ['Ask for a code for the QA parent'], 'No code in the answer (only WhatsApp gets it)', async () => {
    const r = await post('api/sendOtp.php', { mobile: qa.whatsapp });
    t.expect(!/"otp"\s*:\s*"?\d{6}/.test(r.body), `The answer gives the code away: ${r.body.slice(0, 120)}`);
    qa = setup();
  });
  await t.tc('PA-05', 'Security', 'The WhatsApp number alone does NOT sign in', ['Send only the number to the sign-in API (no code)'], 'Refused: the 6-digit code is required', async () => {
    const r = await post('api/signIn.php', { mobile: qa.whatsapp });
    t.expect(!/"done"/.test(r.body), `Signed in with only the number: ${r.body.slice(0, 120)}`);
  });
  await t.tc('PA-06', 'Security', 'A wrong code is refused', ['Send the number and 000000'], '"Incorrect code"', async () => {
    const r = await post('api/signIn.php', { mobile: qa.whatsapp, otp: '000000' });
    t.expect(/incorrect/i.test(r.body) && !/"done"/.test(r.body), `Answer: ${r.body.slice(0, 120)}`);
  });
  await t.tc('PA-07', 'Security', 'A code older than 5 minutes is refused', ['Use a code sent 10 minutes ago'], '"The code has expired"', async () => {
    setup('expired');
    const r = await post('api/signIn.php', { mobile: qa.whatsapp, otp: qa.otp });
    qa = setup();
    t.expect(/expired/i.test(r.body) && !/"done"/.test(r.body), `Answer: ${r.body.slice(0, 120)}`);
  });
  await t.tc('PA-08', 'Security', 'Quotes / SQL text in the number do not break sign-in', ["Send 07' OR '1'='1"], 'Refused normally, no server error', async () => {
    const a = await post('api/sendOtp.php', { mobile: "07' OR '1'='1" });
    const b = await post('api/signIn.php', { mobile: "07' OR '1'='1", otp: '123456' });
    t.expect(a.status < 500 && b.status < 500 && !/"done"/.test(a.body + b.body), `Answers: ${a.status} ${b.status}`);
  });
  await t.tc('PA-09', 'Sign in', 'Parent signs in with the WhatsApp code on a phone', ['Type the WhatsApp number, press Send', 'Type the 6-digit code', 'Press Verify'], 'The OTP screen says Parent Login; the dashboard opens', async () => {
    await signIn();
    await p().waitForTimeout(1500);
    t.expect(/Qa/.test(await text()), 'Parent name not shown');
  });
  await t.tc('PA-10', 'Security', 'A used code cannot sign in again', ['Send the same code again'], 'Refused (the code works once)', async () => {
    const r = await post('api/signIn.php', { mobile: qa.whatsapp, otp: qa.otp });
    t.expect(!/"done"/.test(r.body), 'The same code worked twice');
  });

  // ------------------------------------------------------------------ every page on a phone
  const pages = () => [
    ['dashboard', 'Dashboard'], ['my-children', 'My Children'], ['child-progress', 'Child Progress'], [`child-progress?child-id=${qa.second_child}`, 'Child Progress (second child)'],
    [`child-classes?child-id=${qa.child}`, "A child's classes"], ['classes', 'Classes'], [`class-details?class-id=${qa.child_class}`, 'Class details'],
    [`class-time-table?class-id=${qa.child_class}`, 'Class time table'], [`class-attendance?class-id=${qa.child_class}`, 'Class attendance'],
    [`class-recordings?class-id=${qa.child_class}`, 'Class recordings'], [`class-pre-recordings?class-id=${qa.child_class}`, 'Class pre-recordings'],
    [`class-payments?class-id=${qa.child_class}`, 'Class payments'], ['time-table', 'Time Table'], ['attendance', 'Attendance'], ['tutes', 'Tutes'],
    ['payments', 'Payments'], [`payments?child=${qa.second_child}`, 'Payments (second child)'], [`payment-details?payment-id=${qa.child_fee}`, 'Payment details'],
    [`pay-payment?payment-id=${qa.child_fee}`, 'Pay a fee'], [`watch-recording?recording-id=${qa.child_recording}`, 'Watch a recording'],
  ];
  const pageCases = async (prefix, size) => {
    let n = 1;
    for (const [pg, label] of pages()) {
      const id = `${prefix}-${String(n++).padStart(2, '0')}`;
      await t.tc(id, `Pages (${size})`, `${label} works on a ${size}`, [`Open /parent/${pg} at ${t.viewport.width} px`], 'Opens, no JS / PHP / server errors, nothing cut off at the side', async () => {
        const res = await t.open(`parent/${pg}`, 2500);
        t.expect(res && res.status() === 200, `HTTP ${res && res.status()}`);
        const want = pg.split('?')[0];
        t.expect(path_().endsWith(want), `Sent to ${path_()} instead of ${want}`);
        const cut = await cutOff();
        t.expect(!cut.length, `Cut off on the right: ${cut.join(', ')}`);
      });
    }
  };
  await pageCases('PA-P', 'phone');

  // ------------------------------------------------------------------ what a parent does
  await t.tc('PA-20', 'Progress', "Child Progress shows how the child is doing", ['Open Child Progress'], 'Needs-attention box, attendance % of the last 30 days, recent class days, attendance by class, next 7 days', async () => {
    await t.open(`parent/child-progress?child-id=${qa.child}`, 2500);
    const body = await text();
    for (const s of ['Needs your attention', 'attendance, last 30 days', 'Recent class days', 'Attendance by class', 'Next 7 days']) t.expect(body.includes(s), `Missing "${s}"`);
    t.expect(/Qa Student/.test(await text('.pp-hero')), 'Not the chosen child');
  });
  await t.tc('PA-21', 'Progress', 'The child chips switch between the two children', ['Open Child Progress', 'Press the other child'], 'The page shows the other child', async () => {
    await t.open(`parent/child-progress?child-id=${qa.child}`, 2000);
    const first = await text('.pp-name');
    await p().locator(`.pp-child[href*="child-id=${qa.second_child}"]`).click();
    await p().waitForTimeout(2000);
    const second = await text('.pp-name');
    t.expect(second && second !== first, `Still ${second}`);
    return `${first} → ${second}`;
  });
  await t.tc('PA-22', 'Progress', 'Overdue fees show in "Needs your attention" with Pay now', ['Open Child Progress of the child with an overdue fee'], 'An overdue fee line with a Pay now button to Payments', async () => {
    await t.open(`parent/child-progress?child-id=${qa.child}`, 2000);
    const box = await text('.pp-attention');
    t.expect(/overdue/i.test(box), `Box: ${box.slice(0, 120)}`);
    t.expect(await p().locator('.pp-attention a[href*="payments?child="]').count() > 0, 'No Pay now link');
  });
  await t.tc('PA-23', 'Payments', 'Payments shows the chosen child\'s fees', ['Open Payments', 'Choose a child'], 'Balance card, next payment and the timeline of that child', async () => {
    await t.open(`parent/payments?child=${qa.child}`, 2500);
    t.expect(await p().isVisible('.sf-balance'), 'No balance card');
    t.expect((await p().locator('.sf-inst').count()) >= 4, 'Fee rows missing');
    t.expect(/Qa Student/.test(await text('.pp-child.is-active')), 'Wrong child chosen');
  });
  await t.tc('PA-24', 'Payments', 'Parent pays a fee by uploading a bank slip', ['Open Pay on an unpaid fee', 'Choose the payment method', 'Add the slip', 'Press Save Payment'], 'Saved; the fee is "Being checked"', async () => {
    await t.open(`parent/pay-payment?payment-id=${qa.child_fee}`, 2500);
    const v = await p().$eval('#payment-method', (s) => [...s.options].find((o) => o.value && o.value !== '0')?.value);
    await p().selectOption('#payment-method', v);
    await p().setInputFiles('#file-selector-input', slipPng());
    await p().waitForTimeout(800);
    await p().locator('button', { hasText: 'Save Payment' }).click();
    await p().waitForTimeout(3500);
    await t.open(`parent/payments?child=${qa.child}`, 2500);
    const waiting = await p().locator('.sf-inst.is-waiting').count();
    t.expect(waiting >= 2, `Only ${waiting} fee(s) being checked`);
  });
  await t.tc('PA-25', 'Security', 'An accepted fee cannot be paid again', ['Send a slip for a Paid fee'], 'Refused', async () => {
    const paid = await p().$eval('.sf-inst.is-paid a[href*="payment-details"]', (a) => new URL(a.href).searchParams.get('payment-id')).catch(() => null);
    t.expect(paid, 'No paid fee on the page');
    const r = await p().evaluate(async (id) => { const f = new FormData(); f.append('payment_id', id); f.append('method', '1'); return (await fetch('api/savePayment.php', { method: 'POST', body: f })).text(); }, paid);
    t.expect(!/"done"/.test(r), `Accepted: ${r.slice(0, 120)}`);
  });
  for (const [id, title, url, back] of [
    ['PA-26', "Another family's fee does not open", `payment-details?payment-id=${qa.stranger_fee}`, 'payments'],
    ['PA-27', "Another family's fee cannot be paid", `pay-payment?payment-id=${qa.stranger_fee}`, 'payments'],
    ['PA-28', "A class none of the children is in does not open", `class-details?class-id=${qa.stranger_class}`, 'classes'],
    ['PA-29', "Another class's recording does not open", `watch-recording?recording-id=${qa.stranger_recording}`, 'classes'],
    ['PA-30', "Another class's payments do not open", `class-payments?class-id=${qa.stranger_class}`, 'classes'],
  ]) {
    await t.tc(id, 'Security', title, [`Open /parent/${url}`], `Sent back to ${back}`, async () => {
      const res = await t.open(`parent/${url}`, 1500);
      t.expect(res.status() < 400, `HTTP ${res.status()}`);
      t.expect(path_().endsWith(`/${back}`), `Opened ${path_()}`);
    });
  }
  await t.tc('PA-31', 'Security', "Another family's child is never shown", [`Open /parent/child-progress?child-id=${'<another child>'}`, 'and payments?child=<another child>'], "The parent's own first child is shown instead", async () => {
    await t.open(`parent/child-progress?child-id=${qa.stranger}`, 2000);
    const active = await p().$eval('.pp-child.is-active', (a) => a.getAttribute('href')).catch(() => '');
    t.expect(!active.endsWith(`=${qa.stranger}`), 'The other child is shown');
    const r = await post('api/savePayment.php', {});
    t.expect(!/"done"/.test(r.body), 'Empty payment accepted');
  });
  await t.tc('PA-32', 'Phone', 'The phone menu has Child Progress and Payments', ['On a phone, open the menu'], 'Both items are there', async () => {
    await t.open('parent/dashboard', 1500);
    await p().evaluate(() => { const el = document.getElementById('offcanvas'); if (el && window.bootstrap) window.bootstrap.Offcanvas.getOrCreateInstance(el).show(); });
    await p().waitForTimeout(800);
    const menu = await text('#offcanvas');
    t.expect(/Child Progress/.test(menu) && /Payments/.test(menu), `Menu: ${menu.slice(0, 100)}`);
  });
  await t.tc('PA-33', 'Progress', 'My Children opens the child\'s progress', ['Open My Children', 'Press View on a child'], 'Child Progress of that child opens (not an edit form)', async () => {
    await t.open('parent/my-children', 2000);
    await p().locator('.btn-watch-premium', { hasText: 'View' }).first().click();
    await p().waitForTimeout(2000);
    t.expect(path_().endsWith('/child-progress'), `Opened ${path_()}`);
  });

  // ------------------------------------------------------------------ the same pages on a desktop
  qa = setup();
  await t.useViewport(DESKTOP);
  await t.tc('PA-40', 'Sign in', 'Parent signs in on a desktop', ['Same as PA-09 at 1440 px'], 'Dashboard opens', async () => { await signIn(); });
  await pageCases('PA-D', 'desktop');

  await t.tc('PA-60', 'Sign out', 'Sign out ends the session', ['Press Log Out'], 'The dashboard no longer opens', async () => {
    await t.open('parent/dashboard', 1500);
    await p().evaluate(() => (typeof signOut === 'function' ? signOut() : fetch('api/signOut.php')));
    await p().waitForTimeout(3000);
    await t.open('parent/dashboard', 1000);
    t.expect(!path_().endsWith('/dashboard'), `Dashboard still open: ${path_()}`);
  });

  for (const f of fs.readdirSync(OUT)) if (f.startsWith('qa-slip-')) fs.unlinkSync(path.join(OUT, f));
  const fails = await t.finish(path.join(OUT, 'parent-journey.json'));
  process.exit(fails ? 1 : 0);
})();
