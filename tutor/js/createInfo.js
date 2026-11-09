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

    getYouTubeVideoId(url) {
        if (!url) return null;

        try {
            // iframe code support
            const iframeMatch = url.match(/src\s*=\s*["']([^"']+)["']/i);
            if (iframeMatch && iframeMatch[1]) {
                url = iframeMatch[1];
            }

            if (url.includes('youtu.be/')) {
                return url.split('youtu.be/')[1].split(/[?&/]/)[0];
            }

            if (url.includes('/embed/')) {
                return url.split('/embed/')[1].split(/[?&/]/)[0];
            }

            const parsedUrl = new URL(url);
            const videoId = parsedUrl.searchParams.get('v');
            if (videoId) return videoId;
        } catch (e) {
            return null;
        }

        return null;
    }

    createYouTubePreview() {
        const videoId = this.getYouTubeVideoId(this.youtubeLink);
        if (!videoId) return '';

        const thumbUrl = `https://img.youtube.com/vi/${videoId}/hqdefault.jpg`;
        const watchUrl = `https://www.youtube.com/watch?v=${videoId}`;

        return `
            <div class="create-info-video-card" data-watch-url="${watchUrl}">
                <div class="create-info-video-label">${this.videoTitle}</div>
                <div class="create-info-video-wrap">
                    <img src="${thumbUrl}" alt="YouTube thumbnail" class="create-info-video-thumb">
                    <div class="create-info-video-overlay">
                        <div class="create-info-video-play">
                            <iconify-icon icon="solar:play-bold" width="20"></iconify-icon>
                        </div>
                    </div>
                </div>
            </div>
        `;
    }

    render() {
        this.button = document.createElement('button');
        this.button.type = 'button';
        this.button.className = 'create-info-btn';
        this.button.innerHTML = `<iconify-icon icon="fe:info" width="20" class="create-info-icon"></iconify-icon>`;

        this.target.appendChild(this.button);

        this.popover = document.createElement('div');
        this.popover.className = 'create-info-popover d-none';

        let stepsHTML = '';
        this.steps.forEach((step, index) => {
            stepsHTML += `
                <div class="create-info-step">
                    <span class="create-info-step-no">${index + 1}.</span>
                    <span>${step}</span>
                </div>
            `;
        });

        const videoHTML = this.createYouTubePreview();

        this.popover.innerHTML = `
            <div class="create-info-title">${this.title}</div>
            <div class="create-info-steps">${stepsHTML}</div>
            ${this.note ? `<div class="create-info-note">${this.note}</div>` : ''}
            ${videoHTML}
        `;

        document.body.appendChild(this.popover);
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

        // video click open new tab
        this.popover.addEventListener('click', (e) => {
            const videoCard = e.target.closest('.create-info-video-card');
            if (videoCard) {
                const watchUrl = videoCard.getAttribute('data-watch-url');
                if (watchUrl) {
                    window.open(watchUrl, '_blank');
                }
            }
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