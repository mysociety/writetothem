<?
/*
 * Main page of FaxYourRepresentative, where you enter your postcode
 * 
 * Copyright (c) 2004 UK Citizens Online Democracy. All rights reserved.
 * Email: francis@mysociety.org. WWW: http://www.mysociety.org
 *
 * $Id: about.php,v 1.1 2004-11-16 15:51:07 francis Exp $
 * 
 */

require_once "../phplib/fyr.php";
require_once "../phplib/forms.php";

# See special about rewrite rule which maps
# "about/qa" to "about.php?page=qa"

$page = get_http_var("page");
$page = preg_replace("/([a-z]+)/", "$1", $page); // safety first
template_draw("about-" . $page, array());

?>

