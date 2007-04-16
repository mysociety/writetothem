<?
/*
 * who.php:
 * Page to ask which representative they would like to contact
 *
 * Copyright (c) 2004 UK Citizens Online Democracy. All rights reserved.
 * Email: francis@mysociety.org. WWW: http://www.mysociety.org
 *
 * $Id: who.php,v 1.86 2007-04-16 22:09:08 francis Exp $
 *
 */

require_once "../phplib/fyr.php";

require_once "../../phplib/utility.php";
require_once "../../phplib/dadem.php";
require_once "../../phplib/mapit.php";
require_once "../../phplib/votingarea.php";

// Input data

// Postcode
$fyr_postcode = get_http_var('pc');
if ($fyr_postcode == '') {
    header('Location: /');
    exit();
}

$area_types = fyr_parse_area_type_list(get_http_var('a'));

debug("FRONTEND", "postcode is $fyr_postcode");
debug_timestamp();
fyr_rate_limit(array('postcode' => array($fyr_postcode, "Postcode that's been typed in ")));
if (get_http_var('err')) {
    $fyr_error = "Please select a representative before clicking Next.";
}

// Find all the districts/constituencies and so on (we call them "voting
// areas") for the postcode
$voting_areas = mapit_get_voting_areas($fyr_postcode);
mapit_check_error($voting_areas);
debug_timestamp();

// Limit to specific types of representatives
$fyr_all_url = null;
if ($area_types) {
    $a = array();
    foreach (array_keys($area_types) as $t) {
        if (array_key_exists($t, $voting_areas))
            $a[$t] = $voting_areas[$t];
        if (array_key_exists($t, $va_inside)
            && array_key_exists($va_inside[$t], $voting_areas))
            $a[$va_inside[$t]] = $voting_areas[$va_inside[$t]];
    }
    $voting_areas = $a;

    $fyr_all_url = htmlspecialchars(url_new('who', false,
                    'pc', $fyr_postcode,
                    'fyr_extref', fyr_external_referrer(),
                    'cocode', get_http_var('cocode')));

}

// If in a county, but not a county electoral division, display explanation
// (which is lack of data from Ordnance Survey)
$fyr_county_note = false;
if (array_key_exists('CTY', $voting_areas) && !array_key_exists('CED', $voting_areas)) {
    $fyr_county_note = true; 
}

$voting_areas_info = mapit_get_voting_areas_info(array_values($voting_areas));
mapit_check_error($voting_areas_info);
debug_timestamp();

$area_representatives = dadem_get_representatives(array_values($voting_areas));
$error = dadem_get_error($area_representatives);
dadem_check_error($area_representatives);
debug_timestamp();

$all_representatives = array();
foreach (array_values($area_representatives) as $rr) {
    $all_representatives = array_merge($all_representatives, $rr);
}
$representatives_info = dadem_get_representatives_info($all_representatives);
dadem_check_error($representatives_info);
debug_timestamp();

