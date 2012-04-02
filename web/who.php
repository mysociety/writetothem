<?
/*
 * who.php:
 * Page to ask which representative they would like to contact
 *
 * Copyright (c) 2004 UK Citizens Online Democracy. All rights reserved.
 * Email: francis@mysociety.org. WWW: http://www.mysociety.org
 *
 * $Id: who.php,v 1.117 2009-11-30 09:26:16 louise Exp $
 *
 */

require_once "../phplib/fyr.php";

require_once "../commonlib/phplib/utility.php";
require_once "../commonlib/phplib/dadem.php";
require_once "../commonlib/phplib/mapit.php";
require_once "../commonlib/phplib/votingarea.php";

// Input data

// Postcode
$fyr_postcode = canonicalise_postcode(get_http_var('pc'));
if ($fyr_postcode == '') {
    header('Location: /');
    exit();
}

$a_forward = get_http_var('a');
if ($cobrand) {
    $a_forward = cobrand_force_representative_type($cobrand, $cocode, $a_forward);
}

$area_types = fyr_parse_area_type_list($a_forward);

debug("FRONTEND", "postcode is $fyr_postcode");
debug_timestamp();
fyr_rate_limit(array('postcode' => array($fyr_postcode, "Postcode that's been typed in ")));

// Find all the districts/constituencies and so on (we call them "voting
// areas") for the postcode
$voting_areas = mapit_get_voting_areas($fyr_postcode);
if (rabx_is_error($voting_areas)) {
    header('Location: ' . url_new('/', true, 'pc', $fyr_postcode));
    exit;
}
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
    $fyr_all_url = '';
    $cobrand_all_url = cobrand_main_write_url($cobrand, $fyr_postcode, $cocode, fyr_external_referrer());
    if ($cobrand_all_url != '') {
        $fyr_all_url = $cobrand_all_url;
    } else {
        $fyr_all_url = htmlspecialchars(url_new('who', false,
                        'pc', $fyr_postcode,
                        'fyr_extref', fyr_external_referrer(),
                        'cocode', $cocode));
    }
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

$meps_hidden = euro_check($area_representatives, $voting_areas);

