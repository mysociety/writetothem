<?
/*
 * stats.php:
 * Statistics!
 *
 * Copyright (c) 2006 UK Citizens Online Democracy. All rights reserved.
 * Email: matthew@mysociety.org. WWW: http://www.mysociety.org
 *
 * $Id: stats.php,v 1.32 2009-09-02 18:11:29 matthew Exp $
 *
 */
require_once '../phplib/fyr.php';
require_once '../commonlib/phplib/mapit.php';
require_once '../commonlib/phplib/dadem.php';

# Read parameters
$type = get_http_var('type');
if (!$type) $type = 'zeitgeist';
$year = get_http_var('year');
if (!$year) $year = '2015';
#if (!get_http_var('really'))
#    $year = '2005'; # XXX temp
$year = intval($year);
$xml = get_http_var('xml');
if (!get_http_var('type') || !get_http_var('year')) {
    header("Location: /stats/$year/$type");
    exit;
}
$postcode = get_http_var('pc');
$parlparse_id = get_http_var('id');
$previous_year = $year - 1;
if ($year == 2005)
    $previous_year = 'FYMP';


# Construct the year navigation bar
$years = array('2005', '2006', '2007', '2008', '2013', '2014', '2015');

# this is so we get the mapit IDs and cons names for the year and
# hence can get the MP for the year from DaDem and not the current
# one
if ( $year >= 2005 && $year <= 2008 ) {
    $mapit_args = array('generation' => '12');
} else if ( $year >= 2013 && $year <= 2015 ) {
    $mapit_args = array('generation' => '13');
}
$got_year = 0;
$year_bar_array = array();
foreach ($years as $y) {
    if ($year == $y) {
        $year_bar_array[] = "<b>$y</b>";
        $got_year = 1;
    } else {
        $year_bar_array[] = "<a href=\"/stats/$y/$type\">$y</a>";
    }
}
if (!$got_year) {
    template_show_error("We don’t have statistics for that year");
}
$year_bar = "<p>Statistics for other years:</p><ul class=\"inline-list\"><li>" . join($year_bar_array, "</li><li>") . "</li></ul>";
#if (!get_http_var('really'))
#    $year_bar = "";


# Construct the navigation tabs for this year's data
$navigation_tabs = array(
  array('mps', 'MPs', 'MP responsiveness league table'),
  array('parties', 'Parties', 'MP responsiveness by party'),
  array('bodies', 'Bodies', 'Responsiveness by body'),
  array('methodology', 'Methodology', 'Methodology')
);
$navigation_tabs_array = array();
foreach($navigation_tabs as $navigation_tab){
  $html = '<span class="hide-for-large-up">' . $navigation_tab[1] . '</span>
           <span class="show-for-large-up">' . $navigation_tab[2] . '</span>';
  if($type == $navigation_tab[0]){
    $navigation_tabs_array[] = '<b>' . $html . '</b>';
  } else {
    $navigation_tabs_array[] = '<a href="/stats/' . $year . '/' . $navigation_tab[0] . '">' . $html . '</a>';
  }
}
$navigation_tabs = '<ul class="inline-list" role="navigation"><li>' . join($navigation_tabs_array, "</li><li>") . '</li></ul>';

require_once "../phplib/summary_report_${year}.php";
require_once "../phplib/questionnaire_report_${year}_WMC.php";

$error_message = '';
$area_representatives = array();
$rep_info = array();
if ( $parlparse_id) {
    $rep_info['parlparse_person_id'] = 'uk.org.publicwhip/person/' . $parlparse_id;
} else if ($postcode) {
    $area_name = '';
    $voting_areas = mapit_call('postcode', $postcode, $mapit_args, array(
        400 => MAPIT_BAD_POSTCODE,
        404 => MAPIT_POSTCODE_NOT_FOUND,
    ));
    if (!rabx_is_error($voting_areas)) {
        # we're grabbing this here so we can use it when checking we've got
        # the correct rep later on
        $area_name = $voting_areas['areas'][$voting_areas['shortcuts']['WMC']]['name'];
        $area_representatives = dadem_get_representatives($voting_areas['shortcuts']['WMC'], True);
        dadem_check_error($area_representatives);
    } else {
        if ($voting_areas->code == MAPIT_BAD_POSTCODE) {
            $error_message = "Sorry, we need your complete UK postcode to identify your elected representatives.";
        } elseif ($voting_areas->code == MAPIT_POSTCODE_NOT_FOUND) {
            $error_message = "We’re not quite sure why, but we can’t seem to recognise your postcode.";
        }
    }
}

