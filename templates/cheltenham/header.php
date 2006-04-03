<?php
header("Content-Type: text/html; charset=utf-8"); ?>
<!DOCTYPE HTML PUBLIC "-//W3C//DTD HTML 4.01 Transitional//EN" "http://www.w3.org/TR/html4/loose.dtd">
<html lang="en">
<head>
<meta http-equiv="Content-Type" content="text/html; charset=utf-8">
<title>WriteToThem.com - <? print $values['title']; ?></title>
<link href="/wtt.css" rel="stylesheet" type="text/css" media="all">
<style type="text/css">@import "cheltenham.css";</style>
<?php if (array_key_exists('robots', $values)) { ?>
<meta name="robots" content="<?=$values['robots'] ?>">
<?php } ?>
<?php if (isset($values['spell'])) { ?>
<script src="/jslib/spell/spellChecker.js"></script>
<?php } ?>
</head>
<body><a name="top" id="top"></a>
<a title="Back to Cheltenham Council website" href="http://www.cheltenham.gov.uk/"><img id="cobrand_logo" alt="Return to www.cheltenham.gov.uk" src="http://www.cheltenham.gov.uk/libraries/images/logo.gif"></a>
<h1 id="heading"><? if ($_SERVER['REQUEST_URI']!='/') print '<a href="/">'; ?>
WriteToThem.com<?
if ($_SERVER['REQUEST_URI']!='/') print '</a>';
?>
</h1>
<div id="content">
<?
	if (substr($real_template_name, 0, 5)=='about' && !isset($values['nobox'])) {
		template_draw('about-sidebar');
	}
?>

