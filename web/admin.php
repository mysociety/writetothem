<?
/*
 * Admin pages for FaxYourRepresentative.
 * 
 * Copyright (c) 2004 UK Citizens Online Democracy. All rights reserved.
 * Email: francis@mysociety.org. WWW: http://www.mysociety.org
 *
 * $Id: admin.php,v 1.1 2004-11-11 12:24:47 francis Exp $
 * 
 */

require_once "../phplib/fyr.php";
require_once "../../phplib/admin.php";

$pages = array(
    new ADMIN_PAGE_RATTY,
    new ADMIN_PAGE_PHPINFO,
);

admin_page_display("FaxYourRepresentative", $pages);

?>

