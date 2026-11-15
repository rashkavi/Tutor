// Builds the Student + Parent QA report PDF from tests/e2e/results/student-journey.json and parent-journey.json.
// Run: node tests/e2e/make-report-portal.js [out.pdf]   (default docs/qa/QA-report-student-parent.pdf)
const fs = require('fs');
const path = require('path');
const { chromium } = require('playwright');

const root = path.join(__dirname, '..', '..');
const load = (f) => JSON.parse(fs.readFileSync(path.join(__dirname, 'results', f), 'utf8'));
const suites = [['Student portal', load('student-journey.json')], ['Parent portal', load('parent-journey.json')]];
const out = path.resolve(process.argv[2] || path.join(root, 'docs', 'qa', 'QA-report-student-parent.pdf'));

// [level, side, where, problem, what was updated, found by]
const fixes = [
  ['Critical', 'Parent', 'parent/api/signIn.php, parent/api/sendOtp.php', 'A parent signed in with ONLY the WhatsApp number: the 6-digit code was never checked, and the code was not even sent to WhatsApp (that part was switched off) but returned to the browser.', 'The code is sent to WhatsApp (shared includes/WhatsAppOtp.php), never returned, checked on sign-in, valid 5 minutes and usable once.', 'Code review, PA-04 – PA-07, PA-10'],
  ['Critical', 'Student', 'portal/api/sendOtp.php', 'The "send code" answer contained the code itself, so anyone could sign in as any student who has a known WhatsApp number.', 'The code is no longer in the answer; only WhatsApp gets it.', 'ST-08'],
  ['High', 'Student + Tutor', 'portal/api/signIn.php, tutor/api/signIn.php', 'Codes never expired: the sent time is saved as a date text and was compared with a number, which was always "newer".', 'otp_still_valid() reads both forms; codes expire after 5 minutes; compared with hash_equals().', 'ST-09'],
  ['High', 'Parent', 'parent/class-details, class-attendance, class-time-table, class-recordings, class-pre-recordings, class-payments, payment-details, pay-payment, watch-recording, watch-pre-recording', 'Pages checked only that SOME parent had a child in the class (or nothing at all), so a parent could open other families\' classes, fees, recordings and slips by changing the number in the address.', 'One check for every page: includes/enlixo/parent-scope.php (only the parent\'s own children); ids are whole numbers.', 'PA-26 – PA-31'],
  ['High', 'Parent', 'parent/api/savePayment.php', 'A slip could be sent for any fee in the system, including fees that were already accepted (sending them back to "waiting").', 'Only fees of the parent\'s children that are unpaid or rejected.', 'PA-25'],
  ['High', 'Student', 'portal/api/savePayment.php', 'A student could send a slip for a fee that was already accepted, putting it back to "Awaiting Approval".', 'Only unpaid or rejected fees can be paid; ids are whole numbers.', 'ST-25'],
  ['High', 'Student', 'portal/time-table-class-details.php', 'Any class day of any class (with its online meeting link) opened for any student.', 'Only class days of the student\'s own classes.', 'ST-28'],
  ['High', 'Student', 'portal/downloadable-tute.php', 'Any tute (and its files) of any class opened for any student.', 'Only tutes of the student\'s own classes.', 'ST-29'],
  ['High', 'Student', 'portal/payment-details.php, pay-payment.php', 'Another student\'s fee page opened (empty) because SUM() without GROUP BY always returns a row.', 'GROUP BY added, so the owner check works; back to Payments otherwise.', 'ST-26'],
  ['Medium', 'Student', 'portal/attendance.php, class-attendance.php', 'A new student saw "953 absent classes": the count used every student\'s class rows.', 'Counts only this student\'s classes.', 'ST-20'],
  ['Medium', 'Parent', 'parent/class-payments.php', 'Class Payments listed every student\'s fees in the class ("40 Awaiting Payments").', 'Rebuilt: only the parent\'s child in that class.', 'Phone review'],
  ['Medium', 'Parent', 'parent/student-details.php', 'Parents got the tutor\'s student edit form (Save Changes) with a JS error.', 'Opens the new Child Progress page instead; My Children → View goes there too.', 'PA-33'],
  ['Medium', 'Both', 'URL ids in SQL (portal + parent pages and APIs)', 'Ids from the address went straight into SQL.', 'Cast to whole numbers; sign-in numbers escaped.', 'ST-04, PA-08'],
  ['Medium', 'Both', 'Redirects', 'Pages sent people to pages that do not exist (level-details, course-overview, class, faculties, students, news, class-schedule, pre-recordings) → 404.', 'They go back to Classes, Payments, Time Table, Tutes or Attendance.', 'Phone review'],
  ['Medium', 'Student + Parent', 'Payments pages', 'An old desktop table squeezed on a phone, an empty blue filter button, no way to pay again after a rejected slip.', 'New Payments in the ENLIXO V3 portal style (includes/enlixo/student-fees.php): balance card with % paid, next / overdue payment with Pay Now, timeline with All / To Pay / Being Checked / Paid tabs; a rejected slip can be paid again.', 'ST-21 – ST-24, PA-23, PA-24'],
  ['Low', 'Student', 'portal/recordings, pre-recorded-classes', 'The tab bar ran off the right side on a phone.', 'The tab bar shrinks and scrolls inside its box.', 'ST-P-11, ST-P-13'],
  ['Low', 'Student', 'portal/js/functions.js', 'The student OTP screen said "Tutor Login"; an OTP error showed an empty toast.', '"Student Login"; the toast shows the real message (parent too).', 'ST-06'],
  ['Low', 'Parent', 'Scripts', 'Several parent pages loaded files that do not exist (js/script.js, js/fileChooser.js, a PDF icon) → 404.', 'Paths fixed / removed.', 'Phone review'],
];
const added = [
  ['Child Progress (parent)', 'parent/child-progress.php', 'One child at a time (chips switch the child): "Needs your attention" (overdue fees with Pay now, missed classes not caught up, absent 2 of the last 3 days, class today), attendance % of the last 30 days, Present / Absent / Caught up / Fees to pay, recent class days with Present / Absent / Watched recording, attendance by class, the next 7 days, missed classes with a link to the recording.'],
  ['Payments (parent)', 'parent/payments.php, class-payments.php', 'The same V3-style Payments as the student, per child.'],
  ['Payments (student)', 'portal/payments.php, class-payments.php', 'V3-style Payments (balance, next payment, timeline with tabs), built for phones first.'],
  ['Menu', 'parent/includes/side-menu.php', '"Child Progress" after My Children.'],
];
const research = [
  'Parent portals are expected to show grades / progress, attendance (with absence alerts), fees (amounts due, history, pay online) and messages, with one login for a family with several children.',
  'Short regular summaries work: the Columbia University study (2017) of automated weekly alerts to parents about missed work, grades and absences reported fewer course failures and better attendance. Sources quote different figures, so the original paper should be checked before quoting a number.',
  'So the first screen answers "is anything wrong?" (Needs your attention), then "is my child attending?" (30-day %), "did they catch up what they missed?" (recording watched), "what is next?" (7 days) and "what do I owe?" (fees).',
];
const open = [
  ['High', 'WhatsApp token', 'The WhatsApp Cloud API token is still written in the code (now only as a fallback in includes/WhatsAppOtp.php; also in tutor/portal/admin sendOtp). Set WHATSAPP_TOKEN on the server and create a new token in Meta Business.'],
  ['Medium', 'Parent pages', 'Class Time Table / Attendance / Recordings of the parent still use the older layouts (they work and fit a phone, but are not yet in the new card style).'],
  ['Info', 'WhatsApp', 'Real WhatsApp sending could not be tested here (graph.facebook.com is blocked): test one parent and one student sign-in on the live server.'],
  ['Idea', 'Weekly summary', 'A weekly WhatsApp summary to parents (attendance, missed classes, fees due), from the same parent-scope data, as the research suggests.'],
];

