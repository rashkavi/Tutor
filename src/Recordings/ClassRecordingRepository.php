<?php
declare(strict_types=1);

namespace Enlixo\Recordings;

use Enlixo\Core\Database;
use Enlixo\Core\HttpException;
use Enlixo\Support\ListQuery;
use Enlixo\Support\Paginated;

/**
 * Class recordings of one tutor: the legacy lecture_recording rows (one per class day, time_table) plus the
 * columns of migration 010 (recording type, clean link, passcode, unlock time).
 *
 * - A new or edited recording stores the clean link (recording_url) and, in the old recording_link column, the
 *   player we build ourselves (iframe for types that play in the page, an "Open recording" link otherwise), so the
 *   student portal pages that still print recording_link keep working. Typed HTML is never stored.
 * - Old rows have no type: an uploaded Bunny video (integrations_integration_id set) or a YouTube embed code.
 * - Availability: Opens on (unlock_at, every plan) and Available until (expire_date, PRO "recordings.expiry").
 */
final class ClassRecordingRepository
{
    public const FILTERS = ['class', 'type', 'state', 'day'];
    public const SORTS = ['date', 'title', 'added'];

    public function __construct(private readonly Database $db, private readonly RecordingTypeRepository $types)
    {
    }

    private static function now(): \DateTimeImmutable
    {
        return new \DateTimeImmutable('now', new \DateTimeZone('Asia/Colombo'));
    }

    /** Classes of the tutor, for the filter and the form. */
    public function classes(int $tutor): array
    {
        return array_map(static fn (array $r): array => ['id' => (int) $r['class_id'], 'name' => (string) $r['class_name'], 'active' => (int) $r['status_status_id'] === 1],
            $this->db->all('SELECT class_id, class_name, status_status_id FROM classes WHERE tutor_id = :t ORDER BY status_status_id = 1 DESC, class_name', ['t' => $tutor]));
    }

    /** Class days of one class that already started (newest first): the days a recording can belong to. */
    public function classDays(int $tutor, int $classId): array
    {
        $rows = $this->db->all(
            "SELECT tt.time_table_id, tt.`date`, tt.start_time, tt.end_time, tt.lecture_title,
                    (SELECT COUNT(*) FROM lecture_recording lr WHERE lr.time_table_time_table_id = tt.time_table_id) AS recordings
               FROM time_table tt JOIN classes c ON c.class_id = tt.classes_class_id
              WHERE c.tutor_id = :t AND c.class_id = :c AND tt.`date` <= :today
              ORDER BY tt.`date` DESC, tt.start_time DESC LIMIT 120",
            ['t' => $tutor, 'c' => $classId, 'today' => self::now()->format('Y-m-d')],
        );
        return array_map(static fn (array $r): array => [
            'id' => (int) $r['time_table_id'], 'date' => (string) $r['date'], 'start' => substr((string) $r['start_time'], 0, 5),
            'end' => substr((string) $r['end_time'], 0, 5), 'title' => (string) ($r['lecture_title'] ?? ''), 'recordings' => (int) $r['recordings'],
        ], $rows);
    }

    private const SELECT = "SELECT lr.lecture_recording_id, lr.recording_title, lr.recording_description, lr.recording_cover, lr.duration, lr.added_at,
                lr.expire_date, lr.unlock_at, lr.recording_url, lr.access_passcode, lr.recording_link, lr.integrations_integration_id,
                lr.tutor_recording_types_id, rt.name AS type_name, rt.platform_key, rt.is_embeddable,
                tt.time_table_id, tt.`date` AS day_date, tt.start_time, tt.lecture_title, c.class_id, c.class_name, c.class_cover,
                (SELECT COUNT(DISTINCT ws.students_student_id) FROM recording_watch_sessions ws
                  WHERE ws.recording_kind = 'lecture' AND ws.recording_id = lr.lecture_recording_id) AS watched
           FROM lecture_recording lr
           JOIN time_table tt ON tt.time_table_id = lr.time_table_time_table_id
           JOIN classes c ON c.class_id = tt.classes_class_id
           LEFT JOIN tutor_recording_types rt ON rt.recording_type_id = lr.tutor_recording_types_id";

