// End-to-end check of the demo database (bin/seed-demo.php) and the plan rules it shows:
//   every demo tutor signs in and sees their plan, STARTER cannot open pre-recorded classes, GROW can,
//   a GROW student sees pre-recorded classes and a STARTER student does not, the staff member signs in.
//   php bin/seed-demo.php reset; bin/dev-up.sh; node tests/e2e/demo-data.e2e.js   Results: results/demo-data.json
const path = require('path');
const fs = require('fs');
const { execFileSync } = require('child_process');
const { Harness } = require('./harness');

const BASE = process.argv[2] || 'http://127.0.0.1:8082';
const OUT = path.join(__dirname, 'results');
const PASSWORD = 'Demo#2026';
const sql = (q) => execFileSync('php', ['-r', `$c = require '${path.join(__dirname, '../../src/bootstrap.php')}'; $a = new Enlixo\\App($c); echo json_encode($a->db()->all(${JSON.stringify(q)}));`]).toString();

(async () => {
  const t = new Harness(BASE);
  t.shots = path.join(OUT, 'shots');
  fs.mkdirSync(t.shots, { recursive: true });
  await t.start();
  const p = () => t.page;
  const text = async (sel = 'body') => (await p().locator(sel).first().innerText().catch(() => '')).replace(/\s+/g, ' ');
  const tutorIn = async (email) => {
    await p().context().clearCookies();
    await t.open('tutor/sign-in', 800);
    await p().fill('#email', email);
    await p().fill('#password', PASSWORD);
    await p().click('button:has-text("Sign In")');
    await p().waitForURL(/dashboard/, { timeout: 15000 });
    await p().waitForTimeout(2500);
  };
  // a student / parent of the demo: the sign-in code is put straight into the row (WhatsApp cannot send here)
  const studentIn = async (area, number) => {
    await p().context().clearCookies();
    const table = area === 'portal' ? 'login_details' : 'parents';
    execFileSync('php', ['-r', `$c = require '${path.join(__dirname, '../../src/bootstrap.php')}'; $a = new Enlixo\\App($c);
      $a->db()->execute("UPDATE ${table} SET verification = '135790', reset_time = :t WHERE whatsapp = :w", ['t' => (new DateTime('now', new DateTimeZone('Asia/Colombo')))->format('Y-m-d H:i:s'), 'w' => '${number}']);`]);
    await p().route(`**/${area}/api/sendOtp.php`, (r) => r.fulfill({ contentType: 'application/json', body: '{"done":"ok"}' }));
    await t.open(`${area}/`, 800);
    await p().fill('#mobile', '0' + number.slice(3));
    await p().locator('#content-div button.custom-btn').first().click();
    await p().waitForSelector('.otp-digit', { timeout: 8000 });
    for (let i = 0; i < 6; i++) await p().fill(`.otp-digit[data-index="${i}"]`, '135790'[i]);
    await p().click('#verifyBtn');
    await p().waitForURL(/dashboard/, { timeout: 10000 });
    await p().unroute(`**/${area}/api/sendOtp.php`);
    await p().waitForTimeout(1500);
  };
  const classOf = (email) => JSON.parse(sql(`SELECT c.class_id FROM classes c JOIN users u ON u.user_id = c.tutor_id WHERE u.user_email = '${email}' ORDER BY c.class_id LIMIT 1`))[0]?.class_id;
  const studentOf = (email) => JSON.parse(sql(`SELECT ld.whatsapp FROM login_details ld JOIN students s ON s.login_details_login_details_id = ld.login_details_id JOIN users u ON u.user_id = s.users_user_id
      JOIN students_has_classes h ON h.students_student_id = s.student_id AND h.status_status_id = 1 WHERE u.user_email = '${email}' ORDER BY s.student_id LIMIT 1`))[0]?.whatsapp;

  for (const [id, email, plan] of [['DM-01', 'demo.free@demo.enlixo.lk', 'FREE'], ['DM-02', 'demo.starter@demo.enlixo.lk', 'STARTER'], ['DM-03', 'demo.grow@demo.enlixo.lk', 'GROW'], ['DM-04', 'demo.custom@demo.enlixo.lk', 'CUSTOM']]) {
    await t.tc(id, 'Demo tutors', `${plan} demo tutor signs in and sees the data`, [`Sign in as ${email}`, 'Open the dashboard and Student Fees'], `Menu card shows ${plan}; classes, students and fees are there`, async () => {
      await t.useViewport({ width: 1440, height: 900 });
      await tutorIn(email);
      t.expect(new RegExp(`${plan} plan`).test(await text('.ex-plan-card')), `Plan card: ${await text('.ex-plan-card')}`);
      await t.open('tutor/fees', 3500);
      t.expect(!/No fees|sample data/i.test(await text('#real-list')) && /LKR/.test(await text('#real-list')), 'Student Fees empty');
      await p().screenshot({ path: path.join(t.shots, `demo-${plan.toLowerCase()}-fees.png`) });
    });
  }

  await t.tc('DM-05', 'Plans', 'STARTER cannot open pre-recorded classes; GROW can', ['STARTER: open a class\'s Pre-Recorded page', 'GROW: the same'], 'STARTER goes to the Upgrade page; GROW sees the page', async () => {
    await tutorIn('demo.starter@demo.enlixo.lk');
    await t.open(`tutor/class-pre-recordings?class-id=${classOf('demo.starter@demo.enlixo.lk')}`, 2500);
    t.expect(/upgrade/.test(p().url()), `STARTER stayed on ${p().url()}`);
    await tutorIn('demo.grow@demo.enlixo.lk');
    await t.open(`tutor/class-pre-recordings?class-id=${classOf('demo.grow@demo.enlixo.lk')}`, 2500);
    t.expect(/class-pre-recordings/.test(p().url()), `GROW sent to ${p().url()}`);
  });

  await t.tc('DM-06', 'Plans', 'Students see pre-recorded classes only from a GROW / CUSTOM tutor', ['GROW student: Pre Recorded Classes', 'STARTER student: the same'], 'GROW student sees cards; STARTER student sees none', async () => {
    await studentIn('portal', studentOf('demo.grow@demo.enlixo.lk'));
    await t.open('portal/pre-recorded-classes', 2500);
    t.expect((await p().locator('.sr-card').count()) > 0, 'GROW student sees no pre-recorded class');
    await t.open('portal/tutes', 2500);
    t.expect((await p().locator('.sr-card').count()) > 0, 'No tutes for the GROW student');
    await p().screenshot({ path: path.join(t.shots, 'demo-grow-student-tutes.png') });
    await studentIn('portal', studentOf('demo.starter@demo.enlixo.lk'));
    await t.open('portal/pre-recorded-classes', 2500);
    t.expect((await p().locator('.sr-card').count()) === 0, 'STARTER student sees pre-recorded classes');
  });

  await t.tc('DM-07', 'Staff', 'The GROW staff member signs in', ['Sign in as assistant.sp@demo.enlixo.lk'], 'Dashboard opens as staff', async () => {
    await tutorIn('assistant.sp@demo.enlixo.lk');
    t.expect(/Staff ·/.test(await text('.ex-sidenav')), 'Not signed in as staff');
  });

  await t.tc('DM-08', 'Parents', 'A STARTER parent signs in', ['Sign in on the parent portal with a demo parent number'], 'Parent dashboard opens', async () => {
    const parent = JSON.parse(sql("SELECT p.whatsapp FROM parents p JOIN parents_has_students ps ON ps.parents_parent_id = p.parent_id JOIN students s ON s.student_id = ps.students_student_id JOIN users u ON u.user_id = s.users_user_id WHERE u.user_email = 'demo.starter@demo.enlixo.lk' LIMIT 1"))[0].whatsapp;
    await studentIn('parent', parent);
    t.expect(/dashboard/.test(p().url()), `Parent at ${p().url()}`);
  });

  await t.finish(path.join(OUT, 'demo-data.json'));
})();
