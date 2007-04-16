<?php

header("Content-Type: text/html; charset=utf-8"); ?>
<!DOCTYPE HTML PUBLIC "-//W3C//DTD HTML 4.01 Transitional//EN" "http://www.w3.org/TR/html4/loose.dtd">
<html lang="en">
<head>
<meta http-equiv="Content-Type" content="text/html; charset=utf-8">
<title>WriteToThem.com - <? print $values['title']; ?></title>
<link href="/wtt.css" rel="stylesheet" type="text/css" media="all">
<link href="/print.css" rel="stylesheet" type="text/css" media="print">
<?php if (array_key_exists('stylesheet', $values)) { ?>
<style type="text/css">@import "<?=$values['stylesheet'] ?>";</style>
<?php }
      if (array_key_exists('robots', $values)) { ?>
<meta name="robots" content="<?=$values['robots'] ?>">
<?php }
      if (isset($values['spell'])) { ?>
<script type="text/javascript" src="/jslib/spell/spellChecker.js"></script>
<?php } ?>
<script type="text/javascript" src="/js.js"></script>
</head>
<body<? if (isset($values['body_id'])) print ' id="' . $values['body_id'] . '"'; ?>><a name="top" id="top"></a>
<?php if (array_key_exists('header', $values)) {
          print $values['header'];
      } ?>
<h1 id="heading"><? if ($_SERVER['REQUEST_URI']!='/') print '<a href="/">'; ?>
WriteToThem.com<?
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
