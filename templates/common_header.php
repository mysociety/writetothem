<?php

header("Content-Type: text/html; charset=utf-8"); 
if (isset($values['cobrand'])) {
  print cobrand_headers($values['cobrand'], 'common_header');
}
 ?>
<!DOCTYPE HTML PUBLIC "-//W3C//DTD HTML 4.01 Transitional//EN" "http://www.w3.org/TR/html4/loose.dtd">
<html lang="en">
<head>
<meta http-equiv="Content-Type" content="text/html; charset=utf-8">
<title>WriteToThem - <? print $values['title']; ?></title>
<link href="/wtt.css" rel="stylesheet" type="text/css" media="all">
<link href="/print.css" rel="stylesheet" type="text/css" media="print">
<!--[if LT IE 7]>
<style type="text/css">@import url("/ie6.css");</style>
<![endif]-->
<link rel="stylesheet" href="/static/js/fancybox/jquery.fancybox-1.3.4.css" type="text/css">
<script src="/jslib/jquery-1.6.2.min.js"></script>
<script src="/static/js/fancybox/jquery.fancybox-1.3.4.pack.js"></script>
<script>
$(function(){
    $('.fancybox').find('small').hide()
      .end().each(function(){
        $(this).fancybox({
            href: $(this).prop('href').replace('#', '-')
        });
    });
});
</script>

<?php if (array_key_exists('stylesheet', $values)) { ?>
<style type="text/css">@import "<?=$values['stylesheet'] ?>";</style>
<?php }
      if (array_key_exists('robots', $values)) { ?>
<meta name="robots" content="<?=$values['robots'] ?>">
<?php }
      if (OPTION_WEB_DOMAIN == 'writetothem.com') {
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
<?php } ?>


</head>
<body<? if (isset($values['body_id'])) print ' id="' . $values['body_id'] . '"'; ?>><a name="top" id="top"></a>
<?php if (array_key_exists('header', $values)) {
          print $values['header'];
      } ?>
<h1 id="heading">
<a href="http://www.mysociety.org/"><img id="logo" alt="Visit mySociety.org" src="/images/mysociety-dark-50.png"><span id="logoie"></span></a>

<? if ($_SERVER['REQUEST_URI']!='/') print '<a href="/">'; ?>
WriteToThem<?
if ($_SERVER['REQUEST_URI']!='/') print '</a>';
if ($_SERVER['REQUEST_URI']!='/')
    print ' <span id="homelink">(<a href="/">home</a>)</span>';
if (OPTION_FYR_REFLECT_EMAILS) {
    print ' <span id="betatest">Staging&nbsp;Site</span>';
}
?>
</h1>
<div id="content">
<?
	if (substr($real_template_name, 0, 5)=='about' && !isset($values['nobox'])) {
		template_draw('about-sidebar');
	}
?>
