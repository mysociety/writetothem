<?php

/*
 * about-page.php:
 * Common template for the about-* help pages whose body content lives in
 * about-md/. The router (web/about.php) sets $values['md_page'] to the
 * requested page name; this template supplies the per-page title and heading
 * and renders the body from about-md/<lang>/<page>.md.
 *
 * To add a new markdown about page: drop an about-md/en/<name>.md file in place
 * and add a title/heading entry below.
 */

require_once __DIR__ . '/about-markdown.php';

$about_pages = [
    'about-us' => [
        'title' => _('Who are you, and why are you doing this?'),
        'heading' => _('Who are you, and why are you doing this?'),
    ],
    'about-campaigns' => [
        'title' => _('Using WriteToThem to run a campaign'),
        'heading' => _('Running a campaign'),
    ],
    'about-guidelines' => [
        'title' => _('Guidelines for campaigning'),
        'heading' => _('Campaigning: terms and conditions'),
    ],
    'about-constituency' => [
        'title' => _('What postcode should I use?'),
        'heading' => _('What postcode should I use?'),
    ],
    'about-branded' => [
        'title' => _('WriteToThem for your website'),
        'heading' => _('WriteToThem for your website'),
    ],
    'about-copyright' => [
        'title' => _('Copyright information'),
        'heading' => _('Copyright information'),
    ],
    'about-linktous' => [
        'title' => _('How to link to us'),
        'heading' => _('How to link to us'),
    ],
    'about-qa' => [
        'title' => _('Questions and answers'),
        'heading' => _('Questions and answers'),
    ],
    'about-lords' => [
        'title' => _('House of Lords'),
        'heading' => _('House of Lords'),
    ],
    'about-privacy' => [
        'title' => _('Privacy'),
        'heading' => _('Privacy'),
    ],
];

$md_page = $values['md_page'];
if (!array_key_exists($md_page, $about_pages)) {
    http_response_code(404);
    template_show_error(_("Page not found."));
}
$meta = $about_pages[$md_page];

$context = about_md_context($md_page);

$values['title'] = $meta['title'];
template_draw('header', $values);

?>

<div class="row">
    <div class="large-10 large-centered columns">

        <div class="row">
            <div class="large-12 columns">
                <h2><?= $meta['heading'] ?></h2>
            </div>
        </div>

        <div class="row">

            <div class="large-8 columns">

<?= about_md("$md_page.md", $context) ?>

            </div>

            <div class="large-4 columns">

                <?php template_draw('about-sidebar'); ?>

            </div>

        </div>
    </div>
</div>

<?php template_draw('footer', $values);
