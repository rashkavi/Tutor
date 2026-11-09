// Base class for UI components: owns a root element, re-renders on setState, cleans up listeners.
export class Component {
  constructor(props = {}) {
    this.props = props;
    this.state = {};
    this.el = null;
    this._cleanups = [];
  }

  /** Subclasses return an Element. */
  render() {
    throw new Error(`${this.constructor.name}.render() not implemented`);
  }

  mount(target) {
    this.el = this.render();
    target.append(this.el);
    this.afterMount?.();
    return this;
  }

  setState(patch) {
    this.state = { ...this.state, ...patch };
    this.update();
  }

  update() {
    if (!this.el) return;
    const next = this.render();
    this.el.replaceWith(next);
    this.el = next;
  }

  listen(target, event, handler, options) {
    target.addEventListener(event, handler, options);
    this._cleanups.push(() => target.removeEventListener(event, handler, options));
  }

  destroy() {
    this._cleanups.forEach((fn) => fn());
    this._cleanups = [];
    this.el?.remove();
  }
}