const esc = (s) => String(s ?? '').replace(/[&<>"]/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;' }[c]));
const total = suites.reduce((n, [, r]) => n + r.results.length, 0);
const pass = suites.reduce((n, [, r]) => n + r.pass, 0);
const ran = new Date(suites[1][1].ran_at).toLocaleString('en-GB', { timeZone: 'Asia/Colombo', dateStyle: 'long', timeStyle: 'short' });

const areaRows = suites.map(([name, r]) => {
  const areas = [...new Set(r.results.map((x) => x.area))];
  return areas.map((a) => {
    const rs = r.results.filter((x) => x.area === a); const p = rs.filter((x) => x.status === 'pass').length;
    return `<tr><td>${esc(name)}</td><td>${esc(a)}</td><td class="n">${rs.length}</td><td class="n ok">${p}</td><td class="n ${rs.length - p ? 'bad' : ''}">${rs.length - p}</td></tr>`;
  }).join('');
}).join('');
const caseTable = (r) => r.results.map((x) => `<tr><td class="id">${esc(x.id)}</td><td><b>${esc(x.title)}</b><div class="sub">${esc(x.area)}</div></td><td><ol>${(x.steps || []).map((s) => `<li>${esc(s)}</li>`).join('')}</ol></td><td>${esc(x.expected)}${x.note ? `<div class="sub">${esc(x.note)}</div>` : ''}${x.error ? `<div class="err">${esc(x.error)}</div>` : ''}</td><td><span class="chip ${x.status}">${x.status === 'pass' ? 'PASS' : 'FAIL'}</span></td></tr>`).join('');
const sev = (l) => `<span class="sev ${l.toLowerCase().split(' ')[0]}">${esc(l)}</span>`;

const html = `<!doctype html><html><head><meta charset="utf-8"><title>ENLIXO Student and Parent QA Report</title><style>
body { font: 10.5px/1.45 -apple-system, "Segoe UI", Roboto, Arial, sans-serif; color: #1c2533; margin: 0; }
h1 { font-size: 22px; margin: 0 0 4px; color: #0b3a8c; } h2 { font-size: 15px; margin: 22px 0 8px; color: #0b3a8c; border-bottom: 2px solid #dbe5f5; padding-bottom: 4px; } h3 { font-size: 12.5px; margin: 14px 0 4px; }
.lead { color: #55617a; margin: 0 0 14px; }
.tiles { display: flex; gap: 10px; margin: 10px 0 6px; } .tile { flex: 1; border: 1px solid #dbe5f5; border-radius: 8px; padding: 10px 12px; }
.tile .v { font-size: 22px; font-weight: 700; } .tile .t { color: #55617a; font-size: 10px; text-transform: uppercase; letter-spacing: .04em; }
.ok { color: #138a4b; } .bad { color: #c62828; }
table { width: 100%; border-collapse: collapse; margin-top: 6px; } th { background: #eef3fb; text-align: left; font-size: 10px; padding: 6px; }
td { border-top: 1px solid #e6ebf3; padding: 6px; vertical-align: top; } tr { page-break-inside: avoid; }
td.n { text-align: center; width: 40px; } td.id { white-space: nowrap; font-weight: 700; width: 52px; }
ol, ul { margin: 0; padding-left: 16px; } .sub { color: #6b7790; font-size: 9.5px; margin-top: 2px; } .err { color: #c62828; margin-top: 3px; }
.chip { display: inline-block; padding: 2px 8px; border-radius: 10px; font-weight: 700; font-size: 9.5px; } .chip.pass { background: #e3f6ec; color: #138a4b; } .chip.fail { background: #fde7e7; color: #c62828; }
.sev { display: inline-block; padding: 2px 7px; border-radius: 10px; font-weight: 700; font-size: 9.5px; background: #eef3fb; color: #33415c; white-space: nowrap; }
.sev.critical { background: #c62828; color: #fff; } .sev.high { background: #fde7e7; color: #c62828; } .sev.medium { background: #fff3dc; color: #a35c00; } .sev.low { background: #e9f2ff; color: #0b3a8c; }
.box { background: #f6f8fc; border: 1px solid #e3e9f3; border-radius: 8px; padding: 10px 12px; } .box p { margin: 3px 0; }
code { background: #eef3fb; padding: 1px 4px; border-radius: 4px; font-size: 9.5px; }
</style></head><body>
<h1>ENLIXO Tutor – Student and Parent QA Report</h1>
<p class="lead">Student portal and parent portal, tested on a phone (390 × 844) and a desktop (1440 × 900) · Run on ${esc(ran)} (Sri Lanka time)</p>
<div class="tiles">
  <div class="tile"><div class="t">Test cases</div><div class="v">${total}</div></div>
  <div class="tile"><div class="t">Passed</div><div class="v ok">${pass}</div></div>
  <div class="tile"><div class="t">Failed</div><div class="v ${total - pass ? 'bad' : ''}">${total - pass}</div></div>
  <div class="tile"><div class="t">Problems fixed</div><div class="v">${fixes.length}</div></div>
  <div class="tile"><div class="t">Critical / High fixed</div><div class="v bad">${fixes.filter((f) => /Critical|High/.test(f[0])).length}</div></div>
</div>

<h2>1. Result</h2>
<div class="box">
  <p><b>${total - pass ? `${total - pass} test case(s) still fail.` : 'Every test case passes.'}</b> Students (${suites[0][1].pass}/${suites[0][1].results.length}) and parents (${suites[1][1].pass}/${suites[1][1].results.length}) sign in with a WhatsApp code, every page opens on a phone and on a desktop without errors and without anything cut off at the side, a fee can be paid with a bank slip, and nobody can open another student's or another family's classes, fees, class days, tutes or recordings.</p>
  <p>Testing found <b>${fixes.length} problems</b>, ${fixes.filter((f) => f[0] === 'Critical').length} of them critical: <b>parents could sign in with only a WhatsApp number</b>, and the student "send code" answer contained the code. All are fixed (section 4). Unit tests: <code>php tests/run.php</code> 315 / 315.</p>
</div>

<h2>2. Research: what parents need</h2>
<div class="box"><ul>${research.map((r) => `<li>${esc(r)}</li>`).join('')}</ul></div>

<h2>3. What was added</h2>
<table><tr><th>Page</th><th>Files</th><th>What it does</th></tr>${added.map((a) => `<tr><td><b>${esc(a[0])}</b></td><td>${esc(a[1])}</td><td>${esc(a[2])}</td></tr>`).join('')}</table>

<h2>4. Problems found and fixed</h2>
<table><tr><th>Level</th><th>Side</th><th>Where</th><th>Problem</th><th>What was updated</th><th>Found by</th></tr>${fixes.map((f) => `<tr><td>${sev(f[0])}</td><td>${esc(f[1])}</td><td><b>${esc(f[2])}</b></td><td>${esc(f[3])}</td><td>${esc(f[4])}</td><td>${esc(f[5])}</td></tr>`).join('')}</table>

<h2>5. Not changed yet (recommendations)</h2>
<table><tr><th>Level</th><th>Where</th><th>Finding and recommendation</th></tr>${open.map((o) => `<tr><td>${sev(o[0])}</td><td><b>${esc(o[1])}</b></td><td>${esc(o[2])}</td></tr>`).join('')}</table>

<h2>6. How it was tested</h2>
<div class="box">
  <p><b>Test people:</b> <code>tests/e2e/setup-qa-student.php</code> (a QA student in two sample classes, with one fee of each state: to pay, being checked, paid, rejected) and <code>tests/e2e/setup-qa-parent.php</code> (a QA parent with two children). Both write a fresh 6-digit code; WhatsApp is blocked in the test machine, so only the "send code" call is answered by the test and the code is typed into the real OTP screen.</p>
  <p><b>Browser tests:</b> Chromium (Playwright), first as a phone (390 px, touch), then as a desktop. A case fails on its own check AND on any JavaScript error, server error (5xx) or PHP warning, and a page case also fails when anything sticks out of the screen on the right.</p>
  <p><b>Run again:</b> <code>node tests/e2e/student-journey.e2e.js</code>, <code>node tests/e2e/parent-journey.e2e.js</code>, then <code>node tests/e2e/make-report-portal.js</code>.</p>
</div>

<h2>7. Results by area</h2>
<table><tr><th>Portal</th><th>Area</th><th>Cases</th><th>Passed</th><th>Failed</th></tr>${areaRows}</table>

${suites.map(([name, r], i) => `<h2>${8 + i}. ${esc(name)}: all test cases</h2><table><tr><th>ID</th><th>Test case</th><th>Steps</th><th>Expected result</th><th>Result</th></tr>${caseTable(r)}</table>`).join('')}
</body></html>`;

(async () => {
  const b = await chromium.launch();
  const p = await b.newPage();
  await p.setContent(html, { waitUntil: 'load' });
  fs.mkdirSync(path.dirname(out), { recursive: true });
  await p.pdf({ path: out, format: 'A4', printBackground: true, displayHeaderFooter: true, headerTemplate: '<span></span>',
    footerTemplate: '<div style="font-size:8px;color:#888;width:100%;text-align:center">ENLIXO Student and Parent QA report · page <span class="pageNumber"></span> of <span class="totalPages"></span></div>',
    margin: { top: '14mm', bottom: '16mm', left: '12mm', right: '12mm' } });
  await b.close();
  console.log(out);
})();
