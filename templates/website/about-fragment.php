<?php

/*
 * about-fragment.php:
 * A bare HTML fragment of a single about-page section (or a page's intro),
 * ajax-loaded into a lightbox by the deep-links on the who/write
 * pages. The router (web/about.php) sets md_page and (optionally) md_anchor.
 */

require_once __DIR__ . '/about-markdown.php';
    
$md_page = $values['md_page'];
$anchor = $values['md_anchor'] ?? null;

$body = about_md_section("$md_page.md", $anchor, about_md_context($md_page));
$full_url = '/' . $md_page . ($anchor ? '#' . $anchor : '');

?>
<div class="help-fragment">

<?= $body ?>

    <p class="help-fragment__more">
        <a href="<?= htmlspecialchars($full_url) ?>"><?= _('Read the full guidance') ?> &rarr;</a>
    </p>
</div>
