<?
/*
 * about.php:
 * Wrapper for displaying templated about/problem pages.
 * 
 * Copyright (c) 2004 UK Citizens Online Democracy. All rights reserved.
 * Email: francis@mysociety.org. WWW: http://www.mysociety.org
 *
 * $Id: about.php,v 1.4 2005-01-11 16:14:51 chris Exp $
 * 
 */

require_once "../phplib/fyr.php";

/* 
 * This page is called through a rewrite mechanism which maps
 *   /$template_name
 * to
 *   /about.php?page=$template_name
 * for about-... and problem-... templates.
 */

$page = get_http_var("page");
if (!isset($page) || !preg_match('/^(?:about|problem)-[a-z]+$/', $page))
    $page = 'index';

$values = array();

/* Don't want problem pages to be indexed, really. */
if (preg_match('/^problem-/', $page))
    $values['robots'] = 'noindex, nofollow';

template_draw($page, $values);

?>

