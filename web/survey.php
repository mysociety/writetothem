<?
/*
 * survey.php:
 * Ask demographics survey for survey.mysociety.org
 * 
 * Copyright (c) 2008 UK Citizens Online Democracy. All rights reserved.
 * Email: francis@mysociety.org. WWW: http://www.mysociety.org
 *
 * $Id: survey.php,v 1.1 2008-05-27 18:57:41 francis Exp $
 * 
 */

require_once "../phplib/fyr.php";
require_once "../phplib/queue.php";

require_once "../../phplib/utility.php";
require_once "../../phplib/auth.php";

fyr_rate_limit(array());

$token = get_http_var('token');
if (!$token) {
    template_show_error("The token was missing.");
}
#$answer = get_http_var('answer');
#if ($answer != "yes" && $answer != "no") {
#    template_show_error("The answer type was missing.");
#}

// 1 is the firsttime question
#$result = msg_record_questionnaire_answer($token, 1, $answer);
#if (rabx_is_error($result)) {
#    template_show_error($result->text);
#}
#$values = msg_admin_get_message($result);

$signature = auth_sign_with_shared_secret($user_code, OPTION_SURVEY_SECRET);
$values = array('user_code' => $user_code,
                'signature' => $signature);

template_draw("survey-questions", $values);

?>

