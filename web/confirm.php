<?
/*
 * Confirmation from the constituent that they want to send the
 * fax/email.  This page is linked to from the email which confirms the
 * constituenys email address.
 * 
 * Copyright (c) 2004 UK Citizens Online Democracy. All rights reserved.
 * Email: francis@mysociety.org. WWW: http://www.mysociety.org
 *
 * $Id: confirm.php,v 1.3 2004-10-28 10:53:19 francis Exp $
 * 
 */

require_once "../phplib/fyr.php";
require_once "../phplib/queue.php";

require_once "../../phplib/utility.php";

fyr_rate_limit(array());

$token = get_http_var('token');
if (!$token) {
    $fyr_error_message = "Please make sure you copy the URL from your
        email properly. The token was missing.";
    include "templates/generalerror.html";
    exit;
}

if (msg_confirm_email($token)) {
    $fyr_title = "We'll send your fax now";
    include "templates/confirm-accept.html";
} else {
    $fyr_title = "Oops... That ain't good...";
    include "templates/confirm-trouble.html";
}

?>

