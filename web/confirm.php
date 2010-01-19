<?
/*
 * Confirmation from the constituent that they want to send the
 * fax/email.  This page is linked to from the email which confirms the
 * constituent's email address.
 * 
 * Copyright (c) 2004 UK Citizens Online Democracy. All rights reserved.
 * Email: francis@mysociety.org. WWW: http://www.mysociety.org
 *
 * $Id: confirm.php,v 1.24 2009-12-07 11:20:57 louise Exp $
 * 
 */

require_once "../phplib/fyr.php";
require_once "../phplib/queue.php";

require_once "../commonlib/phplib/utility.php";

fyr_rate_limit(array());


$ad = get_http_var('ad');
if ($ad) {
    $values = array(
        'recipient_via' => null, 'recipient_name' => 'Recipient Name', 'recipient_type' => 'Type',
        'sender_name' => 'Sender Name', 'sender_email' => 'email', 'sender_postcode' => 'SW1A1AA',
        'advert' => $ad, 'cobrand' => $cobrand, 'host' => fyr_get_host()
    );
    template_draw("confirm-accept", $values);
    exit;
}

$template_params = array('host' => fyr_get_host(), 'cobrand' => $cobrand);
$token = get_http_var('token');
if (!$token) {

    $missing_token_message = cobrand_missing_token_message($cobrand);
    if (!$missing_token_message) {
         $missing_token_message = "Please make sure you copy the URL from your email properly. The token was missing.";
    }
    template_show_error($missing_token_message);
}

$result = msg_confirm_email($token);
if (rabx_is_error($result)) {

    if ($result->code == FYR_QUEUE_MESSAGE_EXPIRED) {
        $url = cobrand_url($cobrand, "/", $cocode);
        $text = <<<EOF
You took so long to confirm your message that under our privacy policy 
your message has already been removed from our database. 
If you'd still like to write a message, you can <a href="$url">try again from the
beginning
EOF;
        template_show_error($text);
    } else {
        template_show_error($result->text);
    }
}
if (!$result) {
    template_draw("confirm-trouble", $template_params);
} else {
    $values = msg_admin_get_message($result);
    if (rabx_is_error($values)) {
        template_show_error($values->text);
    } elseif ($values['cobrand'] && cobrand_post_letter_send($values)) {
        // Do nothing - cobrand_post_letter_send must do the special action e.g. header or template_draw etc.
    } else {
        $values['cobrand'] = $cobrand;
        $values['host'] = fyr_get_host();
        template_draw("confirm-accept", $values);
    }
}

?>

