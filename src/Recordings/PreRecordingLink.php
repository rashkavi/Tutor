<?php
declare(strict_types=1);

namespace Enlixo\Recordings;

/**
 * The "where is the video" part of a pre-recorded class (tutor/add-pre-recorded-class.php, edit-pre-recording.php,
 * saved by tutor/api/addPreRecording.php / editPreRecording.php). Same recording types and link check as class
 * recordings (Settings -> Recording Types), plus the passcode and "Opens on".
 *
 * check() returns ['errors' => [field => message], 'set' => [column => value]]; the API writes 'set' to pre_recordings.
 * Field keys: type, link, passcode, unlock_at.
 */
final class PreRecordingLink
{
    public function __construct(private readonly RecordingTypeRepository $types)
    {
    }

    /**
     * @param array $in       type, link, passcode, unlock_at (Y-m-d\TH:i from a datetime-local box)
     * @param array|null $old the pre_recordings row being edited (null when adding)
     */
    public function check(int $tutor, array $in, string $title, ?array $old = null): array
    {
        $err = [];
        $set = [];
        $passcode = trim((string) ($in['passcode'] ?? ''));
        $unlock = trim((string) ($in['unlock_at'] ?? ''));
        // an old row with an uploaded Bunny video keeps its video; only the passcode / opens on can change
        $keepUpload = $old !== null && !empty($old['integrations_integration_id']) && empty($old['tutor_recording_types_id'])
            && filter_var($in['keep_video'] ?? false, FILTER_VALIDATE_BOOLEAN);

        if (mb_strlen($passcode) > 100) {
            $err['passcode'] = 'Use up to 100 characters';
        }
        if (!$keepUpload) {
            $type = $this->types->find($tutor, (int) ($in['type'] ?? 0));
            $oldType = (int) ($old['tutor_recording_types_id'] ?? 0);
            if (!$type || (!$type['active'] && $type['id'] !== $oldType)) {
                $err['type'] = 'Choose where the video is kept';
            } else {
                $check = $this->types->verify($tutor, $type['id'], (string) ($in['link'] ?? ''));
                if (!$check['ok']) {
                    $err['link'] = $check['error'];
                } else {
                    $set += [
                        'tutor_recording_types_id' => $type['id'],
                        'recording_url' => $check['url'],
                        'recording_link' => LinkChecker::playerHtml($check['url'], $title !== '' ? $title : 'Pre-recorded class', $type['embeddable']),
                        'integrations_integration_id' => null,
                    ];
                }
            }
        }
        $set['access_passcode'] = $passcode !== '' ? $passcode : null;
        $set['unlock_at'] = null;
        if ($unlock !== '') {
            $d = \DateTimeImmutable::createFromFormat('Y-m-d\TH:i', $unlock, new \DateTimeZone('Asia/Colombo'));
            if (!$d) {
                $err['unlock_at'] = 'Choose a date and time';
            } else {
                $set['unlock_at'] = $d->format('Y-m-d H:i:00');
            }
        }
        return ['errors' => $err, 'set' => $err ? [] : $set];
    }
}
