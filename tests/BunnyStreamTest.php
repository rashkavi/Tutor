<?php
declare(strict_types=1);

use Enlixo\Http\Client\HttpClient;
use Enlixo\Recordings\BunnyStream;
use Enlixo\Recordings\LinkChecker;

final class FakeBunnyHttp implements HttpClient
{
    public array $calls = [];
    public function __construct(private array $answers) {}
    public function request(string $method, string $url, array $headers = [], ?string $body = null): array
    {
        $this->calls[] = [$method, $url, $headers['AccessKey'] ?? null];
        foreach ($this->answers as $part => $a) {
            if (str_contains($url, $part)) {
                return $a;
            }
        }
        return ['status' => 404, 'body' => null, 'raw' => ''];
    }
}

const BG = 'aaaaaaaa-bbbb-cccc-dddd-eeeeeeeeeeee';

test('Bunny links of every kind (and the video ID alone) are read', function () {
    foreach ([
        'https://iframe.mediadelivery.net/embed/12345/' . BG,
        'https://iframe.mediadelivery.net/play/12345/' . strtoupper(BG),
        'https://player.mediadelivery.net/embed/12345/' . BG . '?autoplay=true',
        'https://video.bunnycdn.com/play/12345/' . BG,
        'https://dash.bunny.net/stream/12345/library/videos?videoId=' . BG,
        '<iframe src="https://iframe.mediadelivery.net/embed/12345/' . BG . '?autoplay=false" loading="lazy"></iframe>',
    ] as $in) {
        T::eq(['library' => 12345, 'guid' => BG], BunnyStream::parse($in), $in);
    }
    T::eq(['library' => null, 'guid' => BG], BunnyStream::parse(' ' . BG . ' '));
    T::eq(null, BunnyStream::parse('https://example.com/embed/12345/' . BG));
    T::eq(null, BunnyStream::parse('http://iframe.mediadelivery.net/embed/12345/' . BG), 'http is refused');
});

test('a connected Bunny type: the video ID is enough, another library is stopped', function () {
    $type = ['name' => 'Bunny Stream', 'platform_key' => 'bunny', 'allowed_hosts' => '', 'link_example' => '', 'bunny_library_id' => 12345];
    T::eq('https://iframe.mediadelivery.net/embed/12345/' . BG, LinkChecker::check($type, BG)['url']);
    $r = LinkChecker::check($type, 'https://iframe.mediadelivery.net/embed/999/' . BG);
    T::eq(false, $r['ok']);
    T::true(str_contains($r['error'], 'library 999'), $r['error']);
    $plain = ['bunny_library_id' => null] + $type;
    $r = LinkChecker::check($plain, BG);
    T::eq(false, $r['ok'], 'not connected: an ID alone is not enough');
    T::true(str_contains($r['error'], 'Recording Types'));
    T::eq(true, LinkChecker::check($plain, 'https://iframe.mediadelivery.net/embed/999/' . BG)['ok'], 'not connected: any library');
});

test('Bunny API: connection test, video details, processing and missing videos', function () {
    $http = new FakeBunnyHttp([
        '/videos?page=1' => ['status' => 200, 'body' => ['items' => []], 'raw' => ''],
        '/videos/' . BG => ['status' => 200, 'body' => ['title' => 'Week 4', 'length' => 3605, 'status' => 4], 'raw' => ''],
        '/videos/bbbbbbbb-bbbb-cccc-dddd-eeeeeeeeeeee' => ['status' => 200, 'body' => ['title' => 'New', 'length' => 0, 'status' => 3], 'raw' => ''],
    ]);
    $b = new BunnyStream($http, 12345, 'key-123');
    T::eq(null, $b->testConnection());
    T::eq('key-123', $http->calls[0][2], 'the API key goes in the AccessKey header');
    $v = $b->video(BG);
    T::eq(['found' => true, 'error' => null, 'title' => 'Week 4', 'seconds' => 3605, 'state' => 'ready'], $v);
    T::eq('processing', $b->video('bbbbbbbb-bbbb-cccc-dddd-eeeeeeeeeeee')['state']);
    T::eq(false, $b->video('cccccccc-bbbb-cccc-dddd-eeeeeeeeeeee')['found']);

    $bad = new BunnyStream(new FakeBunnyHttp(['/videos' => ['status' => 401, 'body' => null, 'raw' => '']]), 12345, 'wrong');
    T::true(str_contains((string) $bad->testConnection(), 'did not accept'));
});
