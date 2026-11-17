<?php
declare(strict_types=1);

namespace Enlixo\Messaging;

use Enlixo\Core\Database;
use Enlixo\Core\HttpException;
use Enlixo\Entitlements\EntitlementService;

/**
 * Message Center: a tutor writes one message to the students and / or parents of chosen classes and sends it by
 * e-mail or WhatsApp.
 *
 * WhatsApp has three ways (tutor_whatsapp.mode), so every tutor can use it:
 *   phone  - "From my phone" (every plan, free): the page opens WhatsApp with the text ready, one person at a time,
 *            from the tutor's own phone or WhatsApp Web. Nothing to set up.
 *   enlixo - "ENLIXO WhatsApp number" (ENVIIZ WhatsApp add-on): sent automatically from ENLIXO's number with the
 *            approved template WHATSAPP_NOTICE_TEMPLATE ({{1}} = tutor / institute, {{2}} = the message).
 *   own    - "My WhatsApp Business number" (GROW and up): sent automatically from the tutor's own number through the
 *            WhatsApp Cloud API, connected with Facebook (Embedded Signup, keeps the WhatsApp Business app working)
 *            or with the phone number id + access token. Template with one variable {{1}} = the message.
 *
 * Sending is split in batches (config messaging.batch) so a big class never times out: create() makes one row per
 * person, sendBatch() sends the next ones and returns the progress.
 */
final class MessageService
{
    public const MODES = ['phone', 'enlixo', 'own'];
    public const MAX_BODY = ['email' => 5000, 'whatsapp' => 1000];
    public const OWN_TEMPLATE_TEXT = "Message from your class:\n\n{{1}}\n\nReply to this chat if you have a question.";

    public function __construct(
        private readonly Database $db,
        private readonly EntitlementService $entitlements,
        private readonly Mailer $mailer,
        private readonly WhatsAppCloud $wa,
        private readonly SecretBox $box,
        private readonly array $cfg,
    ) {
    }

    // ------------------------------------------------------------------ settings

    /** What this tutor can use, and how WhatsApp is set up (never the token itself). */
    public function setup(int $tutorId): array
    {
        $w = $this->whatsappRow($tutorId);
        $own = $this->entitlements->has($tutorId, 'comms.whatsapp');
        $enlixo = $this->entitlements->has($tutorId, 'comms.enviiz_whatsapp');
        $mode = $w['mode'] ?? 'phone';
        if (($mode === 'own' && (!$own || empty($w['token_enc']))) || ($mode === 'enlixo' && !$enlixo)) {
            $mode = 'phone';
        }
        $wcfg = $this->cfg['whatsapp'] ?? [];
        return [
            'tutor' => $this->brand($tutorId),
            'email' => ['allowed' => $this->entitlements->has($tutorId, 'comms.email'), 'ready' => $this->mailer->ready()],
            'whatsapp' => [
                'mode' => $mode,
                'modes' => [
                    'phone' => ['allowed' => true, 'ready' => true],
                    'enlixo' => ['allowed' => $enlixo, 'ready' => $enlixo && $this->platformToken() !== ''],
                    'own' => ['allowed' => $own, 'ready' => $own && !empty($w['token_enc'])],
                ],
                'own' => $w && !empty($w['token_enc']) ? [
                    'number' => $w['display_number'], 'name' => $w['verified_name'], 'phone_number_id' => $w['phone_number_id'],
                    'business_account_id' => $w['business_account_id'], 'template' => $w['template_name'], 'lang' => $w['template_lang'],
                    'via' => $w['connected_via'], 'connected_at' => $w['connected_at'], 'last_error' => $w['last_error'],
                ] : null,
                'embedded' => ($wcfg['meta_app_id'] ?? '') !== '' && ($wcfg['embedded_config_id'] ?? '') !== '' && ($wcfg['meta_app_secret'] ?? '') !== ''
                    ? ['app_id' => $wcfg['meta_app_id'], 'config_id' => $wcfg['embedded_config_id']] : null,
                'template_text' => self::OWN_TEMPLATE_TEXT,
            ],
        ];
    }

