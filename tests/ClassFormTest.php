<?php
declare(strict_types=1);

use Enlixo\Classes\ClassFormSchema;
use Enlixo\Core\HttpException;

test('Class form: core fields can never be hidden, own fields are cleaned', function () {
    $cfg = ClassFormSchema::sanitiseSettings([
        'hidden' => ['subject', 'description', 'nonsense'],
        'custom' => [
            ['label' => '  Institute name ', 'type' => 'text', 'info' => 'Where it runs'],
            ['label' => 'Unit', 'type' => 'select', 'options' => ['Unit 1', '', 'Unit 2', 'Unit 2']],
            ['key' => 'cf_hall_abc12', 'label' => 'Hall', 'type' => 'number', 'required' => 1],
        ],
    ]);
    T::eq(['description'], $cfg['hidden'], 'only optional fields hide');
    T::eq('Institute name', $cfg['custom'][0]['label']);
    T::true((bool) preg_match('/^cf_institute_name_[0-9a-f]{5}$/', $cfg['custom'][0]['key']), 'new field gets a stable key');
    T::eq([['value' => 'Unit 1', 'label' => 'Unit 1'], ['value' => 'Unit 2', 'label' => 'Unit 2']], $cfg['custom'][1]['options'], 'empty and duplicate choices dropped');
    T::eq('cf_hall_abc12', $cfg['custom'][2]['key'], 'existing key kept on rename');
    T::eq(true, $cfg['custom'][2]['required']);

    $fields = array_column(ClassFormSchema::forTutor(['hidden' => ['subject', 'description'], 'custom' => $cfg['custom']]), null, 'key');
    T::true(isset($fields['subject']), 'core field still shown');
    T::true(!isset($fields['description']), 'hidden field removed');
    T::eq('custom', $fields['cf_hall_abc12']['section']);
});

test('Class form: bad own fields are rejected with messages', function () {
    $e = T::throws(HttpException::class, fn () => ClassFormSchema::sanitiseSettings(['custom' => [['label' => '', 'type' => 'text'], ['label' => 'X', 'type' => 'select', 'options' => ['one']], ['label' => 'Y', 'type' => 'html']]]));
    T::eq(['custom.0.label', 'custom.1.options', 'custom.2.type'], array_keys($e?->details['fields'] ?? []));
});

test('Class form settings: required, names, order and own-field sections', function () {
    $cfg = ClassFormSchema::sanitiseSettings([
        'hidden' => ['cf_unit_aaaaa'],
        'required' => ['location', 'schedule', 'cover', 'is_free', 'subject', 'nonsense'],
        'labels' => ['location' => ['label' => 'Institute', 'info' => 'Where you teach', 'placeholder' => ''], 'medium' => ['label' => 'Medium'], 'bogus' => ['label' => 'x']],
        'order' => ['basics' => ['description', 'cf_unit_aaaaa', 'location', 'subject'], 'nowhere' => ['name']],
        'custom' => [['key' => 'cf_unit_aaaaa', 'label' => 'Unit', 'type' => 'text', 'section' => 'basics'], ['label' => 'Batch', 'type' => 'text', 'section' => 'mars']],
    ]);
    T::eq(['cf_unit_aaaaa'], $cfg['hidden'], 'own fields can be hidden too');
    T::eq(['location', 'schedule', 'cover'], $cfg['required'], 'only optional text/number/date/times/image fields can be made required');
    T::eq(['location' => ['label' => 'Institute', 'info' => 'Where you teach']], $cfg['labels'], 'unchanged names and unknown fields are not stored');
    T::eq(['basics' => ['description', 'cf_unit_aaaaa', 'subject']], $cfg['order'], 'order keeps only fields of that section');
    T::eq('custom', $cfg['custom'][1]['section'], 'unknown section falls back to More details');

    $all = ClassFormSchema::allForTutor($cfg);
    $basics = array_values(array_map(static fn ($f) => $f['key'], array_filter($all, static fn ($f) => $f['section'] === 'basics')));
    T::eq(['description', 'cf_unit_aaaaa', 'subject'], array_slice($basics, 0, 3), 'tutor order first');
    $byKey = array_column($all, null, 'key');
    T::eq(false, $byKey['cf_unit_aaaaa']['enabled']);
    T::eq('Institute', $byKey['location']['label']);
    T::eq('Hall / location', $byKey['location']['default_label']);
    T::eq(true, $byKey['location']['required']);
    T::eq(true, $byKey['subject']['core'] ?? false);
    T::eq('Weekly times', $byKey['schedule']['typeName']);
    $shown = array_column(ClassFormSchema::forTutor($cfg), 'key');
    T::true(!in_array('cf_unit_aaaaa', $shown, true), 'hidden own field not on the form');
});

test('Class thumbnail prompt uses the class details and keeps the text exact', function () {
    $p = \Enlixo\Classes\ClassThumbnailService::prompt(['subject' => 'Physics', 'exam' => 'A/L', 'year' => '2027', 'medium' => 'Sinhala', 'type' => 'Theory', 'name' => '', 'description' => 'Mechanics and waves'], 'minimal');
    T::true(str_contains($p, '"Physics"'), 'headline');
    T::true(str_contains($p, '"2027 A/L Theory"'), 'second line');
    T::true(str_contains($p, 'Minimal'), 'style');
    T::true(str_contains($p, 'Mechanics and waves'), 'description');
    T::true(str_contains($p, 'Sinhala'), 'medium');
});

test('Gemini image client reads the image and rejects missing or fake images', function () {
    $png = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg==');
    $http = new class implements \Enlixo\Http\Client\HttpClient {
        public array $next = [];
        public array $sent = [];
        public function request(string $method, string $url, array $headers = [], ?string $body = null): array { $this->sent[] = [$url, $headers, $body]; return array_shift($this->next); }
    };
    $client = new \Enlixo\Ai\GeminiImageClient($http, 'k-123');
    $http->next[] = ['status' => 200, 'raw' => '', 'body' => ['candidates' => [['content' => ['parts' => [['text' => 'here'], ['inlineData' => ['mimeType' => 'image/png', 'data' => base64_encode($png)]]]]]]]];
    $img = $client->generate('a cover');
    T::eq('image/png', $img['mime']);
    T::eq('k-123', $http->sent[0][1]['x-goog-api-key'], 'key sent in a header, not the URL');
    T::true(!str_contains($http->sent[0][0], 'k-123'));
    T::eq('16:9', json_decode($http->sent[0][2], true)['generationConfig']['imageConfig']['aspectRatio']);

    $http->next[] = ['status' => 200, 'raw' => '', 'body' => ['candidates' => [['content' => ['parts' => [['inlineData' => ['mimeType' => 'image/png', 'data' => base64_encode('<svg onload=alert(1)>')]]]]]]]];
    $e = T::throws(HttpException::class, fn () => $client->generate('x'));
    T::eq('ai_no_image', $e?->errorCode);

    $none = new \Enlixo\Ai\GeminiImageClient($http, '');
    $e = T::throws(HttpException::class, fn () => $none->generate('x'));
    T::eq(503, $e?->status);
});
