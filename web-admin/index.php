<?
/*
 * Admin pages for FaxYourRepresentative.
 * 
 * Copyright (c) 2004 UK Citizens Online Democracy. All rights reserved.
 * Email: francis@mysociety.org. WWW: http://www.mysociety.org
 *
 * $Id: index.php,v 1.10 2005-01-12 17:36:40 chris Exp $
 * 
 */

require_once "../conf/general";
require_once "../phplib/admin-fyrqueue.php";
require_once "../../phplib/admin.php";

$pages = array(
    new ADMIN_PAGE_FYR_QUEUE(),
    new ADMIN_PAGE_RATTY('fyr-web', "FYR website"),
    new ADMIN_PAGE_RATTY('fyr-abuse', "Abuse Checks"),
    new ADMIN_PAGE_REPS,
    null, // space separator on menu
    new ADMIN_PAGE_SERVERINFO,
    new ADMIN_PAGE_CONFIGINFO,
    new ADMIN_PAGE_PHPINFO,
);

admin_page_display(str_replace("http://", "", OPTION_BASE_URL), $pages);

?>

