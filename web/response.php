<?php
/*
 * Record answer to questionnaire to find out whether the representative
 * replied to their constituent.
 * 
 * Copyright (c) 2004 UK Citizens Online Democracy. All rights reserved.
 * Email: francis@mysociety.org. WWW: http://www.mysociety.org
 *
 * $Id: response.php,v 1.11 2009-11-19 11:38:18 matthew Exp $
 * 
 */

require_once "../phplib/fyr.php";
require_once "../phplib/queue.php";

require_once "../commonlib/phplib/utility.php";

fyr_rate_limit(array());

$token = get_http_var('token');
if (!$token) {
    $missing_token_message = cobrand_missing_token_message($cobrand);
    if (!$missing_token_message) {
         $missing_token_message = _("Please make sure you copy the URL from your email properly. The token was missing.");
    }
    template_show_error($missing_token_message);
}
$answer = get_http_var('answer');
if ($answer != "yes" && $answer != "no" && $answer != "unsatisfactory" && $answer != "not_expected") {
    $missing_answer_message = cobrand_missing_answer_message($cobrand);
    if (!$missing_answer_message) {
         $missing_answer_message = _("Please make sure you copy the URL from your email properly. The answer type was missing.");
    }
    template_show_error($missing_answer_message);
}

// Look up info about the message
$msg_id = msg_get_questionnaire_message($token);
msg_check_error($msg_id);
if (!$msg_id) {
    $unfound_token_message = cobrand_unfound_token_message($cobrand);
    if (!$unfound_token_message) {
        $unfound_token_message = _("Failed to look up message id for token");
    }
    template_show_error($unfound_token_message);
}

// 0 is the responsiveness question
$result = msg_record_questionnaire_answer($token, 0, $answer);
msg_check_error($result);

// Go straight to the service questions form, passing the answer for context
$firsttime_url = cobrand_url(
    $cobrand,
    "/firsttime?token=" . urlencode($token) . "&response=" . urlencode($answer),
    $cocode
);
header("Location: $firsttime_url");
exit;

?>

