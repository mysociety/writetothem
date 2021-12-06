<?php
/*
 * about.php:
 * Wrapper for displaying templated about/problem pages.
 * 
 * Copyright (c) 2004 UK Citizens Online Democracy. All rights reserved.
 * Email: francis@mysociety.org. WWW: http://www.mysociety.org
 *
 * $Id: about.php,v 1.15 2009-09-29 15:49:46 louise Exp $
 * 
 */

require_once "../phplib/fyr.php";
require_once "../phplib/emailform.php";

/* 
 * This page is called through a rewrite mechanism which maps
 *   /$template_name
 * to
 *   /about.php?page=$template_name
 * for about-... and problem-... templates. We also allow access to the
 * write-checkemail and confirm-accept templates, because that's handy for
 * debugging.
 */

$page = get_http_var("page");
if (!isset($page) || (!preg_match('/^(?:about|problem)-[a-z-]+$/', $page) && $page != 'write-checkemail' && $page != 'confirm-accept')) {
    $page = 'about-index';
}

if ($page == 'about-index') {
    header("Location: about-us");
    exit;
}
if ($page == 'about-pledgebank') {
    header("Location: https://www.pledgebank.com");
    exit;
}

$t = 'about-qa-';
if (substr($page, 0, strlen($t)) == $t) {
    $page = substr_replace($page, 'qa/', 0, strlen($t));
}
$t = 'about-yourrep-';
if (substr($page, 0, strlen($t)) == $t) {
    $page = substr_replace($page, 'yourrep/', 0, strlen($t));
}

$values = array('cobrand' => $cobrand);

if ($page == 'write-checkemail')
    /* Fill in some other values as required. */
    $values['voting_area'] = array('rep_name' => 'MP' /* ... XXX */);

if ($page == 'confirm-accept') {
    $values['recipient_via'] = '';
    $values['recipient_name'] = 'Recipient Name';
    $values['sender_postcode'] = 'SW1A 0AA';
    $values['group_id'] = '';
}
if ($page == 'write-checkemail') {
    $values['group_msg'] = '';
}

/* Don't want problem pages to be indexed, really. */
if (preg_match('/^problem-/', $page))
    $values['robots'] = 'noindex, nofollow';

template_draw($page, $values);

?>

