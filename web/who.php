<?
/*
 * who.php:
 * Page to ask which representative they would like to contact
 * 
 * Copyright (c) 2004 UK Citizens Online Democracy. All rights reserved.
 * Email: francis@mysociety.org. WWW: http://www.mysociety.org
 *
 * $Id: who.php,v 1.56 2005-02-09 11:59:50 chris Exp $
 * 
 */

require_once "../phplib/fyr.php";

require_once "../../phplib/utility.php";
require_once "../../phplib/dadem.php";
require_once "../../phplib/mapit.php";

// Input data

// Postcode
$fyr_postcode = get_http_var('pc');

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

$voting_areas_info = mapit_get_voting_areas_info(array_values($voting_areas));
mapit_check_error($voting_areas_info);
debug_timestamp();

$area_representatives = dadem_get_representatives(array_values($voting_areas));
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
$fyr_error = null;
foreach ($va_display_order as $va_type) {
    if (is_array($va_type)) {
        if (!array_key_exists($va_type[0], $voting_areas))
            continue;
    } else {
        if (!array_key_exists($va_type, $voting_areas))
            continue;
    }

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

    // Create HTML
    if (is_array($va_type)) {
        if ($rep_count > 1) {
            $text = "<h3>Your {$va_info[0]['rep_name_long_plural']}</h3><p>";
        } else {
            $text = "<h3>Your {$va_info[0]['rep_name_long']}</h3><p>";
        }
        if ($rep_counts[0]>1) {
            $text .= "Your $rep_counts[0] {$va_info[0]['name']} {$va_info[0]['rep_name_plural']} represent you ${eb_info['attend_prep']} ";
        } else {
            $text .= "Your {$va_info[0]['name']} {$va_info[0]['rep_name']} represents you ${eb_info['attend_prep']} ";
        }    
        $text .= "${eb_info['name']}.  ${eb_info['description']}</p>";
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
        $text .= display_reps($representatives[1]);
    } else {
        if ($rep_count > 1) {
            $text = "<h3>Your ${va_info['rep_name_long_plural']}</h3><p>";
            $text .= "Your $rep_count ${va_info['name']} ${va_info['rep_name_plural']} represent you ${eb_info['attend_prep']} ";
        } else {
            $text = "<h3>Your ${va_info['rep_name_long']}</h3><p>";
            $text .= "Your ${va_info['name']} ${va_info['rep_name']} represents you ${eb_info['attend_prep']} ";
        }
        $text .= "${eb_info['name']}.  ${eb_info['description']}";
        /* Note categories of representatives who typically aren't paid for
         * their work.... */
        if (!$va_salaried[$va_type])
            $text .= " Most ${va_info['rep_name_long_plural']} are not paid for the work they do.";
        $text .= "</p>";

        if($va_type == 'WMC') {
            $twfy = join('',file('http://www.theyworkforyou.com/mp/?c=' . urlencode(str_replace(' and ',' &amp; ',$va_info['name']))));
            if (preg_match('#<img src="/images/mps/(\d+.jpg)"#', $twfy, $matches))
                $representatives_info[$representatives[0]]['image'] = $matches[1];
        }
        $text .= display_reps($representatives);

        if ($va_type == 'WMC') {
            $text .= '<p style="font-size: 80%" id="twfy"><a href="http://www.theyworkforyou.com/mp/?c=' . urlencode(str_replace(' and ',' &amp; ',$va_info['name'])) . '">Find out more about ' . $representatives_info[$representatives[0]]['name'] . ' at TheyWorkForYou.com</a></p>';
        }
        global $va_council_child_types;
        if (in_array($va_type, $va_council_child_types)) {
            $text .= '<p style="font-size: 80%"><a href="corrections?id='.$va_specificid.'">Have you spotted a mistake in the above list?</a></p>';
        }
    }

    array_push($fyr_representatives, $text);
    debug_timestamp();
}

// Display page, using all the fyr_* variables set above.
template_draw("who", array("reps" => $fyr_representatives, "error" => $fyr_error));

debug_timestamp();

function display_reps($representatives) {
    global $representatives_info, $fyr_postcode;
    $rep_list = ''; $photo = 0;
    foreach ($representatives as $rep_specificid) {
        $rep_info = $representatives_info[$rep_specificid];
        $rep_list .= '<li>';
        if ($rep_specificid == '2000005') {
            $rep_list .= '<img src="images/zz99zz.jpeg" align="left">';
            $photo = 1;
        } elseif (array_key_exists('image', $rep_info)) {
            $rep_list .= '<img src="http://www.theyworkforyou.com/images/mps/'.$rep_info['image'].'" align="left">';
            $photo = 1;
        }
        $rep_list .= '<a href="'
                       . htmlspecialchars(new_url('write', 0, 
                                              'who', $rep_specificid,
                                               'pc', $fyr_postcode,
                                               'fyr_extref', fyr_external_referrer()))
                       . '">'
                       . htmlspecialchars($rep_info['name'])
                       . '</a>';
        if (array_key_exists('party', $rep_info))
                       $rep_list .= '<br>' . htmlspecialchars($rep_info['party']);
    }
    return '<ul'.($photo==1?' id="photo"':'').'>' . $rep_list . '</ul>';
}
?>
