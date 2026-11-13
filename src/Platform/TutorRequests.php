<?php
declare(strict_types=1);

namespace Enlixo\Platform;

use Enlixo\Catalog\FeatureCatalog;
use Enlixo\Core\Database;
use Enlixo\Core\HttpException;

/**
 * Online tutor sign-up requests (requested_users, migration 013).
 *
 *   public form  tutor-registration-application.php  → submit()
 *   admin list   admin/requested-users.php            → list(), one()
 *   approve      admin/add-tutor.php?request=ID        → approve(): PlatformBilling::addTutor() with the admin's choices,
 *                                                         then the request is linked to the new tutor
 *   reject       → reject() with a reason
 */
final class TutorRequests
{
    public const PENDING = 1;
    public const APPROVED = 2;
    public const REJECTED = 3;
    public const MAX_PER_IP_PER_HOUR = 5;
    public const MODES = ['physical' => 'Physical classes', 'online' => 'Online classes', 'both' => 'Physical and online'];
    public const HEARD = ['Facebook', 'YouTube', 'WhatsApp', 'Google', 'A friend or another tutor', 'ENLIXO team visit', 'Other'];

    public function __construct(
        private readonly Database $db,
        private readonly PlatformCatalog $catalog,
        private readonly PlatformSettings $settings,
    ) {
    }

    // ================================================================ what the public form and the landing page show

    /** Plans with their full feature list, add-ons, titles, districts and the free trial rule. */
    public function signupInfo(): array
    {
        $rules = $this->settings->rules();
        return [
            'plans' => $this->plans(),
            'comparison' => self::comparison(),
            'addons' => $this->addons(),
            'titles' => array_map(static fn ($t) => ['id' => (int) $t['title_id'], 'name' => $t['title']], $this->db->all('SELECT title_id, title FROM title ORDER BY title_id')),
            'districts' => array_column($this->db->all('SELECT district FROM district ORDER BY district'), 'district'),
            'modes' => self::MODES,
            'heard' => self::HEARD,
            'promos' => array_map(static fn ($p) => ['plan' => $p['plan'], 'percent' => $p['percent'], 'title' => $p['title']], Promotions::CODES),
            'trial' => ['days' => (int) $rules['trial_days'], 'plan' => strtoupper((string) ($this->catalog->planByKey((string) $rules['trial_plan'])['payment_plan'] ?? $rules['trial_plan'])), 'students' => (int) $rules['trial_student_cap']],
        ];
    }

    /** START / GROW / PRO from payment_plans (prices the admin set) with the features from the pricing sheet. */
    public function plans(): array
    {
        $rows = $this->catalog->planRows();
        if (!$rows) { // payment_plans not filled yet: the pricing sheet values
            $rows = [];
            foreach (FeatureCatalog::PLANS as $key => $p) {
                $rows[] = ['payment_plan_id' => $p['id'], 'payment_plan' => $p['name'], 'plan_key' => $key, 'per_month_amount' => ['start' => 3000, 'grow' => 7500, 'pro' => 0][$key],
                    'annual_discount' => 0, 'student_limit' => $p['student_limit'], 'extra_pack_price' => $key === 'pro' ? null : 1000, 'tagline' => ''];
            }
        }
        $all = FeatureCatalog::features();
        $out = [];
        $prev = null;
        foreach ($rows as $p) {
            $key = (string) $p['plan_key'];
            $mine = array_values(array_map(static fn ($f) => $f['name'], array_filter($all, static fn ($f) => in_array($key, $f['plans'], true))));
            $prevNames = $prev === null ? [] : array_values(array_map(static fn ($f) => $f['name'], array_filter($all, static fn ($f) => in_array($prev, $f['plans'], true))));
            $out[] = [
                'key' => $key,
                'name' => strtoupper((string) $p['payment_plan']),
                'monthly' => (float) $p['per_month_amount'],
                'yearly_discount' => (float) $p['annual_discount'],
                'limit' => $p['student_limit'] === null ? null : (int) $p['student_limit'],
                'pack_price' => $p['extra_pack_price'] === null ? null : (float) $p['extra_pack_price'],
                'pack_size' => Lifecycle::PACK_SIZE,
                'tagline' => (string) ($p['tagline'] ?? ''),
                'everything_in' => $prev === null ? null : $prevName,
                'new_features' => $prev === null ? $mine : array_values(array_diff($mine, $prevNames)),
                'feature_count' => count($mine),
                'custom' => $key === 'pro',
            ];
            $prev = $key;
            $prevName = strtoupper((string) $p['payment_plan']);
        }
        array_unshift($out, self::freePlan());
        return $out;
    }

