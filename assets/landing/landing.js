// ENLIXO Tutor landing page (index.php): reveal on scroll, counters, word rotator, 3D tilt, star field,
// nav, sign-in menu, role tabs, monthly / yearly prices. Everything stops for "reduce motion".
(() => {
  const reduce = matchMedia('(prefers-reduced-motion: reduce)').matches;
  const $ = (s, r = document) => r.querySelector(s);
  const $$ = (s, r = document) => [...r.querySelectorAll(s)];
  const fmt = (n) => Math.round(n).toLocaleString('en-US');

  // ---------- nav: glass after the hero, active link, mobile menu, sign-in menu ----------
  const nav = $('#lp-nav');
  const onScroll = () => nav.classList.toggle('scrolled', scrollY > 40);
  onScroll();
  addEventListener('scroll', onScroll, { passive: true });

  const burger = $('#lp-burger');
  const mobile = $('#lp-mobile');
  burger?.addEventListener('click', () => {
    const open = mobile.hidden;
    mobile.hidden = !open;
    burger.setAttribute('aria-expanded', String(open));
    nav.classList.add('scrolled');
  });
  $$('#lp-mobile a[href^="#"]').forEach((a) => a.addEventListener('click', () => { mobile.hidden = true; burger.setAttribute('aria-expanded', 'false'); }));

  const signin = $('.lp-signin');
  const signBtn = $('#lp-signin-btn');
  const setSign = (open) => { signin.classList.toggle('open', open); signBtn.setAttribute('aria-expanded', String(open)); };
  signBtn?.addEventListener('click', (e) => { e.stopPropagation(); setSign(!signin.classList.contains('open')); });
  document.addEventListener('click', (e) => { if (!signin.contains(e.target)) setSign(false); });
  document.addEventListener('keydown', (e) => { if (e.key === 'Escape') setSign(false); });

  const links = $$('.lp-links a');
  const sections = links.map((a) => $(a.getAttribute('href'))).filter(Boolean);
  const spy = new IntersectionObserver((entries) => entries.forEach((en) => {
    if (en.isIntersecting) links.forEach((a) => a.classList.toggle('on', a.getAttribute('href') === `#${en.target.id}`));
  }), { rootMargin: '-45% 0px -50% 0px' });
  sections.forEach((s) => spy.observe(s));

  // ---------- counters ----------
  const count = (el) => {
    const to = Number(el.dataset.count);
    if (reduce) { el.textContent = fmt(to); return; }
    const t0 = performance.now();
    const dur = 1600;
    const step = (t) => {
      const p = Math.min(1, (t - t0) / dur);
      el.textContent = fmt(to * (1 - (1 - p) ** 4));
      if (p < 1) requestAnimationFrame(step);
    };
    requestAnimationFrame(step);
  };

  // ---------- reveal on scroll (staggered inside a group) ----------
  const reveal = new IntersectionObserver((entries) => entries.forEach((en) => {
    if (!en.isIntersecting) return;
    const el = en.target;
    el.classList.add('in');
    $$('[data-count]', el).forEach(count);
    reveal.unobserve(el);
  }), { threshold: 0.15, rootMargin: '0px 0px -40px 0px' });
  $$('[data-reveal]').forEach((el) => {
    const sibs = $$(':scope > [data-reveal]', el.parentElement);
    el.style.setProperty('--rd', `${Math.min(sibs.indexOf(el), 6) * 80}ms`);
    reveal.observe(el);
  });
  ['#lp-chaos', '#lp-steps'].forEach((s) => { const el = $(s); if (el) reveal.observe(el); });
  setTimeout(() => $$('.lp-mock [data-count]').forEach(count), 500);

  // ---------- hero word rotator ----------
  const words = ['like a pro.', 'in Sinhala.', 'in Tamil.', 'from your phone.', 'without Excel.'];
  const rot = $('#lp-rot');
  if (rot && !reduce) {
    let i = 0;
    setInterval(() => {
      rot.classList.add('out');
      setTimeout(() => { i = (i + 1) % words.length; rot.textContent = words[i]; rot.classList.remove('out'); }, 350);
    }, 2600);
  }

  // ---------- mouse: hero spotlight, mock-up tilt, card glow + tilt ----------
  const hero = $('.lp-hero');
  const mock = $('#lp-mock');
  const fine = matchMedia('(pointer: fine)').matches;
  if (!reduce && fine) {
    hero.addEventListener('pointermove', (e) => {
      const r = hero.getBoundingClientRect();
      const x = (e.clientX - r.left) / r.width;
      const y = (e.clientY - r.top) / r.height;
      hero.style.setProperty('--mx', `${x * 100}%`);
      hero.style.setProperty('--my', `${y * 100}%`);
      if (mock && innerWidth > 640) mock.style.transform = `rotateY(${-14 + (x - 0.5) * 12}deg) rotateX(${8 - (y - 0.5) * 10}deg) rotateZ(1deg)`;
    });
    hero.addEventListener('pointerleave', () => { if (mock) mock.style.transform = ''; });

    $$('[data-tilt]').forEach((card) => {
      card.addEventListener('pointermove', (e) => {
        const r = card.getBoundingClientRect();
        const x = (e.clientX - r.left) / r.width;
        const y = (e.clientY - r.top) / r.height;
        card.style.setProperty('--tx', `${x * 100}%`);
        card.style.setProperty('--ty', `${y * 100}%`);
        const lift = card.classList.contains('lp-plan-pop') ? -16 : -4;
        card.style.transform = `perspective(900px) rotateX(${(0.5 - y) * 5}deg) rotateY(${(x - 0.5) * 6}deg) translateY(${lift}px)`;
      });
      card.addEventListener('pointerleave', () => { card.style.transform = ''; });
    });
  }

  // ---------- star field ----------
  const canvas = $('#lp-stars');
  if (canvas && !reduce) {
    const ctx = canvas.getContext('2d');
    let w = 0; let h = 0; let stars = [];
    const size = () => {
      const dpr = Math.min(devicePixelRatio || 1, 2);
      w = canvas.clientWidth; h = canvas.clientHeight;
      canvas.width = w * dpr; canvas.height = h * dpr;
      ctx.setTransform(dpr, 0, 0, dpr, 0, 0);
      stars = Array.from({ length: Math.round((w * h) / 9000) }, () => ({ x: Math.random() * w, y: Math.random() * h, r: Math.random() * 1.3 + 0.2, v: Math.random() * 0.25 + 0.05, p: Math.random() * Math.PI * 2 }));
    };
    size();
    addEventListener('resize', size);
    let running = true;
    new IntersectionObserver(([en]) => { running = en.isIntersecting; if (running) requestAnimationFrame(draw); }).observe(canvas);
    function draw(t) {
      if (!running) return;
      ctx.clearRect(0, 0, w, h);
      for (const s of stars) {
        s.y -= s.v; if (s.y < -2) { s.y = h + 2; s.x = Math.random() * w; }
        ctx.globalAlpha = 0.35 + Math.sin(t / 900 + s.p) * 0.3;
        ctx.fillStyle = '#cfe0ff';
        ctx.fillRect(s.x, s.y, s.r, s.r);
      }
      requestAnimationFrame(draw);
    }
  }

  // ---------- role tabs (arrow keys too) ----------
  const tabs = $$('.lp-tabs [role=tab]');
  const ink = $('.lp-tab-ink');
  const moveInk = (t) => { if (!ink || !t) return; ink.style.width = `${t.offsetWidth}px`; ink.style.transform = `translateX(${t.offsetLeft - 4}px)`; };
  const pick = (t, focus = false) => {
    tabs.forEach((x) => {
      const on = x === t;
      x.classList.toggle('on', on);
      x.setAttribute('aria-selected', String(on));
      x.tabIndex = on ? 0 : -1;
      const panel = document.getElementById(x.getAttribute('aria-controls'));
      panel.hidden = !on;
      panel.classList.toggle('on', on);
    });
    moveInk(t);
    if (focus) t.focus();
  };
  tabs.forEach((t, i) => {
    t.addEventListener('click', () => pick(t));
    t.addEventListener('keydown', (e) => {
      if (e.key === 'ArrowRight') pick(tabs[(i + 1) % tabs.length], true);
      if (e.key === 'ArrowLeft') pick(tabs[(i - 1 + tabs.length) % tabs.length], true);
    });
  });
  addEventListener('load', () => moveInk($('.lp-tabs .on')));
  addEventListener('resize', () => moveInk($('.lp-tabs .on')));
  moveInk($('.lp-tabs .on'));
  if (!reduce && tabs.length) {
    let auto = setInterval(() => { const i = tabs.findIndex((t) => t.classList.contains('on')); pick(tabs[(i + 1) % tabs.length]); }, 6000);
    $('#roles')?.addEventListener('pointerdown', () => clearInterval(auto), { once: true });
    tabs.forEach((t) => t.addEventListener('click', () => { clearInterval(auto); auto = null; }));
  }

  // ---------- "how many students?" calculator: FREE up to the free limit, then the cheapest plan ----------
  const calc = $('#lp-calc');
  if (calc) {
    const plans = JSON.parse(calc.dataset.plans || '[]');
    const freeMax = Number(calc.dataset.free) || 50;
    const range = $('#lp-calc-range');
    const out = $('#lp-calc-n');
    const planEl = $('#lp-calc-plan');
    const priceEl = $('#lp-calc-price');
    const perEl = $('#lp-calc-per');
    const note = $('#lp-calc-note');
    const cta = $('#lp-calc-cta');
    let last = '';
    const best = (n) => {
      if (n <= freeMax) return { key: 'free', name: 'FREE', cost: 0 };
      let pick = null;
      for (const p of plans) {
        if (p.key === 'free' || p.limit === null) continue;
        const extra = Math.max(0, n - p.limit);
        if (extra && !p.pack_price) continue;
        const packs = Math.ceil(extra / (p.pack_size || 50));
        const cost = p.monthly + packs * (p.pack_price || 0);
        if (!pick || cost < pick.cost) pick = { key: p.key, name: p.name, cost, packs };
      }
      return pick ?? { key: 'pro', name: 'PRO', cost: null };
    };
    const update = () => {
      const n = Number(range.value);
      out.textContent = n;
      range.style.setProperty('--fill', `${((n - range.min) / (range.max - range.min)) * 100}%`);
      const b = best(n);
      const free = b.key === 'free';
      calc.classList.toggle('is-free', free);
      planEl.textContent = b.name;
      priceEl.textContent = b.cost === null ? 'Custom' : `LKR ${fmt(b.cost)}`;
      perEl.textContent = b.cost === null ? 'we send you a price' : 'a month';
      note.textContent = free ? `Free while you teach up to ${freeMax} students. No card, no invoices.`
        : b.cost === null ? 'Over the PRO limit: our team gives you a price for your class.'
          : `${b.packs ? `Includes ${b.packs} extra pack${b.packs > 1 ? 's' : ''} of 50 students. ` : ''}Starts with a free trial.`;
      cta.href = `${calc.dataset.signup}?plan=${b.key}`;
      cta.firstChild.textContent = free ? 'Start with FREE ' : `Start with ${b.name} `;
      if (b.key !== last) { [planEl, priceEl].forEach((x) => { x.classList.remove('bump'); void x.offsetWidth; x.classList.add('bump'); }); last = b.key; }
    };
    range.addEventListener('input', update);
    update();
  }


  // ---------- language demo: the same screen in Sinhala, Tamil and English ----------
  const demo = $('#lp-lang-demo');
  if (demo) {
    const W = {
      dashboard: ['උපකරණ පුවරුව', 'முகப்பு', 'Dashboard'],
      classes: ['පන්ති', 'வகுப்புகள்', 'Classes'],
      timetable: ['කාල සටහන', 'நேர அட்டவணை', 'Time Table'],
      attendance: ['පැමිණීම', 'வருகை', 'Attendance'],
      payments: ['ගෙවීම්', 'கட்டணங்கள்', 'Payments'],
      recordings: ['පටිගත කිරීම්', 'பதிவுகள்', 'Recordings'],
      hello: ['ආයුබෝවන්, කවින්දි', 'வணக்கம், கவிந்தி', 'Hello, Kavindi'],
      today: ['අද පන්ති 2ක්', 'இன்று 2 வகுப்புகள்', '2 classes today'],
      present: ['පැමිණ ඇත', 'வருகை', 'Present'],
      students: ['සිසුන්', 'மாணவர்கள்', 'Students'],
      classes3: ['පන්ති 3', '3 வகுப்புகள்', '3 classes'],
      next: ['ඊළඟ පන්තිය', 'அடுத்த வகுப்பு', 'Next class'],
      join: ['එක්වන්න', 'சேரவும்', 'Join'],
      fees: ['ගෙවිය යුතු ගාස්තු', 'செலுத்த வேண்டிய கட்டணம்', 'Fees to pay'],
      paynow: ['දැන් ගෙවන්න', 'இப்போது செலுத்தவும்', 'Pay now'],
      tutes: ['නිබන්ධන', 'குறிப்புகள்', 'Tutes'],
      new: ['අලුත්', 'புதியது', 'New'],
      watch: ['නරඹන්න', 'பார்க்கவும்', 'Watch'],
    };
    const idx = { si: 0, ta: 1, en: 2 };
    const btns = $$('.lp-ld-switch button', demo);
    const ink = $('.lp-ld-ink', demo);
    const app = $('.lp-ld-app', demo);
    const moveLdInk = (b) => { if (b && ink) { ink.style.width = `${b.offsetWidth}px`; ink.style.transform = `translateX(${b.offsetLeft - 4}px)`; } };
    const setLang = (lang) => {
      const i = idx[lang];
      btns.forEach((b) => { const on = b.dataset.lang === lang; b.classList.toggle('on', on); b.setAttribute('aria-selected', String(on)); });
      moveLdInk(btns.find((b) => b.dataset.lang === lang));
      app.setAttribute('lang', lang);
      app.classList.add('swap');
      setTimeout(() => {
        $$('[data-k]', app).forEach((el) => { el.textContent = W[el.dataset.k]?.[i] ?? ''; });
        app.classList.remove('swap');
      }, reduce ? 0 : 220);
    };
    setLang('si');
    addEventListener('load', () => moveLdInk($('.lp-ld-switch .on', demo)));
    addEventListener('resize', () => moveLdInk($('.lp-ld-switch .on', demo)));
    let langTimer = null;
    const order = ['si', 'ta', 'en'];
    const startAuto = () => {
      if (reduce || langTimer) return;
      langTimer = setInterval(() => { const cur = btns.find((b) => b.classList.contains('on'))?.dataset.lang; setLang(order[(order.indexOf(cur) + 1) % 3]); }, 2600);
    };
    btns.forEach((b) => b.addEventListener('click', () => { clearInterval(langTimer); langTimer = -1; setLang(b.dataset.lang); }));
    new IntersectionObserver(([en]) => { if (en.isIntersecting && langTimer === null) startAuto(); }, { threshold: 0.4 }).observe(demo);
  }

  // ---------- monthly / yearly prices ----------
  const tween = (el, to) => {
    const from = Number(el.textContent.replace(/,/g, '')) || 0;
    if (reduce) { el.textContent = fmt(to); return; }
    const t0 = performance.now();
    const step = (t) => { const p = Math.min(1, (t - t0) / 500); el.textContent = fmt(from + (to - from) * (1 - (1 - p) ** 3)); if (p < 1) requestAnimationFrame(step); };
    requestAnimationFrame(step);
  };
  $$('.lp-cycle button').forEach((b) => b.addEventListener('click', () => {
    const cycle = b.dataset.cycle;
    $$('.lp-cycle button').forEach((x) => { x.classList.toggle('on', x === b); x.setAttribute('aria-checked', String(x === b)); });
    $$('.lp-price b').forEach((p) => tween(p, Number(p.dataset[cycle])));
    $$('.lp-price-note').forEach((n) => { n.textContent = n.dataset[`${cycle}Note`]; });
    $$('[data-plan-link]').forEach((a) => { a.href = `tutor-registration-application.php?plan=${a.dataset.planLink}&cycle=${cycle}`; });
  }));
})();
