<?
/*
 * Admin pages for FaxYourRepresentative.
 * 
 * Copyright (c) 2004 UK Citizens Online Democracy. All rights reserved.
 * Email: francis@mysociety.org. WWW: http://www.mysociety.org
 *
 * $Id: index.php,v 1.11 2005-01-12 18:03:12 francis Exp $
 * 
 */

require_once "../conf/general";
require_once "../phplib/admin-fyrqueue.php";
require_once "../../phplib/admin.php";

$pages = array(
    new ADMIN_PAGE_FYR_QUEUE(),
    new ADMIN_PAGE_RATTY('fyr-web', "WTT Website", "These rules limit
        access to the WriteToThem website."),
    new ADMIN_PAGE_RATTY('fyr-abuse', "Message Abuse", "These rules
        apply to messages when they are first submitted onto the message
        queue by the user."),
    new ADMIN_PAGE_REPS,
    null, // space separator on menu
    new ADMIN_PAGE_SERVERINFO,
    new ADMIN_PAGE_CONFIGINFO,
    new ADMIN_PAGE_PHPINFO,
);

admin_page_display(str_replace("http://", "", OPTION_BASE_URL), $pages);

?>