    public function list(int $tutor, ListQuery $q): Paginated
    {
        $now = self::now();
        $base = ['c.tutor_id = :t'];
        $params = ['t' => $tutor];
        if ($q->filter('class') !== null) {
            $base[] = 'c.class_id = :c';
            $params['c'] = (int) $q->filter('class');
        }
        if ($q->filter('day') !== null) {
            $base[] = 'tt.time_table_id = :day';
            $params['day'] = (int) $q->filter('day');
        }
        $type = $q->filter('type');
        if ($type === 'old') {
            $base[] = 'lr.tutor_recording_types_id IS NULL';
        } elseif ($type !== null) {
            $base[] = 'lr.tutor_recording_types_id = :rt';
            $params['rt'] = (int) $type;
        }
        $state = $q->filter('state');
        if (in_array($state, ['available', 'scheduled', 'expired'], true)) {
            $params['today'] = $now->format('Y-m-d');
            if ($state !== 'expired') {
                $params['now'] = $now->format('Y-m-d H:i:s');
            }
            $base[] = match ($state) {
                'expired' => 'lr.expire_date IS NOT NULL AND lr.expire_date < :today',
                'scheduled' => '(lr.expire_date IS NULL OR lr.expire_date >= :today) AND lr.unlock_at IS NOT NULL AND lr.unlock_at > :now',
                default => '(lr.expire_date IS NULL OR lr.expire_date >= :today) AND (lr.unlock_at IS NULL OR lr.unlock_at <= :now)',
            };
        }
        [$where, $params] = $q->where(['lr.recording_title', 'c.class_name', 'tt.lecture_title'], [], $base, $params);
        $from = 'FROM lecture_recording lr JOIN time_table tt ON tt.time_table_id = lr.time_table_time_table_id JOIN classes c ON c.class_id = tt.classes_class_id';
        $total = (int) $this->db->value("SELECT COUNT(*) $from $where", $params);
        $rows = $this->db->all(self::SELECT . " $where " . $q->orderBy(['date' => 'tt.`date`', 'title' => 'lr.recording_title', 'added' => 'lr.added_at'], 'tt.`date` DESC, lr.lecture_recording_id') . ' ' . $q->limit(), $params);
        return new Paginated(array_map(fn (array $r): array => $this->shape($r, $now), $rows), $total, $q);
    }

    public function find(int $tutor, int $id): ?array
    {
        $r = $this->db->one(self::SELECT . ' WHERE c.tutor_id = :t AND lr.lecture_recording_id = :id', ['t' => $tutor, 'id' => $id]);
        return $r ? $this->shape($r, self::now()) : null;
    }

    private function shape(array $r, \DateTimeImmutable $now): array
    {
        $url = (string) ($r['recording_url'] ?? '');
        if ($r['tutor_recording_types_id'] === null) {
            // old rows: a YouTube embed code, or a video uploaded to Bunny (recording_link = the Bunny video id)
            $uploaded = $r['integrations_integration_id'] !== null;
            if (!$uploaded && $url === '') {
                $url = (string) (LinkChecker::extractUrl((string) $r['recording_link']) ?? '');
            }
            $type = $uploaded
                ? ['id' => null, 'name' => 'Uploaded video', 'key' => 'upload', 'embeddable' => true, 'old' => true]
                : ['id' => null, 'name' => 'YouTube (added before)', 'key' => 'youtube', 'embeddable' => true, 'old' => true];
        } else {
            $type = ['id' => (int) $r['tutor_recording_types_id'], 'name' => (string) ($r['type_name'] ?? 'Deleted type'), 'key' => (string) ($r['platform_key'] ?? 'custom'), 'embeddable' => (bool) ($r['is_embeddable'] ?? 0), 'old' => false];
        }
        $unlock = $r['unlock_at'] ? new \DateTimeImmutable((string) $r['unlock_at'], $now->getTimezone()) : null;
        $expire = $r['expire_date'] ? (string) $r['expire_date'] : null;
        $state = $expire !== null && $expire < $now->format('Y-m-d') ? 'expired' : ($unlock && $unlock > $now ? 'scheduled' : 'available');
        $thumb = null;
        if (!empty($r['recording_cover'])) {
            $thumb = 'recording-thumbnail/' . $r['recording_cover'];
        } elseif ($type['key'] === 'youtube' && preg_match('#/embed/([A-Za-z0-9_-]{11})#', $url, $m)) {
            $thumb = "https://i.ytimg.com/vi/{$m[1]}/mqdefault.jpg";
        } elseif (!empty($r['class_cover'])) {
            $thumb = 'classes-covers/' . $r['class_cover'];
        }
        return [
            'id' => (int) $r['lecture_recording_id'],
            'title' => (string) $r['recording_title'],
            'description' => (string) ($r['recording_description'] ?? ''),
            'duration' => (int) $r['duration'],
            'added_at' => (string) $r['added_at'],
            'class' => ['id' => (int) $r['class_id'], 'name' => (string) $r['class_name']],
            'day' => ['id' => (int) $r['time_table_id'], 'date' => (string) $r['day_date'], 'start' => substr((string) $r['start_time'], 0, 5), 'title' => (string) ($r['lecture_title'] ?? '')],
            'type' => $type,
            'url' => $url,
            'passcode' => (string) ($r['access_passcode'] ?? ''),
            'unlock_at' => $unlock?->format('Y-m-d\TH:i'),
            'expire_date' => $expire,
            'state' => $state,
            'watched' => (int) $r['watched'],
            'thumb' => $thumb,
        ];
    }

