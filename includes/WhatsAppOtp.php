<?php
/**
 * Sign-in codes (OTP) sent on WhatsApp, one place for the parent portal (and any new sign-in):
 *   whatsapp_send_otp('+94771234567', 123456)  -> true when WhatsApp accepted the message
 *   otp_still_valid($row['reset_time'])        -> true while the code is younger than OTP_VALID_SECONDS
 * The code itself is NEVER returned to the browser; it only goes to the person's WhatsApp.
 * Settings: WHATSAPP_TOKEN and WHATSAPP_PHONE_ID (environment). Put the token there on the server and create a new
 * one in Meta Business: the old token below is still in the code history and should be retired.
 */
const OTP_VALID_SECONDS = 300;

if (!function_exists('whatsapp_send_otp')) {
    function whatsapp_send_otp(string $to, int $otp): bool
    {
        $token = getenv('WHATSAPP_TOKEN') ?: 'EAAGrCyhRGJ8BQZCo1i0polWgFjEUboynGwLlavuwj4vFJYtZBL6kdqXItxIT3fBZCOkElPIlGE7A2oLnXVASJDOQKjKItJ3G43MSrc3GHPdrruQgzam7scrzXi8hf6dRzqCmu83kA0Nvn0YLfoqdTmVHuqrvoZB97c1DdEr2Rm7jr3R80yxyDUxIul3d3ZBcMnwZDZD';
        $phoneId = getenv('WHATSAPP_PHONE_ID') ?: '470586202805184';
        $curl = curl_init("https://graph.facebook.com/v22.0/{$phoneId}/messages");
        curl_setopt_array($curl, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_TIMEOUT => 15,
            CURLOPT_HTTPHEADER => ["Authorization: Bearer {$token}", 'Content-Type: application/json'],
            CURLOPT_POSTFIELDS => json_encode([
                'messaging_product' => 'whatsapp',
                'recipient_type' => 'individual',
                'to' => $to,
                'type' => 'template',
                'template' => [
                    'name' => 'send_otp',
                    'language' => ['code' => 'en_US'],
                    'components' => [
                        ['type' => 'body', 'parameters' => [['type' => 'text', 'text' => (string) $otp]]],
                        ['type' => 'button', 'sub_type' => 'url', 'index' => '0', 'parameters' => [['type' => 'text', 'text' => (string) $otp]]],
                    ],
                ],
            ]),
        ]);
        $response = curl_exec($curl);
        curl_close($curl);
        $data = is_string($response) ? json_decode($response, true) : null;
        if (!is_array($data) || isset($data['error'])) {
            error_log('WhatsApp OTP not sent: ' . (is_array($data) ? json_encode($data['error'] ?? $data) : 'no answer'));
            return false;
        }
        return true;
    }

    /** reset_time is a date-time string ('Y-m-d H:i:s', Asia/Colombo) or, in older rows, a Unix time */
    function otp_still_valid($resetTime): bool
    {
        if ($resetTime === null || $resetTime === '') {
            return false;
        }
        $sent = is_numeric($resetTime) ? (int) $resetTime : strtotime((string) $resetTime . ' Asia/Colombo');
        return $sent !== false && $sent > time() - OTP_VALID_SECONDS && $sent <= time() + 60;
    }
}