    public function setMode(int $tutorId, string $mode): array
    {
        if (!in_array($mode, self::MODES, true)) {
            throw HttpException::validation(['mode' => 'Choose how WhatsApp messages are sent.']);
        }
        $s = $this->setup($tutorId)['whatsapp']['modes'][$mode];
        if (!$s['allowed']) {
            throw HttpException::validation(['mode' => $mode === 'own' ? 'Your own WhatsApp number is on the GROW plan.' : 'The ENLIXO WhatsApp number is an add-on. Ask ENLIXO to switch it on.']);
        }
        if (!$s['ready']) {
            throw HttpException::validation(['mode' => $mode === 'own' ? 'Connect your WhatsApp Business number first.' : 'The ENLIXO WhatsApp number is not ready yet. Please tell ENLIXO support.']);
        }
        $this->db->execute('INSERT INTO tutor_whatsapp (tutor_id, mode) VALUES (:t, :m) ON DUPLICATE KEY UPDATE mode = VALUES(mode)', ['t' => $tutorId, 'm' => $mode]);
        return $this->setup($tutorId);
    }

    /** Own number, typed in: phone number id + access token (+ business account id) and the template. Checked with Meta first. */
    public function connectManual(int $tutorId, array $in): array
    {
        $this->entitlements->require($tutorId, 'comms.whatsapp');
        $w = $this->whatsappRow($tutorId);
        $phoneId = preg_replace('/\D+/', '', (string) ($in['phone_number_id'] ?? '')) ?? '';
        $waba = preg_replace('/\D+/', '', (string) ($in['business_account_id'] ?? '')) ?? '';
        $token = trim((string) ($in['token'] ?? ''));
        $template = strtolower(trim((string) ($in['template'] ?? '')));
        $lang = trim((string) ($in['lang'] ?? 'en')) ?: 'en';
        $errors = [];
        if (!preg_match('/^\d{8,20}$/', $phoneId)) {
            $errors['phone_number_id'] = 'Enter the Phone number ID from WhatsApp Manager › API Setup (only numbers, for example 109876543210987).';
        }
        if ($token === '' && empty($w['token_enc'])) {
            $errors['token'] = 'Paste the permanent access token (a System User token from Meta Business Settings).';
        } elseif ($token !== '' && (strlen($token) < 40 || preg_match('/\s/', $token))) {
            $errors['token'] = 'This does not look like a WhatsApp access token. Copy it again without spaces.';
        }
        if (!preg_match('/^[a-z0-9_]{1,100}$/', $template)) {
            $errors['template'] = 'Enter the template name exactly as in WhatsApp Manager (small letters, numbers and _), for example class_notice.';
        }
        if (!preg_match('/^[a-z]{2}(_[A-Z]{2})?$/', $lang)) {
            $errors['lang'] = 'Enter the template language code, for example en or en_US.';
        }
        if ($errors) {
            throw HttpException::validation($errors);
        }
        $useToken = $token !== '' ? $token : (string) $this->box->open($w['token_enc'] ?? null);
        $check = $this->wa->check($useToken, $phoneId);
        if (!$check['ok']) {
            throw HttpException::validation(['token' => 'WhatsApp said: ' . $check['error']]);
        }
        $this->saveOwn($tutorId, $phoneId, $waba ?: null, $useToken, $check, $template, $lang, 'manual');
        return $this->setup($tutorId);
    }

    /** Own number through Facebook (Embedded Signup): the code from the popup + the ids it reported. */
    public function connectEmbedded(int $tutorId, array $in): array
    {
        $this->entitlements->require($tutorId, 'comms.whatsapp');
        $wcfg = $this->cfg['whatsapp'] ?? [];
        if (($wcfg['meta_app_id'] ?? '') === '' || ($wcfg['meta_app_secret'] ?? '') === '') {
            throw new HttpException(409, 'not_configured', 'Connect with Facebook is not set up on this server. Use the form instead.');
        }
        $code = trim((string) ($in['code'] ?? ''));
        $phoneId = preg_replace('/\D+/', '', (string) ($in['phone_number_id'] ?? '')) ?? '';
        $waba = preg_replace('/\D+/', '', (string) ($in['business_account_id'] ?? '')) ?? '';
        if ($code === '' || $phoneId === '') {
            throw HttpException::validation(['code' => 'Facebook did not finish the connection. Please try again.']);
        }
        $x = $this->wa->exchangeCode($wcfg['meta_app_id'], $wcfg['meta_app_secret'], $code);
        if (!$x['ok']) {
            throw HttpException::validation(['code' => 'WhatsApp said: ' . $x['error']]);
        }
        $check = $this->wa->check($x['token'], $phoneId);
        if (!$check['ok']) {
            throw HttpException::validation(['code' => 'WhatsApp said: ' . $check['error']]);
        }
        $template = strtolower(trim((string) ($in['template'] ?? ''))) ?: 'class_notice';
        $this->saveOwn($tutorId, $phoneId, $waba ?: null, $x['token'], $check, $template, 'en', 'embedded');
        return $this->setup($tutorId);
    }

