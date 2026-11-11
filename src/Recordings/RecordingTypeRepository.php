<?php
declare(strict_types=1);

namespace Enlixo\Recordings;

use Enlixo\Core\Crypto;
use Enlixo\Core\Database;
use Enlixo\Core\HttpException;
use Enlixo\Http\Client\CurlHttpClient;
use Enlixo\Http\Client\HttpClient;

/**
 * Settings -> Recording Types of one tutor (table tutor_recording_types, migration 010).
 * Like ENLIXO V3 Settings -> Recording Types: the platforms recordings can be added from, in the tutor's order,
 * each switched on or off. A type used by recordings can be switched off but not deleted; one must stay on.
 * Types are used by class recordings (lecture_recording) and pre-recorded classes (pre_recordings).
 * A Bunny Stream type can be connected to the tutor's Bunny library (library ID + API key, migration 011).
 */
final class RecordingTypeRepository
{
    /** Added for a tutor the first time the list is read: [name, platform, example, on]. */
    public const DEFAULTS = [
        ['YouTube', 'youtube', 'https://www.youtube.com/watch?v=dQw4w9WgXcQ', true],
        ['Bunny Stream', 'bunny', 'https://iframe.mediadelivery.net/embed/12345/aaaaaaaa-bbbb-cccc-dddd-eeeeeeeeeeee', true],
        ['Zoom', 'zoom', 'https://us02web.zoom.us/rec/share/abcDEF123456', true],
        ['Google Drive', 'drive', 'https://drive.google.com/file/d/1AbCdEfGhIjKlMnOp/view', true],
        ['Vimeo', 'vimeo', 'https://vimeo.com/123456789', false],
    ];

    public function __construct(
        private readonly Database $db,
        private readonly ?Crypto $crypto = null,
        private readonly ?HttpClient $http = null,
    ) {
    }

    /** Bunny client of a connected type, or null (not connected, or the key cannot be read). */
    public function bunny(int $tutor, int $typeId): ?BunnyStream
    {
        $r = $this->db->one(
            "SELECT bunny_library_id, bunny_api_key FROM tutor_recording_types WHERE recording_type_id = :id AND owner_user_id = :t AND platform_key = 'bunny'",
            ['id' => $typeId, 't' => $tutor],
        );
        if (!$r || !$r['bunny_library_id'] || !$r['bunny_api_key'] || $this->crypto === null) {
            return null;
        }
        try {
            $key = $this->crypto->decrypt((string) $r['bunny_api_key']);
        } catch (\Throwable $e) {
            error_log('bunny key: ' . $e->getMessage());
            return null;
        }
        return new BunnyStream($this->http ?? new CurlHttpClient(8), (int) $r['bunny_library_id'], $key);
    }

    public function ensureDefaults(int $tutor): void
    {
        if ((int) $this->db->value('SELECT COUNT(*) FROM tutor_recording_types WHERE owner_user_id = :t', ['t' => $tutor]) > 0) {
            return;
        }
        foreach (self::DEFAULTS as $i => [$name, $key, $example, $on]) {
            $this->db->execute(
                'INSERT IGNORE INTO tutor_recording_types (owner_user_id, name, platform_key, link_example, is_embeddable, sort_order, is_active)
                 VALUES (:t, :n, :k, :e, :emb, :o, :a)',
                ['t' => $tutor, 'n' => $name, 'k' => $key, 'e' => $example, 'emb' => LinkChecker::embeddable($key) ? 1 : 0, 'o' => $i + 1, 'a' => $on ? 1 : 0],
            );
        }
    }

