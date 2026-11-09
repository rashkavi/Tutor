<?php
declare(strict_types=1);

namespace Enlixo\Ai;

use Enlixo\Core\HttpException;
use Enlixo\Http\Client\HttpClient;

/**
 * Makes one image from a text prompt with Google's Gemini image model (REST generateContent).
 * The API key stays on the server (config ai.gemini_api_key or the GEMINI_API_KEY env var).
 */
final class GeminiImageClient
{
    private const ENDPOINT = 'https://generativelanguage.googleapis.com/v1beta/models/%s:generateContent';
    private const MAX_BYTES = 8 * 1024 * 1024;

    public function __construct(
        private readonly HttpClient $http,
        private readonly string $apiKey,
        private readonly string $model = 'gemini-2.5-flash-image',
    ) {
    }

    public function configured(): bool
    {
        return $this->apiKey !== '';
    }

    /** @return array{mime:string, bytes:string} */
    public function generate(string $prompt, string $aspectRatio = '16:9'): array
    {
        if (!$this->configured()) {
            throw new HttpException(503, 'ai_not_configured', 'AI thumbnails are not set up yet. Add a Gemini API key in config/config.local.php (ai.gemini_api_key).');
        }
        $body = json_encode([
            'contents' => [['parts' => [['text' => $prompt]]]],
            'generationConfig' => ['responseModalities' => ['IMAGE'], 'imageConfig' => ['aspectRatio' => $aspectRatio]],
        ], JSON_UNESCAPED_UNICODE);

        try {
            $res = $this->http->request('POST', sprintf(self::ENDPOINT, rawurlencode($this->model)), [
                'Content-Type' => 'application/json',
                'x-goog-api-key' => $this->apiKey,
            ], $body);
        } catch (\RuntimeException) {
            throw new HttpException(502, 'ai_failed', 'The AI service did not answer. Please try again in a minute.');
        }

        if ($res['status'] === 429) {
            throw new HttpException(429, 'ai_busy', 'The AI service is busy right now. Please try again in a minute.');
        }
        if ($res['status'] !== 200 || !is_array($res['body'])) {
            error_log('Gemini image error ' . $res['status'] . ': ' . substr($res['raw'], 0, 500));
            throw new HttpException(502, 'ai_failed', 'We couldn\'t make a thumbnail this time. Please try again.');
        }

        foreach ($res['body']['candidates'][0]['content']['parts'] ?? [] as $part) {
            $data = $part['inlineData']['data'] ?? $part['inline_data']['data'] ?? null;
            if (!is_string($data)) {
                continue;
            }
            $bytes = base64_decode($data, true);
            if ($bytes === false || strlen($bytes) > self::MAX_BYTES) {
                break;
            }
            // Trust the bytes, not the declared type.
            $info = @getimagesizefromstring($bytes);
            $mime = match ($info[2] ?? null) { IMAGETYPE_PNG => 'image/png', IMAGETYPE_JPEG => 'image/jpeg', IMAGETYPE_WEBP => 'image/webp', default => null };
            if ($mime !== null) {
                return ['mime' => $mime, 'bytes' => $bytes];
            }
        }
        // No image: usually the prompt was blocked by the safety filter.
        throw new HttpException(422, 'ai_no_image', 'The AI could not make a picture from these details. Try another style or change the description.');
    }
}