    /** Numbers for the stat cards (one class or all). */
    public function overview(int $tutor, ?int $classId): array
    {
        $now = self::now();
        $p = ['t' => $tutor] + ($classId ? ['c' => $classId] : []);
        $cls = $classId ? ' AND c.class_id = :c' : '';
        $from = "FROM lecture_recording lr JOIN time_table tt ON tt.time_table_id = lr.time_table_time_table_id JOIN classes c ON c.class_id = tt.classes_class_id WHERE c.tutor_id = :t$cls";
        $months = [];
        for ($i = 5; $i >= 0; $i--) {
            $m = $now->modify("first day of -$i month");
            $months[$m->format('Y-m')] = ['label' => $m->format('M'), 'value' => 0];
        }
        foreach ($this->db->all("SELECT DATE_FORMAT(lr.added_at, '%Y-%m') ym, COUNT(*) n $from AND lr.added_at >= :since GROUP BY ym", $p + ['since' => array_key_first($months) . '-01']) as $r) {
            if (isset($months[$r['ym']])) {
                $months[$r['ym']]['value'] = (int) $r['n'];
            }
        }
        $row = $this->db->one(
            "SELECT COUNT(*) total, COALESCE(SUM(lr.duration), 0) minutes,
                    SUM(lr.unlock_at IS NOT NULL AND lr.unlock_at > :now AND (lr.expire_date IS NULL OR lr.expire_date >= :today)) scheduled,
                    SUM(lr.expire_date IS NOT NULL AND lr.expire_date < :today2) expired,
                    SUM(lr.expire_date IS NOT NULL AND lr.expire_date BETWEEN :today3 AND :week) expiring
             $from",
            $p + ['now' => $now->format('Y-m-d H:i:s'), 'today' => $now->format('Y-m-d'), 'today2' => $now->format('Y-m-d'), 'today3' => $now->format('Y-m-d'), 'week' => $now->modify('+7 days')->format('Y-m-d')],
        );
        $watchers = (int) $this->db->value(
            "SELECT COUNT(DISTINCT ws.students_student_id) FROM recording_watch_sessions ws
               JOIN lecture_recording lr ON ws.recording_kind = 'lecture' AND ws.recording_id = lr.lecture_recording_id
               JOIN time_table tt ON tt.time_table_id = lr.time_table_time_table_id JOIN classes c ON c.class_id = tt.classes_class_id
              WHERE c.tutor_id = :t$cls AND ws.started_at >= :since",
            $p + ['since' => $now->format('Y-m-01')],
        );
        $vals = array_values($months);
        return [
            'total' => (int) $row['total'],
            'minutes' => (int) $row['minutes'],
            'this_month' => end($vals)['value'],
            'last_month' => $vals[count($vals) - 2]['value'],
            'bars' => $vals,
            'scheduled' => (int) $row['scheduled'],
            'expired' => (int) $row['expired'],
            'expiring' => (int) $row['expiring'],
            'watchers' => $watchers,
        ];
    }

