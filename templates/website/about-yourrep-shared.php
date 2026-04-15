<?php

$pd = new Parsedown();

/**
 * Load a markdown file with language awareness.
 * Tries LANGUAGE first (e.g. cy), falls back to en.
 */
function yourrep_md($filename, $pd) {
    $base = __DIR__ . '/yourrep-md';
    $lang = defined('LANGUAGE') ? LANGUAGE : 'en';
    $path = "$base/$lang/$filename";
    if ($lang !== 'en' && !file_exists($path)) {
        $path = "$base/en/$filename";
    }
    if (!file_exists($path)) {
        return '';
    }
    return $pd->text(file_get_contents($path));
}

?>
