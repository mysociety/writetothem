<?
/*
 * about.php:
 * Wrapper for displaying templated about/problem pages.
 * 
 * Copyright (c) 2004 UK Citizens Online Democracy. All rights reserved.
 * Email: francis@mysociety.org. WWW: http://www.mysociety.org
 *
 * $Id: about.php,v 1.8 2005-01-11 16:59:58 chris Exp $
 * 
 */

require_once "../phplib/fyr.php";

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
if (!isset($page) || (!preg_match('/^(?:about|problem)-[a-z]+$/', $page) && $page != 'write-checkemail' && $page != 'confirm-accept'))
    $page = 'index';
    
$values = array();

if ($page == 'write-checkemail')
    /* Fill in some other values as required. */
    $values['voting_area'] = array('rep_name' => 'MP' /* ... XXX */);


/* Don't want problem pages to be indexed, really. */
if (preg_match('/^problem-/', $page))
    $values['robots'] = 'noindex, nofollow';

template_draw($page, $values);

?>