if ($type == 'mps') {
    // Table of responsiveness of MPs
    $last_year = array();
    if (file_exists("../phplib/questionnaire_report_${previous_year}_WMC.php")) {
        require_once "../phplib/questionnaire_report_${previous_year}_WMC.php";
        $last_year = $GLOBALS["questionnaire_report_{$previous_year}_WMC"];
    }
    # the current MP may not be the MP from the year of the stats so loop back
    # through the reps till we get one who is in the stats. We need to check the
    # area is the same on the small chance that e.g when asking for the 2007 stats
    # the MP elected in 2015 was MP for a different seat and so you get their stats but
    # for the wrong seat - e.g asking for stats for Uxbridge and South Ruislip for
    # 2007 would get you Boris Johnson's stats for Henley in 2007. This only works if
    # the cons name matches with MaPit which is should :/
    if ($area_representatives) {
        foreach ( $area_representatives as $rep ) {
            $area_rep = dadem_get_representative_info($rep);
            dadem_check_error($rep);
            $key = $area_rep['parlparse_person_id'];
            if ( array_key_exists($key, $GLOBALS["questionnaire_report_${year}_WMC"]) &&
                 $area_name == $GLOBALS["questionnaire_report_${year}_WMC"][$key]["area"] ) {
                $rep_info = $area_rep;
                break;
            }
        }
    }
    mp_response_table($year, $xml, $rep_info, $GLOBALS["questionnaire_report_${year}_WMC"], $GLOBALS["zeitgeist_by_summary_type_$year"], $last_year, $error_message, $postcode);

} elseif ($type == 'parties') {
    party_response_table($year, $GLOBALS["party_report_${year}_WMC"]);

} elseif ($type == 'bodies') {
    type_response_table($year, $GLOBALS["zeitgeist_by_summary_type_$year"], $GLOBALS["questionnaire_report_${year}_WMC"]);

} elseif ($type == 'methodology') {
    questionnaire_report($year, $GLOBALS["zeitgeist_by_summary_type_$year"], $GLOBALS["questionnaire_report_${year}_WMC"]);

} elseif ($type == 'zeitgeist') {
    // This URL used to house the "summary" page, before we split out onto 4 tabs.
    // Redirect the visitor to the "mps" page, since that's probably what they want.
    header("Location: /stats/$year/mps");
    exit;

} else {
    template_show_error("Unknown report type '".htmlspecialchars($type)."'");
}

function fuzzy_response_description($a) {
    if ($a < 0.20)
        return "very low";
    else if ($a < 0.40)
        return "low";
    else if ($a < 0.60)
        return "medium";
    else if ($a < 0.80)
        return "high";
    else if ($a <= 1.00)
        return "very high";
    return "unknown $a";
}

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

function party_response_table($year, $party_summary) {
    uasort($party_summary, 'sort_by_responsiveness');
    $parties_by_responsiveness = array_keys($party_summary);
    uasort($party_summary, 'sort_by_firsttime');
    $parties_by_firsttime = array_keys($party_summary);

    global $year_bar;
    global $navigation_tabs;
    if ($year == '2015') {
        $title_string = "WriteToThem.com Zeitgeist 2015&ndash;2016";
    } else {
        $title_string = "WriteToThem.com Zeitgeist $year";
    }
    template_draw('stats-party-performance', array(
        "title" => $title_string,
        'year' => $year,
        'year_bar' => $year_bar,
        'navigation_tabs' => $navigation_tabs,
        'party_summary' => $party_summary,
        'parties_by_responsiveness' => $parties_by_responsiveness,
        'parties_by_firsttime' => $parties_by_firsttime
    ));
}

function type_response_table($year, $type_summary, $questionnaire_report) {
    uasort($type_summary, 'sort_by_responsiveness');
    $types_by_responsiveness = array_keys($type_summary);

    $data = array();
    foreach ($questionnaire_report as $key => $row) {
        if (is_array($row)) {
            // No need for data on individuals for the methodology page
        } else {
            // These are handy figures like "total_dispatched_success" etc.
            $data['info'][$key] = $row;
        }
    }

    $non_mp_sent = 0;
    foreach ($type_summary as $type => $row) {
    	if ($type != 'westminster' && $type != 'total')
	    $non_mp_sent += $row['dispatched_success'];
    }
    $data['info']['non_mp_sent'] = $non_mp_sent;

    global $year_bar;
    global $navigation_tabs;
    if ($year == '2015') {
        $title_string = "WriteToThem.com Zeitgeist 2015&ndash;2016";
    } else {
        $title_string = "WriteToThem.com Zeitgeist $year";
    }
    template_draw('stats-type-performance', array(
        "title" => $title_string,
        'year' => $year,
        'year_bar' => $year_bar,
        'navigation_tabs' => $navigation_tabs,
        'type_summary' => $type_summary,
        'types_by_responsiveness' => $types_by_responsiveness,
        'data' => $data
    ));
}

