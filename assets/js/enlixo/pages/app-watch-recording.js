// portal/watch-recording.php and portal/watch-pre-recording.php: watch-time limit on the Bunny player.
import { ApiClient } from '../core/ApiClient.js';
import { PlayerJsAdapter } from '../recordings/PlayerAdapters.js';
import { WatchTimeTracker } from '../recordings/WatchTimeTracker.js';

const meter = document.getElementById('watch-meter');
const iframe = document.getElementById('videoIframe');

if (meter && iframe) {
  const wrap = iframe.closest('.video-wrapper') ?? iframe.parentElement;
  wrap.classList.add('ex-root', 'ex-lock-host');
  const adapter = await PlayerJsAdapter.create(iframe).catch((e) => { console.warn(e); return null; });
  const tracker = new WatchTimeTracker({ api: new ApiClient(), kind: meter.dataset.kind, id: meter.dataset.id, adapter, meterEl: meter, lockHost: wrap });
  if (adapter) tracker.start(); else tracker.showStatusOnly();
}