// For each voting area in order, find all the representatives.  Put
// descriptive text and form text in an array for the template to
// render.
$fyr_representatives = array();
$fyr_headings = array();
$fyr_error = null;
foreach ($va_display_order as $va_type) {
    // Search for whether display type is fully present
    if (is_array($va_type)) {
        $ok = false;
        foreach ($va_type as $vat) {
            if (array_key_exists($vat, $voting_areas))
                $ok = true;
        }
        if (!$ok) 
            continue;
    } else {
        if (!array_key_exists($va_type, $voting_areas))
            continue;
    }

    // If it is, display it
    unset($va_specificid); unset($va_info);
    if (is_array($va_type)) {
        foreach ($va_type as $vat) $va_specificid[] = $voting_areas[$vat];
        foreach ($va_specificid as $vasid) $va_info[] = $voting_areas_info[$vasid];
    } else {
        $va_specificid = $voting_areas[$va_type];
        // The voting area is the ward/division. e.g. West Chesterton Electoral Division
        debug("FRONTEND", "voting area is type $va_type id $va_specificid");
        $va_info = $voting_areas_info[$va_specificid];
    }

    // The elected body is the overall entity. e.g. Cambridgeshire County
    // Council.
    if (is_array($va_type)) {
        $eb_type = $va_inside[$va_type[0]];
    } else {
        $eb_type = $va_inside[$va_type];
    }
    $eb_specificid = $voting_areas[$eb_type];
    debug("FRONTEND", "electoral body is type $eb_type id $eb_specificid");
    $eb_info = $voting_areas_info[$eb_specificid];

    // Description of areas of responsibility
    $eb_info['description'] = $va_responsibility_description[$eb_type];

    // Count representatives
    unset($representatives);
    if (is_array($va_type)) {
        foreach ($va_specificid as $vasid) $representatives[] = $area_representatives[$vasid];
        $rep_count = 0;
        foreach ($representatives as $key => $rep) {
            $rep_counts[] = count($rep);
            $rep_count += count($rep);
            shuffle($representatives[$key]);
        }
    } else {
        $representatives = $area_representatives[$va_specificid];
        $rep_count = count($representatives);
        shuffle($representatives);
    }

    if ($rep_count == 0) continue;

    // Data bad due to election etc?
    $disabled = false;
    if (is_array($va_type))
        $va_alone = $va_info[0];
    else 
        $va_alone = $va_info;
    $parent_status = dadem_get_area_status($va_alone['parent_area_id']);
    dadem_check_error($parent_status);
    $status = dadem_get_area_status($va_alone['area_id']);
    dadem_check_error($status);
    if ($parent_status != 'none' || $status != 'none') {
        $disabled = true;
    }

    // Create HTML
    global $disabled_child_types;
    if (is_array($va_type)) {
        // Plural
        if ($rep_count > 1) {
            $heading = "Your {$va_info[0]['rep_name_long_plural']}";
            $text = "<p>";
        } else {
            $heading = "Your {$va_info[0]['rep_name_long']}";
            $text = "<p>";
        }
        if ($rep_counts[0]>1) {
            $text .= "Your $rep_counts[0] {$va_info[0]['name']} {$va_info[0]['rep_name_plural']} represent you ${eb_info['attend_prep']} ";
        } else {
            $text .= "Your {$va_info[0]['name']} {$va_info[0]['rep_name']} represents you ${eb_info['attend_prep']} ";
        }
        $text .= "${eb_info['name']}.  ${eb_info['description']}</p>";
        if ($rep_counts[0]>1) {
            $text .= write_all_link($va_type[0], $va_info[0]['rep_name_plural']);
	}
        $text .= display_reps($representatives[0]);
        $text .= '<p>';
        if ($va_type[1] == 'LAE') {
            $text .= "$rep_counts[1] London Assembly list members also represent you";
        } elseif ($rep_counts[1] > 1) {
            $text .= "$rep_counts[1] {$va_info[1]['name']} {$va_info[1]['type_name']} {$va_info[1]['rep_name_plural']} also represent you";
        } else {
            $text .= "One {$va_info[1]['name']} {$va_info[1]['type_name']} {$va_info[1]['rep_name']} also represents you";
        }
        $text .= '.</p>';
        
        if ($rep_counts[1]>1) {
            $text .= write_all_link($va_type[1], $va_info[1]['rep_name_plural']);
        }

        $text .= display_reps($representatives[1]);
    } else {
        // Singular
        if ($rep_count > 1) {
            $heading = "Your ${va_info['rep_name_long_plural']}";
            $text = "<p>Your $rep_count ${va_info['name']} ${va_info['rep_name_plural']} represent you ${eb_info['attend_prep']} ";
        } else {
            $heading = "Your ${va_info['rep_name_long']}";
            $text = "<p>Your ${va_info['name']} ${va_info['rep_name']} represents you ${eb_info['attend_prep']} ";
        }
        $text .= "${eb_info['name']}.  ${eb_info['description']}";
        /* Note categories of representatives who typically aren't paid for
         * their work.... */
        if (!$va_salaried[$va_type])
            $text .= " Most ${va_info['rep_name_long_plural']} are not paid for the work they do.";
        $text .= "</p>";
	
	if ($rep_count > 1) {
            $text .= write_all_link($va_type, $va_info['rep_name_plural']);
        }

        if($va_type == 'WMC' && file_exists('mpphotos/'.$representatives[0].'.jpg')) {
            $representatives_info[$representatives[0]]['image'] = "/mpphotos/" . $representatives[0] . ".jpg";
        }
        $text .= display_reps($representatives);

        if ($va_type == 'WMC') {
            $text .= '<p id="twfy"><a href="http://www.theyworkforyou.com/mp/?c=' . urlencode(str_replace(' and ',' &amp; ',$va_info['name'])) . '">Find out more about ' . $representatives_info[$representatives[0]]['name'] . ' at TheyWorkForYou.com</a></p>';
            # .maincol / .firstcol have margin-bottom set to none, override
            $text .= '<h3 class="houseoflords">House of Lords</h3>';
            $text .= '<p>Lords are not elected by you, but they still get to vote in Parliament just like your MP. You may want to write to a Lord (<a href="about-lords">more info</a>).</p>';
            $text .= '<ul><li><a href="/lords">Write to a Lord</a></li></ul>';
#            $text .= '<div style="padding: 0.25cm; font-size: 80%; background-color: #ffffaa; text-align: center;">';
# yellow flash advert
#            $text .= '</div>';
        }
        global $va_council_child_types;
        if (in_array($va_type, $va_council_child_types)) {
            $text .= '<p style="font-size: 80%"><a href="corrections?id='.$va_specificid.'">Have you spotted a mistake in the above list?</a></p>';
        }
    }

    if ($disabled) {
        if ($status == "recent_election" || $parent_status == "recent_election") {
            $text = "<p>Due to the recent election, we don't yet have details for this
                representative.  We'll be adding them as soon as we can.</p>";
        } elseif ($status == "pending_election" || $parent_status == "pending_election") {
            $text = "<p>There's an upcoming election.  We'll be adding your new
                    representative as soon as we can after the election.</p>";
        } else {
            $text = "Representative details not available for unknown reason.";
        }
        $text .="<p>Why not take this as an opportunity to <strong>write to one of your
            other representatives</strong>? Their job is to help you too!</p>";
        array_push($fyr_representatives, $text);
        array_push($fyr_headings, "<h3><strike>$heading</strike></h3>");
    } else {
        array_push($fyr_representatives, $text);
        array_push($fyr_headings, "<h3>$heading</h3>");
    }
    debug_timestamp();
}

