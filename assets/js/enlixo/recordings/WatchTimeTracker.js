import { h, fmt, fill } from '../core/dom.js';
import { Meter } from '../components/Meter.js';
import { Toast } from '../components/Toast.js';
import { ApiError } from '../core/ApiClient.js';

/**
 * Server-counted watch time (like ENLIXO V3). Works with any PlayerAdapter.
 * - heartbeat every N s only while playing and the tab is visible
 * - on pause / end: one final heartbeat; on play: a `resume` ping (paused time never counts)
 * - when the server says time is up, the player is removed and a lock card is shown
 */
export class WatchTimeTracker {
  constructor({ api, kind, id, adapter, meterEl, lockHost }) {
    Object.assign(this, { api, kind, id, adapter, meterEl, lockHost });
    this.session = null;
    this.timer = null;
    this.warned = false;
  }

  /** When the player controls can't load: still show the meter (time can't be counted on this page load). */
  async showStatusOnly() {
    try {
      const { data } = await this.api.get(`/student/recordings/${this.kind}/${this.id}/status`);
      this.render(data);
    } catch (e) {
      fill(this.meterEl, h('span', { class: 'ex-badge ex-danger' }, e.message));
    }
  }

  async start() {
    try {
      const { data } = await this.api.post(`/student/recordings/${this.kind}/${this.id}/start`, {});
      this.session = data.session_token;
      this.interval = data.heartbeat_seconds * 1000;
      this.render(data.status);
    } catch (e) {
      if (e instanceof ApiError && e.code === 'recording_locked') return this.lock({ ...e.details, message: e.message });
      fill(this.meterEl, h('span', { class: 'ex-badge ex-danger' }, e.message));
      return;
    }
    this.adapter.on('play', () => this.#onPlay());
    this.adapter.on('pause', () => this.#onPause());
    this.adapter.on('ended', () => this.#onPause());
    document.addEventListener('visibilitychange', () => { if (document.hidden) this.#onPause(); });
  }

  #onPlay() {
    if (!this.session) return;
    this.#beat(true);
    clearInterval(this.timer);
    this.timer = setInterval(() => { if (!document.hidden) this.#beat(false); }, this.interval);
  }

  #onPause() {
    if (!this.timer) return;
    clearInterval(this.timer);
    this.timer = null;
    this.#beat(false);
  }

  async #beat(resume) {
    if (!this.session) return;
    try {
      const { data } = await this.api.post('/student/recordings/heartbeat', { session_token: this.session, position: await this.adapter.position(), resume });
      this.render(data);
      if (!data.can_play) this.lock(data);
    } catch (e) {
      if (e.code === 'session_not_found') { this.adapter.stop(); Toast.error(e.message); }
    }
  }

  render(s) {
    if (!s.allowed_seconds) return fill(this.meterEl, h('div', { class: 'ex-muted' }, 'Unlimited viewing for this recording.'));
    if (s.state === 'warning' && !this.warned) { this.warned = true; Toast.show(`Only ${fmt.minutes(s.remaining_seconds)} of watch time left for this recording.`); }
    const note = !s.can_play ? 'Your watch time for this recording is used up.'
      : s.state === 'warning' ? `⚠ ${fmt.minutes(s.remaining_seconds)} left. The recording closes when your time is used up.`
        : `${fmt.minutes(s.remaining_seconds)} left. Time only counts while the video is playing.`;
    fill(this.meterEl,
      h('div', { class: 'ex-kv' }, h('b', {}, 'Your watch time'), h('span', {}, `${fmt.minutes(s.used_seconds)} used of ${fmt.minutes(s.allowed_seconds)}`)),
      Meter.render(s.used_seconds, s.allowed_seconds),
      h('div', { class: 'ex-small ex-muted', style: { marginTop: '6px' } }, note),
      s.expires_at ? h('div', { class: 'ex-small ex-muted' }, `Available until ${fmt.date(s.expires_at)}`) : null);
  }

  lock(s) {
    clearInterval(this.timer);
    this.session = null;
    this.adapter.stop();
    const title = { expired: 'Recording expired', unpaid: 'Fee not paid', not_yet_available: 'Not available yet' }[s.state] ?? 'Watch time used up';
    this.lockHost.append(h('div', { class: 'ex-player-lock' }, h('div', { class: 'ex-gated-cta' },
      h('div', { class: 'ex-lock-orb', html: '<svg viewBox="0 0 24 24" width="24" height="24" fill="currentColor"><path d="M12 1.5a5 5 0 0 0-5 5V9H6a2 2 0 0 0-2 2v9a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2v-9a2 2 0 0 0-2-2h-1V6.5a5 5 0 0 0-5-5Zm-3 5a3 3 0 1 1 6 0V9H9V6.5Z"/></svg>' }),
      h('h2', {}, title),
      h('div', { style: { opacity: 0.85 } }, s.message ?? 'This recording is locked.'))));
    if (s.allowed_seconds) this.render(s);
  }
}
