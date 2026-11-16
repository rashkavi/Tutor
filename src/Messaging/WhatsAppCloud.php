<?php
declare(strict_types=1);

namespace Enlixo\Messaging;

/**
 * WhatsApp Cloud API (graph.facebook.com). A message that starts a conversation must use a template that Meta
 * approved; the class notice templates have one or two text variables. Driver 'log' writes the message to
 * storage/messages.log instead of calling Meta (tests and local copies).
 */
final class WhatsAppCloud
{
    public function __construct(private readonly string $driver, private readonly string $logFile, private readonly string $version = 'v22.0')
    {
    }

    /** Sri Lankan 07... numbers become 947...; other numbers keep their digits (country code expected). */
    public static function digits(string $number): ?string
    {
        $d = preg_replace('/\D+/', '', $number) ?? '';
        if (preg_match('/^0(7\d{8})$/', $d, $m)) {
            return '94' . $m[1];
        }
        if (preg_match('/^00(\d{8,15})$/', $d, $m)) {
            return $m[1];
        }
        return preg_match('/^[1-9]\d{7,14}$/', $d) ? $d : null;
    }

    /** Template variables may not contain line breaks, tabs or more than 4 spaces in a row (Meta rule). */
    public static function param(string $text): string
    {
        $t = preg_replace("/\s*\R+\s*/u", ' · ', trim($text)) ?? '';
        $t = preg_replace('/[\t ]{2,}/', ' ', $t) ?? '';
        return mb_substr($t, 0, 1024);
    }

    /** @param list<string> $params @return array{ok:bool, error:?string, id:?string} */
    public function sendTemplate(string $token, string $phoneNumberId, string $to, string $template, string $lang, array $params): array
    {
        $payload = [
            'messaging_product' => 'whatsapp',
            'to' => $to,
            'type' => 'template',
            'template' => [
                'name' => $template,
                'language' => ['code' => $lang],
                'components' => [['type' => 'body', 'parameters' => array_map(static fn ($p) => ['type' => 'text', 'text' => self::param((string) $p)], $params)]],
            ],
        ];
        if ($this->driver === 'log') {
            MessageLog::write($this->logFile, ['channel' => 'whatsapp', 'from' => $phoneNumberId, 'to' => $to, 'template' => $template, 'params' => $params]);
            return ['ok' => true, 'error' => null, 'id' => 'log-' . bin2hex(random_bytes(4))];
        }
        [$code, $body] = $this->call('POST', "/{$phoneNumberId}/messages", $token, $payload);
        if ($code >= 200 && $code < 300 && isset($body['messages'][0]['id'])) {
            return ['ok' => true, 'error' => null, 'id' => (string) $body['messages'][0]['id']];
        }
        return ['ok' => false, 'error' => self::explain($body), 'id' => null];
    }

    /** The number behind a phone number id, to check a token. @return array{ok:bool, error:?string, number?:string, name?:string, quality?:string} */
    public function check(string $token, string $phoneNumberId): array
    {
        if ($this->driver === 'log') {
            return ['ok' => true, 'error' => null, 'number' => '+94 77 000 0000', 'name' => 'Test Number', 'quality' => 'GREEN'];
        }
        [$code, $body] = $this->call('GET', "/{$phoneNumberId}?fields=display_phone_number,verified_name,quality_rating", $token);
        if ($code === 200 && isset($body['display_phone_number'])) {
            return ['ok' => true, 'error' => null, 'number' => (string) $body['display_phone_number'], 'name' => (string) ($body['verified_name'] ?? ''), 'quality' => (string) ($body['quality_rating'] ?? '')];
        }
        return ['ok' => false, 'error' => self::explain($body)];
    }

    /** Embedded Signup: the code from Facebook login becomes a business token. @return array{ok:bool, error:?string, token?:string} */
    public function exchangeCode(string $appId, string $appSecret, string $code): array
    {
        if ($this->driver === 'log') {
            return ['ok' => true, 'error' => null, 'token' => 'log-token'];
        }
        [$status, $body] = $this->call('GET', '/oauth/access_token?' . http_build_query(['client_id' => $appId, 'client_secret' => $appSecret, 'code' => $code]), null);
        return $status === 200 && !empty($body['access_token'])
            ? ['ok' => true, 'error' => null, 'token' => (string) $body['access_token']]
            : ['ok' => false, 'error' => self::explain($body)];
    }

    private function call(string $method, string $path, ?string $token, ?array $json = null): array
    {
        $ch = curl_init('https://graph.facebook.com/' . $this->version . $path);
        $headers = ['Accept: application/json'];
        if ($token) {
            $headers[] = 'Authorization: Bearer ' . $token;
        }
        if ($json !== null) {
            $headers[] = 'Content-Type: application/json';
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($json));
        }
        curl_setopt_array($ch, [CURLOPT_CUSTOMREQUEST => $method, CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 20, CURLOPT_HTTPHEADER => $headers]);
        $raw = curl_exec($ch);
        $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $err = curl_error($ch);
        curl_close($ch);
        if ($raw === false) {
            error_log('WhatsApp Cloud API ' . $path . ': ' . $err);
            return [0, ['error' => ['message' => 'WhatsApp could not be reached. Try again later.']]];
        }
        return [$code, json_decode((string) $raw, true) ?: []];
    }

    /** Meta's error in words a tutor understands. */
    public static function explain(array $body): string
    {
        $e = $body['error'] ?? [];
        $code = (int) ($e['code'] ?? 0);
        return match (true) {
            $code === 190 => 'The access token is not valid any more. Connect your number again.',
            $code === 132001 => 'The message template was not found or is not approved yet in WhatsApp Manager.',
            $code === 132000 => 'The template needs a different number of variables.',
            in_array($code, [131026, 131030], true) => 'This number cannot receive WhatsApp messages.',
            $code === 131047 => 'More than 24 hours passed since this person wrote to you; only a template can be sent.',
            in_array($code, [4, 80007, 130429, 131048, 131056], true) => 'WhatsApp is limiting how fast messages go out. Try again in a few minutes.',
            $code === 100 => 'WhatsApp did not accept the phone number id or a value: ' . (string) ($e['message'] ?? ''),
            default => (string) ($e['message'] ?? 'WhatsApp did not accept the message.'),
        };
    }
}
