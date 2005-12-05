<?
/*
 * firsttime.php:
 * Record answer to question about whether this is the first time the
 * constituent has contacted an elected representative.
 * 
 * Copyright (c) 2004 UK Citizens Online Democracy. All rights reserved.
 * Email: francis@mysociety.org. WWW: http://www.mysociety.org
 *
 * $Id: firsttime.php,v 1.3 2005-12-05 20:57:33 francis Exp $
 * 
 */

require_once "../phplib/fyr.php";
require_once "../phplib/queue.php";

require_once "../../phplib/utility.php";

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
if ($answer == "yes") {
    template_draw("firsttime-yes", $values);
} elseif ($answer == "no") {
    template_draw("firsttime-no", $values);
} else {
    template_show_error("Unknown answer.");
}

?>