// For each voting area in order, find all the representatives.  Put
// descriptive text and form text in an array for the template to
// render.
$fyr_representatives = array();
$fyr_headings = array();
$fyr_rep_descs = array(); 
$fyr_rep_lists = array();
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
    $rep_counts = array();
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

    $col_blurb = cobrand_col_blurb($cobrand, $va_type, $va_info, $eb_info, $rep_count, $rep_counts, $representatives, $va_salaried);
    if (!$col_blurb) {
         $col_blurb = col_blurb($va_type, $va_info, $eb_info, $rep_count, $rep_counts, $representatives, $va_salaried);
    }
    $text = '';
    $col_after = '';
    // Already putting 'write all' link in list? 
    $options = cobrand_rep_list_options($cobrand);
    $skip_write_all = false;
    if (array_key_exists('include_write_all', $options) && $options['include_write_all']) {
         $skip_write_all = true;
    }
    // Create HTML
    global $disabled_child_types;
    if (is_array($va_type)) {
        // Plural
        if ($rep_count > 1) {
            $heading = "Your {$va_info[0]['rep_name_long_plural']}";
        } else {
            $heading = "Your {$va_info[0]['rep_name_long']}";
        }
        if ($rep_count && $rep_counts[0]>1 && ! $skip_write_all) {
            $text .= write_all_link($va_type[0], $va_info[0]['rep_name_plural']);
        }
        if ($rep_count)
            $text .= display_reps($va_type[0], $representatives[0], $va_info[0], array());
        $text .= '<p>';
        if ($va_type[1] == 'LAE') {
            $text .= "$rep_counts[1] London Assembly list members also represent you";
        } elseif ($rep_count && $rep_counts[1] > 1) {
            $text .= "$rep_counts[1] {$va_info[1]['name']} {$va_info[1]['type_name']} {$va_info[1]['rep_name_plural']} also represent you";
        } else {
            $text .= "One {$va_info[1]['name']} {$va_info[1]['type_name']} {$va_info[1]['rep_name']} also represents you";
        }
        if ($va_type[1] == 'SPE') {
            $text .= '; if you are writing on a constituency matter or similar <strong>local or personal problem</strong>, please
write to your <strong>constituency MSP</strong> above, or pick just <strong>one</strong> of your regional MSPs.
Only <strong>one</strong> MSP is allowed to help you at a time';
}
        $text .= '.</p>';
        
        if ($rep_count && $rep_counts[1]>1 && $va_type[1] != 'SPE' && $va_type[1] != 'LAE' && ! $skip_write_all) {
            $text .= '<p>' . write_all_link($va_type[1], $va_info[1]['rep_name_plural']) . '</p>';
        }

        if ($rep_count)
            $text .= display_reps($va_type[1], $representatives[1], $va_info[1], array());

        if ($rep_count && $rep_counts[1]>1 && ($va_type[1] == 'SPE' || $va_type[1] == 'LAE') && ! $skip_write_all) {
            $text .= '<p>' . write_all_link($va_type[1], $va_info[1]['rep_name_plural']) . '</p>';
        }

    } else {
        // Singular
        if ($rep_count > 1) {
            if ($va_type == 'EUR' && count($meps_hidden))
                $rep_count += count($meps_hidden);
            $heading = "Your ${va_info['rep_name_long_plural']}";
        } else {
            $heading = "Your ${va_info['rep_name_long']}";
        }
        
        if ($rep_count > 1 && ! $skip_write_all) {
            $text .= '<p>' . write_all_link($va_type, $va_info['rep_name_plural']) . '</p>';
        }

        if($va_type == 'WMC' && $rep_count > 0 && file_exists('mpphotos/'.$representatives[0].'.jpg')) {
            $representatives_info[$representatives[0]]['image'] = "/mpphotos/" . $representatives[0] . ".jpg";
        }
        $text .= display_reps($va_type, $representatives, $va_info, array());

        if ($va_type == 'WMC') {
            if ($rep_count)
                $text .= '<p id="twfy"><a href="http://www.theyworkforyou.com/mp/?c=' . urlencode(str_replace(' and ',' &amp; ',$va_info['name'])) . '">Find out more about ' . $representatives_info[$representatives[0]]['name'] . ' at TheyWorkForYou</a></p>';
            # .maincol / .firstcol have margin-bottom set to none, override
            $col_after .= '<h3 class="houseoflords">House of Lords</h3>';
            $col_after .= '<p>Lords are not elected by you, but they still get to vote in Parliament just like your MP. You may want to write to a Lord (<a href="about-lords">more info</a>).</p>';
            $col_after .= '<ul><li><a href="/lords">Write to a Lord</a></li></ul>';
#            $text .= '<div style="padding: 0.25cm; font-size: 80%; background-color: #ffffaa; text-align: center;">';
# yellow flash advert
#            $text .= '</div>';
        } elseif ($va_type == 'EUR' && count($meps_hidden)) {
            # XXX Specific to what euro_check currently does!
            $text .= '<p style="margin-top:2em"><small>The Conservative MEPs
for your region have informed us that they have divided it into areas, with ';
            if (count($meps_hidden)==1)
                $text .= 'one or two MEPs';
            else
                $text .= 'one MEP';
            $text .= ' dealing with constituent correspondence per area, so we only show ';
            if (count($meps_hidden)==1)
                $text .= 'them';
            else
                $text .= 'that MEP';
            $text .= ' above; you can contact the ';
            if (count($meps_hidden)==1)
                $text .= 'other';
            else
                $text .= 'others';
            $text .= ' here:</small></p>';
            $text .= display_reps($va_type, $meps_hidden, $va_info, array('small' => true));
        }
        global $va_council_child_types;
        if (in_array($va_type, $va_council_child_types) && cobrand_display_councillor_correction_link($cobrand)) {
            $text .= '<p style="font-size: 80%"><a href="corrections?id='.$va_specificid.'">Have you spotted a mistake in the above list?</a></p>';
        }
    }

    if ($disabled) {
        if ($status == "boundary_changes" || $parent_status == "boundary_changes") {
            $text = "<p>There have been boundary changes at the last election that
            means we can't yet say who your representative is. We hope to get our
            boundary database updated as soon as we can.</p>";
        } elseif ($status == "recent_election" || $parent_status == "recent_election") {
            $text = "<p>Due to the recent election, we don't yet have details for this
                representative.  We'll be adding them as soon as we can.</p>";
        } elseif ($status == "pending_election" || $parent_status == "pending_election") {
            $text = "<p>There's an upcoming election.  We'll be adding your new
                    representative as soon as we can after the election.</p>";
        } else {
            $text = "Representative details are not available for an unknown reason.";
        }
        $heading = "<strike>$heading</strike>";
    }
    array_push($fyr_rep_descs, $col_blurb);
    array_push($fyr_rep_lists, $text);
    array_push($fyr_representatives, "$col_blurb$text$col_after");
    array_push($fyr_headings, "<h3>$heading</h3>");
    debug_timestamp();
}

// Display page, using all the fyr_* variables set above.
template_draw("who", array(
    "reps" => $fyr_representatives,
    "template" => "who", 
    "headings" => $fyr_headings,
    "error" => $fyr_error,
    "county_note" => $fyr_county_note,
    "all_url" => $fyr_all_url,
    "cobrand" => $cobrand, 
    "host" => fyr_get_host(), 
    "rep_lists" => $fyr_rep_lists, 
    "rep_descs" => $fyr_rep_descs
    ));

debug_timestamp();

