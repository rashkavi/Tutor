// One small interface over different players, so the watch-time tracker doesn't care which player a page uses.
//   adapter.on('play' | 'pause' | 'ended', fn)   adapter.position() -> Promise<seconds>   adapter.stop()

export class Html5VideoAdapter {
  constructor(video) { this.video = video; }
  on(event, fn) { this.video.addEventListener(event, fn); }
  async position() { return Math.floor(this.video.currentTime || 0); }
  stop() { this.video.pause(); this.video.removeAttribute('src'); this.video.load(); }
  get element() { return this.video; }
}

/**
 * Bunny Stream iframe (iframe.mediadelivery.net) via the player.js protocol.
 * https://docs.bunny.net/docs/playback-control-api
 */
export class PlayerJsAdapter {
  static SCRIPT = 'https://assets.mediadelivery.net/playerjs/player-0.1.0.min.js';

  static async create(iframe) {
    if (!window.playerjs) {
      await new Promise((resolve, reject) => {
        const s = document.createElement('script');
        s.src = PlayerJsAdapter.SCRIPT;
        s.onload = resolve;
        s.onerror = () => reject(new Error('Could not load the video player controls.'));
        document.head.append(s);
      });
    }
    const player = new window.playerjs.Player(iframe);
    await new Promise((resolve) => player.on('ready', resolve));
    return new PlayerJsAdapter(iframe, player);
  }

  constructor(iframe, player) {
    this.iframe = iframe;
    this.player = player;
    this.last = 0;
    player.on('timeupdate', (d) => { this.last = Math.floor(d?.seconds ?? this.last); });
  }

  on(event, fn) { this.player.on(event, fn); }
  async position() { return this.last; }
  stop() { try { this.player.pause(); } catch { /* ignore */ } this.iframe.remove(); }
  get element() { return this.iframe; }
}