    /**
     * FREE: tutors with up to 50 students use the START features at no cost. On approval it becomes START with
     * complimentary access (PlatformBilling 'comp'), so nothing is invoiced while the tutor stays small.
     */
    public const FREE_LIMIT = 50;

    public static function freePlan(): array
    {
        $start = array_values(array_map(static fn ($f) => $f['name'], array_filter(FeatureCatalog::features(), static fn ($f) => in_array('start', $f['plans'], true))));
        return [
            'key' => 'free', 'name' => 'FREE', 'monthly' => 0.0, 'yearly_discount' => 0.0, 'limit' => self::FREE_LIMIT,
            'pack_price' => null, 'pack_size' => Lifecycle::PACK_SIZE, 'tagline' => 'Free for tutors with up to ' . self::FREE_LIMIT . ' students',
            'everything_in' => null, 'new_features' => $start, 'feature_count' => count($start), 'free' => true, 'same_as' => 'start',
        ];
    }

    /** The pricing sheet as a table: category → [feature, [plan => true|false], add-on pricing or null]. */
    public static function comparison(): array
    {
        $out = [];
        foreach (FeatureCatalog::features() as $key => $f) {
            $out[$f['category']][] = [
                'key' => $key,
                'name' => $f['name'],
                'description' => $f['description'],
                'plans' => array_combine(array_keys(FeatureCatalog::PLANS), array_map(static fn ($k) => in_array($k, $f['plans'], true), array_keys(FeatureCatalog::PLANS))),
                'addon' => isset($f['addon']) ? $f['addon']['pricing'] : null,
            ];
        }
        return array_map(static fn ($cat, $rows) => ['category' => $cat, 'rows' => $rows], array_keys($out), $out);
    }

    /** Add-ons that are offered (admin Plans & Prices); before they are set up, the add-ons on the pricing sheet. */
    public function addons(): array
    {
        $rows = array_values(array_filter($this->catalog->addons(), static fn ($a) => $a['on']));
        if ($rows) {
            return array_map(static fn ($a) => ['key' => $a['key'], 'name' => $a['name'], 'note' => $a['note']], $rows);
        }
        $out = [];
        foreach (FeatureCatalog::features() as $f) {
            if (isset($f['addon'])) {
                $out[] = ['key' => $f['addon']['key'], 'name' => $f['addon']['name'], 'note' => $f['addon']['pricing']];
            }
        }
        return $out;
    }

    // ================================================================ public form