// Display page, using all the fyr_* variables set above.
template_draw("who", array(
    "reps" => $fyr_representatives,
    "headings" => $fyr_headings,
    "error" => $fyr_error,
    "county_note" => $fyr_county_note,
    "all_url" => $fyr_all_url,
    ));

debug_timestamp();

function write_all_link($va_type, $rep_desc_plural){
    global $fyr_postcode;
    $a = '<a href="' .
                htmlspecialchars(url_new('write', true,
                                         'who', 'all',
                                         'type', $va_type,
                                         'pc', $fyr_postcode,
                                         'fyr_extref', fyr_external_referrer(),
                                         'cocode', get_http_var('cocode')))
                . '">Write to all your ' . $rep_desc_plural . '</a>';
    return $a;

}
function display_reps($representatives) {
    global $representatives_info, $fyr_postcode;
    $rep_list = ''; $photo = 0;
    foreach ($representatives as $rep_specificid) {
        $rep_info = $representatives_info[$rep_specificid];
        $rep_list .= '<li>';
        $a = '<a href="' .
                    htmlspecialchars(url_new('write', true,
                                        'who', $rep_specificid,
                                        'pc', $fyr_postcode,
                                        'fyr_extref', fyr_external_referrer(),
                                        'cocode', get_http_var('cocode')))
                . '">';
        if ($rep_specificid == '2000005') {
            $rep_list .= $a . '<img alt="" title="Portrait of Stom Teinberg MP" src="images/zz99zz.jpeg" align="left" border="0">';
            $photo = 1;
        } elseif (array_key_exists('image', $rep_info)) {
            $rep_list .= $a . '<img alt="" title="Portrait of ' . htmlspecialchars($rep_info['name']) . '" src="' . $rep_info['image'] . '" align="left">';
            $photo = 1;
        }
        if ($photo == 0) $rep_list .= $a;
        $rep_list .= htmlspecialchars($rep_info['name']) . '</a>';
        if (array_key_exists('party', $rep_info))
                       $rep_list .= '<br>' . htmlspecialchars($rep_info['party']);
    }
    return '<ul'.($photo==1?' id="photo"':'').'>' . $rep_list . '</ul>';
}
?>
