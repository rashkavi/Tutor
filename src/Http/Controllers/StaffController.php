<?php
declare(strict_types=1);

namespace Enlixo\Http\Controllers;

use Enlixo\App;
use Enlixo\Core\AuthContext;
use Enlixo\Core\HttpException;
use Enlixo\Core\JsonResponse;
use Enlixo\Core\Request;

/**
 * Settings → Staff & Roles (tutor/staff.php): the tutor's assistants. A staff member is a `users` row with role 3
 * (Staff) and a `tutor_staff` row that ties them to ONE tutor and ONE of that tutor's roles (`tutor_roles`).
 * They sign in on the tutor sign-in page with their own e-mail and password (tutor/api/signInWithEmail.php) and
 * work inside the tutor's account; the API gives them only what their role allows (PermissionService).
 * Needs the GROW feature `staff.custom_roles` and the permission `settings.staff`.
 *   GET  /api/v1/tutor/staff                 staff + roles to choose from
 *   POST /api/v1/tutor/staff                 {first_name, last_name, email, mobile, role_id} -> invite (set-password e-mail)
 *   PUT  /api/v1/tutor/staff/{id}            {role_id?, active?}
 *   POST /api/v1/tutor/staff/{id}/resend     a new set-password link
 */
final class StaffController
{
    private const STAFF_ROLE = 3;
    public const LINK_DAYS = 7;

    public function __construct(private readonly App $app)
    {
    }

    private function guard(Request $r): AuthContext
    {
        $auth = $r->auth();
        $this->app->permissions()->require($auth, 'settings.staff');
        return $auth;
    }

