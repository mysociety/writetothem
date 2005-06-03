<?php

# syndication type, read from domain name
$syn = str_replace('.writetothem.com', '', $_SERVER['HTTP_HOST']);
# or override for debugging
if (array_key_exists('syn', $_GET)) {
    $syn = $_GET['syn'];
}

header("Content-Type: text/html; charset=utf-8"); ?>
<!DOCTYPE HTML PUBLIC "-//W3C//DTD HTML 4.01 Transitional//EN" "http://www.w3.org/TR/html4/loose.dtd">
<html lang="en">
<head>
<meta http-equiv="Content-Type" content="text/html; charset=utf-8">
<title>WriteToThem.com Beta Test - <? print $values['title']; ?></title>
<link href="/wtt.css" rel="stylesheet" type="text/css" media="all">
<?php if ($syn == 'cheltenham') { ?>
<style type="text/css">@import "cheltenham.css";</style>
<?php }
if (array_key_exists('robots', $values)) { ?>
<meta name="robots" content="<?=$values['robots'] ?>">
<?php } ?>
</head>
<body><a name="top" id="top"></a>
<?php if ($syn == 'cheltenham') 
        print '<a title="Back to Cheltenham Council website" href="http://www.cheltenham.gov.uk/"><img id="chelt" alt="Return to www.cheltenham.gov.uk" src="http://www.cheltenham.gov.uk/libraries/images/logo.gif"></a>';
?>
<h1 id="heading"><? if ($_SERVER['REQUEST_URI']!='/') print '<a href="/">'; ?>
WriteToThem.com<?
if ($_SERVER['REQUEST_URI']!='/') print '</a>';
if (OPTION_FYR_REFLECT_EMAILS) {
    print ' <span id="betatest">Staging&nbsp;Site</span>';
} else {
    print ' <span id="betatest">Beta&nbsp;Test</span>';
}
?>
</h1>
<div id="content">
<?
	if (substr($template_name, 0, 5)=='about' && !isset($values['nobox'])) {
		include 'about-sidebar.html';
	}
?>

