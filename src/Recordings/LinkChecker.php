<?php
declare(strict_types=1);

namespace Enlixo\Recordings;

/**
 * Checks a pasted recording link against one recording type and turns it into the clean link we store and play
 * (same rules as ENLIXO V3 RecordingManager::verifyOne, plus Google Drive). Pure: no database, easy to test.
 *
 * A type = ['name' => 'Zoom', 'platform_key' => 'zoom', 'allowed_hosts' => '', 'link_example' => '...'].
 * Returns ['ok' => bool, 'url' => clean link or '', 'error' => message for the tutor or ''].
 */
final class LinkChecker
{
    /** platform key => [label, plays inside the page?, help shown in Settings] */
    public const PLATFORMS = [
        'youtube' => ['YouTube', true, 'YouTube video links (unlisted videos work too). Playlists are not accepted.'],
        'bunny' => ['Bunny Stream', true, 'Bunny Stream video links (embed, play or dashboard link). Connect your library and the video ID alone is enough.'],
        'drive' => ['Google Drive', true, 'A video file shared from Google Drive ("Anyone with the link").'],
        'vimeo' => ['Vimeo', true, 'Vimeo video links, private links with a hash too.'],
        'zoom' => ['Zoom cloud recording', false, 'Zoom cloud recording share links (/rec/share/ or /rec/play/). Zoom does not allow playing inside the page.'],
        'teams' => ['Microsoft Teams / OneDrive', false, 'Teams, Stream, SharePoint or OneDrive video links. They open in a new tab.'],
        'custom' => ['Other website', false, 'Any https link on the websites you list, for example dropbox.com. Opens in a new tab.'],
    ];

    public const MAX_LINK = 600;

    public static function embeddable(string $key): bool
    {
        return self::PLATFORMS[$key][1] ?? false;
    }

    /** "drive.google.com, https://www.dropbox.com/x" -> ['drive.google.com', 'www.dropbox.com'] or an error message. */
    public static function parseHosts(string $hosts): array|string
    {
        $list = [];
        foreach (preg_split('/[\s,;]+/', strtolower($hosts), -1, PREG_SPLIT_NO_EMPTY) as $h) {
            $h = preg_replace('#^https?://#', '', $h);
            $h = rtrim(explode('/', $h)[0], '.');
            if (!preg_match('/^([a-z0-9]([a-z0-9-]*[a-z0-9])?\.)+[a-z]{2,}$/', $h)) {
                return "\"$h\" is not a website address. Write it like dropbox.com";
            }
            $list[$h] = true;
        }
        if (count($list) > 10) {
            return 'Enter up to 10 websites';
        }
        return $list === [] ? 'Enter at least one website, for example dropbox.com' : array_keys($list);
    }

    private static function hostMatches(string $host, array $allowed): bool
    {
        foreach ($allowed as $a) {
            if ($host === $a || str_ends_with($host, '.' . $a)) {
                return true;
            }
        }
        return false;
    }

    /** The box content -> a clean https link, or null. An <iframe> embed code is accepted too. */
    public static function extractUrl(string $input): ?string
    {
        $input = trim($input);
        if ($input === '') {
            return null;
        }
        if (stripos($input, '<iframe') !== false) {
            if (!preg_match('/<iframe[^>]*\ssrc\s*=\s*["\']([^"\']+)["\']/i', $input, $m)) {
                return null;
            }
            $input = html_entity_decode(trim($m[1]), ENT_QUOTES);
        }
        if (str_starts_with($input, '//')) {
            $input = 'https:' . $input;
        }
        if (preg_match('/\s/', $input) || mb_strlen($input) > self::MAX_LINK) {
            return null;
        }
        return $input;
    }

