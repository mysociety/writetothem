<?
/*
 * about.php:
 * Wrapper for displaying templated about pages.
 * 
 * Copyright (c) 2004 UK Citizens Online Democracy. All rights reserved.
 * Email: francis@mysociety.org. WWW: http://www.mysociety.org
 *
 * $Id: about.php,v 1.3 2005-01-11 15:23:55 chris Exp $
 * 
 */

require_once "../phplib/fyr.php";

# See special about rewrite rule which maps
# "about-qa" to "about.php?page=qa"

$page = get_http_var("page");
$page = preg_replace("/([a-z]+)/", "$1", $page); // safety first
if (!$page) $page = 'index';
template_draw("about-" . $page, array());

?>