    /** @return array{ref:string} */
    public function submit(array $in, ?string $ip, string $now): array
    {
        if (trim((string) ($in['website'] ?? '')) !== '') { // filled only by bots
            return ['ref' => 'TR-' . substr(md5((string) microtime(true)), 0, 6)];
        }
        if ($ip !== null && (int) $this->db->value(
            'SELECT COUNT(*) FROM requested_users WHERE ip = :ip AND requested_date_time >= :s',
            ['ip' => $ip, 's' => (new \DateTimeImmutable($now))->modify('-1 hour')->format('Y-m-d H:i:s')],
        ) >= self::MAX_PER_IP_PER_HOUR) {
            throw new HttpException(429, 'too_many', 'Too many requests from this connection. Please try again in an hour, or call us.');
        }

        $e = [];
        $str = static function (string $k, int $max, bool $req, string $msg) use ($in, &$e): string {
            $v = trim(preg_replace('/\s+/u', ' ', (string) ($in[$k] ?? '')) ?? '');
            if ($req && $v === '') {
                $e[$k] = $msg;
            } elseif (mb_strlen($v) > $max) {
                $e[$k] = "Up to $max characters.";
            }
            return $v;
        };
        $title = (int) ($in['title'] ?? 0);
        if (!$this->db->value('SELECT 1 FROM title WHERE title_id = :t', ['t' => $title])) {
            $e['title'] = 'Choose a title.';
        }
        $first = $str('first_name', 45, true, 'Enter your first name.');
        $last = $str('last_name', 45, true, 'Enter your last name.');
        $email = strtolower($str('email', 100, true, 'Enter your e-mail address.'));
        if ($email !== '' && !isset($e['email'])) {
            if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $e['email'] = 'Enter a valid e-mail address, for example ruwan@gmail.com.';
            } elseif ($this->db->value('SELECT 1 FROM users WHERE LOWER(user_email) = :e', ['e' => $email])) {
                $e['email'] = 'An account already uses this e-mail address. Sign in, or use "Forgot password".';
            } elseif ($this->db->value('SELECT 1 FROM requested_users WHERE LOWER(email) = :e AND request_status_request_status_id = :p', ['e' => $email, 'p' => self::PENDING])) {
                $e['email'] = 'We already have a request from this e-mail address. Our team will contact you soon.';
            }
        }
        $mobileRaw = $str('mobile', 15, true, 'Enter your mobile number.');
        $mobile = $mobileRaw === '' ? null : PlatformBilling::phone($mobileRaw);
        if ($mobileRaw !== '' && $mobile === null) {
            $e['mobile'] = 'Enter a Sri Lankan mobile number, 10 digits, for example 0771234567.';
        } elseif ($mobile !== null && $this->db->value('SELECT 1 FROM users WHERE user_mobile IN (:a, :b)', ['a' => $mobile, 'b' => '+94' . substr($mobile, 1)])) {
            $e['mobile'] = 'An account already uses this mobile number.';
        }
        $waRaw = $str('whatsapp', 15, false, '');
        $wa = $waRaw === '' ? $mobile : PlatformBilling::phone($waRaw);
        if ($waRaw !== '' && $wa === null) {
            $e['whatsapp'] = 'Enter a Sri Lankan mobile number, 10 digits, or leave it empty if it is the same as your mobile.';
        }
        $nic = strtoupper($str('nic', 12, false, ''));
        if ($nic !== '' && !preg_match('/^(\d{9}[VX]|\d{12})$/', $nic)) {
            $e['nic'] = 'Enter a valid NIC: 9 digits and V, or 12 digits.';
        }

        $institute = $str('institute', 100, false, '');
        $subject = $str('subject', 60, true, 'Enter the main subject you teach.');
        $grades = $str('grades', 100, false, '');
        $mode = (string) ($in['mode'] ?? '');
        if (!isset(self::MODES[$mode])) {
            $e['mode'] = 'Choose how you teach.';
        }
        $district = $str('district', 45, false, '');
        if ($district !== '' && !$this->db->value('SELECT 1 FROM district WHERE district = :d', ['d' => $district])) {
            $e['district'] = 'Choose a district from the list.';
        }
        $students = trim((string) ($in['students'] ?? ''));
        if ($students !== '' && (filter_var($students, FILTER_VALIDATE_INT) === false || (int) $students < 1 || (int) $students > 100000)) {
            $e['students'] = 'Enter about how many students you have, numbers only, for example 120.';
        }
        $heard = $str('heard_from', 60, false, '');
        $message = $str('message', 500, false, '');

        $plan = (string) ($in['plan'] ?? '');
        if (!in_array($plan, array_column($this->plans(), 'key'), true)) {
            $e['plan'] = 'Choose a plan. You can change it later.';
        }
        $cycle = ($in['cycle'] ?? '') === 'yearly' ? 'yearly' : 'monthly';
        $offered = array_column($this->addons(), 'key');
        $addons = array_values(array_intersect(array_map('strval', (array) ($in['addons'] ?? [])), $offered));
        $promo = Promotions::find((string) ($in['promo'] ?? ''));
        if (empty($in['agree'])) {
            $e['agree'] = 'Please agree so that our team can contact you.';
        }
        if ($e) {
            throw new HttpException(422, 'validation_failed', 'Please check the highlighted fields.', $e);
        }

