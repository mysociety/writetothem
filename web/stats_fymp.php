<?
/*
 * stats.php:
 * Statistics!
 * 
 * Copyright (c) 2006 UK Citizens Online Democracy. All rights reserved.
 * Email: matthew@mysociety.org. WWW: http://www.mysociety.org
 *
 * $Id: stats_fymp.php,v 1.1 2006-02-19 12:40:50 matthew Exp $
 * 
 */
require_once '../phplib/fyr.php';
require_once "../phplib/questionnaire_report_FYMP_WMC.php";
mp_response_table($GLOBALS["questionnaire_report_FYMP_WMC"]);

function mp_response_table($questionnaire_report) {
    # Read in data
    $data = array();
    foreach ($questionnaire_report as $key => $row) {
        if (is_array($row)) {
            $data['data'][] = array(
                'person_id' => $key,
                'name' => $row['name'],
                'party' => $row['party'],
                'area' => $row['area'],
                'sent' => $row['dispatched_success'],
                'category' => $row['category'],
                'notes' => category_lookup($row['category']),
                'response' => round($row['responded_mean'] * 100, 1),
                'low' => round($row['responded_95_low'] * 100, 1),
                'high' => round($row['responded_95_high'] * 100, 1)
            );
        } else {
            $data['info'][$key] = $row;
        }
    }

    # Sort data
    function by_name($a, $b) {
        return strcmp($a['name'], $b['name']);
    }
    function by_area($a, $b) {
        return strcmp($a['area'], $b['area']);
    }
    function by_sent($a, $b) {
        if ($a['sent']<$b['sent']) return 1;
        elseif ($a['sent']>$b['sent']) return -1;
        return 0;
    }
    $sort = get_http_var('o');
    if ($sort == 'n') {
        usort($data['data'], 'by_name');
    } elseif ($sort == 'c') {
        usort($data['data'], 'by_area');
    } elseif ($sort == 's') {
        usort($data['data'], 'by_sent');
    } else {
        function by_response($a, $b) {
            if ($a['category'] != 'good' && $b['category'] == 'good')
                return 1;
            if ($b['category'] != 'good' && $a['category'] == 'good')
                return -1;
            if ($a['category'] != 'good' && $b['category'] != 'good')
                return by_name($a, $b);
            if ($a['response']<$b['response']) return 1;
            elseif ($a['response']>$b['response']) return -1;
            if ($a['low']<$b['low']) return 1;
            elseif ($a['low']>$b['low']) return -1;
            return 0;
        }
        usort($data['data'], 'by_response');
    }
    $data['info']['sort'] = $sort;

    # Output data
    template_draw('stats-fymp', array(
        "title" => "FYMP stats",
        'data' => $data
        ));
}

function category_lookup($cat) {
    if ($cat == 'good') return '';
    elseif ($cat == 'shame') return "MP doesn't accept messages via WriteToThem";
    elseif ($cat == 'toofew') return 'Too few messages sent to MP';
    elseif ($cat == 'unknown') return 'We need to manually check this MP';
    elseif ($cat == 'cheat') return 'MP attempted to improve their response rate by sending themselves messages';
    elseif ($cat == 'badcontact') return 'WriteToThem had possibly bad contact details for this MP';
    else template_show_error("Unknown MP categorisation '".htmlspecialchars($cat)."'");
    return $cat;
}
?>
