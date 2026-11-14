// Tiny end-to-end test harness on top of Playwright (no test runner needed).
// Each test case: id, area, title, steps, expected, fn(ctx). While a case runs, the harness collects JS errors,
// server errors (HTTP 5xx from this site) and PHP warnings printed into pages, and the case fails on any of them.
const fs = require('fs');
const path = require('path');
const { chromium } = require('playwright');

class Harness {
  constructor(base) {
    this.base = base.replace(/\/$/, '');
    this.results = [];
    this.host = new URL(this.base).host;
  }

  async start() {
    this.browser = await chromium.launch();
    this.ctx = await this.browser.newContext({ viewport: { width: 1440, height: 900 }, locale: 'en-US' });
    this.page = await this.ctx.newPage();
    this.reset();
    this.page.on('pageerror', (e) => this.jsErrors.push(e.message));
    this.page.on('response', async (r) => {
      const u = new URL(r.url());
      if (u.host !== this.host) return;
      if (r.status() >= 500) this.serverErrors.push(`${r.status()} ${u.pathname}`);
      const type = r.headers()['content-type'] ?? '';
      if (/text\/html|application\/json/.test(type)) {
        const body = await r.text().catch(() => '');
        const m = body.match(/<b>(Warning|Notice|Deprecated|Fatal error|Parse error)<\/b>:?\s*([^<]{0,160})|Uncaught [A-Za-z\\]+(?:Exception|Error)[^<]{0,120}/);
        if (m) this.phpWarnings.push(`${u.pathname}: ${m[0].replace(/<\/?b>/g, '').trim()}`);
      }
    });
  }

  reset() { this.jsErrors = []; this.serverErrors = []; this.phpWarnings = []; }

  url(p) { return `${this.base}/${p.replace(/^\//, '')}`; }

  async open(p, wait = 1800) {
    const res = await this.page.goto(this.url(p), { waitUntil: 'domcontentloaded' });
    await this.page.waitForTimeout(wait);
    return res;
  }

  /** fails the case when the page had JS errors, 5xx answers or PHP warnings */
  clean(extra = '') {
    const problems = [...this.jsErrors.map((e) => `JS error: ${e}`), ...this.serverErrors.map((e) => `Server error: ${e}`), ...this.phpWarnings.map((e) => `PHP: ${e}`)];
    if (problems.length) throw new Error(`${extra ? `${extra}: ` : ''}${problems.slice(0, 4).join(' | ')}`);
  }

  async tc(id, area, title, steps, expected, fn) {
    this.reset();
    const t0 = Date.now();
    let status = 'pass';
    let error = null;
    let note = null;
    try {
      const r = await fn(this);
      if (typeof r === 'string') note = r;
      this.clean();
    } catch (e) {
      status = 'fail';
      error = String(e.message || e).split('\n')[0].slice(0, 400);
      await this.page.screenshot({ path: path.join(this.shots, `${id}.png`) }).catch(() => {});
    }
    this.results.push({ id, area, title, steps, expected, status, error, note, ms: Date.now() - t0 });
    console.log(`${status === 'pass' ? 'PASS' : 'FAIL'}  ${id}  ${title}${error ? `\n      ${error}` : ''}`);
  }

  expect(cond, msg) { if (!cond) throw new Error(msg); }

  async api(method, p, body) {
    return this.page.evaluate(async ([m, url, b]) => {
      const r = await fetch(url, { method: m, headers: { Accept: 'application/json', 'Content-Type': 'application/json', 'X-Requested-With': 'enlixo' }, body: b ? JSON.stringify(b) : undefined });
      let json = null;
      try { json = await r.json(); } catch { /* not json */ }
      return { status: r.status, json };
    }, [method, this.url(`api/v1${p}`), body]);
  }

  async finish(file) {
    await this.browser.close();
    fs.mkdirSync(path.dirname(file), { recursive: true });
    const pass = this.results.filter((r) => r.status === 'pass').length;
    fs.writeFileSync(file, JSON.stringify({ ran_at: new Date().toISOString(), base: this.base, pass, fail: this.results.length - pass, results: this.results }, null, 1));
    console.log(`\n${pass} passed, ${this.results.length - pass} failed. Results: ${file}`);
    return this.results.length - pass;
  }
}

module.exports = { Harness };