    /**
     * Add ($id null) or edit a recording. $canExpire = the tutor's plan has "recordings.expiry".
     * Throws 422 with field errors.
     */
    public function save(int $tutor, ?int $id, array $in, int $userId, bool $canExpire): array
    {
        $old = $id ? ($this->find($tutor, $id) ?? throw HttpException::notFound('This recording was not found.')) : null;
        $err = [];
        $title = trim((string) ($in['title'] ?? ''));
        $desc = trim((string) ($in['description'] ?? ''));
        $dayId = (int) ($in['day'] ?? 0);
        $duration = trim((string) ($in['duration'] ?? ''));
        $passcode = trim((string) ($in['passcode'] ?? ''));
        $unlock = trim((string) ($in['unlock_at'] ?? ''));
        $expire = trim((string) ($in['expire_date'] ?? ''));
        $keepUpload = $old !== null && $old['type']['key'] === 'upload';

        $day = $dayId > 0 ? $this->db->one(
            'SELECT tt.time_table_id, tt.`date`, c.class_id FROM time_table tt JOIN classes c ON c.class_id = tt.classes_class_id WHERE tt.time_table_id = :d AND c.tutor_id = :t',
            ['d' => $dayId, 't' => $tutor],
        ) : null;
        if ((int) ($in['class'] ?? 0) <= 0) {
            $err['class'] = 'Choose the class';
        } elseif (!$day) {
            $err['day'] = 'Choose the class day this recording is from';
        } elseif ((int) $day['class_id'] !== (int) $in['class']) {
            $err['day'] = 'This class day belongs to another class';
        }
        if ($title === '') {
            $err['title'] = 'Enter a title, for example Week 4: Integration';
        } elseif (mb_strlen($title) > 100) {
            $err['title'] = 'Use up to 100 characters';
        }
        if (mb_strlen($desc) > 1000) {
            $err['description'] = 'Use up to 1000 characters';
        }
        if ($duration === '' || !ctype_digit($duration) || (int) $duration < 1 || (int) $duration > 900) {
            $err['duration'] = 'Enter the length in minutes, from 1 to 900';
        }
        if (mb_strlen($passcode) > 100) {
            $err['passcode'] = 'Use up to 100 characters';
        }
        $type = null;
        $check = null;
        if (!$keepUpload) {
            $type = $this->types->find($tutor, (int) ($in['type'] ?? 0));
            if (!$type || (!$type['active'] && $type['id'] !== ($old['type']['id'] ?? null))) {
                $err['type'] = 'Choose where the recording is kept';
            } else {
                $check = $this->types->verify($tutor, $type['id'], (string) ($in['link'] ?? ''));
                if (!$check['ok']) {
                    $err['link'] = $check['error'];
                }
            }
        }
        $unlockAt = null;
        if ($unlock !== '') {
            $d = \DateTimeImmutable::createFromFormat('Y-m-d\TH:i', $unlock, new \DateTimeZone('Asia/Colombo'));
            if (!$d) {
                $err['unlock_at'] = 'Choose a date and time';
            } else {
                $unlockAt = $d->format('Y-m-d H:i:00');
            }
        }
        $expireDate = null;
        if ($expire !== '') {
            if (!$canExpire && $expire !== ($old['expire_date'] ?? null)) {
                $err['expire_date'] = 'Closing recordings on a date is on the GROW plan';
            } elseif (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $expire) || !strtotime($expire)) {
                $err['expire_date'] = 'Choose a date';
            } elseif ($expire < self::now()->format('Y-m-d') && $expire !== ($old['expire_date'] ?? null)) {
                $err['expire_date'] = 'Choose today or a later date';
            } elseif ($unlockAt !== null && $expire < substr($unlockAt, 0, 10)) {
                $err['expire_date'] = 'Must be on or after the day it opens';
            } else {
                $expireDate = $expire;
            }
        }
        if ($err) {
            throw new HttpException(422, 'validation_failed', 'Please check the highlighted fields.', $err);
        }

        $p = [
            'title' => $title, 'desc' => $desc !== '' ? $desc : null, 'dur' => (int) $duration, 'day' => $dayId,
            'unlock' => $unlockAt, 'expire' => $expireDate, 'pass' => $passcode !== '' ? $passcode : null,
        ];
        $linkSql = '';
        if (!$keepUpload) {
            $player = LinkChecker::playerHtml($check['url'], $title, $type['embeddable']);
            $p += ['type' => $type['id'], 'url' => $check['url'], 'player' => $player];
            $linkSql = ', tutor_recording_types_id = :type, recording_url = :url, recording_link = :player, integrations_integration_id = NULL';
        }
        if ($old) {
            $this->db->execute(
                "UPDATE lecture_recording SET recording_title = :title, recording_description = :desc, duration = :dur, time_table_time_table_id = :day,
                        unlock_at = :unlock, expire_date = :expire, access_passcode = :pass$linkSql
                  WHERE lecture_recording_id = :id",
                $p + ['id' => $id],
            );
        } else {
            $id = $this->db->insert(
                'INSERT INTO lecture_recording (recording_title, recording_description, duration, time_table_time_table_id, unlock_at, expire_date, access_passcode,
                        tutor_recording_types_id, recording_url, recording_link, users_user_id, added_at)
                 VALUES (:title, :desc, :dur, :day, :unlock, :expire, :pass, :type, :url, :player, :user, :added)',
                $p + ['user' => $userId, 'added' => self::now()->format('Y-m-d H:i:s')],
            );
        }
        return ['old' => $old, 'new' => $this->find($tutor, $id)];
    }

    public function delete(int $tutor, int $id): array
    {
        $r = $this->find($tutor, $id) ?? throw HttpException::notFound('This recording was not found.');
        $this->db->transaction(function () use ($id): void {
            $this->db->execute("DELETE FROM recording_access_rules WHERE recording_kind = 'lecture' AND recording_id = :id", ['id' => $id]);
            $this->db->execute("DELETE FROM recording_student_overrides WHERE recording_kind = 'lecture' AND recording_id = :id", ['id' => $id]);
            $this->db->execute('DELETE FROM lecture_recording WHERE lecture_recording_id = :id', ['id' => $id]);
        });
        return $r;
    }
}