function write_all_link($va_type, $rep_desc_plural) {
    global $cobrand, $cocode;
    global $fyr_postcode;
    if ($rep_desc_plural == 'London Assembly Members')
        $rep_desc_plural = 'London Assembly list members';
    if ($rep_desc_plural == 'MSPs')
        $rep_desc_plural = 'regional MSPs';
    $url = general_write_all_url($va_type, $fyr_postcode);
    $a = cobrand_write_all_link($cobrand, $url, $rep_desc_plural, $cocode);
    if (!$a) {
        $a = '<a href="' . cobrand_url($cobrand, $url, $cocode) . '">Write to all your ' . $rep_desc_plural . '</a>';  
        if ($va_type == 'SPE') {
            $a .= ' <small>(only use this option if you are writing to your
MSPs about <strong>voting issues or other issues concerning matters in the
Scottish Parliament</strong>. If you have a constituency matter or similar
local or personal problem, please write to your constituency MSP above, or
pick just one of your regional MSPs.)</small>';
        }
    }
    return $a;

}

function general_write_all_url($va_type, $fyr_postcode){
    global $cocode;
    return htmlspecialchars(url_new('/write', true,
                                    'who', 'all',
                                    'type', $va_type,
                                    'pc', $fyr_postcode,
                                    'fyr_extref', fyr_external_referrer(),
                                    'cocode', $cocode));
}

function general_write_rep_url($va_type, $rep_specificid, $fyr_postcode){
    global $cocode;
    return htmlspecialchars(url_new('/write', true,
                                    'who', $rep_specificid,
                                    'pc', $fyr_postcode,
                                    'fyr_extref', fyr_external_referrer(),
                                    'cocode', $cocode));
}

function col_blurb($va_type, $va_info, $eb_info, $rep_count, $rep_counts, $representatives, $va_salaried){
  
    $col_blurb = "<p>";
     
    if (is_array($va_type)) {
        $col_blurb .= rep_text($rep_count, $rep_counts[0], $va_info[0], $eb_info);
    } else {
        $col_blurb .= rep_text($rep_count, $rep_count, $va_info, $eb_info);
        if (!$va_salaried[$va_type] && $va_info['country']!='S')
            $col_blurb .= " Most ${va_info['rep_name_long_plural']} are not paid a salary, but get a basic allowance for the work they do.";
    }
    $col_blurb .= "</p>";
    return $col_blurb;
}
     

function rep_text($main_rep_count, $rep_count, $va_info, $eb_info) {
    $text = '';
    if ($main_rep_count && $rep_count > 1) {
        $text = "Your $rep_count ${va_info['name']} ${va_info['rep_name_plural']} represent you ${eb_info['attend_prep']} ";
    } else {
        $text = "Your ${va_info['name']} ${va_info['rep_name']} represents you ${eb_info['attend_prep']} ";
    }
    $text .= "${eb_info['name']}.  ${eb_info['description']}";
    return $text;
}

function display_reps($va_type, $representatives, $va_info, $options) {
    global $representatives_info, $fyr_postcode, $cobrand, $cocode;
    $rep_list = ''; $photo = 0;
    $default_options = cobrand_rep_list_options($cobrand);
    $options = array_merge($default_options, $options);
    foreach ($representatives as $rep_specificid) {
        $rep_info = $representatives_info[$rep_specificid];
        $rep_list .= '<li>';
        $url = general_write_rep_url($va_type, $rep_specificid, $fyr_postcode);
        $a = '<a href="' .  cobrand_url($cobrand, $url, $cocode) . '">';
        if ($rep_specificid == '2000005') {
            $rep_list .= $a . '<img alt="" title="Portrait of Stom Teinberg MP" src="images/zz99zz.jpeg" align="left" border="0">';
            $photo = 1;
        } elseif (array_key_exists('image', $rep_info)) {
            $rep_list .= $a . '<img alt="" title="Portrait of ' . htmlspecialchars($rep_info['name']) . '" src="' . $rep_info['image'] . '" align="left">';
            $photo = 1;
        }
        if ($photo == 0) $rep_list .= $a;
        $rep_list .= htmlspecialchars($rep_info['name']) . '</a>';
        if (array_key_exists('party', $rep_info)) {
            $rep_list .= ' <span class="party">(' . str_replace(' ', '&nbsp;', htmlspecialchars($rep_info['party'])) . ')</span>';
        }
    }

    if (array_key_exists('include_write_all', $options) && $options['include_write_all'] && count($representatives) > 1){
        $rep_list .= '<li class="all">';
        $rep_list .= write_all_link($va_type, $va_info['rep_name_plural']);
        $rep_list .= '</li>';
    }
    $rep_type =  str_replace(' ', '-', strtolower($va_info['rep_name_plural']));
    $out = '<ul class="' . $rep_type . '" ';
    if ($photo==1) $out .= ' id="photo"';
    if (array_key_exists('small', $options) && $options['small']) $out .= ' style="font-size:83%"';
    $out .= '>';
    $out .= $rep_list . '</ul>';
    return $out;
}
?>
