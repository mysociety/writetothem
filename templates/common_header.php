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

    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <?php if (array_key_exists('robots', $values)): ?>
    <meta name="robots" content="<?=$values['robots']?>">
    <?php endif; ?>

    <title>WriteToThem - <?=$values['title']?></title>

    <link rel="stylesheet" href="/static/css/wtt.css">
    
    <link rel="shortcut icon" href="/favicon.ico" type="image/x-icon">
    <link rel="icon" type="image/png" href="/static/img/favicon-128.png">
    <link rel="apple-touch-icon-precomposed" sizes="57x57" href="/static/img/favicon-57.png">
    <link rel="apple-touch-icon-precomposed" sizes="72x72" href="/static/img/favicon-72.png">
    <link rel="apple-touch-icon-precomposed" sizes="114x114" href="/static/img/favicon-114.png">
    <link rel="apple-touch-icon-precomposed" sizes="144x144" href="/static/img/favicon-144.png">
    
    <meta property="og:title" content="WriteToThem">
    <meta property="og:type" content="website">
    <meta property="og:url" content="<?=OPTION_BASE_URL?>">
    <meta property="og:image" content="<?=OPTION_BASE_URL?>/static/img/favicon-128.png">

    <script src="/static/js/vendor/custom.modernizr.js"></script>

    <?php if (array_key_exists('stylesheet', $values)): ?>
    <style type="text/css">@import "<?=$values['stylesheet']?>";</style>
    <?php

    endif;

    if (OPTION_WEB_DOMAIN == 'writetothem.com'):

    ?>
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
        <div class="large-12 columns">
            <strong>This is a staging site.</strong> Emails will not be sent to representatives, but will instead be sent to you.
        </div>
    </div>
</div>

<?php

endif;

if ( ( ! isset($values['template']) OR $values['template'] !== 'index-index') AND ! isset($values['skip_header']) ):

?>

<div class="banner-top">
    <div class="row">
        <div class="large-12 columns">
            <a href="/"><img src="/static/img/logo.png" data-interchange="[/static/img/logo@2x.png, (retina)]" alt="WriteToThem"></a>
        </div>
    </div>
</div>

<?php endif; ?>

<?php
if (array_key_exists('header', $values)) {
    print $values['header'];
}
?>

