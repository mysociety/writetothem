<?php header("Content-Type: text/html; charset=utf-8"); ?>
<!DOCTYPE HTML PUBLIC "-//W3C//DTD HTML 4.01 Transitional//EN" "http://www.w3.org/TR/html4/loose.dtd">
<html lang="en">
<head>
<meta http-equiv="Content-Type" content="text/html; charset=utf-8">
<title>WriteToThem.com Beta Test - <? print $values['title']; ?></title>
<?php
if (array_key_exists('css', $_REQUEST) && !preg_match('#"#',$_REQUEST['css'])) { ?>
<style type="text/css">
@import "<?=$_REQUEST['css'] ?>";
</style>
<?php
} else { ?>
<link href="/wtt.css" rel="stylesheet" type="text/css" media="all">
<?php
}
if (array_key_exists('robots', $values)) { ?>
<meta name="robots" content="<?=$values['robots'] ?>">
<?php } ?>
</head>
<body><a name="top" id="top"></a>
<h1 id="heading"><? if ($_SERVER['REQUEST_URI']!='/') print '<a href="/">'; ?>
WriteToThem.com<?
if ($_SERVER['REQUEST_URI']!='/') print '</a>';
if (OPTION_FYR_REFLECT_EMAILS) {
    print " - Staging Site";
} else {
    print " - Beta Test";
}
?>
</h1>
<div id="content">
<?
	if (substr($template_name, 0, 5)=='about') {
		include 'about-sidebar.html';
	}
?>

