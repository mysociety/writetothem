<?
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
         $missing_token_message = "Please make sure you copy the URL from your email properly. The token was missing.";
    }
    template_show_error($missing_token_message);
}
$answer = get_http_var('answer');
if ($answer != "yes" && $answer != "no") {
    $missing_answer_message = cobrand_missing_answer_message($cobrand);
    if (!$missing_answer_message) {
         $missing_answer_message = "Please make sure you copy the URL from your email properly. The answer type was missing.";
    }
    template_show_error($missing_answer_message);
}

$yes_url = cobrand_url($cobrand, "/firsttime?token=" . urlencode($token) .  "&amp;answer=yes", $cocode);
$no_url = cobrand_url($cobrand, "/firsttime?token=" . urlencode($token) .  "&amp;answer=no", $cocode);

$values = array(
    'first_time_yes' => "\"$yes_url\"",
    'first_time_no' => "\"$no_url\"",
    'cobrand' => $cobrand, 
    'cocode' => $cocode,
    'host' => fyr_get_host()
    );

// Look up info about the message
$msg_id = msg_get_questionnaire_message($token);
msg_check_error($msg_id);
if (!$msg_id) {
    $unfound_token_message = cobrand_unfound_token_message($cobrand);
    if (!$unfound_token_message) {
        $unfound_token_message = "Failed to look up message id for token";
    }
    template_show_error($unfound_token_message);
}
$msg_info = msg_admin_get_message($msg_id);
msg_check_error($msg_info);
$values = array_merge($msg_info, $values);

// UCL A/B Testing
require_once "ucl_ab_test.php";
$UCLTest = new UCLTest($msg_id);
require_once "../commonlib/phplib/survey.php";
list($values['user_code'], $values['auth_signature']) = survey_sign_email_address($values['sender_email']);
$values['done_survey'] = survey_check_if_already_done($values['user_code'], $values['auth_signature']);
$values['test_token'] = $UCLTest->test_token;
$values['return_url'] = OPTION_BASE_URL;

// 0 is the responsiveness question
$result = msg_record_questionnaire_answer($token, 0, $answer);
msg_check_error($result);
if ($answer == "yes") {
    template_draw("response-yes", $values);
} elseif ($answer == "no") {
    template_draw("response-no", $values);
} else {
    template_show_error("Unknown answer.");
}

?>

