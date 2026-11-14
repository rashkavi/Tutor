// End-to-end QA of a tutor's day after the plan is active.
//   php tests/e2e/setup-qa-tutor.php                       (test database only: makes the QA tutor, plan Active)
//   php -S 127.0.0.1:8082 bin/dev-router.php               (from the repo root)
//   node tests/e2e/tutor-journey.e2e.js [http://127.0.0.1:8082]
// Results: tests/e2e/results/tutor-journey.json (+ a screenshot per failed case). Exit code = number of failures.
const path = require('path');
const fs = require('fs');
const { Harness } = require('./harness');

const BASE = process.argv[2] || 'http://127.0.0.1:8082';
const QA = { email: 'qa.tutor@enlixo.test', password: 'QaTutor#2026' };
const OUT = path.join(__dirname, 'results');
const stamp = Date.now().toString().slice(-6);

(async () => {
  const t = new Harness(BASE);
  t.shots = path.join(OUT, 'shots');
  fs.mkdirSync(t.shots, { recursive: true });
  await t.start();
  const p = t.page;
  const text = async (sel = 'body') => (await p.locator(sel).first().innerText().catch(() => '')).replace(/\s+/g, ' ');
  const signIn = async (email, password, remember = false) => {
    await t.open('tutor/sign-in', 800);
    await p.fill('#email', email);
    await p.fill('#password', password);
    await p.click('button:has-text("Sign In")');
  };
  let classId = null;
  let studentId = null;

  // ------------------------------------------------------------------ sign in
  await t.tc('TC-01', 'Sign in', 'Signed-out tutor cannot open the dashboard', ['Open /tutor/dashboard without signing in'], 'Sent to the sign-in page', async () => {
    await t.open('tutor/dashboard', 800);
    t.expect(!/dashboard/.test(p.url()) || /sign|index/.test(p.url()), `Stayed on ${p.url()}`);
  });
  await t.tc('TC-02', 'Sign in', 'Sign-in page opens', ['Open /tutor/sign-in'], 'E-mail, password and Sign In are shown', async () => {
    await t.open('tutor/sign-in', 800);
    t.expect(await p.isVisible('#email') && await p.isVisible('#password'), 'Fields missing');
  });
  await t.tc('TC-03', 'Sign in', 'Empty sign-in is refused with messages', ['Leave e-mail and password empty', 'Press Sign In'], 'Both fields show what to fill in; still signed out', async () => {
    await t.open('tutor/sign-in', 800);
    await p.click('button:has-text("Sign In")');
    await p.waitForTimeout(1200);
    const msg = await text('#email-feedback') + await text('#password-feedback');
    t.expect(/enter/i.test(msg), `No validation message (got "${msg}")`);
  });
  await t.tc('TC-04', 'Sign in', 'Wrong password is refused', ['Enter the QA e-mail and a wrong password', 'Press Sign In'], '"Incorrect email or password"; still signed out', async () => {
    await signIn(QA.email, 'wrong-password');
    await p.waitForTimeout(1500);
    t.expect(/incorrect/i.test(await text('#password-feedback')), 'No "incorrect" message');
  });
  await t.tc('TC-05', 'Security', 'SQL in the e-mail field does not sign in or break the page', ["Enter ' OR '1'='1 as the e-mail and any password", 'Press Sign In'], 'A normal refusal, no server error, still signed out', async () => {
    await signIn("' OR '1'='1' -- ", 'x');
    await p.waitForTimeout(1500);
    const r = await t.api('GET', '/me/access');
    t.expect(r.status === 401 || r.status === 403, `Signed in with SQL text (status ${r.status})`);
  });
  await t.tc('TC-06', 'Sign in', 'Correct e-mail and password sign in', ['Enter the QA tutor e-mail and password', 'Press Sign In'], 'The dashboard opens with "Hello Qa Tutor"', async () => {
    await signIn(QA.email, QA.password, true);
    await p.waitForURL(/dashboard/, { timeout: 9000 });
    await p.waitForTimeout(1500);
    t.expect(/Qa Tutor/.test(await text('header, .header, body')), 'Name not shown');
  });
  await t.tc('TC-07', 'Security', '"Remember me" never stores the password in a cookie', ['Send the sign-in with rememberMe = true (the API the page uses)', 'Read the browser cookies'], 'Only the e-mail is remembered; no cookie holds the password', async () => {
    await p.evaluate(async ([e, pw]) => { await fetch('api/signInWithEmail.php', { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify({ email: e, password: pw, rememberMe: true }) }); }, [QA.email, QA.password]);
    const cookies = await t.ctx.cookies();
    const leak = cookies.find((c) => decodeURIComponent(c.value) === QA.password || /password/i.test(c.name));
    t.expect(!leak, `Cookie "${leak?.name}" holds the password`);
  });

  // ------------------------------------------------------------------ plan
  await t.tc('TC-08', 'Plan', 'The plan is active and its features are open', ['GET /api/v1/me/access'], 'STARTER, not locked; class setup unlocked', async () => {
    const r = await t.api('GET', '/me/access');
    t.expect(r.status === 200, `HTTP ${r.status}`);
    const d = r.json.data;
    t.expect(d.plan.key === 'start', `Plan ${d.plan.key}`);
    const setup = d.features.find((f) => f.key === 'classes.setup');
    t.expect(setup && setup.unlocked, 'classes.setup locked');
  });
  await t.tc('TC-09', 'Plan', 'My Subscription shows the active STARTER plan', ['Open Finance › My Subscription'], 'Plan STARTER and status Active', async () => {
    await t.open('tutor/subscription', 2500);
    const s = await text();
    t.expect(/STARTER/i.test(s), 'STARTER not shown');
    t.expect(/Active/.test(s), 'Active not shown');
  });
  await t.tc('TC-10', 'Plan', 'Side menu shows the plan and the student limit', ['Look at the plan card in the side menu'], '"STARTER plan" and "of 150 students"', async () => {
    const s = await text('#sideMenu');
    t.expect(/STARTER/i.test(s) && /150/.test(s), `Got "${s.slice(-120)}"`);
  });

  // ------------------------------------------------------------------ every page opens cleanly
  const pages = [
    ['dashboard', 'Dashboard'], ['classes', 'All Classes'], ['create-class', 'Create a Class'], ['students', 'Registered Students'],
    ['requested-students', 'Requested Students'], ['add-student', 'Add New Student'], ['add-registered-student-to-class', 'Add Registered Student'],
    ['upload-student-bulk', 'Upload Students Bulk'], ['time-table', 'Time Table'], ['recordings', 'Class Recordings'],
    ['attendance-dashboard', 'Attendance Dashboard'], ['mark-attendance', 'Mark Attendance'], ['fees', 'Student Fees'], ['subscription', 'My Subscription'],
    ['message-center', 'Message Center'], ['class-form-settings', 'Class Form settings'], ['student-form-settings', 'Student Form settings'],
    ['fee-settings', 'Fees & Receipts settings'], ['attendance-settings', 'Attendance settings'], ['recording-types', 'Recording Types'],
    ['intergrations', 'Integrations'], ['my-profile', 'My Profile'], ['subscription?tab=plans', 'Plans & Billing'],
  ];
  let n = 11;
  for (const [pg, label] of pages) {
    const id = `TC-${String(n++).padStart(2, '0')}`;
    await t.tc(id, 'Pages', `${label} opens without errors`, [`Open /tutor/${pg} from the side menu`], 'Page loads (HTTP 200), no JS / PHP / server errors, not sent back to sign in', async () => {
      const res = await t.open(`tutor/${pg}`, 2500);
      t.expect(res && res.status() === 200, `HTTP ${res && res.status()}`);
      t.expect(!/sign-in|\/tutor\/?$|index$/.test(new URL(p.url()).pathname), `Sent to ${p.url()}`);
      const s = await text();
      t.expect(s.length > 200, 'Page is empty');
    });
  }

  // ------------------------------------------------------------------ work: class → student → time table → recording → tute → attendance → fees
  await t.tc('TC-40', 'Classes', 'Create a class with the Create a Class form', ['Open Create a Class', 'Pick exam type, year, subject, medium, class type', 'Physical, monthly fee 2,500, due day 10', 'Press Create'], 'Class saved and shown in All Classes', async () => {
    await t.open('tutor/create-class', 2500);
    for (const id of ['f-exam_type', 'f-year', 'f-subject', 'f-medium', 'f-class_type', 'f-delivery_mode']) {
      const opt = await p.$$eval(`#${id} option`, (os) => os.map((o) => o.value).filter((v) => v));
      if (opt.length) await p.selectOption(`#${id}`, opt[0]);
    }
    await p.fill('#f-name', `QA Physics ${stamp}`);
    if (await p.isVisible('#f-location')) await p.fill('#f-location', 'QA Hall 1');
    await p.fill('#f-fee', '2500');
    await p.fill('#f-due_day', '10');
    const before = p.url();
    await p.locator('button.custom-btn:visible', { hasText: /create|save/i }).last().click();
    await p.waitForTimeout(3500);
    const r = await t.api('GET', '/tutor/classes?per_page=50');
    const row = (r.json?.data ?? []).find((c) => (c.name ?? c.class_name ?? '').includes(`QA Physics ${stamp}`));
    t.expect(row, `Class not saved (still on ${before === p.url() ? 'the form' : p.url()}): ${await text('.notifications')}`);
    classId = row.id ?? row.class_id;
    return `Class #${classId}`;
  });
  await t.tc('TC-41', 'Classes', 'The new class opens its details page', ['Open the class from All Classes'], 'Class name and summary are shown', async () => {
    t.expect(classId, 'No class from TC-40');
    await t.open(`tutor/class-details?class-id=${classId}`, 3000);
    t.expect((await text()).includes(`QA Physics ${stamp}`), 'Class name not shown');
  });
  await t.tc('TC-42', 'Students', 'Add a new student to the class', ['Open Add New Student', 'Fill the required fields', 'Press Save Student'], 'Student saved and listed in Registered Students', async () => {
    t.expect(classId, 'No class from TC-40');
    await t.open('tutor/add-student', 3000);
    const fill = async (sel, v) => { if (await p.locator(sel).count()) { await p.locator(sel).first().fill(v); return true; } return false; };
    // only the required lists (Class, Gender); the guardian block stays empty, so it is not asked for
    for (const lbl of [/^Class/, /^Gender/]) {
      const sel = p.getByRole('combobox', { name: lbl }).first();
      // the Class list: the class made in TC-40; other lists: the first real choice
      if (await sel.count()) { const v = await sel.evaluate((x, cid) => ([...x.options].find((o) => o.value === String(cid)) || [...x.options].find((o) => o.value))?.value, classId); if (v) await sel.selectOption(v); }
    }
    await fill('[name=first_name], #f-first_name', 'Kavindi');
    await fill('[name=last_name], #f-last_name', `QA${stamp}`);
    await fill('[name=email], #f-email', `kavindi.${stamp}@example.com`);
    await fill('[name=whatsapp], #f-whatsapp', `07${stamp.slice(0, 2)}${stamp}12`.slice(0, 10));
    await fill('[name=mobile], #f-mobile', `07${stamp.slice(0, 2)}${stamp}12`.slice(0, 10));
    const dob = p.getByLabel(/Date of Birth/i).first();
    if (await dob.count()) await dob.fill('2008-05-14');
    await p.locator('button', { hasText: 'Save Student' }).first().click();
    await p.waitForTimeout(3500);
    const r = await t.api('GET', `/tutor/students?q=QA${stamp}`);
    const row = (r.json?.data ?? [])[0];
    t.expect(row, `Student not saved: ${(await text('.notifications')) || (await p.$$eval('.is-invalid, .text-dark-red', (e) => e.map((x) => x.textContent || x.name).filter(Boolean).join(', ')))}`);
    studentId = row.id ?? row.student_id;
    return `Student #${studentId}`;
  });
  await t.tc('TC-43', 'Students', 'Registered Students lists the new student', ['Open Registered Students', 'Search the student'], 'The student is in the list', async () => {
    await t.open(`tutor/students?q=QA${stamp}`, 3000);
    t.expect((await text()).includes(`QA${stamp}`), 'Student not listed');
  });
  await t.tc('TC-44', 'Classes', 'Class students list shows the student', ['Open the class › Students'], 'The student is in the class list', async () => {
    t.expect(classId && studentId, 'Needs TC-40 and TC-42');
    await t.open(`tutor/class-students?class-id=${classId}`, 3000);
    t.expect((await text()).includes(`QA${stamp}`), 'Student not in the class');
  });
  for (const [id, pg, label] of [
    ['TC-45', 'class-time-table', 'Class time table'], ['TC-46', 'class-recordings', 'Class recordings'], ['TC-47', 'class-tutes', 'Class tutes'],
    ['TC-48', 'class-attendance', 'Class attendance'], ['TC-49', 'class-payments', 'Class payments'], ['TC-50', 'class-payment-plans', 'Class payment plans'],
    ['TC-51', 'add-tute', 'Add Tute'], ['TC-52', 'add-recording', 'Add Recording'], ['TC-53', 'schedule-class', 'Schedule a class day'], ['TC-54', 'edit-class', 'Edit Class'],
  ]) {
    await t.tc(id, 'Classes', `${label} page of the class opens`, [`Open /tutor/${pg}?class-id=<new class>`], 'Page loads, no JS / PHP / server errors', async () => {
      t.expect(classId, 'No class from TC-40');
      const res = await t.open(`tutor/${pg}?class-id=${classId}`, 3000);
      t.expect(res && res.status() === 200, `HTTP ${res && res.status()}`);
      t.expect(!/sign-in/.test(p.url()), 'Sent to sign in');
      t.expect(!/class-id=/.test(p.url()) || p.url().includes(String(classId)) || /classes/.test(p.url()), `Redirected to ${p.url()}`);
    });
  }
  await t.tc('TC-55', 'Language', 'Switching to Sinhala translates the menu, English brings it back', ['Choose සිංහල in the language menu', 'Then English'], 'Menu reads උපකරණ පුවරුව, then Dashboard', async () => {
    await t.open('tutor/dashboard', 2000);
    await p.evaluate(() => { localStorage.setItem('lang', 'si'); return window.EnlixoI18n?.apply('si'); });
    await p.waitForTimeout(1500);
    t.expect((await text('#sideMenu')).includes('උපකරණ පුවරුව'), 'Not translated');
    await p.evaluate(() => { localStorage.setItem('lang', 'en'); return window.EnlixoI18n?.apply('en'); });
    await p.waitForTimeout(800);
    t.expect((await text('#sideMenu')).includes('Dashboard'), 'English not back');
  });

  // ------------------------------------------------------------------ sign out
  await t.tc('TC-56', 'Sign out', 'Sign out ends the session', ['Press the sign-out button in the side menu'], 'Sign-in page; the dashboard and the API are closed again', async () => {
    await t.open('tutor/dashboard', 1500);
    await p.evaluate(() => (typeof signOut === 'function' ? signOut() : (location.href = 'api/signOut.php')));
    await p.waitForTimeout(2500);
    const r = await t.api('GET', '/me/access');
    t.expect(r.status === 401 || r.status === 403, `API still open (${r.status})`);
    await t.open('tutor/dashboard', 800);
    t.expect(!/\/dashboard/.test(new URL(p.url()).pathname) || /sign/.test(p.url()), `Dashboard still open: ${p.url()}`);
  });

  const fails = await t.finish(path.join(OUT, 'tutor-journey.json'));
  process.exit(fails ? 1 : 0);
})();
