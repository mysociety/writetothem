<?php header("Content-Type:", "text/html; charset=utf-8"); ?>
<!DOCTYPE HTML PUBLIC "-//W3C//DTD HTML 4.01 Transitional//EN" "http://www.w3.org/TR/html4/loose.dtd">
<html lang="en">
<head>
<meta http-equiv="Content-Type" content="text/html; charset=utf-8">
<title>WriteToThem.com Beta Test - <? print $values['title']; ?></title>
<link rel="stylesheet" type="text/css" href="style-fymp/fax_style.css">
<?
if ($values['robots']) { ?>
<meta name="robots" content="<?=$values['robots'] ?>">
<? } ?>
</head>
<body bgcolor="#ffffff" text="#006666" link="#006666" alink="#999966" vlink="#006666">
<div align="center">

<?
    if (OPTION_FYR_REFLECT_EMAILS) {
            print "<p>Test site - this will reflect emails to representatives back to you.</p>";
    }

?>

<TABLE CELLSPACING="0" CELLPADDING="0" BORDER="0" WIDTH="530">

<TR>
<TD align=center VALIGN="top">
<IMG SRC="images/mosaic.gif" WIDTH="529" HEIGHT="12" BORDER="0" ALT="mosaic_strip"><BR><BR>
</TD>

</TR><TR><TD valign="top">
<TABLE CELLPADDING="4" CELLSPACING="2" BORDER="0" WIDTH="100%">
<TR>
<TD BGCOLOR="#cccc99" ALIGN="center"><A HREF="/">Home</A></TD>
<TD BGCOLOR="#cccc99" ALIGN="center"><A HREF="/about-yourrep">Your Representative</A></TD>
<TD BGCOLOR="#cccc99" ALIGN="center"><A HREF="/about-qa">Q&amp;A</A></TD>
<TD BGCOLOR="#cccc99" ALIGN="center"><A HREF="/about-us">About Us</A></TD>
<TD BGCOLOR="#cccc99" ALIGN="center"><A HREF="/about-contact">Contact Us</A></TD>
</TR>
</TABLE>

<!-- </DIV> -->

