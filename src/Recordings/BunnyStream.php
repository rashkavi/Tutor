<?php
declare(strict_types=1);

namespace Enlixo\Recordings;

use Enlixo\Http\Client\HttpClient;

/**
 * The tutor's own Bunny Stream library (Settings -> Recording Types -> a Bunny Stream type -> Connect).
 * Read only: we never upload or change anything in Bunny. Used to
 *  - check the library ID + API key when the tutor saves them,
 *  - confirm a pasted video is in that library and read its title, length and whether Bunny finished processing it.
 * API: https://docs.bunny.net/reference/video_getvideo (header AccessKey = the library's Stream API key).
 */
final class BunnyStream
{
    public const GUID = '[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}';
    private const API = 'https://video.bunnycdn.com/library/';

    public function __construct(private readonly HttpClient $http, private readonly int $libraryId, private readonly string $apiKey)
    {
    }

    /**
     * Library + video id from anything a tutor may paste: the embed or play link, the direct play link,
     * a dashboard link, an iframe embed code or only the video ID (then library is null).
     * @return array{library:?int, guid:string}|null
     */
    public static function parse(string $input): ?array
    {
        $input = trim($input);
        if (preg_match('/^(' . self::GUID . ')$/i', $input, $m)) {
            return ['library' => null, 'guid' => strtolower($m[1])];
        }
        $url = LinkChecker::extractUrl($input) ?? $input;
        $p = parse_url($url);
        if (!is_array($p) || empty($p['host']) || strtolower($p['scheme'] ?? '') !== 'https') {
            return null;
        }
        $host = strtolower($p['host']);
        $path = $p['path'] ?? '';
        $players = ['iframe.mediadelivery.net', 'player.mediadelivery.net', 'video.bunnycdn.com'];
        if (in_array($host, $players, true) && preg_match('#^/(?:embed|play)/(\d{1,10})/(' . self::GUID . ')/?$#i', $path, $m)) {
            return ['library' => (int) $m[1], 'guid' => strtolower($m[2])];
        }
        // dash.bunny.net/stream/12345/library/videos?videoId=GUID  or  .../videos/GUID
        if ($host === 'dash.bunny.net' && preg_match('#^/stream/(\d{1,10})/#', $path, $m)) {
            parse_str($p['query'] ?? '', $q);
            $guid = null;
            if (preg_match('#/(' . self::GUID . ')/?$#i', $path, $g)) {
                $guid = $g[1];
            } else {
                foreach ($q as $v) {
                    if (is_string($v) && preg_match('/^' . self::GUID . '$/i', $v)) {
                        $guid = $v;
                    }
                }
            }
            return $guid ? ['library' => (int) $m[1], 'guid' => strtolower($guid)] : null;
        }
        return null;
    }

    public static function embedUrl(int $library, string $guid): string
    {
        return 'https://iframe.mediadelivery.net/embed/' . $library . '/' . strtolower($guid);
    }

    /** null = the library ID and key work; otherwise the message for the tutor. */
    public function testConnection(): ?string
    {
        try {
            $res = $this->http->request('GET', self::API . $this->libraryId . '/videos?page=1&itemsPerPage=1', $this->headers());
        } catch (\Throwable $e) {
            return 'Could not reach Bunny. Check the internet connection of the server and try again.';
        }
        return match (true) {
            $res['status'] >= 200 && $res['status'] < 300 => null,
            $res['status'] === 401, $res['status'] === 403 => "Bunny did not accept this API key for library {$this->libraryId}. Copy the API key of this library again.",
            $res['status'] === 404 => "Bunny has no library with the ID {$this->libraryId}.",
            default => 'Bunny answered with an error (' . $res['status'] . '). Try again in a moment.',
        };
    }

    /**
     * One video of the library.
     * @return array{found:bool, error:?string, title?:string, seconds?:int, state?:string}
     *         state: ready | processing | failed. error = Bunny could not be asked (the link is still accepted).
     */
    public function video(string $guid): array
    {
        try {
            $res = $this->http->request('GET', self::API . $this->libraryId . '/videos/' . rawurlencode($guid), $this->headers());
        } catch (\Throwable $e) {
            return ['found' => true, 'error' => 'Could not reach Bunny to read the video details.'];
        }
        if ($res['status'] === 404) {
            return ['found' => false, 'error' => null];
        }
        if ($res['status'] < 200 || $res['status'] >= 300 || !is_array($res['body'])) {
            return ['found' => true, 'error' => 'Bunny did not send the video details (' . $res['status'] . ').'];
        }
        $b = $res['body'];
        // Bunny video status: 0 created, 1 uploaded, 2 processing, 3 transcoding, 4 finished, 5 error, 6 upload failed, 7/8 ready (JIT)
        $status = (int) ($b['status'] ?? 4);
        return [
            'found' => true,
            'error' => null,
            'title' => trim((string) ($b['title'] ?? '')),
            'seconds' => max(0, (int) ($b['length'] ?? 0)),
            'state' => match (true) {
                in_array($status, [4, 7, 8], true) => 'ready',
                in_array($status, [5, 6], true) => 'failed',
                default => 'processing',
            },
        ];
    }

    private function headers(): array
    {
        return ['AccessKey' => $this->apiKey, 'Accept' => 'application/json'];
    }
}
