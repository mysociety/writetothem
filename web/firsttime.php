<?
/*
 * firsttime.php:
 * Record answer to question about whether this is the first time the
 * constituent has contacted an elected representative.
 * 
 * Copyright (c) 2004 UK Citizens Online Democracy. All rights reserved.
 * Email: francis@mysociety.org. WWW: http://www.mysociety.org
 *
 * $Id: firsttime.php,v 1.10 2009-11-19 11:41:18 matthew Exp $
 * 
 */

require_once "../phplib/fyr.php";
require_once "../phplib/queue.php";

require_once "../commonlib/phplib/utility.php";
require_once "../commonlib/phplib/survey.php";

fyr_rate_limit(array());

$token = get_http_var('token');
if (!$token) {
    template_show_error("The token was missing.");
}
$answer = get_http_var('answer');
if ($answer != "yes" && $answer != "no") {
    template_show_error("The answer type was missing.");
}

// 1 is the firsttime question
$result = msg_record_questionnaire_answer($token, 1, $answer);
if (rabx_is_error($result)) {
    template_show_error($result->text);
}
$values = msg_admin_get_message($result);
$values['cobrand'] = $cobrand;


// SurveyGizmo survey hook
// Current url for Proxy Use Survey - but will not be displayed

$send_to_survey_gizmo = 0; 
// use rand(0, 1); for a 50% referral rate 

if ($send_to_survey_gizmo == 1) {
    $values['title'] = "Help us by answering a few quick questions";
    $values['survey_url'] = 'https://www.surveygizmo.com/s3/" . 
							"4563120/A-few-more-questions-2" .
							"?__no_style=true&amp;" .
							"__ref=pol-info-survey&amp;message_id=' .
							$values['id'];
    template_draw("surveygizmo-survey", $values);
} else {
    // Questionnaire done
    template_draw("survey-done", $values);
}
