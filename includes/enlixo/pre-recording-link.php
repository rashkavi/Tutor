<?php
/**
 * tutor/api/addPreRecording.php and editPreRecording.php: the recording type, link, passcode and "Opens on" of a
 * pre-recorded class, checked by src/Recordings/PreRecordingLink.php (same rules as Class Recordings).
 *
 *   $res = enlixo_pre_recording_link($tutorUserId, $_POST, $title, $oldRowOrNull);
 *   foreach ($res['errors'] as $k => $msg) $status->$k = $msg;
 *   ... "UPDATE pre_recordings SET " . $res['sql'] ...   ($res['sql'] = "`col`='value', ..." , escaped)
 */
require_once __DIR__ . '/../../src/Legacy/Bridge.php';

if (!function_exists('enlixo_pre_recording_link')) {
    function enlixo_pre_recording_link(int $tutorUserId, array $in, string $title, ?array $old = null): array
    {
        $app = \Enlixo\Legacy\Bridge::app();
        if ($app === null) {
            return ['errors' => ['type' => 'Recording types are not available yet. Please contact support.'], 'set' => [], 'sql' => ''];
        }
        try {
            $res = (new \Enlixo\Recordings\PreRecordingLink($app->recordingTypes()))->check($tutorUserId, $in, $title, $old);
        } catch (\Throwable $e) {
            error_log('pre-recording link: ' . $e->getMessage());
            return ['errors' => ['link' => 'The link could not be checked. Please try again.'], 'set' => [], 'sql' => ''];
        }
        $parts = [];
        foreach ($res['set'] as $col => $val) {
            $parts[] = '`' . $col . '`=' . ($val === null ? 'NULL' : "'" . Database::escape((string) $val) . "'");
        }
        return $res + ['sql' => implode(', ', $parts)];
    }
}
