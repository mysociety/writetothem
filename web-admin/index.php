<?
/*
 * Admin pages for FaxYourRepresentative.
 * 
 * Copyright (c) 2004 UK Citizens Online Democracy. All rights reserved.
 * Email: francis@mysociety.org. WWW: http://www.mysociety.org
 *
 * $Id: index.php,v 1.1 2004-11-15 12:38:15 francis Exp $
 * 
 */

require_once "../phplib/fyr.php";
require_once "../phplib/admin-fyrqueue.php";
require_once "../../phplib/admin.php";

$pages = array(
    new ADMIN_PAGE_FYR_QUEUE,
    new ADMIN_PAGE_RATTY,
    new ADMIN_PAGE_PHPINFO,
    new ADMIN_PAGE_SERVERINFO,
);

admin_page_display("FaxYourRepresentative", $pages);

?>

