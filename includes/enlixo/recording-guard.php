<?php
/**
 * Watch-time limit for a recording page (portal/watch-recording.php, portal/watch-pre-recording.php).
 *
 * Usage, before the player markup:
 *   $recordingGuard = require '../includes/enlixo/recording-guard.php';   // needs $guardKind ('lecture'|'pre'), $guardRecordingId, $guardStudentId
 *   if (!$recordingGuard['can_play']) { echo $recordingGuard['lock_html']; } else { ...iframe... echo $recordingGuard['meter_html']; }
 *
 * The server decides: when the time is used up / expired, the Bunny embed URL is never sent to the browser.
 */
require_once __DIR__ . '/../../src/Legacy/Bridge.php';

$guardResult = ['can_play' => true, 'lock_html' => '', 'meter_html' => ''];
$app = \Enlixo\Legacy\Bridge::app();
if ($app === null) {
    return $guardResult; // new code not configured: behave like before (no limits)
}

try {
    $svc = $app->recordings();
    $status = $svc->status((int) $guardStudentId, $guardKind, (int) $guardRecordingId);
} catch (\Throwable $e) {
    error_log('recording-guard: ' . $e->getMessage());
    return $guardResult;
}

$e = static fn ($v) => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
$guardResult['can_play'] = $status->canPlay();

if (!$status->canPlay()) {
    $title = match ($status->state) {
        'expired' => 'Recording expired',
        'not_yet_available' => 'Not available yet',
        'no_access' => 'Not enrolled',
        default => 'Watch time used up',
    };
    $guardResult['lock_html'] = '<div class="ex-root ex-player-wrap"><div class="ex-player-lock"><div class="ex-gated-cta">'
        . '<div class="ex-lock-orb"><iconify-icon icon="mdi:lock" width="26"></iconify-icon></div>'
        . '<h2>' . $e($title) . '</h2><div style="opacity:.85">' . $e($svc->lockedMessage($status)) . '</div>'
        . '</div></div></div>';
}

// The meter + tracker: assets/js/enlixo/pages/app-watch-recording.js reads these data attributes.
$guardResult['meter_html'] = sprintf(
    '<div class="ex-root ex-card ex-card-pad mt-3" id="watch-meter" data-kind="%s" data-id="%d" data-limited="%s"></div>',
    $e($guardKind),
    (int) $guardRecordingId,
    $status->allowedSeconds === null ? '0' : '1',
);

return $guardResult;
