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

    <?php if (array_key_exists('stylesheet', $values)): ?>
    <style type="text/css">@import "<?=$values['stylesheet']?>";</style>
    <?php endif; ?>

    <!-- Google Content Experiments for redesign A/B testing -->
    <script src="//www.google-analytics.com/cx/api.js"></script>
    <script>
        cxApi.setChosenVariation(
            1,
            'XHF3VzPlR2i_39Q5ASqoCw'
        );
    </script>

    <?php if (OPTION_WEB_DOMAIN == 'writetothem.com'): ?>
    <script type="text/javascript">
      var _gaq = _gaq || [];
      _gaq.push(['_setAccount', '<?=OPTION_GOOGLE_ANALYTICS_TRACKING_CODE?>']);
      _gaq.push(['_setDomainName', 'writetothem.com']);
      _gaq.push(['_setAllowLinker', true]);
      _gaq.push (['_gat._anonymizeIp']);
      _gaq.push(['_trackPageview']);
      (function() {
        var ga = document.createElement('script'); ga.type = 'text/javascript'; ga.async = true;
        ga.src = ('https:' == document.location.protocol ? 'https://ssl' : 'http://www') + '.google-analytics.com/ga.js';
        var s = document.getElementsByTagName('script')[0]; s.parentNode.insertBefore(ga, s);
      })();

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

<div class="content-wrapper">
<div class="row">
    <div class="large-12 columns content">

<?php

if ( ! isset($values['skip_header']) ):

?>




<div class="row banner-top">
    <div class="large-10 large-centered columns">
        <div class="ms_header_nav">
            <nav>
                <ul class="menu">
                    <li id="ms_logo"><a class="ms_header_nav-logo" target="_blank" href="http://www.mysociety.org">&nbsp;</a></li>
                </ul>
            </nav>
        </div>
        <a href="/" class="wtt-logo"><img src="/static/img/logo.png" style="width:250px;height:70px;" alt="WriteToThem"></a>
    </div>
</div>

<?php endif; ?>

<?php
if (array_key_exists('header', $values)) {
    print $values['header'];
}
?>

