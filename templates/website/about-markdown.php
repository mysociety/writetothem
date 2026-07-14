<?php

/*
 * about-markdown.php:
 * Shared framework for rendering markdown content files inside templates.
 *
 * Content lives under templates/website/<subdir>/<lang>/<file>.md and is
 * rendered with Parsedown. Lookups are language-aware and fall back to
 * English, so a page only needs a cy/ file where a real Welsh translation
 * exists.
 *
 * On top of plain markdown we add two features (see AboutParsedown):
 *   - headings may carry an explicit anchor with `## Heading {#anchor}`,
 *     falling back to a slug of the heading text. This keeps deep-links such
 *     as /about-qa#formletters stable.
 *   - a `[TOC]` line is replaced with an in-page TOC built from the page's
 *     headings, and a "back to top" link is appended after each section.
 */

require_once __DIR__ . '/../../vendor/Parsedown.php';

class AboutParsedown extends Parsedown {

    /**
     * Headings seen during the current text() call, in document order.
     * Collected by blockHeader() as a side effect of parsing; exposed only
     * through renderWithHeadings() so it can't be read stale.
     *
     * @var array<array{level:int,text:string,id:string}>
     */
    private array $headings = [];

    /**
     * Render markdown to HTML and return the headings collected along the way,
     * as [html, headings]. Bundling the two keeps them in step: there is no
     * separate "remember to reset the heading list" step for callers to forget.
     */
    public function renderWithHeadings(string $text): array {
        $this->headings = [];
        $html = $this->text($text);
        return [$html, $this->headings];
    }

    /*
     * Parsedown calls blockHeader() for every heading (# .. ######). We
     * hook it to (a) honour an explicit {#anchor} suffix, (b) fall back to a
     * slug, and (c) record the heading so the [TOC] menu can be built from it.
     */
    protected function blockHeader($Line) {
        // Let Parsedown do the normal parsing first; $Block is the element it
        // will render (or null if this line isn't actually a heading).
        $Block = parent::blockHeader($Line);
        if (!isset($Block)) {
            return $Block;
        }

        // Re-derive the heading level (number of leading '#') and its text the
        // same way Parsedown does, so we can inspect/rewrite the text.
        $level = strspn($Line['text'], '#');
        $text = trim(trim($Line['text'], '#'), ' ');

        // A trailing "{#anchor}" gives the heading an explicit, stable id. We
        // strip it from the visible text (both in our record and in the element
        // Parsedown will render) and use it as the id; otherwise slug the text.
        if (preg_match('/^(.*?)\s*\{#([A-Za-z0-9_\-]+)\}$/', $text, $m)) {
            $text = rtrim($m[1]);
            $id = $m[2];
            $Block['element']['handler']['argument'] = $text;
        } else {
            $id = $this->slug($text);
        }

        // Emit id="..." on the rendered <hN>, and remember the heading for the
        // menu. tabindex="-1" makes the heading a focusable jump target.
        $Block['element']['attributes']['id'] = $id;
        $Block['element']['attributes']['tabindex'] = '-1';
        $this->headings[] = ['level' => $level, 'text' => $text, 'id' => $id];

        return $Block;
    }

    /**
     * Derive a URL-friendly anchor from heading text (fallback when no
     * explicit {#anchor} is given).
     */
    protected function slug(string $text): string {
        $text = html_entity_decode(strip_tags($text), ENT_QUOTES, 'UTF-8');
        $text = strtolower(trim($text));
        $text = preg_replace('/[^a-z0-9]+/', '-', $text);
        return trim($text, '-');
    }
}

/**
 * Resolve, substitute and parse a markdown file, returning the rendered HTML
 * alongside the headings and the parser used, as
 * ['html' => string, 'headings' => array, 'pd' => AboutParsedown].
 *
 * Resolves $subdir/<LANGUAGE>/$filename, falling back to $subdir/en/$filename
 * when the current language has no translation. $context supplies {{KEY}}
 * placeholders substituted into the source before it is parsed.
 */
