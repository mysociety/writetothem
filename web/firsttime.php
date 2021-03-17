<?php
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
// Current url for local councillor name recognition survey
// Survey disabled
// https://www.surveygizmo.com/s3/5057760/WTT-Name-Recognition-Local-Council

$local_council_codes = ['DIW','CED','LBW','COP','LGE','MTW','UTE','UTW'];
$wrote_to_councillor = in_array($values["recipient_type"], $local_council_codes);

//send no surveys
$send_to_survey_gizmo = 0; 
// use rand(0, 1); for a 50% referral rate 

if ($send_to_survey_gizmo) {
    $values['title'] = "Help us by answering a few quick questions";
    $values['survey_url'] = 'https://www.surveygizmo.com/s3/' .
	'5057760/WTT-Name-Recognition-Local-Council' . 
	'?__no_style=true&amp;' .
	'__ref=WTT-Name-Recognition-Local-Councily&amp;' .
	'recipient_id=' . $values['recipient_id'] . '&amp;' .
	'first_use=' . ($values['questionnaire_1_yes'] ? 'true' : 'false');
    template_draw("surveygizmo-survey", $values);
} else {
    // Questionnaire done
    template_draw("survey-done", $values);
}