function questionnaire_report($year, $type_summary, $questionnaire_report) {
    global $year_bar;
    global $navigation_tabs;

    $data = array();
    foreach ($questionnaire_report as $key => $row) {
        if (is_array($row)) {
            // No need for data on individuals for the methodology page
        } else {
            // These are handy figures like "total_dispatched_success" etc.
            $data['info'][$key] = $row;
        }
    }

    $non_mp_sent = 0;
    foreach ($type_summary as $type => $row) {
    	if ($type != 'westminster' && $type != 'total')
	    $non_mp_sent += $row['dispatched_success'];
    }
    $data['info']['non_mp_sent'] = $non_mp_sent;

    if ($year == '2015') {
        $title_string = "WriteToThem.com Zeitgeist 2015&ndash;2016";
    } else {
        $title_string = "WriteToThem.com Zeitgeist $year";
    }
    template_draw('stats-methodology', array(
        "title" => $title_string,
        'year' => $year,
        'year_bar' => $year_bar,
        'navigation_tabs' => $navigation_tabs,
        'type_summary' => $type_summary,
        'data' => $data
    ));
}

function mp_response_table($year, $xml, $rep_info, $questionnaire_report, $type_summary, $last_year_report, $error_message, $pc) {
    $last_year_data = array();
    foreach ($last_year_report as $key => $row) {
        if (is_array($row)) {
            $last_year_data[] = array(
                'name' => $row['name'],
                'person_id' => $row['person_id'],
                'category' => $row['category'],
                'response' => $row['responded_mean'],
                'low' => $row['responded_95_low'],
                'high' => $row['responded_95_high'],
            );
        }
    }
    usort($last_year_data, 'by_response');
    $position = 0;
    $same_stat = 1;
    $last_response = -1;
    $last_low = -1;
    $fymp_ranked = array();
    $fymp_response = array();
    $fymp_category = array();
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
        $fymp_response[$row['person_id']] = $row['response'];
        $fymp_category[$row['person_id']] = $row['category'];
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
                'responded' => $row['responded'],
                'responded_outof' => $row['responded_outof'],
                # XXX fymp_ means really 'last_year_', should fix that
                'fymp_rank' => array_key_exists($key, $fymp_ranked) ? $fymp_ranked[$key] : null,
                'fymp_response' => array_key_exists($key, $fymp_response) ? $fymp_response[$key] : null,
                'fymp_notes' => array_key_exists($key, $fymp_category) ? category_lookup($fymp_category[$key]) : ''
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
        $parlparse_id = str_replace('uk.org.publicwhip/person/', '', $key);
        $row = $questionnaire_report[$key];
        $data['info']['mp'] = array_merge($row, array(
            'id' => $parlparse_id,
            'notes' => category_lookup($row['category']),
            'response' => $row['responded_mean'],
            'low' => $row['responded_95_low'],
            'high' => $row['responded_95_high'],
            # XXX fymp_ means really 'last_year_', should fix that
            'fymp_rank' => array_key_exists($key, $fymp_ranked) ? $fymp_ranked[$key] : null,
            'fymp_response' => array_key_exists($key, $fymp_response) ? $fymp_response[$key] : null,
            'fymp_notes' => array_key_exists($key, $fymp_category) ? category_lookup($fymp_category[$key]) : ''
	));
    }

    # Output data
    global $year_bar;
    global $navigation_tabs;
    if ($year == '2015') {
        $title_string = "WriteToThem.com Zeitgeist 2015&ndash;2016";
    } else {
        $title_string = "WriteToThem.com Zeitgeist $year";
    }
    template_draw($xml ? 'stats-mp-twfy' : 'stats-mp-performance', array(
        "title" => $title_string,
        'year' => $year,
        'year_bar' => $year_bar,
        'data' => $data,
        'error_message' => $error_message,
        'pc' => $pc,
        'navigation_tabs' => $navigation_tabs,
        ));
}

function category_lookup($cat) {
    if ($cat == 'good') return '';
    elseif ($cat == 'shame') return "MP did not accept messages via WriteToThem";
    elseif ($cat == 'toofew') return 'Too little data for valid analysis';
    elseif ($cat == 'unknown') return 'We need to manually check this MP';
    elseif ($cat == 'cheat') return '<a href="https://www.writetothem.com/about-ilg">MP attempted to improve their response rate by sending themselves messages</a>';
    elseif ($cat == 'badcontact') return 'WriteToThem had possibly bad contact details for this MP';
    else template_show_error("Unknown MP categorisation '".htmlspecialchars($cat)."'");
    return $cat;
}
?>

