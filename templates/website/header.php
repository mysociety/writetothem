<?php header("Content-Type: text/html; charset=utf-8"); ?>
<!DOCTYPE HTML PUBLIC "-//W3C//DTD HTML 4.01 Transitional//EN" "http://www.w3.org/TR/html4/loose.dtd">
<html lang="en">
<head>
<meta http-equiv="Content-Type" content="text/html; charset=utf-8">
<title>WriteToThem.com Beta Test - <? print $values['title']; ?></title>
<style type="text/css">
<?php
if (array_key_exists('css', $_REQUEST) && !preg_match('#"#',$_REQUEST['css'])) {
	print '@import "'.$_REQUEST['css'].'";'."\n";
} else { ?>
@import "/wtt.css";
<?php } ?>
</style>
<?php
if (array_key_exists('robots', $values)) { ?>
<meta name="robots" content="<?=$values['robots'] ?>">
<?php } ?>
</head>
<body><a name="top" id="top">
<h1 id="heading"><a href="/">WriteToThem.com</a> 
<?
	if (OPTION_FYR_REFLECT_EMAILS) {
        print " - Staging Site";
	} else {
        print "- Beta Test";
    }
?>
</h1>
<div id="content">
<?
	if (substr($template_name, 0, 5)=='about') {
		include 'about-sidebar.html';
	}
?>

<!-- Needs to be all on one line to prevent extra whitespace in Firefox -->
<!-- <ul id="nav"><li><a href="/">Home</a></li><li><a href="/about-yourrep">Your Representative</a></li><li><a href="/about-qa">Q&amp;A</a></li><li><a href="/about-us">About Us</a></li><li><a href="/about-contact">Contact Us</a></li></ul> -->

