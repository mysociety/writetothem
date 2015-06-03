<?php

header("Content-Type: text/html; charset=utf-8");

if (isset($values['cobrand']))
{
    print cobrand_headers($values['cobrand'], 'common_header');
}

?><!DOCTYPE html>
<!--[if IE 8]><html class="no-js lt-ie9" lang="en"><![endif]-->
<!--[if gt IE 8]><!--><html class="no-js" lang="en"><!--<![endif]-->
<head>

    <meta http-equiv="Content-Type" content="text/html; charset=utf-8">

    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">

    <?php if (array_key_exists('robots', $values)): ?>
    <meta name="robots" content="<?=$values['robots']?>">
    <?php endif; ?>

    <title>WriteToThem - <?=$values['title']?></title>

    <link rel="stylesheet" href="/static/css/wtt.css">
    <link rel="stylesheet" href="/static/js/fancybox/jquery.fancybox-1.3.4.css" type="text/css">
    <?php if (array_key_exists('stylesheet', $values)): ?>
    <style type="text/css">@import "<?=$values['stylesheet']?>";</style>
    <?php endif; ?>

    <link rel="shortcut icon" href="/favicon.ico" type="image/x-icon">
    <link rel="icon" type="image/png" href="/static/img/favicon-128.png">
    <link rel="apple-touch-icon-precomposed" sizes="60x60" href="/static/img/favicon-60.png">
    <link rel="apple-touch-icon-precomposed" sizes="76x76" href="/static/img/favicon-76.png">
    <link rel="apple-touch-icon-precomposed" sizes="120x120" href="/static/img/favicon-120.png">
    <link rel="apple-touch-icon-precomposed" sizes="156x156" href="/static/img/favicon-156.png">

    <meta property="og:title" content="WriteToThem">
    <meta property="og:type" content="website">
    <meta property="og:url" content="<?=OPTION_BASE_URL?>">
    <meta property="og:image" content="<?=OPTION_BASE_URL?>/static/img/favicon-256.png">
    <meta property="og:description" content="WriteToThem is a website which provides an easy way to contact MPs, councillors and other elected representatives.">

    <script src="/static/js/vendor/custom.modernizr.js"></script>

    <?php if (OPTION_WEB_DOMAIN == 'writetothem.com'): ?>

    <?php
        $url = parse_url($_SERVER['REQUEST_URI']);
        if ($url['path'] == '/' && isset($values['experiment']) && $values['experiment']) {
    ?>
        <!-- Load GA experiments API -->
        <script src="//www.google-analytics.com/cx/api.js?experiment=9AJDuy1gQjiG51CmMpaVlg"></script>
        <script>
            // Select GA experiment variation
            var chosenVariation = cxApi.chooseVariation();

            // Text variations to use
            var pageVariations = [

                function() {    // Original
                    document.getElementById('title').innerHTML = 'Write to your politicians, national or local, for free.';
                },
                function() {    // Variant 1
                    document.getElementById('title').innerHTML = 'Email your MP, local councillors or other representatives.';
                },
                function() {    // Variant 2
                    document.getElementById('title').innerHTML = 'Email the people in power.';
                }
            ];
        </script>
    <?php } ?>

    <script>
        (function(i,s,o,g,r,a,m){i['GoogleAnalyticsObject']=r;i[r]=i[r]||function(){
        (i[r].q=i[r].q||[]).push(arguments)},i[r].l=1*new Date();a=s.createElement(o),
        m=s.getElementsByTagName(o)[0];a.async=1;a.src=g;m.parentNode.insertBefore(a,m)
        })(window,document,'script','//www.google-analytics.com/analytics.js','ga');

        ga('create', '<?=OPTION_GOOGLE_ANALYTICS_TRACKING_CODE?>', 'writetothem.com');
        ga('set', 'anonymizeIp', true);
        ga('send', 'pageview');

        function trackFormSubmit(form, category, name) {
            try {
                ga('send', 'event', category, name);
            } catch(err){}
            setTimeout(function() {
                form.submit();
            }, 100);
        }

    </script>

    <?php endif; ?>

    <?php if (array_key_exists('header_js', $values)): ?>
    <script>
        <?=$values['header_js']?>
    </script>
    <?php endif; ?>

</head>

<body>

<?php if (OPTION_FYR_REFLECT_EMAILS): ?>

<div class="staging">
    <div class="row">
        <div class="large-10 large-centered columns">
            <strong>This is a staging site.</strong> Emails will not be sent to representatives, but will instead be sent to you.
        </div>
    </div>
</div>

<?php endif; ?>

<div class="ms-header">
    <div class="ms-header__container large-10 large-centered columns">
        <div class="ms-header__logo">
            <a href="http://mysociety.org">mySociety</a>
        </div>
    </div>
</div>

<div class="content-wrapper">
    <div class="row row-full-width">
        <div class="large-12 columns content">

          <?php if ( ! isset($values['skip_header']) ): ?>
            <div class="row banner-top">
                <div class="large-10 large-centered columns">
                    <a href="/" class="wtt-logo"><img src="/static/img/logo.png" style="max-width:250px;max-height:100%;" alt="WriteToThem"></a>
                    <a href="/about-qa" target="_blank" class="wtt-help" title="Opens in a new window">Help</a>
                </div>
            </div>
          <?php endif; ?>

<?php
if (array_key_exists('header', $values)) {
    print $values['header'];
}
?>

