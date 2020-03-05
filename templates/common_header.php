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
    <script>
        function addss(d,u,l) {
            l=d.createElement('link'),l.rel='stylesheet',l.href=u;
            d.getElementsByTagName('head')[0].appendChild(l);
        }
        if (navigator.userAgent.match(/Windows.*Chrom(e|ium)\/2[2-8]\./)) {
            addss(document, '/static/css/chrome22-28.css');
        }
    </script>

    <?php if (OPTION_WEB_DOMAIN == 'writetothem.com'): ?>

    <script>
        (function(i,s,o,g,r,a,m){i['GoogleAnalyticsObject']=r;i[r]=i[r]||function(){
        (i[r].q=i[r].q||[]).push(arguments)},i[r].l=1*new Date();a=s.createElement(o),
        m=s.getElementsByTagName(o)[0];a.async=1;a.src=g;m.parentNode.insertBefore(a,m)
        })(window,document,'script','//www.google-analytics.com/analytics.js','ga');

        ga('create', '<?=OPTION_GOOGLE_ANALYTICS_TRACKING_CODE?>', 'writetothem.com', {'storage':'none'});
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

        function setDimension1(value) {
            try {
                ga('set', 'dimension1', value);
            } catch(err){}
        }
    </script>

    <?php endif; ?>

    <?php if (array_key_exists('header_js', $values)): ?>
    <script>
        <?=$values['header_js']?>
    </script>
    <?php endif; ?>

</head>

<body <?php if (array_key_exists('body_class', $values)): ?>class="<?php echo $values['body_class']; ?>"<?php endif; ?>>

<?php if (BANNER_MESSAGE): ?>

<div class="banner banner--donate">
    <div class="row">
        <div class="large-10 large-centered columns">
            <?=BANNER_MESSAGE ?>
        </div>
    </div>
</div>

<?php endif; ?>

<?php if (0 && OPTION_FYR_REFLECT_EMAILS): ?>

<div class="banner banner--staging">
    <div class="row">
        <div class="large-10 large-centered columns">
            <strong>This is a staging site.</strong> Emails will not be sent to representatives, but will instead be sent to you.
        </div>
    </div>
</div>

<?php endif; ?>

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

