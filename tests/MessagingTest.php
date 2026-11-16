<?php
declare(strict_types=1);

use Enlixo\Messaging\MessageService;
use Enlixo\Messaging\SecretBox;
use Enlixo\Messaging\WhatsAppCloud;

test('messaging: WhatsApp numbers become digits with the country code', function () {
    T::eq('94771234567', WhatsAppCloud::digits('077 123 4567'));
    T::eq('94771234567', WhatsAppCloud::digits('+94 77-123-4567'));
    T::eq('447911123456', WhatsAppCloud::digits('0044 7911 123456'));
    T::eq(null, WhatsAppCloud::digits('12345'), 'too short');
    T::eq(null, WhatsAppCloud::digits(''), 'empty');
});

test('messaging: a template variable has no line breaks or long runs of spaces (Meta rule)', function () {
    T::eq('Hi · No class on Saturday', WhatsAppCloud::param("Hi\n\n  No class    on Saturday"));
    T::eq(1024, mb_strlen(WhatsAppCloud::param(str_repeat('a', 2000))));
});

test('messaging: {name}, {student}, {class} and {tutor} are filled in per person', function () {
    $r = ['name' => 'Nimal Perera', 'class_name' => 'A/L Physics'];
    T::eq('Hi Nimal, A/L Physics – Perera Academy', MessageService::personalise('Hi {name}, {class} – {tutor}', $r, 'Perera Academy'));
    T::eq('Hi there', MessageService::personalise('Hi {name}', ['name' => ''], 'X'));
});

test('messaging: the e-mail is escaped (typed HTML is shown as text)', function () {
    $html = MessageService::emailHtml('<b>Hi</b>', "Line 1\n<script>x</script>", 'A & B');
    T::true(str_contains($html, '&lt;b&gt;Hi&lt;/b&gt;'), 'subject escaped');
    T::true(str_contains($html, '&lt;script&gt;'), 'body escaped');
    T::true(str_contains($html, 'Line 1<br />'), 'line breaks kept');
    T::true(str_contains($html, 'A &amp; B'), 'brand escaped');
});

test('messaging: saved tokens are encrypted and read back; a damaged value gives null', function () {
    $box = new SecretBox('test-app-key', sys_get_temp_dir() . '/unused.key');
    $sealed = $box->seal('EAAG-secret-token');
    T::true(!str_contains($sealed, 'EAAG'), 'not stored as plain text');
    T::eq('EAAG-secret-token', $box->open($sealed));
    T::eq(null, $box->open('v1:' . base64_encode(random_bytes(40))), 'tampered');
    T::eq(null, $box->open(null));
    $file = sys_get_temp_dir() . '/enlixo-test-' . bin2hex(random_bytes(3)) . '.key';
    $b2 = new SecretBox('', $file);
    T::eq('abc', $b2->open($b2->seal('abc')), 'key file made on first use');
    @unlink($file);
});

test('messaging: Meta errors are explained in plain words', function () {
    T::true(str_contains(WhatsAppCloud::explain(['error' => ['code' => 190]]), 'token'), 'expired token');
    T::true(str_contains(WhatsAppCloud::explain(['error' => ['code' => 132001]]), 'template'), 'missing template');
    T::eq('WhatsApp did not accept the message.', WhatsAppCloud::explain([]));
});
