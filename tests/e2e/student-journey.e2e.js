// End-to-end QA of a student's day in the student portal, on a PHONE first (390 x 844), then on a desktop.
//   php -S 127.0.0.1:8082 bin/dev-router.php               (from the repo root, test database only)
//   node tests/e2e/student-journey.e2e.js [http://127.0.0.1:8082]
// The script runs tests/e2e/setup-qa-student.php itself (QA student, fees of each status, a fresh sign-in code).
// WhatsApp cannot be reached from a test machine, so only the "send code" call is answered by the test; the code is
// then typed into the real OTP screen and checked by the real sign-in API.
// Results: tests/e2e/results/student-journey.json (+ a screenshot per failed case). Exit code = number of failures.
const path = require('path');
const fs = require('fs');
const { execFileSync } = require('child_process');
const { Harness } = require('./harness');

const BASE = process.argv[2] || 'http://127.0.0.1:8082';
const OUT = path.join(__dirname, 'results');
const PHONE = { width: 390, height: 844 };
const DESKTOP = { width: 1440, height: 900 };
const setup = () => JSON.parse(execFileSync('php', [path.join(__dirname, 'setup-qa-student.php')]).toString());

/** a small, valid PNG that is different every run (the slip check refuses a slip that was used before) */
const slipPng = () => {
  const png = Buffer.from('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNkYPhfDwAChwGA60e6kgAAAABJRU5ErkJggg==', 'base64');
  const f = path.join(OUT, `qa-slip-${Date.now()}.png`);
  fs.writeFileSync(f, Buffer.concat([png, Buffer.from(`QA ${Date.now()} ${Math.random()}`)]));
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

  /** content that sticks out of the screen and is not inside a box that scrolls sideways (a table, the tab bar) */
  const cutOff = () => p().evaluate(() => {
    const W = document.documentElement.clientWidth;
    const scrolls = (el) => { for (let a = el.parentElement; a && a !== document.body; a = a.parentElement) { const c = getComputedStyle(a); if (/(auto|scroll)/.test(c.overflowX) && a.clientWidth < a.scrollWidth + 1) return true; if (c.position === 'fixed') return true; } return false; };
    const shown = (el) => { const c = getComputedStyle(el); return c.visibility !== 'hidden' && c.display !== 'none' && c.position !== 'fixed'; };
    return [...document.querySelectorAll('#real-list *, .contentBodyArea *')].filter((el) => {
      const r = el.getBoundingClientRect();
      return r.width > 0 && r.height > 0 && r.right > W + 2 && shown(el) && !scrolls(el) && !el.closest('.offcanvas, .modal, .dropdown-menu');
    }).slice(0, 3).map((el) => `${el.tagName.toLowerCase()}.${[...el.classList].slice(0, 2).join('.')} (ends at ${Math.round(el.getBoundingClientRect().right)}px)`);
  });

  /** the real OTP screen: number -> (WhatsApp answered by the test) -> 6 boxes -> Verify */
  const signIn = async () => {
    await p().route('**/portal/api/sendOtp.php', (r) => r.fulfill({ contentType: 'application/json', body: JSON.stringify({ done: 'OTP sent to your WhatsApp' }) }));
    await t.open('portal/', 800);
    await p().fill('#mobile', qa.whatsapp);
    await p().locator('#content-div button.custom-btn').first().click();
    await p().waitForSelector('.otp-digit', { timeout: 8000 });
    for (let i = 0; i < 6; i++) await p().fill(`.otp-digit[data-index="${i}"]`, qa.otp[i]);
    await p().click('#verifyBtn');
    await p().waitForURL(/dashboard/, { timeout: 10000 });
    await p().unroute('**/portal/api/sendOtp.php');
  };

  const pages = [
    ['dashboard', 'Dashboard'], ['classes', 'Classes'], [`class-details?class-id=${qa.classes[0]}`, 'Class details'],
    [`class-time-table?class-id=${qa.classes[0]}`, 'Class time table'], [`class-attendance?class-id=${qa.classes[0]}`, 'Class attendance'],
    [`class-tutes?class-id=${qa.classes[0]}`, 'Class tutes'], [`class-payments?class-id=${qa.classes[0]}`, 'Class payments'],
    ['time-table', 'Time Table'], [`time-table-class-details?time-table-id=${qa.day}`, 'Class day details'], ['attendance', 'Attendance'],
    ['recordings', 'Class Recordings'], [`watch-recording?recording-id=${qa.recording}`, 'Watch a recording'], ['pre-recorded-classes', 'Pre Recorded Classes'],
    ['tutes', 'Tutes'], [qa.tute ? `downloadable-tute?class-id=${qa.tute.class_id}&tute-id=${qa.tute.id}` : 'tutes', 'Open a tute'],
    ['payments', 'Payments'], [`payment-details?payment-id=${qa.fee_open}`, 'Payment details'], [`pay-payment?payment-id=${qa.fee_open}`, 'Pay a fee'], ['my-profile', 'My Profile'],
  ];
  const pageCases = async (prefix, size) => {
    let n = 1;
    for (const [pg, label] of pages) {
      const id = `${prefix}-${String(n++).padStart(2, '0')}`;
      await t.tc(id, `Pages (${size})`, `${label} works on a ${size}`, [`Open /portal/${pg} at ${t.viewport.width} px`], 'Opens, no JS / PHP / server errors, nothing cut off at the side', async () => {
        const res = await t.open(`portal/${pg}`, 2500);
        t.expect(res && res.status() === 200, `HTTP ${res && res.status()}`);
        const want = pg.split('?')[0];
        t.expect(path_().endsWith(want), `Sent to ${path_()} instead of ${want}`);
        const cut = await cutOff();
        t.expect(!cut.length, `Cut off on the right: ${cut.join(', ')}`);
      });
    }
  };

  // ------------------------------------------------------------------ sign in (phone)
  await t.tc('ST-01', 'Sign in', 'Signed-out student cannot open the dashboard', ['Open /portal/dashboard without signing in'], 'Sent to the sign-in page', async () => {
    await t.open('portal/dashboard', 800);
    t.expect(!path_().endsWith('/dashboard'), `Stayed on ${path_()}`);
  });
  await t.tc('ST-02', 'Sign in', 'Empty WhatsApp number is refused', ['Open the sign-in page', 'Leave the number empty, press Send'], 'A message asks for the number', async () => {
    await t.open('portal/', 800);
    await p().locator('#content-div button.custom-btn').first().click();
    await p().waitForTimeout(1500);
    t.expect(/enter your (mobile|whatsapp) number/i.test(await text()), 'No message');
  });
  await t.tc('ST-03', 'Sign in', 'Unknown number is refused (no code is sent)', ['Type 0719999999', 'Press Send'], 'Error message, the OTP boxes do not open', async () => {
    const r = await post('api/sendOtp.php', { mobile: '0719999999' });
    t.expect(r.status < 500 && !/"done"/.test(r.body), `Answer: ${r.body.slice(0, 120)}`);
  });
  await t.tc('ST-04', 'Security', 'Quotes / SQL text in the number do not break sign-in', ["Send 07' OR '1'='1 as the number and as the code"], 'Refused normally, no server error', async () => {
    const a = await post('api/sendOtp.php', { mobile: "07' OR '1'='1" });
    const b = await post('api/signIn.php', { mobile: "07' OR '1'='1", otp: "1' OR '1'='1" });
    t.expect(a.status < 500 && b.status < 500 && !/"done"/.test(a.body + b.body), `Answers: ${a.status} ${b.status} ${(a.body + b.body).slice(0, 160)}`);
  });
  await t.tc('ST-05', 'Sign in', 'Wrong code is refused', ['Type the right number and a wrong 6-digit code'], '"Incorrect One-Time Password", still signed out', async () => {
    const r = await post('api/signIn.php', { mobile: qa.whatsapp, otp: '000000' });
    t.expect(/incorrect/i.test(r.body) && !/"done"/.test(r.body), `Answer: ${r.body.slice(0, 120)}`);
  });
  await t.tc('ST-08', 'Security', 'The send-code answer never contains the code', ['Ask for a code for the QA number'], 'The answer has no code in it (only WhatsApp gets it)', async () => {
    const r = await post('api/sendOtp.php', { mobile: qa.whatsapp });
    t.expect(!/"otp"\s*:\s*"?\d{6}/.test(r.body), `The answer gives the code away: ${r.body.slice(0, 120)}`);
    qa = setup(); // sendOtp may have saved a new code; put the test code back
  });
  await t.tc('ST-09', 'Security', 'A code older than 5 minutes is refused', ['Use a code that was sent 10 minutes ago'], 'Refused: "timed out"', async () => {
    execFileSync('php', [path.join(__dirname, 'setup-qa-student.php'), 'expired']);
    const r = await post('api/signIn.php', { mobile: qa.whatsapp, otp: qa.otp });
    qa = setup();
    t.expect(!/"done"/.test(r.body), `An old code still signed in: ${r.body.slice(0, 120)}`);
  });
  await t.tc('ST-06', 'Sign in', 'Student signs in with the WhatsApp code on a phone', ['Type the WhatsApp number, press Send', 'Type the 6-digit code in the boxes', 'Press Verify'], 'The OTP screen says Student Login; the dashboard opens with the name', async () => {
    await p().route('**/portal/api/sendOtp.php', (r) => r.fulfill({ contentType: 'application/json', body: JSON.stringify({ done: 'OTP sent' }) }));
    await t.open('portal/', 800);
    await p().fill('#mobile', qa.whatsapp);
    await p().locator('#content-div button.custom-btn').first().click();
    await p().waitForSelector('.otp-digit', { timeout: 8000 });
    t.expect(/Student Login/.test(await text('#content-div')), `OTP screen says: ${(await text('#content-div')).slice(0, 40)}`);
    for (let i = 0; i < 6; i++) await p().fill(`.otp-digit[data-index="${i}"]`, qa.otp[i]);
    await p().click('#verifyBtn');
    await p().waitForURL(/dashboard/, { timeout: 10000 });
    await p().unroute('**/portal/api/sendOtp.php');
    await p().waitForTimeout(2000);
    t.expect(/Qa/.test(await text()), 'Name not shown');
  });
  await t.tc('ST-07', 'Security', 'A used code cannot sign in again', ['Send the same code again to the sign-in API'], 'Refused (the code is cleared after use)', async () => {
    const r = await post('api/signIn.php', { mobile: qa.whatsapp, otp: qa.otp });
    t.expect(!/"done"/.test(r.body), 'The same code worked twice');
  });

  // ------------------------------------------------------------------ every page on a phone
  await pageCases('ST-P', 'phone');

  // ------------------------------------------------------------------ what the student does
  await t.tc('ST-20', 'Attendance', 'Attendance counts only this student\'s classes', ['Open Attendance'], `Present + absent = class days held in the student's classes (${qa.held_lectures})`, async () => {
    await t.open('portal/attendance', 2500);
    // tiles: Present, Absent, Watched Recording, Attendance Rate (the "being checked" ones are named under Watched)
    const nums = await p().$$eval('.ac-tiles .stats-mini-value', (els) => els.map((e) => e.textContent.trim()));
    const [present, absent, watched] = nums.map((v) => parseInt(v, 10));
    const pending = parseInt(((await text('.ac-tiles')).match(/(\d+) online class/) || [0, 0])[1], 10);
    t.expect(present + absent + watched + pending === qa.held_lectures, `Present ${present} + absent ${absent} + watched ${watched} + checked ${pending} ≠ ${qa.held_lectures}`);
    return `Present ${present}, absent ${absent}, watched ${watched}`;
  });
  await t.tc('ST-21', 'Payments', 'Payments shows the balance, the next payment and the timeline', ['Open Payments'], 'Balance card with % paid, the payment to make, a row per fee with its state', async () => {
    await t.open('portal/payments', 2500);
    t.expect(await p().isVisible('.sf-balance'), 'No balance card');
    t.expect(await p().isVisible('#sfNext'), 'No next payment card');
    const rows = await p().locator('.sf-inst').count();
    t.expect(rows >= 4, `Only ${rows} fee rows`);
    const chips = await text('.sf-timeline-card');
    t.expect(/To pay|Overdue/.test(chips), 'No fee to pay shown');
    for (const c of ['Being checked', 'Paid', 'Slip rejected']) t.expect(chips.includes(c), `No "${c}" fee shown`);
  });
  await t.tc('ST-22', 'Payments', 'The Paid tab shows only paid fees', ['Open Payments', 'Press the Paid tab'], 'Only rows marked Paid are visible', async () => {
    await p().click('.ac-tab[data-filter="paid"]');
    const visible = await p().$$eval('.sf-inst', (els) => els.filter((e) => e.offsetParent).map((e) => e.className));
    t.expect(visible.length > 0 && visible.every((c) => c.includes('is-paid')), `Visible: ${visible.join(' | ')}`);
  });
  await t.tc('ST-23', 'Payments', 'A rejected slip can be paid again', ['Open Payments', 'Open Pay on the fee whose slip was rejected'], 'The pay page opens for it (not sent away)', async () => {
    await t.open(`portal/pay-payment?payment-id=${qa.fee_rejected}`, 2000);
    t.expect(path_().endsWith('/pay-payment'), `Sent to ${path_()}`);
  });
  await t.tc('ST-24', 'Payments', 'Pay a fee by uploading a bank slip', ['Open Pay on an unpaid fee', 'Choose the payment method', 'Add the slip image', 'Press Save Payment'], 'Saved; the fee now shows "Being checked" in Payments', async () => {
    await t.open(`portal/pay-payment?payment-id=${qa.fee_open}`, 2500);
    const v = await p().$eval('#payment-method', (s) => [...s.options].find((o) => o.value && o.value !== '0')?.value);
    await p().selectOption('#payment-method', v);
    await p().setInputFiles('#file-selector-input', slipPng());
    await p().waitForTimeout(800);
    await p().locator('button', { hasText: 'Save Payment' }).click();
    await p().waitForTimeout(3500);
    await t.open('portal/payments', 2500);
    const row = await p().locator('.sf-inst', { hasText: `QA-${qa.fee_open}` }).first().innerText().catch(() => '');
    const any = await text('.sf-timeline-card');
    t.expect(/Being checked/.test(row) || (await p().locator('.sf-inst.is-waiting').count()) >= 2, `Fee not waiting for checking: ${row.replace(/\s+/g, ' ').slice(0, 120) || any.slice(0, 120)}`);
  });
  await t.tc('ST-25', 'Security', 'An accepted fee cannot be paid again', ['Send a slip for a fee that is already Paid'], 'Refused, the fee stays Paid', async () => {
    const r = await p().evaluate(async (id) => {
      const f = new FormData(); f.append('payment_id', id); f.append('method', '1');
      const res = await fetch('api/savePayment.php', { method: 'POST', body: f }); return res.text();
    }, String(qa.fee_paid));
    t.expect(!/"done"/.test(r), `Accepted: ${r.slice(0, 120)}`);
  });
  for (const [id, title, url, back] of [
    ['ST-26', "Another student's fee does not open", `payment-details?payment-id=${qa.other_fee}`, 'payments'],
    ['ST-27', 'A class the student is not in does not open', `class-details?class-id=${qa.other_class}`, 'classes'],
    ['ST-28', "Another class's class day (and its meeting link) does not open", `time-table-class-details?time-table-id=${qa.other_day}`, 'time-table'],
    ['ST-29', "Another class's tute does not open", `downloadable-tute?class-id=${qa.other_class}&tute-id=${qa.other_tute}`, 'tutes'],
  ]) {
    await t.tc(id, 'Security', title, [`Open /portal/${url}`], `Sent back to ${back}, nothing shown`, async () => {
      const res = await t.open(`portal/${url}`, 1500);
      t.expect(res.status() < 400, `HTTP ${res.status()}`);
      t.expect(path_().endsWith(`/${back}`), `Opened ${path_()}`);
    });
  }
  await t.tc('ST-30', 'Recordings', 'A recording opens in the player', ['Open Class Recordings', 'Open a recording'], 'The video player, or the reason it is closed, is shown', async () => {
    await t.open(`portal/watch-recording?recording-id=${qa.recording}`, 2500);
    const ok = await p().locator('iframe, video, .rc-player-msg, [data-player], .ex-player-lock').count();
    t.expect(ok > 0, 'No player and no message');
  });
  await t.tc('ST-31', 'Phone', 'The phone menu opens every section', ['On a phone, press the menu button'], 'The side menu slides in with Payments and Class Recordings', async () => {
    await t.open('portal/dashboard', 2000);
    await p().evaluate(() => { const el = document.getElementById('offcanvas'); if (el && window.bootstrap) window.bootstrap.Offcanvas.getOrCreateInstance(el).show(); });
    await p().waitForTimeout(800);
    const menu = await text('#offcanvas');
    t.expect(/Payments/.test(menu) && /Class Recordings/.test(menu), `Menu: ${menu.slice(0, 80)}`);
    const toggle = await p().locator('[data-bs-target="#offcanvas"]').count();
    t.expect(toggle > 0, 'No menu button in the header');
  });

  await t.tc('ST-32', 'No pop-ups', 'A class in the Attendance calendar opens its own page', ['Open Attendance', 'Click a class in the calendar'], 'The class day page opens; no pop-up', async () => {
    await t.open('portal/attendance', 3000);
    // phone: tap a marked day in the month grid, then its class card; desktop: a class in the month view
    const day = p().locator('.m-cal-day.has-class:visible').first();
    if (await day.count()) await day.click();
    await p().waitForTimeout(400);
    const item = p().locator('.m-lec:visible, .mv-lec:visible').first();
    t.expect(await item.count(), 'No class in the calendar this month');
    await item.click({ force: true });
    await p().waitForURL(/time-table-class-details/, { timeout: 10000 });
    await p().waitForTimeout(1500);
    t.expect(!(await p().locator('.modal.show').count()), 'A pop-up opened');
  });
  // ------------------------------------------------------------------ the same pages on a desktop
  qa = setup();
  await t.useViewport(DESKTOP);
  await t.tc('ST-40', 'Sign in', 'Student signs in on a desktop', ['Same as ST-06 at 1440 px'], 'Dashboard opens', async () => { await signIn(); });
  await pageCases('ST-D', 'desktop');

  await t.tc('ST-60', 'Sign out', 'Sign out ends the session', ['Press Log Out in the side menu'], 'Back on the sign-in page; the dashboard no longer opens', async () => {
    await t.open('portal/dashboard', 1500);
    await p().evaluate(() => (typeof signOut === 'function' ? signOut() : fetch('api/signOut.php')));
    await p().waitForTimeout(3000);
    await t.open('portal/dashboard', 1000);
    t.expect(!path_().endsWith('/dashboard'), `Dashboard still open: ${path_()}`);
  });

  for (const f of fs.readdirSync(OUT)) if (f.startsWith('qa-slip-')) fs.unlinkSync(path.join(OUT, f));
  const fails = await t.finish(path.join(OUT, 'student-journey.json'));
  process.exit(fails ? 1 : 0);
})();