function render_md_parts(string $filename, string $subdir, array $context = []): ?array {
    $base = __DIR__ . "/$subdir";
    $lang = defined('LANGUAGE') ? LANGUAGE : 'en';
    $path = "$base/$lang/$filename";
    if ($lang !== 'en' && !file_exists($path)) {
        $path = "$base/en/$filename";
    }
    if (!file_exists($path)) {
        return null;
    }

    // Substitute {{KEY}} placeholders in the raw markdown, before parsing, so a
    // value can appear anywhere (text, an href, ...) and be parsed in context.
    $text = file_get_contents($path);
    foreach ($context as $key => $value) {
        $text = str_replace('{{' . $key . '}}', htmlspecialchars($value, ENT_QUOTES | ENT_HTML5, 'UTF-8'), $text);
    }

    $pd = new AboutParsedown();
    [$html, $headings] = $pd->renderWithHeadings($text);

    return ['html' => $html, 'headings' => $headings, 'pd' => $pd];
}

/**
 * Render a markdown file to HTML.
 *
 * Resolves $subdir/<LANGUAGE>/$filename, falling back to $subdir/en/$filename
 * when the current language has no translation. $context supplies {{KEY}}
 * placeholders substituted into the source before it is parsed, which lets
 * pages inject dynamic values (e.g. an obfuscated contact address) into
 * otherwise-static content. A `[TOC]` line becomes an auto-generated in-page
 * TOC (see about_page_toc / about_insert_toplinks).
 */
function render_md(string $filename, string $subdir, $context = []) {
    $parts = render_md_parts($filename, $subdir, $context);
    if ($parts === null) {
        return '';
    }
    $html = $parts['html'];

    // A page opts into the generated menu by including a `[TOC]` line, which
    // Parsedown renders as a literal <p>[TOC]</p>. Order matters here: insert
    // the toplinks first, while [TOC] is still an ordinary paragraph, so the
    // menu markup we substitute in next is never mistaken for section content.
    if (strpos($html, '[TOC]') !== false) {
        $html = about_insert_toplinks($html);
        $menu = about_page_toc($parts['headings'], $parts['pd']);
        $html = str_replace(['<p>[TOC]</p>', '[TOC]'], $menu, $html);
    }

    return $html;
}

/**
 * Render an about-page markdown file from templates/website/about-md.
 */
function about_md(string $filename, $context = []) {
    return render_md($filename, 'about-md', $context);
}


/**
 * Build the {{...}} substitutions for the about-linktous embed builders.
 * The builders' CSS and JS live in the normal site-wide assets (see
 * web/static/sass/_linktous.scss and web/static/js/linktous.js).
 *
 * @return array<string,string> extra context entries for render_md()
 */
function linktous_builder_context(): array {
    $frag = __DIR__ . '/about-md/fragments';
    $options = file_get_contents("$frag/linktous-rep-options.html");

    return [
        'FORM_BUILDER' => str_replace('{{REP_OPTIONS}}', $options,
            file_get_contents("$frag/linktous-form-builder.html")),
        'LINK_BUILDER' => str_replace('{{REP_OPTIONS}}', $options,
            file_get_contents("$frag/linktous-link-builder.html")),
    ];
}

/**
 * The dynamic {{KEY}} context for an about-md page.
 */
function about_md_context(string $md_page): array {
    $context = [];
    if ($md_page === 'about-qa') {
        $context['CONTACT_EMAIL'] = OPTION_CONTACT_EMAIL;
    }
    if ($md_page === "about-linktous") {
        $context += linktous_builder_context();
    }
    return $context;
}

/**
 * Render a single section of an about-md page: the heading whose id is $anchor
 * plus the body beneath it, as a fragment for the write-page
 * lightbox. A null $anchor returns the whole page body, without the [TOC]
 * menu (whose in-page anchor links only make sense on the full page).
 */
function about_md_section(string $filename, ?string $anchor, array $context = []): string {
    $parts = render_md_parts($filename, 'about-md', $context);
    if ($parts === null) {
        return '';
    }

    if ($anchor === null) {
        return str_replace('<p>[TOC]</p>', '', $parts['html']);
    }

    // Split on heading tags
    /// the array alternates  intro / heading / body
       $split = preg_split(
        '/(<h[1-6]\b[^>]*>.*?<\/h[1-6]>)/s',
        $parts['html'], -1, PREG_SPLIT_DELIM_CAPTURE
    );
    for ($k = 1; $k < count($split); $k += 2) {
        if (strpos($split[$k], 'id="' . $anchor . '"') !== false) {
            return $split[$k] . ($split[$k + 1] ?? '');
        }
    }
    return '';   // unknown anchor
}