    /** @return list<array<string,mixed>> */
    public function all(int $tutor, bool $activeOnly = false): array
    {
        $this->ensureDefaults($tutor);
        $rows = $this->db->all(
            'SELECT rt.*, (SELECT COUNT(*) FROM lecture_recording lr WHERE lr.tutor_recording_types_id = rt.recording_type_id)
                    + (SELECT COUNT(*) FROM pre_recordings pr WHERE pr.tutor_recording_types_id = rt.recording_type_id) AS used
               FROM tutor_recording_types rt
              WHERE rt.owner_user_id = :t' . ($activeOnly ? ' AND rt.is_active = 1' : '') . '
              ORDER BY rt.sort_order, rt.recording_type_id',
            ['t' => $tutor],
        );
        return array_map(static fn (array $r): array => [
            'id' => (int) $r['recording_type_id'],
            'name' => (string) $r['name'],
            'platform_key' => (string) $r['platform_key'],
            'platform_label' => LinkChecker::PLATFORMS[$r['platform_key']][0] ?? (string) $r['platform_key'],
            'allowed_hosts' => (string) ($r['allowed_hosts'] ?? ''),
            'link_example' => (string) ($r['link_example'] ?? ''),
            'embeddable' => (bool) $r['is_embeddable'],
            'active' => (bool) $r['is_active'],
            'used' => (int) $r['used'],
            'bunny_library_id' => $r['bunny_library_id'] !== null ? (int) $r['bunny_library_id'] : null,
            'bunny_connected' => $r['bunny_library_id'] !== null && !empty($r['bunny_api_key']),
        ], $rows);
    }

    public function find(int $tutor, int $id): ?array
    {
        foreach ($this->all($tutor) as $t) {
            if ($t['id'] === $id) {
                return $t;
            }
        }
        return null;
    }