    public function disconnect(int $tutorId): array
    {
        $this->db->execute(
            "UPDATE tutor_whatsapp SET mode = 'phone', token_enc = NULL, phone_number_id = NULL, business_account_id = NULL, display_number = NULL,
                    verified_name = NULL, connected_via = NULL, connected_at = NULL, last_error = NULL WHERE tutor_id = :t",
            ['t' => $tutorId],
        );
        return $this->setup($tutorId);
    }

    /** A test message to the tutor's own number with the current way of sending. */
    public function test(int $tutorId, string $to): array
    {
        $digits = WhatsAppCloud::digits($to);
        if (!$digits) {
            throw HttpException::validation(['to' => 'Enter a WhatsApp number, for example 0771234567.']);
        }
        $mode = $this->setup($tutorId)['whatsapp']['mode'];
        if ($mode === 'phone') {
            return ['link' => 'https://wa.me/' . $digits . '?text=' . rawurlencode('Test message from ' . $this->brand($tutorId) . ' (ENLIXO Message Center).')];
        }
        $r = $this->sendWhatsApp($tutorId, $mode, $digits, 'This is a test message from the ENLIXO Message Center.');
        if (!$r['ok']) {
            throw HttpException::validation(['to' => 'Not sent: ' . $r['error']]);
        }
        return ['sent' => true];
    }

    /**
     * One automatic notice to one person (a parcel was sent, ...): WhatsApp when the tutor's WhatsApp sends by itself
     * (their Business number or ENLIXO's), else e-mail when the person has an address and e-mail works, else nothing.
     * Never throws; system notices do not count against the monthly Message Center limit.
     * @return array{channel:?string, ok:bool, error:?string}
     */
    public function notify(int $tutorId, ?string $whatsapp, ?string $email, string $name, string $subject, string $text): array
    {
        try {
            $setup = $this->setup($tutorId);
            $digits = $whatsapp ? WhatsAppCloud::digits($whatsapp) : null;
            if ($digits && ($setup['whatsapp']['mode'] ?? 'phone') !== 'phone') {
                $r = $this->sendWhatsApp($tutorId, (string) $setup['whatsapp']['mode'], $digits, $text);
                if ($r['ok']) {
                    return ['channel' => 'whatsapp', 'ok' => true, 'error' => null];
                }
            }
            if ($email && filter_var($email, FILTER_VALIDATE_EMAIL) && !empty($setup['email']['ready'])) {
                $brand = $this->brand($tutorId);
                $r = $this->mailer->send($email, $name, $subject, self::emailHtml($subject, $text, $brand), $text . "\n\n— " . $brand, null, $brand . ' via ENLIXO');
                return ['channel' => 'email', 'ok' => (bool) $r['ok'], 'error' => $r['ok'] ? null : (string) $r['error']];
            }
            return ['channel' => null, 'ok' => false, 'error' => 'No automatic channel (WhatsApp sends from the phone and no e-mail address)'];
        } catch (\Throwable $e) {
            error_log('Notice not sent: ' . $e->getMessage());
            return ['channel' => null, 'ok' => false, 'error' => $e->getMessage()];
        }
    }

    // ------------------------------------------------------------------ audience and messages

    /** Who would get it, before sending: counts and the first names. */
    public function preview(int $tutorId, array $in): array
    {
        [$channel, , $audience, $classes] = $this->choices($tutorId, $in, false);
        $people = $this->people($tutorId, $classes, $audience, $channel);
        $ok = array_values(array_filter($people, static fn ($p) => $p['address'] !== null));
        return [
            'total' => count($people),
            'reachable' => count($ok),
            'missing' => count($people) - count($ok),
            'students' => count(array_filter($ok, static fn ($p) => $p['kind'] === 'student')),
            'parents' => count(array_filter($ok, static fn ($p) => $p['kind'] === 'parent')),
            'sample' => array_slice(array_map(static fn ($p) => ['name' => $p['name'], 'kind' => $p['kind'], 'address' => $p['address'], 'class' => $p['class']], $ok), 0, 5),
        ];
    }