    public static function check(array $type, string $input): array
    {
        $fail = static fn (string $msg): array => ['ok' => false, 'url' => '', 'error' => $msg];
        $name = (string) $type['name'];
        $example = ($type['link_example'] ?? '') !== '' ? " For example: {$type['link_example']}" : '';

        if (trim($input) === '') {
            return $fail('Paste the link of the recording');
        }
        if (($type['platform_key'] ?? '') === 'bunny') {
            return self::bunny($type, $input, $fail);
        }
        $raw = self::extractUrl($input);
        if ($raw === null) {
            return $fail(mb_strlen(trim($input)) > self::MAX_LINK
                ? 'The link is too long (up to ' . self::MAX_LINK . ' characters)'
                : "That does not look like a $name link.$example");
        }
        $p = parse_url($raw);
        if (!$p || empty($p['host']) || strtolower($p['scheme'] ?? '') !== 'https') {
            return $fail("The $name link must start with https://.$example");
        }
        $host = strtolower($p['host']);
        $path = $p['path'] ?? '/';
        parse_str($p['query'] ?? '', $q);
        $bad = static fn (): array => $fail("That is not a $name link.$example");
        $keep = static fn (): array => ['ok' => true, 'url' => 'https://' . $host . $path . (isset($p['query']) ? '?' . $p['query'] : ''), 'error' => ''];

        switch ($type['platform_key']) {
            case 'youtube':
                if (!in_array($host, ['youtube.com', 'www.youtube.com', 'm.youtube.com', 'youtube-nocookie.com', 'www.youtube-nocookie.com', 'youtu.be'], true)) {
                    return $bad();
                }
                $id = null;
                if ($host === 'youtu.be') {
                    $id = explode('/', ltrim($path, '/'))[0];
                } elseif (preg_match('#^/(embed|shorts|live|v)/([^/?]+)#', $path, $m)) {
                    $id = $m[2];
                } elseif ($path === '/watch') {
                    $id = $q['v'] ?? null;
                }
                if (!is_string($id) || !preg_match('/^[A-Za-z0-9_-]{11}$/', $id)) {
                    return $fail(isset($q['list']) && empty($q['v'])
                        ? 'Playlist links are not accepted. Paste the link of one video.'
                        : "That is not a $name video link.$example");
                }
                return ['ok' => true, 'url' => "https://www.youtube.com/embed/$id", 'error' => ''];

            case 'drive':
                if ($host !== 'drive.google.com') {
                    return $bad();
                }
                $id = null;
                if (preg_match('#^/file/d/([A-Za-z0-9_-]{10,})#', $path, $m)) {
                    $id = $m[1];
                } elseif (in_array($path, ['/open', '/uc'], true) && isset($q['id'])) {
                    $id = (string) $q['id'];
                }
                if (!$id || !preg_match('/^[A-Za-z0-9_-]{10,}$/', $id)) {
                    return $fail(str_starts_with($path, '/drive/folders')
                        ? 'That is a folder link. Open the video and copy its own share link.'
                        : "That is not a $name file link.$example");
                }
                return ['ok' => true, 'url' => "https://drive.google.com/file/d/$id/preview", 'error' => ''];

            case 'vimeo':
                if (!in_array($host, ['vimeo.com', 'www.vimeo.com', 'player.vimeo.com'], true)) {
                    return $bad();
                }
                $id = null;
                $hash = $q['h'] ?? null;
                if ($host === 'player.vimeo.com' && preg_match('#^/video/(\d+)#', $path, $m)) {
                    $id = $m[1];
                } elseif (preg_match('#^/(?:channels/[^/]+/|groups/[^/]+/videos/)?(\d+)(?:/([0-9a-f]+))?/?$#i', $path, $m)) {
                    $id = $m[1];
                    $hash ??= $m[2] ?? null;
                }
                if (!$id) {
                    return $bad();
                }
                return ['ok' => true, 'url' => "https://player.vimeo.com/video/$id" . (is_string($hash) && preg_match('/^[0-9a-f]+$/i', $hash) ? "?h=$hash" : ''), 'error' => ''];

            case 'zoom':
                if (!self::hostMatches($host, ['zoom.us', 'zoomgov.com'])) {
                    return $bad();
                }
                if (!preg_match('#^/rec/(share|play)/[A-Za-z0-9._~\-]{10,}#', $path)) {
                    return $fail("That is a Zoom link but not a cloud recording. It must have /rec/share/ or /rec/play/ in it.$example");
                }
                return $keep();

            case 'teams':
                $ok = self::hostMatches($host, ['sharepoint.com', 'microsoftstream.com', '1drv.ms', 'onedrive.live.com'])
                    || in_array($host, ['teams.microsoft.com', 'teams.live.com'], true);
                if (!$ok || strlen($path) < 4 && !isset($p['query'])) {
                    return $bad();
                }
                return $keep();

            case 'custom':
                $allowed = array_values(array_filter(array_map('trim', explode(',', strtolower((string) ($type['allowed_hosts'] ?? ''))))));
                if ($allowed === [] || !self::hostMatches($host, $allowed)) {
                    return $fail("$name links must be on " . implode(', ', $allowed));
                }
                return $keep();
        }
        return $fail('This recording type is not set up correctly. Check Settings > Recording Types.');
    }

    /** Bunny: any Bunny link or only the video ID (when the type is connected to a library) -> the embed link. */
    private static function bunny(array $type, string $input, \Closure $fail): array
    {
        $name = (string) $type['name'];
        $lib = (int) ($type['bunny_library_id'] ?? 0);
        $b = BunnyStream::parse($input);
        if ($b === null) {
            return $fail("That is not a $name video link. In Bunny open the video and copy its embed link" . ($lib ? ' or its video ID.' : '.'));
        }
        if ($b['library'] === null) {
            if (!$lib) {
                return $fail("Only a video ID was pasted. Paste the full link, or connect your Bunny library to $name in Settings > Recording Types so the ID is enough.");
            }
            $b['library'] = $lib;
        } elseif ($lib && $b['library'] !== $lib) {
            return $fail("This video is in Bunny library {$b['library']}, but $name is connected to library $lib. Paste a video from library $lib, or add another Bunny type for that library.");
        }
        return ['ok' => true, 'url' => BunnyStream::embedUrl($b['library'], $b['guid']), 'error' => '', 'bunny' => $b];
    }

    /**
     * What the old recording_link column gets, so pages that still print it keep working: our own iframe for
     * types that play in the page, otherwise a button that opens the link.
     */
    public static function playerHtml(string $url, string $title, bool $embeddable): string
    {
        return $embeddable
            ? self::embedHtml($url, $title)
            : '<a href="' . htmlspecialchars($url, ENT_QUOTES) . '" target="_blank" rel="noopener" class="custom-btn px-4 text-decoration-none">Open the recording</a>';
    }

    /** The iframe we build ourselves for an embeddable link (typed HTML is never stored or shown). */
    public static function embedHtml(string $url, string $title = 'Class recording'): string
    {
        return '<iframe src="' . htmlspecialchars($url, ENT_QUOTES) . '" title="' . htmlspecialchars($title, ENT_QUOTES) . '" frameborder="0" '
            . 'allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture; fullscreen" allowfullscreen '
            . 'style="width:100%;aspect-ratio:16/9;border:0"></iframe>';
    }
}
