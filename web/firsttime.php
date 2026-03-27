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

fyr_rate_limit(array());

$token = get_http_var('token');
if (!$token) {
    template_show_error(_("The token was missing."));
}

$preview = ($token === 'preview');

// Handle service questions form submission (Q1 + Q2-Q4)
$submitted = get_http_var('submitted');
if ($submitted) {
    if (!$preview) {
        // Record Q1: first-time question
        $answer = get_http_var('answer');
        if ($answer == "yes" || $answer == "no") {
            $result = msg_record_questionnaire_answer($token, 1, $answer);
            if (rabx_is_error($result)) {
                template_show_error($result->text);
            }
        }

        // Record Q2-Q4: Likert service questions
        $valid_likert = array('strongly_agree', 'agree', 'neutral', 'disagree', 'strongly_disagree');
        foreach (array(2 => 'q2', 3 => 'q3', 4 => 'q4') as $qn => $param) {
            $val = get_http_var($param);
            if ($val && in_array($val, $valid_likert)) {
                $result = msg_record_questionnaire_answer($token, $qn, $val);
                if (rabx_is_error($result)) {
                    template_show_error($result->text);
                }
            }
        }

        // Record Q5: NPS (integer 0-10)
        $nps = get_http_var('q5');
        if ($nps !== '' && $nps !== false && ctype_digit(strval($nps)) && (int)$nps >= 0 && (int)$nps <= 10) {
            $result = msg_record_questionnaire_answer($token, 5, strval((int)$nps));
            if (rabx_is_error($result)) {
                template_show_error($result->text);
            }
        }
    }

    // All done
    $values = array('cobrand' => $cobrand, 'cocode' => $cocode, 'host' => fyr_get_host());
    template_draw("survey-done", $values);
    exit;
}

// Show combined form with first-time question + service questions
// Randomly select 2 from the pool of optional service questions
$service_question_pool = array(2, 3, 4, 5);
shuffle($service_question_pool);
$service_questions = array_slice($service_question_pool, 0, 2);
sort($service_questions);

// Check if this is a multi-recipient message
$is_multi = false;
if (!$preview) {
    $is_multi = msg_is_multi_questionnaire_message($token) ? true : false;
}

$values = array(
    'token' => $token,
    'response' => get_http_var('response'),
    'service_questions' => $service_questions,
    'is_multi' => $is_multi,
    'cobrand' => $cobrand,
    'cocode' => $cocode,
    'host' => fyr_get_host(),
);
template_draw("service-questions", $values);