    public function index(Request $r): JsonResponse
    {
        $auth = $this->guard($r);
        $db = $this->app->db();
        $rows = $db->all(
            'SELECT s.tutor_staff_id, s.status_status_id, s.added_at, s.tutor_roles_tutor_role_id role_id, r.name role_name,
                    u.user_id, u.user_fname, u.user_lname, u.user_email, u.user_mobile, (u.user_password IS NOT NULL AND u.user_password <> \'\') has_password,
                    (SELECT MAX(d.last_active) FROM users_devices d WHERE d.users_user_id = u.user_id) last_active
               FROM tutor_staff s
               JOIN users u ON u.user_id = s.staff_user_id
               LEFT JOIN tutor_roles r ON r.tutor_role_id = s.tutor_roles_tutor_role_id
              WHERE s.tutor_id = :t ORDER BY s.status_status_id, u.user_fname, u.user_lname',
            ['t' => $auth->tenantId()],
        );
        $roles = $db->all('SELECT tutor_role_id id, name, description FROM tutor_roles WHERE tutor_id = :t ORDER BY name', ['t' => $auth->tenantId()]);
        return JsonResponse::ok([
            'staff' => array_map(static fn ($s) => [
                'id' => (int) $s['tutor_staff_id'], 'user_id' => (int) $s['user_id'],
                'name' => trim($s['user_fname'] . ' ' . $s['user_lname']), 'first_name' => (string) $s['user_fname'], 'last_name' => (string) $s['user_lname'],
                'email' => (string) $s['user_email'], 'mobile' => (string) ($s['user_mobile'] ?? ''),
                'role_id' => (int) $s['role_id'], 'role' => (string) ($s['role_name'] ?? '—'),
                'active' => (int) $s['status_status_id'] === 1, 'joined' => (bool) $s['has_password'],
                'added_at' => (string) $s['added_at'], 'last_active' => $s['last_active'] ? (string) $s['last_active'] : null,
            ], $rows),
            'roles' => array_map(static fn ($x) => ['id' => (int) $x['id'], 'name' => (string) $x['name'], 'description' => (string) ($x['description'] ?? '')], $roles),
        ]);
    }

    public function store(Request $r): JsonResponse
    {
        $auth = $this->guard($r);
        $tutor = $auth->tenantId();
        $db = $this->app->db();
        $in = $r->body;
        $e = [];
        $first = trim((string) ($in['first_name'] ?? ''));
        $last = trim((string) ($in['last_name'] ?? ''));
        $email = mb_strtolower(trim((string) ($in['email'] ?? '')));
        $mobile = preg_replace('/[\s\-]/', '', (string) ($in['mobile'] ?? ''));
        $roleId = (int) ($in['role_id'] ?? 0);
        if ($first === '' || mb_strlen($first) > 45) {
            $e['first_name'] = 'Enter the first name (up to 45 characters).';
        }
        if ($last === '' || mb_strlen($last) > 45) {
            $e['last_name'] = 'Enter the last name (up to 45 characters).';
        }
        if (!filter_var($email, FILTER_VALIDATE_EMAIL) || mb_strlen($email) > 100) {
            $e['email'] = 'Enter a valid e-mail address, for example nimal@gmail.com.';
        } elseif ($db->value('SELECT 1 FROM users WHERE LOWER(user_email) = :e', ['e' => $email])) {
            $e['email'] = 'This e-mail address already has an ENLIXO account. Use another address.';
        }
        if ($mobile !== '') {
            if (preg_match('/^\+?94(7\d{8})$/', $mobile, $m)) {
                $mobile = '0' . $m[1];
            }
            if (!preg_match('/^07\d{8}$/', $mobile)) {
                $e['mobile'] = 'Enter a Sri Lankan mobile number, for example 0771234567.';
            } elseif ($db->value('SELECT 1 FROM users WHERE user_mobile = :m OR user_whatsapp = :m', ['m' => $mobile])) {
                $e['mobile'] = 'This number already has an ENLIXO account.';
            }
        }
        if ($roleId <= 0 || !$db->value('SELECT 1 FROM tutor_roles WHERE tutor_role_id = :r AND tutor_id = :t', ['r' => $roleId, 't' => $tutor])) {
            $e['role_id'] = 'Choose what this person may do. Make a role first on the Roles tab if you have none.';
        }
        if ($e) {
            throw HttpException::validation($e);
        }
        $title = (int) ($db->value('SELECT title_id FROM title ORDER BY title_id LIMIT 1') ?? 1);
        $id = $db->transaction(function () use ($db, $first, $last, $email, $mobile, $title, $auth, $tutor, $roleId) {
            $userId = $db->insert(
                'INSERT INTO users (title_title_id, user_fname, user_lname, user_email, user_mobile, user_reg_date, status_status_id, user_roles_user_role_id, added_by)
                 VALUES (:ti, :f, :l, :e, :m, NOW(), 1, :role, :by)',
                ['ti' => $title, 'f' => $first, 'l' => $last, 'e' => $email, 'm' => $mobile !== '' ? $mobile : null, 'role' => self::STAFF_ROLE, 'by' => $auth->userId],
            );
            return $db->insert('INSERT INTO tutor_staff (tutor_id, staff_user_id, tutor_roles_tutor_role_id, status_status_id, added_at) VALUES (:t, :u, :r, 1, NOW())',
                ['t' => $tutor, 'u' => $userId, 'r' => $roleId]);
        });
        $invite = $this->invite((int) $id, $r);
        $this->audit($auth, 'staff.invited', $id, ['email' => $email, 'role_id' => $roleId]);
        return JsonResponse::ok(['id' => $id, 'invite' => $invite,
            'message' => $invite['sent'] ? "Invitation sent to $email." : 'Staff member added. The e-mail could not be sent: copy the link and send it on WhatsApp.'], [], 201);
    }

    public function update(Request $r): JsonResponse
    {
        $auth = $this->guard($r);
        $row = $this->row($auth, (int) $r->param('id'));
        $db = $this->app->db();
        $set = [];
        if (array_key_exists('role_id', $r->body)) {
            $roleId = (int) $r->body['role_id'];
            if (!$db->value('SELECT 1 FROM tutor_roles WHERE tutor_role_id = :r AND tutor_id = :t', ['r' => $roleId, 't' => $auth->tenantId()])) {
                throw HttpException::validation(['role_id' => 'Choose one of your roles.']);
            }
            $set['tutor_roles_tutor_role_id'] = $roleId;
        }
        if (array_key_exists('active', $r->body)) {
            $set['status_status_id'] = filter_var($r->body['active'], FILTER_VALIDATE_BOOL) ? 1 : 2;
        }
        if (!$set) {
            throw HttpException::validation(['role_id' => 'Nothing to change.']);
        }
        $db->execute('UPDATE tutor_staff SET ' . implode(', ', array_map(static fn ($k) => "$k = :$k", array_keys($set))) . ' WHERE tutor_staff_id = :id', $set + ['id' => $row['tutor_staff_id']]);
        if (isset($set['status_status_id']) && $set['status_status_id'] === 2) {
            // signed-in devices of that person stop at their next page (sign-in checks tutor_staff again)
            $db->execute('UPDATE users_devices SET is_active = 0 WHERE users_user_id = :u', ['u' => $row['staff_user_id']]);
        }
        $this->audit($auth, 'staff.updated', (int) $row['tutor_staff_id'], $set);
        $msg = isset($set['status_status_id']) ? ($set['status_status_id'] === 1 ? 'Staff member switched on. They can sign in again.' : 'Staff member switched off. They can no longer sign in.') : 'Role changed. It applies from their next page.';
        return JsonResponse::ok(['message' => $msg]);
    }

    public function resend(Request $r): JsonResponse
    {
        $auth = $this->guard($r);
        $row = $this->row($auth, (int) $r->param('id'));
        if ((int) $row['status_status_id'] !== 1) {
            throw HttpException::validation(['id' => 'Switch this staff member on first.']);
        }
        $invite = $this->invite((int) $row['tutor_staff_id'], $r);
        $this->audit($auth, 'staff.invite_resent', (int) $row['tutor_staff_id'], []);
        return JsonResponse::ok(['invite' => $invite, 'message' => $invite['sent'] ? 'A new link was sent to ' . $invite['email'] . '.' : 'The e-mail could not be sent: copy the link and send it on WhatsApp.']);
    }

    private function row(AuthContext $auth, int $id): array
    {
        return $this->app->db()->one('SELECT * FROM tutor_staff WHERE tutor_staff_id = :id AND tutor_id = :t', ['id' => $id, 't' => $auth->tenantId()])
            ?? throw HttpException::notFound('This staff member was not found.');
    }

    private function audit(AuthContext $auth, string $action, int $id, array $data): void
    {
        try {
            (new \Enlixo\Roles\PdoAuditLogger($this->app->db(), (string) ($_SERVER['REMOTE_ADDR'] ?? '')))->log($auth->tenantId(), $auth->userId, $action, 'tutor_staff', (string) $id, $data);
        } catch (\Throwable $e) {
            error_log('Staff audit: ' . $e->getMessage());
        }
    }

    /** One-time set-password link (tutor/setup-password, 7 days), e-mailed; the link also comes back so it can go on WhatsApp. */
    private function invite(int $staffId, Request $r): array
    {
        $db = $this->app->db();
        $s = $db->one(
            'SELECT u.user_id, u.user_fname, u.user_lname, u.user_email, t.user_fname tf, t.user_lname tl
               FROM tutor_staff s JOIN users u ON u.user_id = s.staff_user_id JOIN users t ON t.user_id = s.tutor_id
              WHERE s.tutor_staff_id = :id',
            ['id' => $staffId],
        );
        $token = bin2hex(random_bytes(20));
        $db->execute('UPDATE users SET user_verification = :v, user_reset_time = :r WHERE user_id = :u', ['v' => $token, 'r' => (string) time(), 'u' => $s['user_id']]);
        $base = rtrim(PlatformController::baseUrl($r), '/');
        $link = $base . '/tutor/setup-password?token=' . $token;
        $tutor = trim($s['tf'] . ' ' . $s['tl']);
        $e = static fn ($x) => htmlspecialchars((string) $x, ENT_QUOTES, 'UTF-8');
        $html = '<!doctype html><html><body style="margin:0;background:#f4f6fb;font-family:Arial,Helvetica,sans-serif;color:#1d2026">'
            . '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="padding:24px 12px"><tr><td align="center">'
            . '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="max-width:600px;background:#ffffff">'
            . '<tr><td style="background:#0061d2;color:#fff;padding:22px 24px;font-size:20px;font-weight:bold">You are invited to ENLIXO Tutor</td></tr>'
            . '<tr><td style="padding:24px;font-size:15px;line-height:1.6">'
            . '<p style="margin:0 0 12px">Hi ' . $e($s['user_fname']) . ',</p>'
            . '<p style="margin:0 0 12px"><b>' . $e($tutor) . '</b> added you as a staff member of their classes on ENLIXO Tutor.</p>'
            . '<p style="margin:0 0 20px">Set your password, then sign in at <a href="' . $e($base . '/tutor/') . '">' . $e($base . '/tutor/') . '</a> with <b>' . $e($s['user_email']) . '</b>.</p>'
            . '<p style="margin:0 0 20px"><a href="' . $e($link) . '" style="display:inline-block;background:#0061d2;color:#fff;text-decoration:none;padding:12px 28px;font-weight:bold">Set my password</a></p>'
            . '<p style="margin:0;font-size:13px;color:#6e7485">The button works for ' . self::LINK_DAYS . ' days. Did not expect this? You can ignore this e-mail.</p>'
            . '</td></tr><tr><td style="padding:16px 24px;border-top:1px solid #e9eaf0;font-size:12px;color:#6e7485">ENLIXO Tutor · Powered by ENVIIZ</td></tr>'
            . '</table></td></tr></table></body></html>';
        try {
            $res = $this->app->mailer()->send((string) $s['user_email'], trim($s['user_fname'] . ' ' . $s['user_lname']), "$tutor added you to ENLIXO Tutor", $html,
                "Hi {$s['user_fname']},\n\n$tutor added you as a staff member on ENLIXO Tutor.\nSet your password: $link (works for " . self::LINK_DAYS . " days)\nThen sign in at $base/tutor/ with {$s['user_email']}.\n\nENLIXO Tutor",
                null, 'ENLIXO Tutor');
            $sent = (bool) ($res['ok'] ?? false);
        } catch (\Throwable $ex) {
            error_log('Staff invite mail: ' . $ex->getMessage());
            $sent = false;
        }
        return ['sent' => $sent, 'link' => $link, 'email' => (string) $s['user_email']];
    }
}