    public function create(int $tutorId, int $byUserId, array $in): array
    {
        [$channel, $mode, $audience, $classes] = $this->choices($tutorId, $in, true);
        $subject = trim((string) ($in['subject'] ?? ''));
        $body = trim(str_replace("\r\n", "\n", (string) ($in['body'] ?? '')));
        $errors = [];
        if ($channel === 'email' && ($subject === '' || mb_strlen($subject) > 150)) {
            $errors['subject'] = $subject === '' ? 'Enter the subject of the e-mail, for example "No class this Saturday".' : 'Keep the subject under 150 characters.';
        }
        if ($body === '') {
            $errors['body'] = 'Write the message.';
        } elseif (mb_strlen($body) > self::MAX_BODY[$channel]) {
            $errors['body'] = 'Keep the message under ' . self::MAX_BODY[$channel] . ' characters.';
        }
        if ($errors) {
            throw HttpException::validation($errors);
        }
        $people = $this->people($tutorId, $classes, $audience, $channel);
        if (!$people) {
            throw HttpException::validation(['classes' => 'Nobody is in the chosen classes yet.']);
        }
        // the plan's messages a month (FREE 100, STARTER 1,000, GROW unlimited): one per person reached
        $this->entitlements->requireWithin($tutorId, 'messages.month', count(array_filter($people, static fn ($p) => $p['address'] !== null)));
        $now = $this->now();
        $id = $this->db->transaction(function () use ($tutorId, $byUserId, $channel, $mode, $audience, $subject, $body, $classes, $people, $now) {
            $skipped = count(array_filter($people, static fn ($p) => $p['address'] === null));
            $id = $this->db->insert(
                'INSERT INTO tutor_messages (tutor_id, channel, mode, audience, subject, body, class_ids, total, skipped, status, created_by, created_at)
                 VALUES (:t, :c, :m, :a, :s, :b, :cl, :n, :sk, :st, :by, :at)',
                ['t' => $tutorId, 'c' => $channel, 'm' => $mode, 'a' => $audience, 's' => $channel === 'email' ? $subject : null, 'b' => $body,
                    'cl' => implode(',', $classes), 'n' => count($people), 'sk' => $skipped, 'st' => count($people) === $skipped ? 'done' : 'sending', 'by' => $byUserId, 'at' => $now],
            );
            foreach ($people as $p) {
                $this->db->execute(
                    'INSERT INTO tutor_message_recipients (message_id, kind, student_id, parent_id, class_name, name, address, status, error)
                     VALUES (:m, :k, :s, :p, :c, :n, :a, :st, :e)',
                    ['m' => $id, 'k' => $p['kind'], 's' => $p['student_id'], 'p' => $p['parent_id'], 'c' => $p['class'], 'n' => $p['name'], 'a' => $p['address'],
                        'st' => $p['address'] === null ? 'skipped' : 'pending', 'e' => $p['address'] === null ? ($channel === 'email' ? 'No e-mail address' : 'No WhatsApp number') : null],
                );
            }
            return $id;
        });
        return $this->detail($tutorId, $id);
    }

    /** Sends the next batch (e-mail, or WhatsApp through ENLIXO / the tutor's own number). */
    public function sendBatch(int $tutorId, int $messageId): array
    {
        $m = $this->message($tutorId, $messageId);
        if ($m['mode'] === 'phone') {
            throw HttpException::validation(['mode' => 'Messages from your phone are sent one by one with the WhatsApp buttons.']);
        }
        $brand = $this->brand($tutorId);
        $replyTo = (string) ($this->db->value('SELECT user_email FROM users WHERE user_id = :t', ['t' => $tutorId]) ?? '');
        $rows = $this->db->all(
            "SELECT * FROM tutor_message_recipients WHERE message_id = :m AND status = 'pending' ORDER BY recipient_id LIMIT " . max(1, (int) ($this->cfg['batch'] ?? 25)),
            ['m' => $messageId],
        );
        foreach ($rows as $r) {
            $text = self::personalise((string) $m['body'], $r, $brand);
            if ($m['channel'] === 'email') {
                $subject = self::personalise((string) $m['subject'], $r, $brand);
                $res = $this->mailer->send((string) $r['address'], (string) $r['name'], $subject, self::emailHtml($subject, $text, $brand), $text . "\n\n— " . $brand, $replyTo ?: null, $brand . ' via ENLIXO');
            } else {
                $res = $this->sendWhatsApp($tutorId, (string) $m['mode'], (string) $r['address'], $text);
            }
            $this->db->execute(
                'UPDATE tutor_message_recipients SET status = :s, error = :e, sent_at = :at WHERE recipient_id = :id',
                ['s' => $res['ok'] ? 'sent' : 'failed', 'e' => $res['ok'] ? null : mb_substr((string) $res['error'], 0, 250), 'at' => $this->now(), 'id' => $r['recipient_id']],
            );
        }
        $this->recount($messageId);
        return $this->detail($tutorId, $messageId, false);
    }