/**
 * Build the in-page TOC from a page's headings. Level-2 headings with
 * level-3 headings beneath them act as (unlinked) group labels, matching the
 * hand-written menus these pages used to carry; a page with only level-2
 * headings produces a flat list of links.
 */
function about_page_toc(array $headings, Parsedown $pd): string {
    // "Grouped" pages (e.g. about-qa) use level-2 headings as section labels
    // and level-3 headings as the linked items; "flat" pages (e.g.
    // about-constituency) have only level-2 headings, which become the links.
    $grouped = in_array(3, array_column($headings, 'level'), true);

    // The empty <span id="top"> is the target for the "back to top" links (see
    // below); tabindex="-1" makes it focusable so keyboard/AT focus actually
    // lands there when the link is followed, rather than only scrolling.
    // The whole menu is a labelled <nav> landmark so it is announced as the
    // page contents and kept distinct from the site's inter-page sidebar.
    $out = '<span id="top" tabindex="-1"></span>' . "\n";
    $out .= '<nav class="page-contents" aria-label="'
        . htmlspecialchars(_('On this page'), ENT_QUOTES) . '">' . "\n";
    $out .= '<ul class="side-nav">' . "\n";

    if ($grouped) {
        // Group label + a nested <ul> of its links, so the parent/child
        // relationship is conveyed to assistive tech, not just by styling.
        $open = false;
        foreach ($headings as $h) {
            $label = $pd->line($h['text']);
            if ($h['level'] === 2) {
                if ($open) {
                    $out .= "</ul></li>\n";
                }
                $out .= '<li class="heading">' . $label . "\n<ul>\n";
                $open = true;
            } elseif ($h['level'] === 3) {
                $out .= '<li><a href="#' . $h['id'] . "\">$label</a></li>\n";
            }
        }
        if ($open) {
            $out .= "</ul></li>\n";
        }
    } else {
        foreach ($headings as $h) {
            if ($h['level'] === 2) {
                // Render the label through Parsedown's inline parser so any
                // markdown or entities come out the same as in the body.
                $label = $pd->line($h['text']);
                $out .= '<li><a href="#' . $h['id'] . "\">$label</a></li>\n";
            }
        }
    }

    $out .= "</ul>\n</nav>\n";

    return $out;
}

/**
 * Append a "back to top" link at the end of each section (i.e. before the next
 * heading, and after the final section), matching the per-answer "top" links
 * these pages used to carry. 
 */
function about_insert_toplinks(string $html): string {
    $top = '<p class="toplink"><a href="#top">' . _('Back to top') . '</a></p>' . "\n";

    // Split on <h2>/<h3> tags, keeping them (DELIM_CAPTURE). The result
    // alternates body/heading/body/heading/...: even indexes are the text
    // between headings, odd indexes are the heading tags themselves.
    //   parts[0]        = content before the first heading (intro + [TOC])
    //   parts[1,3,5,..] = headings
    //   parts[2,4,6,..] = each heading's section body
    $parts = preg_split('/(<h[23]\b[^>]*>.*?<\/h[23]>)/s', $html, -1, PREG_SPLIT_DELIM_CAPTURE);
    if (count($parts) <= 1) {
        return $html;   // no headings, nothing to do
    }

    $out = $parts[0];
    for ($k = 1; $k < count($parts); $k += 2) {
        // Put a "top" link *before* this heading — i.e. at the end of the
        // previous section — but only if that previous section actually had
        // content. This skips the first heading (k == 1, preceded by the intro)
        // and the empty gap between a group heading and its first question.
        $preceding = $parts[$k - 1];
        if ($k > 1 && trim(strip_tags($preceding)) !== '') {
            $out .= $top;
        }
        $out .= $parts[$k];                          // the heading
        if (isset($parts[$k + 1])) {
            $out .= $parts[$k + 1];                  // section body
        }
    }

    // The final section has no following heading, so close it off explicitly.
    $trailing = $parts[count($parts) - 1];
    if (trim(strip_tags($trailing)) !== '') {
        $out .= $top;
    }

    return $out;
}
