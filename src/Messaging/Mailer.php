<?php
declare(strict_types=1);

namespace Enlixo\Messaging;

use PHPMailer\PHPMailer\PHPMailer;

/**
 * Sends one e-mail. Driver 'smtp' uses PHPMailer (includes/phpmailer/src) with the MAIL_* settings; when they are not
 * set it reuses the mailbox the old app already sends from (includes/phpmailer/mail-process.php), so nothing new has to
 * be configured. Driver 'log' only writes the e-mail to storage/messages.log (tests and local copies).
 */
final class Mailer
{
    public function __construct(private readonly array $cfg, private readonly string $driver, private readonly string $logFile)
    {
    }

    /** @return array{ok:bool, error:?string} */
    public function send(string $to, string $toName, string $subject, string $html, string $text, ?string $replyTo = null, ?string $fromName = null): array
    {
        if ($this->driver === 'log') {
            MessageLog::write($this->logFile, ['channel' => 'email', 'to' => $to, 'name' => $toName, 'subject' => $subject, 'text' => $text, 'reply_to' => $replyTo, 'from_name' => $fromName]);
            return ['ok' => true, 'error' => null];
        }
        $s = $this->settings();
        if ($s['host'] === '' || $s['username'] === '') {
            return ['ok' => false, 'error' => 'E-mail is not set up on the server yet.'];
        }
        require_once dirname(__DIR__, 2) . '/includes/phpmailer/src/Exception.php';
        require_once dirname(__DIR__, 2) . '/includes/phpmailer/src/PHPMailer.php';
        require_once dirname(__DIR__, 2) . '/includes/phpmailer/src/SMTP.php';
        try {
            $mail = new PHPMailer(true);
            $mail->isSMTP();
            $mail->Host = $s['host'];
            $mail->SMTPAuth = true;
            $mail->Username = $s['username'];
            $mail->Password = $s['password'];
            $mail->SMTPSecure = $s['port'] === 587 ? PHPMailer::ENCRYPTION_STARTTLS : PHPMailer::ENCRYPTION_SMTPS;
            $mail->Port = $s['port'];
            $mail->CharSet = 'UTF-8';
            $mail->Timeout = 20;
            $mail->setFrom($s['from'] ?: $s['username'], $fromName ?: $s['from_name']);
            $mail->addAddress($to, $toName);
            if ($replyTo && filter_var($replyTo, FILTER_VALIDATE_EMAIL)) {
                $mail->addReplyTo($replyTo, $fromName ?: '');
            }
            $mail->isHTML(true);
            $mail->Subject = $subject;
            $mail->Body = $html;
            $mail->AltBody = $text;
            $mail->send();
            return ['ok' => true, 'error' => null];
        } catch (\Throwable $e) {
            error_log('Message Center e-mail to ' . $to . ' failed: ' . $e->getMessage());
            return ['ok' => false, 'error' => 'The e-mail server did not accept it. Try again later.'];
        }
    }

    public function ready(): bool
    {
        if ($this->driver === 'log') {
            return true;
        }
        $s = $this->settings();
        return $s['host'] !== '' && $s['username'] !== '';
    }

    private function settings(): array
    {
        $s = $this->cfg + ['host' => '', 'port' => 465, 'username' => '', 'password' => '', 'from' => '', 'from_name' => 'ENLIXO Tutor'];
        if ($s['host'] === '') {
            $legacy = @file_get_contents(dirname(__DIR__, 2) . '/includes/phpmailer/mail-process.php') ?: '';
            $grab = static fn (string $prop): string => preg_match('/\$mail->' . $prop . '\s*=\s*([\'"])(.*?)\1/', $legacy, $m) ? $m[2] : '';
            $s['host'] = $grab('Host');
            $s['username'] = $grab('Username');
            $s['password'] = $grab('Password');
            $s['port'] = preg_match('/\$mail->Port\s*=\s*(\d+)/', $legacy, $m) ? (int) $m[1] : 465;
            $s['from'] = $s['username'];
        }
        return $s;
    }
}
