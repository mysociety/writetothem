<?
/*
 * Confirmation from the constituent that they want to send the
 * fax/email.  This page is linked to from the email which confirms the
 * constituent's email address.
 * 
 * Copyright (c) 2004 UK Citizens Online Democracy. All rights reserved.
 * Email: francis@mysociety.org. WWW: http://www.mysociety.org
 *
 * $Id: confirm.php,v 1.6 2004-11-15 18:35:23 francis Exp $
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
    template_show_error();
}

$result = msg_confirm_email($token);
if ($fyr_error_message = msg_get_error($result)) {
    template_show_error();
} else if (!$result) {
    $fyr_title = "Oops... That ain't good...";
    template_draw("confirm-trouble");
} else {
    $fyr_title = "We'll send your message now";
    template_draw("confirm-accept");
}

?>

