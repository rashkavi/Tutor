// End-to-end QA of Student Fees on the REAL data (src/Fees/TutorFees.php, FeeController): overview, slips to check,
// approve -> receipt, reject with a reason, Record Payment (cash) -> receipt, the student's Fee Card, Fee Settings
// (a bank account is saved and logged), reminders by link, and the free card lock on plans without fees.advanced.
//   bin/dev-up.sh  then  node tests/e2e/fees-journey.e2e.js [http://127.0.0.1:8082]   Results: results/fees-journey.json
const path = require('path');
const fs = require('fs');
const { execFileSync } = require('child_process');
const { Harness } = require('./harness');

const BASE = process.argv[2] || 'http://127.0.0.1:8082';
const OUT = path.join(__dirname, 'results');
const php = (f, ...a) => execFileSync('php', [path.join(__dirname, f), ...a]).toString();

(async () => {
  php('setup-qa-tutor.php');
  php('setup-qa-fees.php');
  const t = new Harness(BASE);
  t.shots = path.join(OUT, 'shots');
  fs.mkdirSync(t.shots, { recursive: true });
  await t.start();
  const p = () => t.page;
  const text = async (sel = 'body') => (await p().locator(sel).first().innerText().catch(() => '')).replace(/\s+/g, ' ');
  const call = (method, url, body) => p().evaluate(async ([m, u, b]) => {
    const r = await fetch(`/api/v1${u}`, { method: m, headers: { 'Content-Type': 'application/json', Accept: 'application/json', 'X-Requested-With': 'enlixo' }, body: b ? JSON.stringify(b) : undefined });
    const txt = await r.text();
    let json = null;
    try { json = JSON.parse(txt); } catch { /* not json */ }
    return { status: r.status, json, txt };
  }, [method, url, body]);
  const snap = (n) => p().screenshot({ path: path.join(t.shots, `${n}.png`) });

  await t.tc('FE-01', 'Overview', 'Student Fees shows the real fees, not sample data', ['Sign in as the QA tutor', 'Open Finance → Student Fees'], 'No "Preview with sample data" note; the QA students are listed', async () => {
    await t.useViewport({ width: 1440, height: 900 });
    await t.open('tutor/sign-in', 800);
    await p().fill('#email', 'qa.tutor@enlixo.test');
    await p().fill('#password', 'QaTutor#2026');
    await p().click('button:has-text("Sign In")');
    await p().waitForURL(/dashboard/, { timeout: 15000 });
    await t.open('tutor/fees', 3500);
    const body = await text('#real-list');
    t.expect(!/sample data/i.test(body), 'Preview note still shown');
    const o = await call('GET', '/tutor/fees/overview');
    t.expect(o.status === 200 && o.json.data.classes.length > 0, `Overview: ${o.status}`);
    await snap('fees-overview');
  });

  let waiting = null;
  await t.tc('FE-02', 'Slips', 'Approve a slip: the fee is paid and a receipt is made', ['Open a fee with a slip to check', 'Approve'], 'Status Paid with an RC- receipt number; the receipt page opens', async () => {
    const s = await call('GET', '/tutor/fees/slips?per_page=5');
    t.expect(s.json.data.length >= 1, 'No slips waiting in the QA data');
    waiting = s.json.data.map((f) => f.id);
    await t.open(`tutor/fee-details?fee-id=${waiting[0]}`, 2500);
    await snap('fee-details');
    const a = await call('POST', `/tutor/fees/${waiting[0]}/approve`, {});
    t.expect(a.status === 200 && a.json.data.fee.status === 'paid' && /^RC-\d+$/.test(a.json.data.fee.receipt), `Approve: ${a.status} ${a.txt.slice(0, 160)}`);
    const r = await call('GET', `/tutor/fees/receipts/${a.json.data.receipt}`);
    t.expect(r.status === 200 && r.json.data.lines.length === 1, `Receipt: ${r.status}`);
    await t.open(`tutor/fee-receipt?receipt-id=${a.json.data.receipt}`, 2500);
    t.expect(/RC-\d+/.test(await text('.fe-receipt-no')), 'Receipt page without a number');
    await snap('fee-receipt');
  });

  await t.tc('FE-03', 'Slips', 'Reject needs a reason, then the student sees it', ['Reject a slip with no reason', 'Reject with a reason'], '400 asking for the reason; then status Slip Rejected with the reason', async () => {
    const id = waiting[1] ?? (await call('GET', '/tutor/fees/slips?per_page=1')).json.data[0]?.id;
    t.expect(id, 'No second slip');
    const bad = await call('POST', `/tutor/fees/${id}/reject`, { reason: '' });
    t.expect(bad.status === 422 || bad.status === 400, `Empty reason: ${bad.status}`);
    const ok = await call('POST', `/tutor/fees/${id}/reject`, { reason: 'The slip is not clear, please upload a clear photo', note: 'QA' });
    t.expect(ok.status === 200 && ok.json.data.fee.status === 'rejected' && /not clear/.test(ok.json.data.fee.reason), `Reject: ${ok.txt.slice(0, 160)}`);
    const again = await call('POST', `/tutor/fees/${id}/approve`, {});
    t.expect(again.status !== 200, 'A rejected slip could be approved again');
  });

  await t.tc('FE-04', 'Record payment', 'Record a cash payment on the page: receipt page with the number', ['Open Record Payment for a student who owes', 'Cash, today', 'Save & Give Receipt'], 'Receipt page with RC- number; the month is Paid', async () => {
    const u = (await call('GET', '/tutor/fees/unpaid?per_page=1')).json.data[0];
    t.expect(u, 'Nobody owes in the QA data');
    await t.open(`tutor/record-payment?student-id=${u.student.id}&months=${u.months[0].id}`, 3000);
    await p().locator('button[data-save]').last().click();
    await p().waitForURL(/fee-receipt/, { timeout: 10000 });
    await p().waitForTimeout(2000);
    t.expect(/RC-\d+/.test(await text('.fe-receipt-no')), 'No receipt number');
    const f = await call('GET', `/tutor/fees/${u.months[0].id}`);
    t.expect(f.json.data.status === 'paid' && f.json.data.method === 'Cash', `Fee: ${f.json.data.status} ${f.json.data.method}`);
    t.expect(f.json.data.history.some((h) => /Receipt RC-/.test(h.what)), 'No history line');
  });

  await t.tc('FE-05', 'Record payment', 'Record Payment checks the form', ['Save with no month, a future date and a bank deposit without reference'], 'Errors for months, date and reference', async () => {
    const s = (await call('GET', '/tutor/fees/students')).json.data[0];
    const r = await call('POST', '/tutor/fees/payments', { student: s.id, items: [], method: 'Bank deposit', paid_on: '2999-01-01', reference: '' });
    const f = r.json?.error?.details?.fields ?? r.json?.error?.details ?? {};
    t.expect(f.items && f.paid_on && f.reference, `Fields: ${JSON.stringify(f)}`);
  });

  await t.tc('FE-06', 'Fee Card', "A student's Fee Card shows their months and receipts", ['Open a student Fee Card'], 'Classes with months, receipts listed, totals', async () => {
    const s = (await call('GET', '/tutor/fees/receipts?per_page=1')).json.data[0];
    t.expect(s, 'No receipt yet');
    const c = await call('GET', `/tutor/students/${s.student.id}/fees`);
    t.expect(c.status === 200 && c.json.data.classes.length && c.json.data.receipts.length, `Card: ${c.status}`);
    await t.open(`tutor/student-fees?student-id=${s.student.id}`, 3000);
    t.expect(/RC-\d+/.test(await text('#real-list')), 'Receipts not on the Fee Card page');
    await snap('fee-card');
  });

  await t.tc('FE-07', 'Settings', 'Fee Settings: a bank account is saved and logged', ['Add a bank account without a number', 'Add it with a number'], 'Error for the number; then saved and in the activity log', async () => {
    const bad = await call('PUT', '/tutor/fee-settings', { part: 'banks', bank: { bank: 'QA Bank', number: '' }, summary: 'x' });
    t.expect(bad.status === 422 || bad.status === 400, `No number: ${bad.status}`);
    const ok = await call('PUT', '/tutor/fee-settings', { part: 'banks', bank: { bank: 'QA Bank', branch: 'Maharagama', name: 'Qa Tutor', number: '8001 0000 1111', on: true }, summary: 'Added bank account QA Bank' });
    t.expect(ok.status === 200, `Save: ${ok.status}`);
    const g = (await call('GET', '/tutor/fee-settings')).json.data;
    t.expect(g.settings.banks.some((b) => b.bank === 'QA Bank') && g.log[0].what === 'Added bank account QA Bank', 'Not saved / logged');
    await t.open('tutor/fee-settings', 3000);
    await snap('fee-settings');
  });

  await t.tc('FE-08', 'Plans', 'Free card / discount needs the plan that has it; reminders by link work', ['STARTER tutor gives a free card', 'Send a reminder by WhatsApp link'], '402 feature_locked for the free card; the link reminder is saved', async () => {
    const u = (await call('GET', '/tutor/fees/unpaid?per_page=1')).json.data[0];
    const c = await call('POST', '/tutor/fees/concessions', { enrolment: u?.id ?? 1, type: 'free', from: '2026-01', reason: 'QA' });
    t.expect(c.status === 402, `Concession on STARTER: ${c.status}`);
    if (u) {
      const r = await call('POST', '/tutor/fees/reminders', { enrolments: [u.id], channel: 'link' });
      t.expect(r.status === 200 && r.json.data.sent === 1, `Reminder: ${r.txt.slice(0, 120)}`);
      const again = (await call('GET', '/tutor/fees/unpaid?per_page=50')).json.data.find((x) => x.id === u.id);
      t.expect(again?.last_reminder, 'Last reminder not kept');
    }
  });

  await t.tc('FE-09', 'Layout', 'Student Fees on a phone: no sideways scroll', ['Open Student Fees on a 390 px phone'], 'Page fits the screen', async () => {
    await t.useViewport({ width: 390, height: 844 });
    await t.open('tutor/sign-in', 800);
    await p().fill('#email', 'qa.tutor@enlixo.test');
    await p().fill('#password', 'QaTutor#2026');
    await p().click('button:has-text("Sign In")');
    await p().waitForURL(/dashboard/, { timeout: 15000 });
    await t.open('tutor/fees', 3500);
    const over = await p().evaluate(() => document.documentElement.scrollWidth - window.innerWidth);
    t.expect(over <= 1, `Overflow ${over}px`);
    await snap('fees-phone');
  });

  await t.finish(path.join(OUT, 'fees-journey.json'));
})();
