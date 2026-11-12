class CreateInfo {
    constructor(options = {}) {
        this.target = document.querySelector(options.target);
        if (!this.target) return;

        this.title = options.title || 'Information';
        this.steps = options.steps || [];
        this.note = options.note || '';
        this.placement = options.placement || 'right';

        // new
        this.youtubeLink = options.youtubeLink || '';
        this.videoTitle = options.videoTitle || 'Watch Video Guide';

        this.button = null;
        this.popover = null;

        this.render();
        this.bindEvents();
    }

    static videos() {
        // one file for every guide video: assets/guides/videos.json ("help" = the (i) next to one field)
        CreateInfo._videos ??= fetch('../assets/guides/videos.json', { cache: 'no-cache' }).then((r) => (r.ok ? r.json() : {})).catch(() => ({}));
        return CreateInfo._videos;
    }

    static esc(s) {
        return String(s ?? '').replace(/[&<>"']/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));
    }

    getYouTubeVideoId(url) {
        if (!url) return null;
        const src = String(url).match(/src\s*=\s*["']([^"']+)["']/i)?.[1] ?? String(url);
        const m = src.match(/(?:youtu\.be\/|youtube(?:-nocookie)?\.com\/(?:embed\/|shorts\/|live\/|watch\?(?:.*&)?v=))([A-Za-z0-9_-]{6,})/);
        return m ? m[1] : null;
    }

    videoHTML() {
        if (!this.youtubeLink) return '';
        const id = this.getYouTubeVideoId(this.youtubeLink);
        return `<button type="button" class="ex-qg-video" data-video="${CreateInfo.esc(this.youtubeLink)}" aria-label="Watch the video guide">
                ${id ? `<img src="https://img.youtube.com/vi/${id}/hqdefault.jpg" alt="" loading="lazy" onerror="this.outerHTML='<span class=&quot;ex-qg-video-blank&quot;></span>'">` : '<span class="ex-qg-video-blank"></span>'}
                <span class="ex-qg-play" aria-hidden="true"><svg viewBox="0 0 24 24" fill="currentColor"><path d="M8 5.5v13l11-6.5z"/></svg></span>
                <span class="ex-qg-video-label">${CreateInfo.esc(this.videoTitle || 'Watch the video guide')}</span>
            </button>`;
    }

    /** Same card as the page Quick guide (assets/js/enlixo/components/QuickGuide.js), so every (i) looks the same. */
    fill() {
        const check = '<span class="ex-qg-check" aria-hidden="true"><svg viewBox="0 0 24 24" fill="currentColor"><path d="M12 2a10 10 0 1 0 0 20 10 10 0 0 0 0-20Zm-1.2 14.2-4-4 1.4-1.4 2.6 2.6 5.6-5.6 1.4 1.4-7 7Z"/></svg></span>';
        this.popover.innerHTML = `
            <div class="ex-qg" role="note">
                <div class="ex-qg-head">
                    <span class="ex-qg-icon" aria-hidden="true"><svg viewBox="0 0 24 24" fill="currentColor"><path d="M12 2a10 10 0 1 0 0 20 10 10 0 0 0 0-20Zm0 2a8 8 0 1 1 0 16 8 8 0 0 1 0-16Zm1 11h-2v-5h2v5Zm0-7h-2V6h2v2Z"/></svg></span>
                    <div><strong>${CreateInfo.esc(this.title)}</strong><small>Help</small></div>
                </div>
                <div class="ex-qg-body">
                    ${this.note ? `<p>${CreateInfo.esc(this.note.trim())}</p>` : ''}
                    ${this.steps.length ? `<ul>${this.steps.map((st) => `<li>${check}<span>${CreateInfo.esc(st)}</span></li>`).join('')}</ul>` : ''}
                    ${this.videoHTML()}
                </div>
            </div>`;
    }

    render() {
        this.button = document.createElement('button');
        this.button.type = 'button';
        this.button.className = 'create-info-btn ex-info-btn';
        this.button.setAttribute('aria-label', `Help: ${this.title}`);
        this.button.innerHTML = '<svg viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M12 2a10 10 0 1 0 0 20 10 10 0 0 0 0-20Zm1 15h-2v-6h2v6Zm0-8h-2V7h2v2Z"/></svg>';
        this.target.appendChild(this.button);

        this.popover = document.createElement('div');
        this.popover.className = 'create-info-popover ex-qg-float d-none';
        this.fill();
        document.body.appendChild(this.popover);

        // no link given here: take it from assets/guides/videos.json by the field's guide id
        const key = (this.target.id || '').trim();
        if (!this.youtubeLink && key) {
            CreateInfo.videos().then((v) => {
                const url = String(v?.help?.[key] ?? '').trim();
                if (/^https:\/\//i.test(url)) { this.youtubeLink = url; this.fill(); }
            });
        }
    }

    bindEvents() {
        this.button.addEventListener('mouseenter', () => this.show());
        this.button.addEventListener('mouseleave', () => {
            setTimeout(() => {
                if (!this.popover.matches(':hover')) this.hide();
            }, 120);
        });

        this.button.addEventListener('click', (e) => {
            e.preventDefault();
            e.stopPropagation();
            this.toggle();
        });

        this.popover.addEventListener('mouseleave', () => this.hide());

        window.addEventListener('resize', () => {
            if (!this.popover.classList.contains('d-none')) {
                this.positionPopover();
            }
        });

        document.addEventListener('click', (e) => {
            if (
                this.popover &&
                !this.popover.classList.contains('d-none') &&
                !this.popover.contains(e.target) &&
                !this.button.contains(e.target)
            ) {
                this.hide();
            }
        });

        // the video plays in the same player as the page guides (or opens in a new tab)
        this.popover.addEventListener('click', (e) => {
            const v = e.target.closest('.ex-qg-video');
            if (!v) return;
            const url = v.getAttribute('data-video');
            if (window.EnlixoGuide) window.EnlixoGuide.play(url, this.title); else window.open(url, '_blank', 'noopener');
        });
    }

    positionPopover() {
        const btnRect = this.button.getBoundingClientRect();
        const pop = this.popover;

        pop.style.top = '0px';
        pop.style.left = '0px';
        pop.classList.remove('d-none');

        const popRect = pop.getBoundingClientRect();
        let top = window.scrollY + btnRect.top - 10;
        let left = 0;

        if (window.innerWidth < 576) {
            left = Math.max(12, window.scrollX + btnRect.left - 110);
            top = window.scrollY + btnRect.bottom + 10;

            if (left + popRect.width > window.scrollX + window.innerWidth - 12) {
                left = window.scrollX + window.innerWidth - popRect.width - 12;
            }

            pop.style.left = `${left}px`;
            pop.style.top = `${top}px`;
            return;
        }

        if (this.placement === 'right') {
            left = window.scrollX + btnRect.right + 12;

            if (left + popRect.width > window.scrollX + window.innerWidth - 12) {
                left = window.scrollX + btnRect.left - popRect.width - 12;
            }
        } else {
            left = window.scrollX + btnRect.left - popRect.width - 12;
        }

        if (top + popRect.height > window.scrollY + window.innerHeight - 12) {
            top = window.scrollY + window.innerHeight - popRect.height - 12;
        }

        if (top < window.scrollY + 12) {
            top = window.scrollY + 12;
        }

        pop.style.left = `${left}px`;
        pop.style.top = `${top}px`;
    }

    show() {
        this.popover.classList.remove('d-none');
        this.positionPopover();
    }

    hide() {
        this.popover.classList.add('d-none');
    }

    toggle() {
        if (this.popover.classList.contains('d-none')) {
            this.show();
        } else {
            this.hide();
        }
    }
}