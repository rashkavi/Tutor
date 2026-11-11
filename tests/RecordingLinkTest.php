<?php
declare(strict_types=1);

use Enlixo\Recordings\LinkChecker;

function lt(string $key, string $hosts = ''): array
{
    return ['name' => ucfirst($key), 'platform_key' => $key, 'allowed_hosts' => $hosts, 'link_example' => ''];
}

test('YouTube watch, short, youtu.be and embed code become one embed link', function () {
    foreach ([
        'https://www.youtube.com/watch?v=dQw4w9WgXcQ&t=10',
        'https://youtu.be/dQw4w9WgXcQ',
        'https://www.youtube.com/shorts/dQw4w9WgXcQ',
        '<iframe width="560" src="https://www.youtube.com/embed/dQw4w9WgXcQ?si=x" frameborder="0"></iframe>',
    ] as $in) {
        T::eq('https://www.youtube.com/embed/dQw4w9WgXcQ', LinkChecker::check(lt('youtube'), $in)['url'], $in);
    }
    T::eq(false, LinkChecker::check(lt('youtube'), 'https://www.youtube.com/playlist?list=PL123')['ok']);
    T::eq(false, LinkChecker::check(lt('youtube'), 'http://youtu.be/dQw4w9WgXcQ')['ok'], 'http is refused');
});

test('Google Drive file links play in the page, folders are refused', function () {
    T::eq('https://drive.google.com/file/d/1AbCdEfGhIjKlMnOp/preview', LinkChecker::check(lt('drive'), 'https://drive.google.com/file/d/1AbCdEfGhIjKlMnOp/view?usp=sharing')['url']);
    T::eq('https://drive.google.com/file/d/1AbCdEfGhIjKlMnOp/preview', LinkChecker::check(lt('drive'), 'https://drive.google.com/open?id=1AbCdEfGhIjKlMnOp')['url']);
    $r = LinkChecker::check(lt('drive'), 'https://drive.google.com/drive/folders/1AbCdEfGhIjKlMnOp');
    T::eq(false, $r['ok']);
    T::eq(true, str_contains($r['error'], 'folder'));
});

test('Bunny, Vimeo and Zoom links', function () {
    T::eq('https://iframe.mediadelivery.net/embed/12345/aaaaaaaa-bbbb-cccc-dddd-eeeeeeeeeeee', LinkChecker::check(lt('bunny'), 'https://player.mediadelivery.net/play/12345/AAAAAAAA-bbbb-cccc-dddd-eeeeeeeeeeee')['url']);
    T::eq('https://player.vimeo.com/video/123456789?h=abc123', LinkChecker::check(lt('vimeo'), 'https://vimeo.com/123456789/abc123')['url']);
    T::eq(true, LinkChecker::check(lt('zoom'), 'https://us02web.zoom.us/rec/share/abcDEF123456?pwd=x')['ok']);
    T::eq(false, LinkChecker::check(lt('zoom'), 'https://us02web.zoom.us/j/123456789')['ok'], 'a meeting link is not a recording');
});

test('Other website accepts only the listed websites (and their sub-domains)', function () {
    T::eq(true, LinkChecker::check(lt('custom', 'dropbox.com'), 'https://www.dropbox.com/s/abc/video.mp4')['ok']);
    T::eq(false, LinkChecker::check(lt('custom', 'dropbox.com'), 'https://evil-dropbox.com/x')['ok']);
    T::eq(['dropbox.com', 'drive.google.com'], LinkChecker::parseHosts('https://dropbox.com/x, drive.google.com'));
    T::eq(true, is_string(LinkChecker::parseHosts('not a site')));
});

test('our player never contains typed HTML', function () {
    $html = LinkChecker::embedHtml('https://www.youtube.com/embed/dQw4w9WgXcQ', 'Week "4" <b>');
    T::eq(false, str_contains($html, '<b>'));
    T::eq(true, str_contains($html, 'allowfullscreen'));
});
