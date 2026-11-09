import { h } from '../core/dom.js';
import { AppShell, DEMO_BASE } from '../layout/AppShell.js';
import { LoadingState } from '../components/LoadingState.js';
import { Html5VideoAdapter } from '../recordings/PlayerAdapters.js';
import { WatchTimeTracker } from '../recordings/WatchTimeTracker.js';

/** Demo player page: an HTML5 <video> + the same WatchTimeTracker the real portal page uses. */
class DemoPlayer {
  constructor({ api, kind, id, videoUrl, studentLabel }) {
    Object.assign(this, { api, kind, id, videoUrl, studentLabel });
  }

  mount(target) {
    const video = h('video', { controls: true, playsinline: true, controlslist: 'nodownload', preload: 'metadata', src: this.videoUrl, oncontextmenu: (e) => e.preventDefault() });
    const watermark = h('div', { class: 'ex-watermark' }, this.studentLabel);
    const wrap = h('div', { class: 'ex-player-wrap' }, video, watermark);
    const meterEl = h('div', { class: 'ex-card ex-card-pad', style: { display: 'grid', gap: '8px', marginTop: '14px' } }, LoadingState.line('50%'), LoadingState.line('100%'));
    target.append(wrap, meterEl);
    const move = () => { watermark.style.top = `${10 + Math.random() * 75}%`; watermark.style.left = `${5 + Math.random() * 60}%`; };
    setInterval(move, 7000); move();
    new WatchTimeTracker({ api: this.api, kind: this.kind, id: this.id, adapter: new Html5VideoAdapter(video), meterEl, lockHost: wrap }).start();
  }
}

const params = new URLSearchParams(location.search);
const shell = await AppShell.boot({ area: 'student', active: 'classes', title: 'Recording' });
shell.page.append(
  h('a', { href: `${DEMO_BASE}student/class.html?id=${params.get('class') ?? 1}&tab=recordings`, class: 'ex-small' }, '← Back to recordings'),
  shell.header('Watch recording', 'Time is counted on the server while the video plays. Pausing stops the clock.'));

const holder = h('div', { style: { maxWidth: '900px' } });
shell.page.append(holder);
new DemoPlayer({ api: shell.api, kind: params.get('kind') ?? 'lecture', id: params.get('id') ?? '1001', videoUrl: DEMO_BASE + 'mock/sample-lesson.mp4', studentLabel: 'Tharushi Silva · EN02417' }).mount(holder);
