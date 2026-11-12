<?php
/**
 * The player of a recording that was added with a Recording Type (Settings -> Recording Types, migrations 010 / 011).
 * Used by every watch page (portal, parent, tutor; class recordings and pre-recorded classes).
 *
 *   require_once '../includes/enlixo/recording-player.php';
 *   $html = enlixo_recording_player($recordingData, $title, $recordingGuard['meter_html'] ?? '');
 *   if ($html !== null) { echo $html; } else { ...old player... }
 *
 * $tutorPreview = the tutor's own watch page: plays before "Opens on" with a note.
 * Returns null for old rows (no recording_url), so the page keeps its old player for them.
 * - Before "Opens on" (unlock_at) the player is never sent: a lock card with the date is shown instead.
 * - Embeddable types play inside the page (Bunny gets the same loader + watch-time meter as uploaded videos).
 * - Zoom / Teams / other websites get an "Open in <type>" button (they do not allow playing inside a page).
 * - A passcode is shown with a Copy button.
 * The URL was checked and cleaned when it was saved (src/Recordings/LinkChecker.php); it is still escaped here.
 */

if (!function_exists('enlixo_recording_player')) {
    function enlixo_recording_type_info(int $typeId): ?array
    {
        if ($typeId <= 0) {
            return null;
        }
        $res = Database::search("SELECT `name`, `platform_key`, `is_embeddable` FROM `tutor_recording_types` WHERE `recording_type_id` = '" . $typeId . "'");
        return $res && $res->num_rows > 0 ? $res->fetch_assoc() : null;
    }

    function enlixo_recording_player(array $row, string $title, string $meterHtml = '', bool $tutorPreview = false): ?string
    {
        $url = trim((string) ($row['recording_url'] ?? ''));
        if ($url === '' || !preg_match('#^https://#i', $url)) {
            return null;
        }
        $e = static fn ($v): string => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
        $type = enlixo_recording_type_info((int) ($row['tutor_recording_types_id'] ?? 0));
        $typeName = $type['name'] ?? 'the website';
        $key = $type['platform_key'] ?? 'custom';
        $embeddable = $type ? (bool) $type['is_embeddable'] : false;
        $css = '<link rel="stylesheet" href="' . $e(Project::getAssetsPath() . '/css/enlixo-recordings.css?v=' . Project::getVersion()) . '">';

        // Opens on: checked here as well, so it holds even when the watch-time service is not configured.
        $unlock = !empty($row['unlock_at']) ? strtotime((string) $row['unlock_at']) : false;
        $opensLater = $unlock !== false && $unlock > time();
        if ($opensLater && !$tutorPreview) {
            return $css . '<div class="rc-watch-lock">'
                . '<div class="rc-watch-icon"><iconify-icon icon="solar:calendar-linear" width="28" height="28"></iconify-icon></div>'
                . '<div class="main-text text-18 fw-semibold">This recording opens later</div>'
                . '<div class="sub-text text-14">You can watch it from <strong>' . $e(date('l, j F Y', $unlock)) . ' at ' . $e(date('g:i A', $unlock)) . '</strong>.</div>'
                . '</div>';
        }

        // the tutor watches before it opens: a note instead of the lock
        $previewNote = $opensLater
            ? '<div class="rc-watch-pass"><iconify-icon icon="solar:calendar-linear" width="20" height="20"></iconify-icon><span class="text-14">Students can watch this from <strong>'
                . $e(date('D, j M Y', $unlock)) . ' at ' . $e(date('g:i A', $unlock)) . '</strong>. You see it now because you are the tutor.</span></div>'
            : '';
        $css .= $previewNote;

        $passcode = trim((string) ($row['access_passcode'] ?? ''));
        $passHtml = '';
        if ($passcode !== '') {
            $passHtml = '<div class="rc-watch-pass">'
                . '<iconify-icon icon="solar:key-linear" width="20" height="20"></iconify-icon>'
                . '<span class="sub-text text-14">Passcode</span>'
                . '<code class="rc-watch-code">' . $e($passcode) . '</code>'
                . '<button type="button" class="custom-btn-outline rc-copy" data-copy="' . $e($passcode) . '" '
                . 'onclick="navigator.clipboard&&navigator.clipboard.writeText(this.dataset.copy).then(()=>{this.textContent=\'Copied\';setTimeout(()=>{this.textContent=\'Copy\'},1500)})">Copy</button>'
                . '<span class="sub-text text-12">' . ($embeddable ? 'Enter it if the player asks for it.' : 'Enter it when ' . $e($typeName) . ' asks for it.') . '</span>'
                . '</div>';
        }

        if ($embeddable) {
            $isBunny = $key === 'bunny';
            $player = '<div class="video-wrapper">'
                . '<div class="video-loader" id="videoLoader"><div class="modern-spinner"></div></div>'
                . '<iframe id="videoIframe" src="' . $e($url) . '" title="' . $e($title) . '" '
                . 'allow="accelerometer; gyroscope; autoplay; clipboard-write; encrypted-media; picture-in-picture; fullscreen" '
                . 'referrerpolicy="strict-origin-when-cross-origin" allowfullscreen></iframe>'
                . '</div>';
            return $css . $player . $passHtml . ($isBunny ? $meterHtml : '');
        }

        return $css . '<div class="rc-watch-open">'
            . '<div class="rc-watch-icon"><iconify-icon icon="solar:play-circle-linear" width="28" height="28"></iconify-icon></div>'
            . '<div class="main-text text-18 fw-semibold">' . $e($typeName) . ' recordings open on their own website</div>'
            . '<div class="sub-text text-14">Press the button to watch it in a new tab.</div>'
            . '<a class="custom-btn px-4 text-decoration-none d-inline-flex align-items-center gap-2" href="' . $e($url) . '" target="_blank" rel="noopener">'
            . '<iconify-icon icon="solar:square-share-line-linear" width="18" height="18"></iconify-icon>Open in ' . $e($typeName) . '</a>'
            . '</div>' . $passHtml;
    }

    /** "Opens on" chip for recording cards in the lists (empty when the recording is already open). */
    function enlixo_recording_opens_html(?string $unlockAt): string
    {
        $t = $unlockAt ? strtotime($unlockAt) : false;
        if ($t === false || $t <= time()) {
            return '';
        }
        static $css = false;
        $link = $css ? '' : '<link rel="stylesheet" href="' . htmlspecialchars(Project::getAssetsPath() . '/css/enlixo-recordings.css?v=' . Project::getVersion(), ENT_QUOTES) . '">';
        $css = true;
        return $link . '<span class="rc-opens"><iconify-icon icon="solar:calendar-linear" width="14" height="14"></iconify-icon>Opens '
            . htmlspecialchars(date('D, j M', $t) . ' at ' . date('g:i A', $t), ENT_QUOTES) . '</span>';
    }
}
