<?php
declare(strict_types=1);

namespace Enlixo\Platform;

use Enlixo\Core\Database;
use Enlixo\Messaging\Mailer;

/**
 * The welcome a new tutor gets when the admin adds them or approves their sign-up request (SaaS practice: the
 * account is ready, here is how to get in, here is your plan and when the trial ends).
 * A one-time "Set your password" link (tutor/setup-password.php?token=, status 1, valid 7 days) is made and sent by
 * e-mail; the admin also gets the link back, so it can be copied or sent on WhatsApp when the e-mail does not arrive.
 * The tutor can also always sign in with a WhatsApp code (tutor/index.php).
 */
final class TutorWelcome
{
    public const LINK_DAYS = 7;

    public function __construct(private readonly Database $db, private readonly Mailer $mailer)
    {
    }

    /** @return array{sent:bool, link:string, email:string} */
    public function send(int $tutorId, string $baseUrl): array
    {
        $t = $this->db->one(
            'SELECT u.user_fname, u.user_lname, u.user_email, u.user_whatsapp, s.status, s.trial_ends_at, s.billing_cycle, s.institute_name, p.payment_plan plan_name
               FROM users u
               LEFT JOIN users_has_payment_plans s ON s.users_user_id = u.user_id
               LEFT JOIN payment_plans p ON p.payment_plan_id = s.payment_plans_payment_plan_id
              WHERE u.user_id = :t ORDER BY s.users_has_payment_plan_id DESC LIMIT 1',
            ['t' => $tutorId],
        );
        if (!$t) {
            return ['sent' => false, 'link' => '', 'email' => ''];
        }
        $token = bin2hex(random_bytes(20));
        $this->db->execute('UPDATE users SET user_verification = :v, user_reset_time = :r WHERE user_id = :t', ['v' => $token, 'r' => (string) time(), 't' => $tutorId]);
        $base = rtrim($baseUrl, '/');
        $link = $base . '/tutor/setup-password?token=' . $token;
        $signIn = $base . '/tutor/';
        $plan = strtoupper((string) ($t['plan_name'] ?? ''));
        $trial = ($t['status'] ?? '') === 'trial' && $t['trial_ends_at'] ? date('j F Y', strtotime((string) $t['trial_ends_at'])) : null;
        $name = (string) $t['user_fname'];
        $res = $this->mailer->send(
            (string) $t['user_email'], trim($t['user_fname'] . ' ' . $t['user_lname']), 'Welcome to ENLIXO Tutor: your account is ready',
            self::html($name, $plan, $trial, $link, $signIn, (string) $t['user_email']),
            "Hi {$name},\n\nYour ENLIXO Tutor account is ready" . ($plan ? " on the {$plan} plan" : '') . ($trial ? ", free until {$trial}" : '') . ".\n\n"
                . "1. Set your password: {$link}\n   (the link works for " . self::LINK_DAYS . " days)\n2. Sign in at {$signIn} with " . $t['user_email'] . " or with a WhatsApp code.\n\nENLIXO Tutor",
            null, 'ENLIXO Tutor',
        );
        return ['sent' => $res['ok'], 'link' => $link, 'email' => (string) $t['user_email']];
    }

    public static function html(string $name, string $plan, ?string $trial, string $link, string $signIn, string $email): string
    {
        $e = static fn ($s) => htmlspecialchars((string) $s, ENT_QUOTES, 'UTF-8');
        $planLine = $plan ? 'You are on the <b>' . $e($plan) . '</b> plan' . ($trial ? ', <b>free until ' . $e($trial) . '</b>' : '') . '.' : '';
        return '<!doctype html><html><body style="margin:0;background:#f4f6fb;font-family:Arial,Helvetica,sans-serif;color:#1d2026">'
            . '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="padding:24px 12px"><tr><td align="center">'
            . '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="max-width:600px;background:#ffffff">'
            . '<tr><td style="background:#0061d2;color:#fff;padding:22px 24px;font-size:20px;font-weight:bold">Welcome to ENLIXO Tutor</td></tr>'
            . '<tr><td style="padding:24px;font-size:15px;line-height:1.6">'
            . '<p style="margin:0 0 12px">Hi ' . $e($name) . ',</p><p style="margin:0 0 12px">Your account is ready. ' . $planLine . '</p>'
            . '<p style="margin:0 0 20px">Set your password to sign in with <b>' . $e($email) . '</b>:</p>'
            . '<p style="margin:0 0 20px"><a href="' . $e($link) . '" style="display:inline-block;background:#0061d2;color:#fff;text-decoration:none;padding:12px 28px;font-weight:bold">Set my password</a></p>'
            . '<p style="margin:0 0 12px;font-size:13px;color:#6e7485">The button works for ' . self::LINK_DAYS . ' days. You can also sign in at <a href="' . $e($signIn) . '">' . $e($signIn) . '</a> with a code sent to your WhatsApp.</p>'
            . '<p style="margin:16px 0 0">First steps: create your first class, add your students (or upload them from Excel), and share your class link.</p>'
            . '</td></tr><tr><td style="padding:16px 24px;border-top:1px solid #e9eaf0;font-size:12px;color:#6e7485">ENLIXO Tutor · Powered by ENVIIZ</td></tr>'
            . '</table></td></tr></table></body></html>';
    }
}
