<?
/*
 * stats.php:
 * Statistics!
 * 
 * Copyright (c) 2006 UK Citizens Online Democracy. All rights reserved.
 * Email: matthew@mysociety.org. WWW: http://www.mysociety.org
 *
 * $Id: stats.php,v 1.18 2007-02-13 14:25:21 matthew Exp $
 * 
 */
require_once '../phplib/fyr.php';
require_once '../../phplib/mapit.php';
require_once '../../phplib/dadem.php';

# Read parameters
$type = get_http_var('type');
if (!$type) $type = 'zeitgeist';
$year = get_http_var('year');
$year = '2005';
$year = intval($year);
$xml = get_http_var('xml');
if (!get_http_var('type') || !get_http_var('year')) {
    header("Location: /stats/$year/$type");
    exit;
}
$postcode = get_http_var('pc');
$previous_year = $year - 1;
if ($year == 2005)
    $previous_year = 'FYMP';

require_once "../phplib/summary_report_${year}.php";
require_once "../phplib/questionnaire_report_${year}_WMC.php";

$rep_info = array();
$voting_areas = mapit_get_voting_areas($postcode);
if (!rabx_is_error($voting_areas)) {
    $voting_areas_info = mapit_get_voting_areas_info(array_values($voting_areas));
    mapit_check_error($voting_areas_info);
    $area_representatives = dadem_get_representatives($voting_areas['WMC']);
    dadem_check_error($area_representatives);
    $rep_info = dadem_get_representative_info($area_representatives[0]);
    dadem_check_error($rep_info);
    $rep_info['postcode'] = $postcode;
}
if ($voting_areas->code == MAPIT_BAD_POSTCODE) {
    $error_message = "Sorry, we need your complete UK postcode to identify your elected representatives.";
    $template = "index-advice";
} elseif ($voting_areas->code == MAPIT_POSTCODE_NOT_FOUND) {
    $error_message = "We're not quite sure why, but we can't seem to recognise your postcode.";
    $template = "index-advice";
}

if ($type == 'mps') {
    // Table of responsiveness of MPs
    require_once "../phplib/questionnaire_report_${previous_year}_WMC.php";
    mp_response_table($year, $xml, $rep_info, $GLOBALS["questionnaire_report_${year}_WMC"], $GLOBALS["zeitgeist_by_summary_type_$year"], $GLOBALS["questionnaire_report_{$previous_year}_WMC"]);
} elseif ($type == 'zeitgeist') {
    // Miscellaneous general statistics
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

function by_name($a, $b) {
    return strcmp($a['name'], $b['name']);
}
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

function mp_response_table($year, $xml, $rep_info, $questionnaire_report, $type_summary, $last_year_report) {
    foreach ($last_year_report as $key => $row) {
        $last_year_data[] = array(
                'name' => $row['name'],
                'person_id' => $row['person_id'],
                'category' => $row['category'],
                'response' => $row['responded_mean'],
                'low' => $row['responded_95_low'],
                'high' => $row['responded_95_high']
        );
    }
    usort($last_year_data, 'by_response');
    $position = 0;
    $same_stat = 1;
    $last_response = -1;
    $last_low = -1;
    foreach ($last_year_data as $key => $row) {
        if ($row['response'] != $last_response || $row['low'] != $last_low) {
	    $position += $same_stat;
	    $same_stat = 1;
	    $last_response = $row['response'];
	    $last_low = $row['low'];
	} else {
	    $same_stat++;
	}
        $fymp_ranked[$row['person_id']] = $position;
    }

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
                'response' => $row['responded_mean'],
                'low' => $row['responded_95_low'],
                'high' => $row['responded_95_high'],
		'fymp_rank' => array_key_exists($key, $fymp_ranked) ? $fymp_ranked[$key] : null
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
        $sort = 'r';
        usort($data['data'], 'by_response');
    }
    $data['info']['sort'] = $sort;

    $data['info']['mp'] = null;
    if (count($rep_info)) {
        $key = $rep_info['parlparse_person_id'];
        $row = $questionnaire_report[$key];
        $data['info']['mp'] = array_merge($row, array(
	    'pc' => $rep_info['postcode'],
            'notes' => category_lookup($row['category']),
            'response' => $row['responded_mean'],
            'low' => $row['responded_95_low'],
            'high' => $row['responded_95_high'],
            'fymp_rank' => array_key_exists($key, $fymp_ranked) ? $fymp_ranked[$key] : null
	));
    }

    # Output data
    template_draw($xml ? 'stats-mp-twfy' : 'stats-mp-performance', array(
        "title" => "WriteToThem.com Zeitgeist $year",
        'year' => $year,
        'data' => $data
        ));
}

function category_lookup($cat) {
    if ($cat == 'good') return '';
    elseif ($cat == 'shame') return "MP doesn't accept messages via WriteToThem";
    elseif ($cat == 'toofew') return 'Too little data for valid analysis';
    elseif ($cat == 'unknown') return 'We need to manually check this MP';
    elseif ($cat == 'cheat') return '<a href="http://www.writetothem.com/about-ilg">MP attempted to improve their response rate by sending themselves messages</a>';
    elseif ($cat == 'badcontact') return 'WriteToThem had possibly bad contact details for this MP';
    else template_show_error("Unknown MP categorisation '".htmlspecialchars($cat)."'");
    return $cat;
}
?>

