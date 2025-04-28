<?php
/*
 * Save the results of the post confirmation survey
 *
 * Copyright (c) 2025 mySociety. All rights reserved.
 *
 */

require_once "../phplib/fyr.php";
require_once "../phplib/queue.php";
require_once "../phplib/forms.php";

require_once "../commonlib/phplib/utility.php";

fyr_rate_limit(array());

$ad = get_http_var('ad');
if ($ad) {
    $values = array(
        'cobrand' => $cobrand, 'host' => fyr_get_host(),
    );
    template_draw("survey-thanks", $values);
    exit;
}

$values = get_all_variables();

if (array_key_exists('msg_summary', $values) && array_key_exists('msg_id', $values)) {
    $result = msg_record_analysis_data(
        $values['msg_id'],
        $values['msg_summary'],
        array(
            'reason' => $values['reason']
        )
    );
}

$values['cobrand'] = $cobrand;
$values['host'] = fyr_get_host();

template_draw("survey-thanks", $values);

?>