    /** Validates and saves. Returns the saved type; throws 422 with field errors. */
    public function save(int $tutor, ?int $id, array $in, int $userId): array
    {
        $err = [];
        $name = trim((string) ($in['name'] ?? ''));
        $key = trim((string) ($in['platform_key'] ?? ''));
        $example = trim((string) ($in['link_example'] ?? ''));
        $hosts = null;

        if ($name === '') {
            $err['name'] = 'Enter a name, for example Zoom';
        } elseif (mb_strlen($name) > 60) {
            $err['name'] = 'Use up to 60 characters';
        } elseif ($this->db->value(
            'SELECT 1 FROM tutor_recording_types WHERE owner_user_id = :t AND LOWER(name) = LOWER(:n)' . ($id ? ' AND recording_type_id <> :id' : ''),
            ['t' => $tutor, 'n' => $name] + ($id ? ['id' => $id] : []),
        )) {
            $err['name'] = 'You already have a recording type with this name';
        }
        if (!isset(LinkChecker::PLATFORMS[$key])) {
            $err['platform_key'] = 'Choose where the recordings are kept';
        } elseif ($key === 'custom') {
            $parsed = LinkChecker::parseHosts((string) ($in['allowed_hosts'] ?? ''));
            if (is_string($parsed)) {
                $err['allowed_hosts'] = $parsed;
            } else {
                $hosts = implode(',', $parsed);
            }
        }
        if (mb_strlen($example) > 255) {
            $err['link_example'] = 'Use up to 255 characters';
        } elseif ($example !== '' && !preg_match('#^https://\S+$#i', $example)) {
            $err['link_example'] = 'The example link must start with https://';
        } elseif ($example !== '' && !isset($err['platform_key']) && !isset($err['allowed_hosts'])) {
            $check = LinkChecker::check(['name' => $name ?: 'this', 'platform_key' => $key, 'allowed_hosts' => (string) $hosts, 'link_example' => ''], $example);
            if (!$check['ok']) {
                $err['link_example'] = 'This example does not pass the link check of this type: ' . $check['error'];
            }
        }
        [$lib, $apiKey] = $key === 'bunny' ? $this->bunnyInput($tutor, $id, $in, $err) : [null, null];
        if ($err) {
            throw new HttpException(422, 'validation_failed', 'Please check the highlighted fields.', $err);
        }

        $params = ['n' => $name, 'k' => $key, 'h' => $hosts, 'e' => $example !== '' ? $example : null, 'emb' => LinkChecker::embeddable($key) ? 1 : 0, 't' => $tutor,
            'lib' => $lib, 'key' => $apiKey];
        if ($id) {
            $old = $this->find($tutor, $id) ?? throw HttpException::notFound('This recording type was not found.');
            if ($old['used'] > 0 && $old['platform_key'] !== $key) {
                throw new HttpException(422, 'validation_failed', 'Please check the highlighted fields.', ['platform_key' => "{$old['used']} recordings use this type, so where they are kept cannot change. Add a new type instead."]);
            }
            $this->db->execute(
                'UPDATE tutor_recording_types SET name = :n, platform_key = :k, allowed_hosts = :h, link_example = :e, is_embeddable = :emb,
                        bunny_library_id = :lib, bunny_api_key = :key
                  WHERE recording_type_id = :id AND owner_user_id = :t',
                $params + ['id' => $id],
            );
        } else {
            $order = (int) $this->db->value('SELECT COALESCE(MAX(sort_order), 0) + 1 FROM tutor_recording_types WHERE owner_user_id = :t', ['t' => $tutor]);
            $id = $this->db->insert(
                'INSERT INTO tutor_recording_types (owner_user_id, name, platform_key, allowed_hosts, link_example, is_embeddable, sort_order, is_active, added_by,
                        bunny_library_id, bunny_api_key)
                 VALUES (:t, :n, :k, :h, :e, :emb, :o, 1, :u, :lib, :key)',
                $params + ['o' => $order, 'u' => $userId],
            );
        }
        return $this->find($tutor, $id);
    }

    /**
     * Bunny library ID + API key from the form. The key is never sent back to the browser: on edit an empty key
     * keeps the saved one; bunny_disconnect = 1 removes both. A new key is tested with Bunny before it is saved.
     * @return array{0:?int, 1:?string} library id, encrypted key
     */
    private function bunnyInput(int $tutor, ?int $id, array $in, array &$err): array
    {
        if (filter_var($in['bunny_disconnect'] ?? false, FILTER_VALIDATE_BOOLEAN)) {
            return [null, null];
        }
        $libText = trim((string) ($in['bunny_library_id'] ?? ''));
        $newKey = trim((string) ($in['bunny_api_key'] ?? ''));
        $saved = $id ? $this->db->one('SELECT bunny_library_id, bunny_api_key FROM tutor_recording_types WHERE recording_type_id = :id AND owner_user_id = :t', ['id' => $id, 't' => $tutor]) : null;
        if ($libText === '' && $newKey === '') {
            return [null, null];
        }
        if (!preg_match('/^\d{1,10}$/', $libText)) {
            $err['bunny_library_id'] = $libText === '' ? 'Enter the Library ID, for example 123456' : 'The Library ID is a number, for example 123456';
            return [null, null];
        }
        $lib = (int) $libText;
        $keepKey = $newKey === '' && $saved && (int) $saved['bunny_library_id'] === $lib ? ($saved['bunny_api_key'] ?: null) : null;
        if ($newKey === '') {
            if ($saved && $saved['bunny_api_key'] && (int) $saved['bunny_library_id'] !== $lib) {
                $err['bunny_api_key'] = 'The Library ID changed: enter the API key of the new library too';
            }
            return [$lib, $keepKey];
        }
        if (!preg_match('/^[A-Za-z0-9-]{20,100}$/', $newKey)) {
            $err['bunny_api_key'] = 'That does not look like a Bunny API key. Copy it from Stream > your library > API';
            return [$lib, null];
        }
        if ($this->crypto === null) {
            $err['bunny_api_key'] = 'The API key cannot be stored safely on this server yet. Please contact support.';
            return [$lib, null];
        }
        $problem = (new BunnyStream($this->http ?? new CurlHttpClient(8), $lib, $newKey))->testConnection();
        if ($problem !== null) {
            $err['bunny_api_key'] = $problem;
            return [$lib, null];
        }
        return [$lib, $this->crypto->encrypt($newKey)];
    }

    private function activeOthers(int $tutor, int $id): int
    {
        return (int) $this->db->value('SELECT COUNT(*) FROM tutor_recording_types WHERE owner_user_id = :t AND is_active = 1 AND recording_type_id <> :id', ['t' => $tutor, 'id' => $id]);
    }

    public function setActive(int $tutor, int $id, bool $on): array
    {
        $t = $this->find($tutor, $id) ?? throw HttpException::notFound('This recording type was not found.');
        if (!$on && $t['active'] && $this->activeOthers($tutor, $id) === 0) {
            throw new HttpException(409, 'conflict', 'Keep at least one recording type switched on, so recordings can still be added.');
        }
        $this->db->execute('UPDATE tutor_recording_types SET is_active = :a WHERE recording_type_id = :id AND owner_user_id = :t', ['a' => $on ? 1 : 0, 'id' => $id, 't' => $tutor]);
        return $t;
    }

    public function delete(int $tutor, int $id): array
    {
        $t = $this->find($tutor, $id) ?? throw HttpException::notFound('This recording type was not found.');
        if ($t['used'] > 0) {
            throw new HttpException(409, 'conflict', "{$t['used']} recordings use {$t['name']}, so it cannot be deleted. Switch it off instead: those recordings keep working.");
        }
        if ($t['active'] && $this->activeOthers($tutor, $id) === 0) {
            throw new HttpException(409, 'conflict', 'Keep at least one recording type switched on, so recordings can still be added.');
        }
        $this->db->execute('DELETE FROM tutor_recording_types WHERE recording_type_id = :id AND owner_user_id = :t', ['id' => $id, 't' => $tutor]);
        return $t;
    }

    /** @param list<int> $ids the whole new order; types not sent keep their place after them */
    public function reorder(int $tutor, array $ids): void
    {
        $n = 1;
        $seen = [];
        foreach ($ids as $id) {
            $id = (int) $id;
            if ($id > 0 && !isset($seen[$id])) {
                $seen[$id] = true;
                $this->db->execute('UPDATE tutor_recording_types SET sort_order = :o WHERE recording_type_id = :id AND owner_user_id = :t', ['o' => $n++, 'id' => $id, 't' => $tutor]);
            }
        }
        foreach ($this->all($tutor) as $t) {
            if (!isset($seen[$t['id']])) {
                $this->db->execute('UPDATE tutor_recording_types SET sort_order = :o WHERE recording_type_id = :id', ['o' => $n++, 'id' => $t['id']]);
            }
        }
    }

    /**
     * Check a link for a type. When it fails, the other switched-on types are tried so the message can say
     * "this looks like a Zoom link".
     */
    public function verify(int $tutor, int $typeId, string $link): array
    {
        $types = $this->all($tutor);
        $type = null;
        foreach ($types as $t) {
            if ($t['id'] === $typeId) {
                $type = $t;
            }
        }
        if ($type === null) {
            return ['ok' => false, 'url' => '', 'error' => 'Choose a recording type'];
        }
        $r = LinkChecker::check($type, $link) + ['embeddable' => $type['embeddable'], 'type' => $type['name']];
        if ($r['ok'] && isset($r['bunny']) && ($bunny = $this->bunny($tutor, $typeId)) !== null) {
            $v = $bunny->video($r['bunny']['guid']);
            if (!$v['found']) {
                return ['ok' => false, 'url' => '', 'error' => "There is no video with this ID in your Bunny library {$type['bunny_library_id']}. Check that you copied the right video.", 'embeddable' => $type['embeddable'], 'type' => $type['name']];
            }
            if ($v['error'] !== null) {
                $r['note'] = $v['error'] . ' The link is fine and will play.';
            } else {
                $r['video'] = ['title' => $v['title'], 'minutes' => (int) ceil($v['seconds'] / 60), 'state' => $v['state']];
                $r['note'] = match ($v['state']) {
                    'processing' => 'Bunny is still processing this video. Students can watch it as soon as Bunny finishes.',
                    'failed' => 'Bunny could not process this video. Upload it to Bunny again before students watch it.',
                    default => null,
                };
            }
        }
        unset($r['bunny']);
        if (!$r['ok']) {
            foreach ($types as $other) {
                if ($other['active'] && $other['id'] !== $typeId && LinkChecker::check($other, $link)['ok']) {
                    $r['error'] = "This looks like a {$other['name']} link. Choose {$other['name']} as the type, or paste a {$type['name']} link.";
                    $r['suggest'] = $other['id'];
                    break;
                }
            }
        }
        return $r;
    }
}