        $id = $this->db->insert(
            'INSERT INTO requested_users (user_fname, user_lname, email, mobile, whatsapp, nic, title_title_id, requested_date_time, request_status_request_status_id,
                    plan_key, billing_cycle, addons, institute_name, main_subject, grades, class_mode, district, expected_students, heard_from, message, ip, promo_code)
             VALUES (:f, :l, :e, :m, :w, :nic, :t, :now, :st, :p, :c, :a, :ins, :sub, :g, :mode, :d, :n, :h, :msg, :ip, :promo)',
            ['f' => $first, 'l' => $last, 'e' => $email, 'm' => $mobile, 'w' => $wa ?? $mobile, 'nic' => $nic === '' ? '0' : $nic, 't' => $title, 'now' => $now, 'st' => self::PENDING,
                'p' => $plan, 'c' => $cycle, 'a' => $addons ? json_encode($addons) : null, 'ins' => $institute ?: null, 'sub' => $subject, 'g' => $grades ?: null, 'mode' => $mode,
                'd' => $district ?: null, 'n' => $students === '' ? null : (int) $students, 'h' => $heard ?: null, 'msg' => $message ?: null, 'ip' => $ip, 'promo' => $promo['code'] ?? null],
        );
        return ['ref' => self::ref($id), 'id' => $id];
    }

    public static function ref(int $id): string
    {
        return sprintf('TR-%05d', $id);
    }

    // ================================================================ admin

    /** Every request, newest first, with the counts per status. */
    public function list(): array
    {
        $rows = array_map([$this, 'shape'], $this->db->all(self::SELECT . ' ORDER BY r.requested_date_time DESC, r.requested_user_id DESC'));
        $count = ['pending' => 0, 'approved' => 0, 'rejected' => 0, 'week' => 0];
        $weekAgo = date('Y-m-d H:i:s', strtotime('-7 days'));
        foreach ($rows as $r) {
            $count[$r['status']] = ($count[$r['status']] ?? 0) + 1;
            if ($r['requested_at'] >= $weekAgo) {
                $count['week']++;
            }
        }
        return ['requests' => $rows, 'counts' => $count];
    }

    public function one(int $id): array
    {
        $r = $this->db->one(self::SELECT . ' WHERE r.requested_user_id = :id', ['id' => $id]) ?? throw HttpException::notFound('This request was not found.');
        $out = $this->shape($r);
        $out['history'] = array_map(static fn ($l) => ['at' => $l['date_updated'], 'what' => $l['log'], 'note' => $l['note'], 'by' => trim($l['user_fname'] . ' ' . $l['user_lname'])], $this->db->all(
            'SELECT l.date_updated, l.log, l.note, u.user_fname, u.user_lname FROM requested_users_logs l JOIN users u ON u.user_id = l.users_user_id
              WHERE l.requested_users_requested_user_id = :id ORDER BY l.requested_users_log_id DESC',
            ['id' => $id],
        ));
        return $out;
    }

    /**
     * Makes the tutor (PlatformBilling::addTutor with the admin's plan / start choices) and marks the request approved.
     * @return int the new tutor's user id
     */
    public function approve(int $id, array $in, int $by, PlatformBilling $billing): int
    {
        $r = $this->one($id);
        if ($r['status'] !== 'pending') {
            throw new HttpException(409, 'already_decided', 'This request was already ' . $r['status'] . '.');
        }
        $tutorId = $billing->addTutor($in, $by);
        $this->db->transaction(function () use ($id, $r, $tutorId, $by, $in) {
            if ($r['nic'] !== '') {
                $this->db->execute('UPDATE users SET nic = :n WHERE user_id = :u AND (nic IS NULL OR nic = \'\')', ['n' => $r['nic'], 'u' => $tutorId]);
            }
            $this->db->execute(
                'UPDATE requested_users SET request_status_request_status_id = :s, tutor_id = :t, decided_by = :b, decided_at = NOW(), decision_note = :n WHERE requested_user_id = :id',
                ['s' => self::APPROVED, 't' => $tutorId, 'b' => $by, 'n' => mb_substr(trim((string) ($in['note'] ?? '')), 0, 255) ?: null, 'id' => $id],
            );
            $this->log($id, $by, 'Approved: tutor account made', trim((string) ($in['note'] ?? '')) ?: null);
        });
        return $tutorId;
    }

    public function reject(int $id, string $reason, int $by): void
    {
        $reason = trim($reason);
        if ($reason === '' || mb_strlen($reason) > 255) {
            throw new HttpException(422, 'validation_failed', 'Give a reason.', ['reason' => 'Say why, up to 255 characters. Only admins see it.']);
        }
        $r = $this->one($id);
        if ($r['status'] !== 'pending') {
            throw new HttpException(409, 'already_decided', 'This request was already ' . $r['status'] . '.');
        }
        $this->db->transaction(function () use ($id, $reason, $by) {
            $this->db->execute(
                'UPDATE requested_users SET request_status_request_status_id = :s, decided_by = :b, decided_at = NOW(), decision_note = :n WHERE requested_user_id = :id',
                ['s' => self::REJECTED, 'b' => $by, 'n' => $reason, 'id' => $id],
            );
            $this->log($id, $by, 'Rejected', $reason);
        });
    }

    /** A rejected request back to pending (rejected by mistake). */
    public function reopen(int $id, int $by): void
    {
        $r = $this->one($id);
        if ($r['status'] !== 'rejected') {
            throw new HttpException(409, 'not_rejected', 'Only a rejected request can be opened again.');
        }
        $this->db->execute('UPDATE requested_users SET request_status_request_status_id = :s, decided_by = NULL, decided_at = NULL, decision_note = NULL WHERE requested_user_id = :id', ['s' => self::PENDING, 'id' => $id]);
        $this->log($id, $by, 'Opened again', null);
    }

    private function log(int $id, int $by, string $what, ?string $note): void
    {
        $this->db->execute(
            'INSERT INTO requested_users_logs (users_user_id, date_updated, log, note, requested_users_requested_user_id) VALUES (:b, NOW(), :l, :n, :id)',
            ['b' => $by, 'l' => mb_substr($what, 0, 100), 'n' => $note, 'id' => $id],
        );
    }

    private const SELECT = 'SELECT r.*, t.title, d.user_fname AS d_fname, d.user_lname AS d_lname
          FROM requested_users r
          LEFT JOIN title t ON t.title_id = r.title_title_id
          LEFT JOIN users d ON d.user_id = r.decided_by';

    private function shape(array $r): array
    {
        $status = [self::APPROVED => 'approved', self::REJECTED => 'rejected'][(int) $r['request_status_request_status_id']] ?? 'pending';
        $addons = json_decode((string) ($r['addons'] ?? ''), true);
        return [
            'id' => (int) $r['requested_user_id'],
            'ref' => self::ref((int) $r['requested_user_id']),
            'title' => (string) ($r['title'] ?? ''),
            'title_id' => (int) $r['title_title_id'],
            'first_name' => (string) $r['user_fname'],
            'last_name' => (string) $r['user_lname'],
            'name' => trim(($r['title'] ? $r['title'] . '. ' : '') . $r['user_fname'] . ' ' . $r['user_lname']),
            'email' => (string) $r['email'],
            'mobile' => PlatformBilling::phone((string) $r['mobile']) ?? (string) $r['mobile'],
            'whatsapp' => PlatformBilling::phone((string) $r['whatsapp']) ?? (string) $r['whatsapp'],
            'nic' => in_array((string) $r['nic'], ['', '0'], true) ? '' : (string) $r['nic'],
            'plan' => $r['plan_key'] ?? null,
            'cycle' => (string) ($r['billing_cycle'] ?? 'monthly'),
            'addons' => is_array($addons) ? $addons : [],
            'institute' => (string) ($r['institute_name'] ?? ''),
            'subject' => (string) ($r['main_subject'] ?? ''),
            'grades' => (string) ($r['grades'] ?? ''),
            'mode' => $r['class_mode'] ?? null,
            'mode_label' => self::MODES[$r['class_mode'] ?? ''] ?? '',
            'district' => (string) ($r['district'] ?? ''),
            'students' => $r['expected_students'] === null ? null : (int) $r['expected_students'],
            'heard_from' => (string) ($r['heard_from'] ?? ''),
            'message' => (string) ($r['message'] ?? ''),
            'requested_at' => (string) $r['requested_date_time'],
            'status' => $status,
            'tutor_id' => $r['tutor_id'] === null ? null : (int) $r['tutor_id'],
            'decided_at' => $r['decided_at'],
            'decided_by' => $r['decided_by'] ? trim($r['d_fname'] . ' ' . $r['d_lname']) : null,
            'decision_note' => $r['decision_note'],
            'promo' => Promotions::find($r['promo_code'] ?? null),
        ];
    }
}