    /** "From my phone": the tutor opened WhatsApp for this person (marks it sent). */
    public function markPhoneSent(int $tutorId, int $messageId, int $recipientId): array
    {
        $m = $this->message($tutorId, $messageId);
        if ($m['mode'] !== 'phone') {
            throw HttpException::validation(['mode' => 'Only messages sent from your phone are marked by hand.']);
        }
        $this->db->execute(
            "UPDATE tutor_message_recipients SET status = 'sent', sent_at = :at WHERE recipient_id = :r AND message_id = :m AND status <> 'skipped'",
            ['at' => $this->now(), 'r' => $recipientId, 'm' => $messageId],
        );
        $this->recount($messageId);
        return $this->detail($tutorId, $messageId, false);
    }

    /** Failed ones go back to pending so Send again tries them once more. */
    public function retry(int $tutorId, int $messageId): array
    {
        $this->message($tutorId, $messageId);
        $this->db->execute("UPDATE tutor_message_recipients SET status = 'pending', error = NULL WHERE message_id = :m AND status = 'failed'", ['m' => $messageId]);
        $this->recount($messageId);
        return $this->detail($tutorId, $messageId, false);
    }

    public function history(int $tutorId, int $page = 1, int $per = 10): array
    {
        $total = (int) $this->db->value('SELECT COUNT(*) FROM tutor_messages WHERE tutor_id = :t', ['t' => $tutorId]);
        $rows = $this->db->all(
            'SELECT message_id, channel, mode, audience, subject, body, class_ids, total, sent, failed, skipped, status, created_at FROM tutor_messages
              WHERE tutor_id = :t ORDER BY message_id DESC LIMIT ' . max(1, $per) . ' OFFSET ' . max(0, ($page - 1) * $per),
            ['t' => $tutorId],
        );
        $names = $this->classNames($tutorId);
        return [
            'rows' => array_map(fn ($m) => $this->shape($m, $names), $rows),
            'meta' => ['total' => $total, 'page' => $page, 'per_page' => $per, 'pages' => max(1, (int) ceil($total / max(1, $per)))],
            'totals' => $this->db->one(
                "SELECT COUNT(*) messages, COALESCE(SUM(sent), 0) sent, COALESCE(SUM(channel = 'email'), 0) email, COALESCE(SUM(channel = 'whatsapp'), 0) whatsapp
                   FROM tutor_messages WHERE tutor_id = :t AND created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)",
                ['t' => $tutorId],
            ),
        ];
    }

    public function detail(int $tutorId, int $messageId, bool $withAll = true): array
    {
        $m = $this->message($tutorId, $messageId);
        $out = $this->shape($m, $this->classNames($tutorId));
        $brand = $this->brand($tutorId);
        $recips = $this->db->all('SELECT * FROM tutor_message_recipients WHERE message_id = :m ORDER BY FIELD(status, \'pending\', \'failed\', \'sent\', \'skipped\'), recipient_id', ['m' => $messageId]);
        $out['recipients'] = array_map(static function ($r) use ($m, $brand) {
            $row = ['id' => (int) $r['recipient_id'], 'kind' => $r['kind'], 'name' => $r['name'], 'class' => $r['class_name'], 'address' => $r['address'],
                'status' => $r['status'], 'error' => $r['error'], 'sent_at' => $r['sent_at']];
            if ($m['mode'] === 'phone' && $r['address']) {
                $row['link'] = 'https://wa.me/' . $r['address'] . '?text=' . rawurlencode(self::personalise((string) $m['body'], $r, $brand));
            }
            return $row;
        }, $recips);
        return $out;
    }

    // ------------------------------------------------------------------ helpers

    /** {name} = the person, {student} = the student (for parents: their child), {class}, {tutor}. */
    public static function personalise(string $text, array $r, string $brand): string
    {
        $first = trim(explode(' ', trim((string) $r['name']))[0] ?? '');
        $student = (string) ($r['student_name'] ?? $r['name']);
        return strtr($text, ['{name}' => $first ?: 'there', '{student}' => $student, '{class}' => (string) ($r['class_name'] ?? ''), '{tutor}' => $brand]);
    }

    public static function emailHtml(string $subject, string $text, string $brand): string
    {
        $e = static fn ($s) => htmlspecialchars($s, ENT_QUOTES, 'UTF-8');
        return '<!doctype html><html><body style="margin:0;background:#f4f6fb;font-family:Arial,Helvetica,sans-serif;color:#1d2026">'
            . '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="padding:24px 12px"><tr><td align="center">'
            . '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="max-width:600px;background:#ffffff">'
            . '<tr><td style="background:#0061d2;color:#ffffff;padding:18px 24px;font-size:18px;font-weight:bold">' . $e($brand) . '</td></tr>'
            . '<tr><td style="padding:24px"><h1 style="font-size:20px;margin:0 0 16px">' . $e($subject) . '</h1>'
            . '<div style="font-size:15px;line-height:1.6">' . nl2br($e($text)) . '</div></td></tr>'
            . '<tr><td style="padding:16px 24px;border-top:1px solid #e9eaf0;font-size:12px;color:#6e7485">Sent by ' . $e($brand) . ' through ENLIXO. Reply to this e-mail to answer your tutor.</td></tr>'
            . '</table></td></tr></table></body></html>';
    }

    private function sendWhatsApp(int $tutorId, string $mode, string $to, string $text): array
    {
        $wcfg = $this->cfg['whatsapp'] ?? [];
        if ($mode === 'enlixo') {
            $token = $this->platformToken();
            if ($token === '') {
                return ['ok' => false, 'error' => 'The ENLIXO WhatsApp number is not set up yet.'];
            }
            return $this->wa->sendTemplate($token, (string) $wcfg['phone_number_id'], $to, (string) $wcfg['notice_template'], (string) ($wcfg['notice_lang'] ?? 'en'), [$this->brand($tutorId), $text]);
        }
        $w = $this->whatsappRow($tutorId);
        $token = $this->box->open($w['token_enc'] ?? null);
        if (!$token || empty($w['phone_number_id'])) {
            return ['ok' => false, 'error' => 'Your WhatsApp number is not connected any more.'];
        }
        $r = $this->wa->sendTemplate($token, (string) $w['phone_number_id'], $to, (string) $w['template_name'], (string) $w['template_lang'], [$text]);
        if (!$r['ok']) {
            $this->db->execute('UPDATE tutor_whatsapp SET last_error = :e WHERE tutor_id = :t', ['e' => mb_substr((string) $r['error'], 0, 250), 't' => $tutorId]);
        }
        return $r;
    }

    /** ENLIXO's token: WHATSAPP_TOKEN, else the one the sign-in codes already use (includes/WhatsAppOtp.php). */
    private function platformToken(): string
    {
        $t = (string) (($this->cfg['whatsapp'] ?? [])['token'] ?? '');
        if ($t !== '') {
            return $t;
        }
        $src = @file_get_contents(dirname(__DIR__, 2) . '/includes/WhatsAppOtp.php') ?: '';
        return preg_match("/getenv\('WHATSAPP_TOKEN'\)\s*\?:\s*'([^']+)'/", $src, $m) ? $m[1] : '';
    }

    private function saveOwn(int $tutorId, string $phoneId, ?string $waba, string $token, array $check, string $template, string $lang, string $via): void
    {
        $this->db->execute(
            "INSERT INTO tutor_whatsapp (tutor_id, mode, phone_number_id, business_account_id, display_number, verified_name, token_enc, template_name, template_lang, connected_via, connected_at, checked_at, last_error)
             VALUES (:t, 'own', :p, :w, :d, :n, :k, :tp, :l, :via, :at, :at2, NULL)
             ON DUPLICATE KEY UPDATE mode = 'own', phone_number_id = VALUES(phone_number_id), business_account_id = VALUES(business_account_id),
                display_number = VALUES(display_number), verified_name = VALUES(verified_name), token_enc = VALUES(token_enc), template_name = VALUES(template_name),
                template_lang = VALUES(template_lang), connected_via = VALUES(connected_via), connected_at = VALUES(connected_at), checked_at = VALUES(checked_at), last_error = NULL",
            ['t' => $tutorId, 'p' => $phoneId, 'w' => $waba, 'd' => $check['number'] ?? null, 'n' => $check['name'] ?? null, 'k' => $this->box->seal($token),
                'tp' => $template, 'l' => $lang, 'via' => $via, 'at' => $this->now(), 'at2' => $this->now()],
        );
    }

    /** @return array{0:string,1:string,2:string,3:list<int>} channel, mode, audience, classes */
    private function choices(int $tutorId, array $in, bool $forSend): array
    {
        $channel = ($in['channel'] ?? '') === 'email' ? 'email' : (($in['channel'] ?? '') === 'whatsapp' ? 'whatsapp' : '');
        $audience = in_array($in['audience'] ?? '', ['students', 'parents', 'both'], true) ? $in['audience'] : '';
        $wanted = array_values(array_unique(array_filter(array_map('intval', (array) ($in['classes'] ?? [])), static fn ($i) => $i > 0)));
        $errors = [];
        if ($channel === '') {
            $errors['channel'] = 'Choose E-mail or WhatsApp.';
        }
        if ($audience === '') {
            $errors['audience'] = 'Choose who gets it: students, parents or both.';
        }
        $classes = [];
        if ($wanted) {
            $classes = array_map('intval', array_column($this->db->all(
                'SELECT class_id FROM classes WHERE tutor_id = :t AND class_id IN (' . implode(',', $wanted) . ')',
                ['t' => $tutorId],
            ), 'class_id'));
        }
        if (!$classes) {
            $errors['classes'] = 'Choose at least one of your classes.';
        }
        $mode = 'email';
        if ($channel === 'email' && !$this->entitlements->has($tutorId, 'comms.email')) {
            $errors['channel'] = 'E-mail is not on your plan.';
        } elseif ($channel === 'email' && $forSend && !$this->mailer->ready()) {
            $errors['channel'] = 'E-mail is not set up on the server yet. Please tell ENLIXO support.';
        } elseif ($channel === 'whatsapp') {
            $mode = $this->setup($tutorId)['whatsapp']['mode'];
        }
        if ($errors) {
            throw HttpException::validation($errors);
        }
        return [$channel, $mode, $audience, $classes];
    }

    /** Active students of the classes and / or their parents, one row per address (no one gets it twice). */
    private function people(int $tutorId, array $classes, string $audience, string $channel): array
    {
        $in = implode(',', array_map('intval', $classes));
        $students = $this->db->all(
            "SELECT s.student_id, ld.first_name, ld.last_name, ld.email, ld.whatsapp, s.mobile, MIN(c.class_name) class_name
               FROM students_has_classes h
               JOIN classes c ON c.class_id = h.classes_class_id AND c.tutor_id = :t
               JOIN students s ON s.student_id = h.students_student_id
               JOIN login_details ld ON ld.login_details_id = s.login_details_login_details_id
              WHERE h.classes_class_id IN ($in) AND h.status_status_id = 1
              GROUP BY s.student_id, ld.first_name, ld.last_name, ld.email, ld.whatsapp, s.mobile
              ORDER BY ld.first_name, ld.last_name",
            ['t' => $tutorId],
        );
        $out = [];
        $seen = [];
        $add = function (array $p) use (&$out, &$seen) {
            $key = $p['address'] !== null ? $p['address'] : $p['kind'] . ':' . $p['student_id'] . ':' . ($p['parent_id'] ?? 0);
            if (isset($seen[$key])) {
                return;
            }
            $seen[$key] = true;
            $out[] = $p;
        };
        $addr = static function (?string $email, ?string $wa) use ($channel): ?string {
            if ($channel === 'email') {
                $email = trim((string) $email);
                return filter_var($email, FILTER_VALIDATE_EMAIL) ? strtolower($email) : null;
            }
            return WhatsAppCloud::digits((string) $wa);
        };
        if (in_array($audience, ['students', 'both'], true)) {
            foreach ($students as $s) {
                $add(['kind' => 'student', 'student_id' => (int) $s['student_id'], 'parent_id' => null, 'class' => $s['class_name'],
                    'name' => trim($s['first_name'] . ' ' . $s['last_name']), 'address' => $addr($s['email'], $s['whatsapp'] ?: $s['mobile'])]);
            }
        }
        if (in_array($audience, ['parents', 'both'], true) && $students) {
            $byId = array_column($students, null, 'student_id');
            $parents = $this->db->all(
                'SELECT p.parent_id, p.parent_fname, p.parent_lname, p.email, p.whatsapp, p.mobile, phs.students_student_id
                   FROM parents_has_students phs JOIN parents p ON p.parent_id = phs.parents_parent_id
                  WHERE phs.students_student_id IN (' . implode(',', array_map('intval', array_keys($byId))) . ')
                  ORDER BY p.parent_fname',
            );
            foreach ($parents as $p) {
                $child = $byId[(int) $p['students_student_id']] ?? null;
                $add(['kind' => 'parent', 'student_id' => (int) $p['students_student_id'], 'parent_id' => (int) $p['parent_id'], 'class' => $child['class_name'] ?? null,
                    'name' => trim($p['parent_fname'] . ' ' . $p['parent_lname']), 'address' => $addr($p['email'], $p['whatsapp'] ?: $p['mobile'])]);
            }
        }
        return $out;
    }

    private function message(int $tutorId, int $messageId): array
    {
        $m = $this->db->one('SELECT * FROM tutor_messages WHERE message_id = :m AND tutor_id = :t', ['m' => $messageId, 't' => $tutorId]);
        if (!$m) {
            throw new HttpException(404, 'not_found', 'This message was not found.');
        }
        return $m;
    }

    private function recount(int $messageId): void
    {
        $c = $this->db->one(
            "SELECT SUM(status = 'sent') sent, SUM(status = 'failed') failed, SUM(status = 'skipped') skipped, SUM(status = 'pending') pending FROM tutor_message_recipients WHERE message_id = :m",
            ['m' => $messageId],
        );
        $this->db->execute(
            'UPDATE tutor_messages SET sent = :s, failed = :f, skipped = :k, status = :st, finished_at = IF(:st2 = \'done\', COALESCE(finished_at, :at), NULL) WHERE message_id = :m',
            ['s' => (int) $c['sent'], 'f' => (int) $c['failed'], 'k' => (int) $c['skipped'], 'st' => (int) $c['pending'] > 0 ? 'sending' : 'done',
                'st2' => (int) $c['pending'] > 0 ? 'sending' : 'done', 'at' => $this->now(), 'm' => $messageId],
        );
    }

    private function shape(array $m, array $names): array
    {
        $ids = array_filter(array_map('intval', explode(',', (string) $m['class_ids'])));
        $pending = max(0, (int) $m['total'] - (int) $m['sent'] - (int) $m['failed'] - (int) $m['skipped']);
        return [
            'id' => (int) $m['message_id'], 'channel' => $m['channel'], 'mode' => $m['mode'], 'audience' => $m['audience'],
            'subject' => $m['subject'], 'body' => $m['body'], 'classes' => array_values(array_map(static fn ($i) => $names[$i] ?? ('Class #' . $i), $ids)),
            'total' => (int) $m['total'], 'sent' => (int) $m['sent'], 'failed' => (int) $m['failed'], 'skipped' => (int) $m['skipped'], 'pending' => $pending,
            'status' => $m['status'], 'created_at' => $m['created_at'],
        ];
    }

    private function classNames(int $tutorId): array
    {
        return array_column($this->db->all('SELECT class_id, class_name FROM classes WHERE tutor_id = :t', ['t' => $tutorId]), 'class_name', 'class_id');
    }

    public function classes(int $tutorId): array
    {
        return array_map(static fn ($r) => ['id' => (int) $r['class_id'], 'name' => $r['class_name'], 'students' => (int) $r['n']], $this->db->all(
            'SELECT c.class_id, c.class_name, (SELECT COUNT(*) FROM students_has_classes h WHERE h.classes_class_id = c.class_id AND h.status_status_id = 1) n
               FROM classes c WHERE c.tutor_id = :t AND c.status_status_id = 1 ORDER BY c.class_name',
            ['t' => $tutorId],
        ));
    }

    private function whatsappRow(int $tutorId): ?array
    {
        return $this->db->one('SELECT * FROM tutor_whatsapp WHERE tutor_id = :t', ['t' => $tutorId]);
    }

    private function brand(int $tutorId): string
    {
        $r = $this->db->one(
            'SELECT u.user_fname, u.user_lname, (SELECT s.institute_name FROM users_has_payment_plans s WHERE s.users_user_id = u.user_id ORDER BY s.users_has_payment_plan_id DESC LIMIT 1) brand
               FROM users u WHERE u.user_id = :t',
            ['t' => $tutorId],
        );
        return trim((string) ($r['brand'] ?? '')) ?: trim(($r['user_fname'] ?? '') . ' ' . ($r['user_lname'] ?? '')) ?: 'Your tutor';
    }

    private function now(): string
    {
        return (new \DateTimeImmutable('now', new \DateTimeZone('Asia/Colombo')))->format('Y-m-d H:i:s');
    }
}
