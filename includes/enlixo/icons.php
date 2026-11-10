<?php
/**
 * The one icon library for every Tutor page: Solar icons, served from this site (assets/icons/icons.json,
 * built by bin/build-icons.mjs from assets/icons/icon-map.json). JS pages use icon() with the same names.
 *   <?= enlixo_icon('edit') ?>   <?= enlixo_icon('classes', 20, 'me-2') ?>
 */
function enlixo_icon(string $name, int $size = 18, string $class = ''): string
{
    static $icons = null;
    if ($icons === null) {
        $icons = json_decode((string) @file_get_contents(dirname(__DIR__, 2) . '/assets/icons/icons.json'), true) ?: [];
    }
    $body = $icons[$name] ?? $icons['info'] ?? '';
    return '<span class="ex-ico ' . htmlspecialchars($class, ENT_QUOTES) . '"><svg xmlns="http://www.w3.org/2000/svg" width="' . $size . '" height="' . $size
        . '" viewBox="0 0 24 24" aria-hidden="true" focusable="false">' . $body . '</svg></span>';
}
