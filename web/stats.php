<?
/*
 * stats.php:
 * Statistics!
 * 
 * Copyright (c) 2006 UK Citizens Online Democracy. All rights reserved.
 * Email: matthew@mysociety.org. WWW: http://www.mysociety.org
 *
 * $Id: stats.php,v 1.9 2006-02-17 15:56:06 matthew Exp $
 * 
 */
require_once '../phplib/fyr.php';

# Read parameters
$type = get_http_var('type');
if (!$type) $type = 'zeitgeist';
$year = get_http_var('year');
if (!$year) $year = '2005';
if (!get_http_var('type') || !get_http_var('year')) {
    header("Location: /stats/$year/$type");
    exit;
}

require_once "../phplib/summary_report_${year}.php";
require_once "../phplib/questionnaire_report_${year}_WMC.php";
if ($type == 'mps') {
    mp_response_table($year, $GLOBALS["questionnaire_report_${year}_WMC"], $GLOBALS["zeitgeist_by_summary_type_$year"]);
} elseif ($type == 'zeitgeist') {
    zeitgeist($year, $GLOBALS["zeitgeist_by_summary_type_$year"],
        $GLOBALS["party_report_${year}_WMC"],
        $GLOBALS["questionnaire_report_${year}_WMC"]
        );
} else {
    template_show_error("Unknown report type '".htmlspecialchars($type)."'");
}

function zeitgeist($year, $type_summary, $party_summary, $questionnaire_report) {
    function sort_by_responsiveness($a, $b) {
        if (isset($a['total'])) return 1;
        if (isset($b['total'])) return -1;
        return $a['responded'] / $a['responded_outof'] <
            $b['responded'] / $b['responded_outof'] ?
            1 : -1;
    }
    function sort_by_firsttime($a, $b) {
        return $a['firsttime'] / $a['firsttime_outof'] <
            $b['firsttime'] / $b['firsttime_outof'] ?
            1 : -1;
    }
    uasort($party_summary, 'sort_by_responsiveness');
    $parties_by_responsiveness = array_keys($party_summary);
    uasort($party_summary, 'sort_by_firsttime');
    $parties_by_firsttime = array_keys($party_summary);
    uasort($type_summary, 'sort_by_responsiveness');
    $types_by_responsiveness = array_keys($type_summary);
    $libdem_leadership_candidates = array();
    if ($year == "2005") {
        $libdem_leadership_candidates = 
            array(
                $questionnaire_report['uk.org.publicwhip/person/11565'],
                $questionnaire_report['uk.org.publicwhip/person/10088'],
                $questionnaire_report['uk.org.publicwhip/person/10298'],
                );
    }
    template_draw('stats-zeitgeist', array(
            "title" => "WriteToThem.com Zeitgeist $year",
            'year' => $year,
            'type_summary' => $type_summary,
            'party_summary' => $party_summary,
            'parties_by_responsiveness' => $parties_by_responsiveness,
            'parties_by_firsttime' => $parties_by_firsttime,
            'types_by_responsiveness' => $types_by_responsiveness,
            'libdem_leadership_candidates' => $libdem_leadership_candidates
            ));
}

function mp_response_table($year, $questionnaire_report, $type_summary) {
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

    $non_mp_sent = 0;
    foreach ($type_summary as $type => $row) {
    	if ($type != 'westminster' && $type != 'total')
	    $non_mp_sent += $row['dispatched_success'];
    }
    $data['info']['non_mp_sent'] = $non_mp_sent;

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
    template_draw('stats-mp-performance', array(
        "title" => "WriteToThem.com Zeitgeist $year",
        'year' => $year,
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

