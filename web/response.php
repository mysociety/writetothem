<?
/*
 * Record answer to questionnaire to find out whether the representative
 * replied to their constituent.
 * 
 * Copyright (c) 2004 UK Citizens Online Democracy. All rights reserved.
 * Email: francis@mysociety.org. WWW: http://www.mysociety.org
 *
 * $Id: response.php,v 1.1 2004-12-15 15:35:04 francis Exp $
 * 
 */

require_once "../phplib/fyr.php";
require_once "../phplib/queue.php";

require_once "../../phplib/utility.php";

fyr_rate_limit(array());

$token = get_http_var('token');
if (!$token) {
    template_show_error("Please make sure you copy the URL from your
        email properly. The token was missing.");
}
$answer = get_http_var('answer');
if ($answer != "yes" && $answer != "no") {
    template_show_error("Please make sure you copy the URL from your
        email properly. The answer type was missing.");
}

$values = array(
    'first_time_yes' => "\"firsttime?token=" . urlencode($token) .  "&answer=yes\"",
    'first_time_no' => "\"firsttime?token=" . urlencode($token) .  "&answer=no\""
    );

// 0 is the responsiveness question
$result = msg_record_questionnaire_answer($token, 0, $answer);
if (rabx_is_error($result)) {
    template_show_error($result->text);
}
if ($answer == "yes") {
    template_draw("response-yes", $values);
} elseif ($answer == "no") {
    template_draw("response-no", $values);
} else {
    template_show_error("Unknown answer.");
}

?>

