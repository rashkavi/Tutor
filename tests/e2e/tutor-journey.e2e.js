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
  // ------------------------------------------------------------------ main actions open PAGES, not pop-ups
  const ids = JSON.parse(require('child_process').execFileSync('php', [path.join(__dirname, 'qa-lookup.php')]).toString());
  const noModal = async () => t.expect(!(await p.locator('.modal.show').count()), 'A pop-up opened');
  // a real fee of the QA tutor (GET /api/v1/tutor/fees/slips for a slip to check, /unpaid for a student who owes)
  const mockFee = (status) => p.evaluate(async (st) => {
    const get = async (u) => (await (await fetch(`/api/v1${u}`, { headers: { Accept: 'application/json', 'X-Requested-With': 'enlixo' } })).json()).data ?? [];
    if (st === 'waiting') { const f = (await get('/tutor/fees/slips?per_page=1'))[0]; return f ? { id: f.id, student: f.student.id } : null; }
    const r = (await get('/tutor/fees/unpaid?per_page=1'))[0];
    return r ? { id: r.months[0].id, student: r.student.id } : null;
  }, status);
  await t.tc('TC-60', 'No pop-ups', 'Rejecting a slip happens on the fee page', ['Open a fee with a slip to check', 'Press Reject'], 'The reason form opens inside the page, no pop-up', async () => {
    await t.open('tutor/fees', 2500);
    const f = await mockFee('waiting');
    t.expect(f, 'No slip to check in the QA data');
    await t.open(`tutor/fee-details?fee-id=${f.id}`, 2500);
    await p.click('.fe-reject-btn');
    await p.waitForTimeout(800);
    t.expect(await p.isVisible('#fe-reject'), 'No reject form on the page');
    await noModal();
  });
  await t.tc('TC-61', 'No pop-ups', 'Record Payment is a page and saving opens the receipt page', ['Open Record Payment for a student who owes', 'Press Save & Give Receipt'], 'Record Payment page, then the Receipt page with the receipt number', async () => {
    await t.open('tutor/fees', 2500);
    const f = await mockFee('unpaid');
    await t.open(`tutor/record-payment?student-id=${f.student}&months=${f.id}`, 3000);
    await noModal();
    await p.locator('button[data-save]').last().click();
    await p.waitForURL(/fee-receipt/, { timeout: 10000 });
    await p.waitForTimeout(2000);
    t.expect(/RC-\d+/.test(await text('.fe-receipt-no')), 'No receipt number');
    await noModal();
  });
  await t.tc('TC-62', 'No pop-ups', 'Free Card / Discount is a page', ['Open a student Fee Card', 'Press Free Card / Discount'], 'The form opens as a page', async () => {
    await t.open('tutor/fees', 2500);
    const f = await mockFee('unpaid');
    await t.open(`tutor/student-fees?student-id=${f.student}`, 3000);
    await p.locator('#card-actions button', { hasText: 'Free Card' }).first().click();
    await p.waitForTimeout(2500);
    t.expect(/fee-concession/.test(p.url()) || /upgrade|GROW/i.test(await text()), `Stayed on ${p.url()}`);
    await noModal();
  });
  await t.tc('TC-63', 'No pop-ups', 'Approving a real payment slip happens on the payment page', ['Open a student payment that waits for approval'], 'An Approve / Reject card is on the page; no pop-up', async () => {
    t.expect(ids.tutor_fee, 'No payment of a QA class');
    await t.open(`tutor/student-payment?payment-id=${ids.tutor_fee}`, 2500);
    t.expect(await p.isVisible('#sp-decide'), 'No approve / reject card');
    await p.click('.sp-choice[data-value="3"]');
    t.expect((await p.inputValue('#status-selector')) === '3', 'Approve not chosen');
    await noModal();
  });
  await t.tc('TC-64', 'No pop-ups', 'An invoice and Pay Now open the invoice page', ['Open My Subscription', 'Open an invoice'], 'The invoice page with the amount (and how to pay while it is open)', async () => {
    t.expect(ids.tutor_invoice, 'The QA tutor has no invoice');
    await t.open(`tutor/invoice?invoice-id=${ids.tutor_invoice}`, 3000);
    t.expect(/Invoice total/.test(await text()), 'Invoice not shown');
    await noModal();
  });
  await t.tc('TC-65', 'No pop-ups', 'Clicking a day in the Time Table shows its classes on the page', ['Open Time Table', 'Click a day'], 'A panel under the calendar lists the classes of that day; no pop-up', async () => {
    await t.open('tutor/time-table', 3000);
    await p.locator('.ex-cal-cell[data-date]').first().click();
    await p.waitForTimeout(800);
    t.expect(await p.isVisible('#eventDayPanel'), 'No day panel');
    await noModal();
  });
  await t.tc('TC-66', 'Time Table', 'A lecture in the Time Table opens Lecture Details', ['Open Time Table', 'Click a lecture'], 'Lecture Details page: date and time, online link, attachments, attendance, recordings and the Mark Attendance / Attendance List / Add Recording / Edit Lecture actions', async () => {
    t.expect(ids.tutor_day, 'No lecture of the QA tutor');
    await t.open('tutor/time-table', 3000);
    const ev = p.locator(`.ex-cal-ev[data-lecture-id="${ids.tutor_day}"], .ex-cal-ev`).first();
    if (await ev.count()) { await ev.click(); await p.waitForURL(/lecture-details/, { timeout: 10000 }); } else { await t.open(`tutor/lecture-details?time-table-id=${ids.tutor_day}`, 2000); }
    await p.waitForTimeout(1500);
    const body = await text();
    for (const s of ['Details', 'Attachments', 'Attendance', 'Recordings', 'Mark Attendance', 'Attendance List', 'Add Recording', 'Edit Lecture']) t.expect(body.includes(s), `Missing "${s}"`);
    t.expect(await p.locator('.ld-file').count() > 0, 'The attachment is not listed');
    t.expect(await p.locator('a[href^="mark-attendance?session="]').count() > 0, 'No Mark Attendance link');
    t.expect(!(await p.locator('.modal.show').count()), 'A pop-up opened');
  });
  await t.tc('TC-67', 'Time Table', "Another tutor's lecture does not open", ['Open lecture-details of a lecture of another tutor'], '"Lecture not found", nothing of it shown', async () => {
    await t.open('tutor/lecture-details?time-table-id=1', 2000);
    t.expect(/Lecture not found/.test(await text()), 'Another tutor\'s lecture opened');
  });
  // ------------------------------------------------------------------ Message Center (run the server with MAIL_DRIVER=log WHATSAPP_DRIVER=log)
  const logLines = () => { try { return fs.readFileSync(path.join(__dirname, '..', '..', 'storage', 'messages.log'), 'utf8').trim().split('\n').length; } catch { return 0; } };
  await t.tc('TC-70', 'Messages', 'Send an e-mail to every student of the classes', ['Open Message Center', 'Choose E-mail, All classes, Students', 'Write a subject and a message with {name}', 'Press Send E-mail'], 'The message page sends everyone and shows Finished; nothing pops up', async () => {
    const before = logLines();
    await t.open('tutor/message-center', 3000);
    await p.locator('.mc-tile', { hasText: 'E-mail' }).click();
    await p.waitForTimeout(400);
    await p.locator('.mc-check-all input').check();
    await p.fill('#mc-subject', `QA notice ${stamp}`);
    await p.fill('#mc-body', 'Hi {name}, no {class} class this Saturday. – {tutor}');
    await p.waitForTimeout(800);
    t.expect(/will get it/.test(await text('#mc-preview')), 'No preview of who gets it');
    await p.click('#mc-send');
    await p.waitForURL(/message\?id=/, { timeout: 10000 });
    await p.waitForFunction(() => /Finished/.test(document.querySelector('.mc-progress')?.innerText ?? ''), null, { timeout: 30000 });
    t.expect(!(await p.locator('.modal.show').count()), 'A pop-up opened');
    t.expect(logLines() > before, 'No e-mail was sent (is the server running with MAIL_DRIVER=log?)');
  });
  await t.tc('TC-71', 'Messages', 'An empty message is refused with clear messages', ['Choose E-mail', 'Leave the subject and message empty', 'Press Send E-mail'], 'Subject and Message show what to fill in; nothing is sent', async () => {
    await t.open('tutor/message-center', 3000);
    await p.locator('.mc-tile', { hasText: 'E-mail' }).click();
    await p.waitForTimeout(400);
    await p.locator('.mc-check-all input').check();
    await p.click('#mc-send');
    await p.waitForTimeout(1500);
    t.expect(/message-center/.test(p.url()), 'Left the page');
    t.expect((await p.locator('.is-invalid').count()) >= 2, 'Fields not marked');
  });
  await t.tc('TC-72', 'Messages', 'WhatsApp from my phone: each person has an Open WhatsApp button', ['Choose WhatsApp (From your phone)', 'Write a message', 'Press Continue to WhatsApp', 'Press Open WhatsApp for the first person'], 'wa.me opens with the message; that person shows Sent', async () => {
    await t.open('tutor/message-center', 3000);
    await p.locator('.mc-check-all input').check();
    await p.fill('#mc-body', 'Hi {name}, QA WhatsApp test.');
    await p.click('#mc-send');
    await p.waitForURL(/message\?id=/, { timeout: 10000 });
    await p.waitForTimeout(2000);
    const link = p.locator('.mc-person a[href^="https://wa.me/"]').first();
    t.expect(await link.count(), 'No Open WhatsApp button');
    t.expect(/text=Hi%20/.test(await link.getAttribute('href')), 'The message is not in the WhatsApp link');
    const [popup] = await Promise.all([p.context().waitForEvent('page', { timeout: 5000 }).catch(() => null), link.click()]);
    if (popup) await popup.close();
    await p.waitForTimeout(1500);
    t.expect(/1 of \d+ opened/.test(await text('.mc-progress')), 'Not marked as sent');
  });
  await t.tc('TC-73', 'Messages', 'WhatsApp Settings shows the three ways; own number is locked on STARTER', ['Open Settings › WhatsApp'], 'From my phone is in use; My WhatsApp Business number says GROW plan with See plans; connecting is refused by the API', async () => {
    await t.open('tutor/whatsapp-settings', 3000);
    const body = await text();
    for (const s of ['From my phone', 'My WhatsApp Business number', 'ENLIXO WhatsApp number', 'In use', 'Send a test message']) t.expect(body.includes(s), `Missing "${s}"`);
    const r = await t.api('POST', '/tutor/whatsapp/connect', { phone_number_id: '109876543210987', token: 'x'.repeat(60), template: 'class_notice', lang: 'en' });
    t.expect(r.status === 402, `Connect answered ${r.status}`);
  });
  await t.tc('TC-74', 'Messages', 'Sent Messages lists what was sent', ['Open Message Center'], 'The table shows the e-mail and the WhatsApp message', async () => {
    await t.open('tutor/message-center', 3500);
    const hist = await text('#mc-history');
    t.expect(hist.includes(`QA notice ${stamp}`) && /WhatsApp/.test(hist), 'History is missing a message');
  });
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
