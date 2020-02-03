<?
/*
 * who.php:
 * Page to ask which representative they would like to contact
 *
 * Copyright (c) 2012 UK Citizens Online Democracy. All rights reserved.
 * Email: matthew@mysociety.org. WWW: http://www.mysociety.org
 *
 */

$attend_prep = array(
    'LBO' => "on the",
    'LAS' => "on the",
    'CTY' => "on",
    'DIS' => "on",
    'UTA' => "on",
    'MTD' => "on",
    'COI' => "on",
    'LGD' => "on",
    'SPA' => "in the",
    'WAS' => "on the",
    'NIA' => "on the",
    'WMP' => "in the",
    'HOL' => "in the",
    'EUP' => "in the",
);

require_once "../phplib/fyr.php";
require_once "../phplib/mapit.php";
require_once "../commonlib/phplib/utility.php";
require_once "../commonlib/phplib/dadem.php";
require_once "../commonlib/phplib/votingarea.php";

$fyr_postcode = get_postcode();
$area_types = get_area_types();
$voting_areas = postcode_to_areas($fyr_postcode);
$fyr_all_url = limit_areas($area_types, $voting_areas); # Might alter voting_areas
$va_ids = area_ids($voting_areas);
$area_representatives = get_reps($va_ids);
$representatives_info = get_reps_info($area_representatives);

// For each voting area in order, find all the representatives.  Put
// descriptive text and form text in an array for the template to
// render.
$fyr_representatives = array();
$fyr_headings = array();
$fyr_blurbs = array();
$fyr_more = array();

foreach ($va_display_order as $va_types) {
    $has_list_reps = is_array($va_types); # e.g. Welsh Assembly, Scottish Parliament, London Assembly
    if (!is_array($va_types)) $va_types = array($va_types);
    if (!type_present($voting_areas, $va_types)) continue;

    $va_areas = type_area($voting_areas, $va_types);
    $eb_area = elected_body_area($voting_areas, $va_types);

    list($representatives, $rep_counts) = get_rep_counts($va_areas, $area_representatives);
    $rep_count = array_sum($rep_counts);

    $col_blurb = col_blurb($va_types, $va_areas[0], $eb_area, $rep_count, $rep_counts[0]);
    if ($has_list_reps) {
        list($text, $col_after) = display_reps_two_types($va_types, $va_areas, $representatives, $rep_count, $rep_counts);
    } else {
        list($text, $col_after) = display_reps_one_type($va_types[0], $va_areas[0], $representatives[0], $rep_count);
    }

    if ($rep_count > 1) {
        $heading = "Your {$va_areas[0]['rep_name_plural']}";
    } else {
        $heading = "Your {$va_areas[0]['rep_name']}";
        if (count($representatives[0]) && $representatives_info[$representatives[0][0]]['name'] == 'Vacant Seat') {
            $text = "<p>There’s an upcoming election.  We’ll be adding your new
                    representative as soon as we can after the election.</p>";
            $col_after = '';
            $heading = "<strike>$heading</strike>";
        }
    }

    // Data bad due to election etc?
    if ( $disabled = check_area_status($va_areas[0]) ) {
        $text = $disabled;
        $col_after = '';
        $heading = "<strike>$heading</strike>";
    }

    array_push($fyr_representatives, $text);
    array_push($fyr_blurbs, $col_blurb);
    array_push($fyr_more, $col_after);
    array_push($fyr_headings, $heading);
    debug_timestamp();
}

// A/B Testing Hack!
if (isset($_GET['t'])) {
    $template = $_GET['t'];
} else {
    $template = 'who';
}

// Inject extra content for Lords
// A/B Testing Hack!
if ($template == 'who' && !$area_types) {
    array_push($fyr_headings, 'House of Lords');
    array_push($fyr_blurbs, '<p>Lords are not elected by you, but they still get to vote in Parliament just like your MP. You may want to write to a Lord (<a href="about-lords">more info</a>).</p>');
    array_push($fyr_representatives, '<ul class="rep-list lords"><li><a href="/lords">Write to a Lord</a></li></ul>');
    array_push($fyr_more, '');
}

// Display page, using all the fyr_* variables set above.
template_draw($template, array(
    "reps" => $fyr_representatives,
    "template" => "who",
    "headings" => $fyr_headings,
    "blurbs" => $fyr_blurbs,
    "more" => $fyr_more,
    "all_url" => $fyr_all_url,
    "cobrand" => $cobrand,
    "host" => fyr_get_host(),
    ));

debug_timestamp();

# ---

function get_postcode() {
    $postcode = canonicalise_postcode(get_http_var('pc'));
    if (!$postcode) {
        header('Location: /');
        exit;
    }
    debug("FRONTEND", "postcode is $postcode");
    fyr_rate_limit(array('postcode' => array($postcode, "Postcode that's been typed in ")));
    return $postcode;
}

function get_area_types() {
    global $cobrand, $cocode;
    $a_forward = get_http_var('a');
    if ($cobrand) {
        $a_forward = cobrand_force_representative_type($cobrand, $cocode, $a_forward);
    }
    $area_types = fyr_parse_area_type_list($a_forward);
    debug_timestamp();
    return $area_types;
}

// Find all the districts/constituencies and so on (we call them "voting
// areas") for the postcode provided
function postcode_to_areas($postcode) {
    $voting_areas = mapit_postcode($postcode);
    if (rabx_is_error($voting_areas)) {
        header('Location: ' . url_new('/', true, 'pc', $postcode));
        exit;
    }
    debug_timestamp();

    # Switch the voting_area array to be TYPE => AREA, instead of ID => AREA.
    $a = array();
    foreach ($voting_areas['areas'] as $id => $area) {
        $a[$area['type']] = $area;
    }
    return $a;
}

function limit_areas($area_types, &$voting_areas) {
    global $va_inside;
    if (!$area_types) return null;

    $a = array();
    foreach (array_keys($area_types) as $t) {
        if (array_key_exists($t, $voting_areas)) {
            $a[$t] = $voting_areas[$t];
        }
        if (array_key_exists($t, $va_inside)
            && array_key_exists($va_inside[$t], $voting_areas)) {
            $a[$va_inside[$t]] = $voting_areas[$va_inside[$t]];
        }
    }
    $voting_areas = $a;

    global $cobrand, $fyr_postcode, $cocode;
    if ($cobrand_all_url = cobrand_main_write_url($cobrand, $fyr_postcode, $cocode, fyr_external_referrer())) {
        return $cobrand_all_url;
    }
    return htmlspecialchars(url_new('who', false,
        'pc', $fyr_postcode,
        'fyr_extref', fyr_external_referrer(),
        'cocode', $cocode
    ));
}

function area_ids($voting_areas) {
    $va_ids = array();
    foreach ($voting_areas as $type => $area) {
        $va_ids[] = $area['id'];
    }
    return $va_ids;
}

function get_reps($va_ids) {
    $area_representatives = dadem_get_representatives($va_ids);
    dadem_check_error($area_representatives);
    debug_timestamp();
    return $area_representatives;
}

function get_reps_info($area_representatives) {
    $all_representatives = array_reduce(array_values($area_representatives), 'array_merge', array());
    $representatives_info = dadem_get_representatives_info($all_representatives);
    dadem_check_error($representatives_info);
    debug_timestamp();
    return $representatives_info;
}

function type_present($voting_areas, $va_types) {
    $ok = false;
    foreach ($va_types as $vat) {
        if (array_key_exists($vat, $voting_areas))
            $ok = true;
    }
    if ($ok) return true;
    return false;
}

# Return the one or two areas for the current type we're looping through,
# and add on rep name related variables
function type_area($voting_areas, $va_types) {
    global $va_rep_name, $va_rep_name_long, $va_rep_name_plural, $va_rep_name_long_plural;
    $va_area = array();
    foreach ($va_types as $vat) {
        $v = $voting_areas[$vat];
        $v['rep_name'] = $va_rep_name[$vat];
        $v['rep_name_long'] = $va_rep_name_long[$vat];
        $v['rep_name_plural'] = $va_rep_name_plural[$vat];
        $v['rep_name_long_plural'] = $va_rep_name_long_plural[$vat];
        $va_area[] = $v;
        // The voting area is the ward/division. e.g. West Chesterton Electoral Division
        debug("FRONTEND", "voting area is type $vat id $v[id]");
    }
    return $va_area;
}

# The elected body is the overall entity. e.g. Cambridgeshire County
# Council.
function elected_body_area($voting_areas, $va_types) {
    global $va_inside, $va_responsibility_description;
    $eb_type = $va_inside[$va_types[0]];
    $eb_area = $voting_areas[$eb_type];
    debug("FRONTEND", "electoral body is type $eb_type id $eb_area[id]");
    $eb_area['description'] = $va_responsibility_description[$eb_type];
    return $eb_area;
}

function get_rep_counts($va_areas, $area_representatives) {
    $representatives = array();
    foreach ($va_areas as $vas) {
        $representatives[] = $area_representatives[$vas['id']];
    }
    $rep_counts = array();
    foreach ($representatives as $key => $rep) {
        $rep_counts[] = count($rep);
        shuffle($representatives[$key]);
    }
    return array($representatives, $rep_counts);
}

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

function general_write_rep_url($rep_specificid, $fyr_postcode){
    global $cocode;
    return htmlspecialchars(url_new('/write', true,
                                    'who', $rep_specificid,
                                    'pc', $fyr_postcode,
                                    'fyr_extref', fyr_external_referrer(),
                                    'cocode', $cocode));
}

function col_blurb($va_types, $va_area, $eb_area, $main_rep_count, $rep_count) {
    global $va_salaried, $attend_prep;
    $col_blurb = "<p>";
    if ($main_rep_count && $rep_count > 1) {
        $col_blurb .= "Your $rep_count ${va_area['name']} ${va_area['rep_name_plural']} represent you";
    } else {
        $col_blurb .= "Your ${va_area['name']} ${va_area['rep_name']} represents you";
    }
    $col_blurb .= ' ' . $attend_prep[$eb_area['type']] . ' ';
    $col_blurb .= "${eb_area['name']}.  ${eb_area['description']}";
    if (count($va_types)==1) {
        if (!$va_salaried[$va_types[0]] && $va_area['country']!='S')
            $col_blurb .= " Most ${va_area['rep_name_long_plural']} are not paid a salary, but get a basic allowance for the work they do.";
    }
    $col_blurb .= "</p>";
    return $col_blurb;
}

function display_reps($va_type, $representatives, $va_area, $options) {
    global $representatives_info, $fyr_postcode, $cobrand, $cocode;
    $rep_list = ''; $photo = 0;
    $default_options = cobrand_rep_list_options($cobrand);
    $options = array_merge($default_options, $options);
    foreach ($representatives as $rep_specificid) {
        $rep_info = $representatives_info[$rep_specificid];
        $rep_list .= '<li>';
        $url = general_write_rep_url($rep_specificid, $fyr_postcode);
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
            $rep_list .= ' <small>(' . str_replace(' ', '&nbsp;', htmlspecialchars($rep_info['party'])) . ')</small>';
        }
    }

    if (array_key_exists('include_write_all', $options) && $options['include_write_all'] && count($representatives) > 1){
        $rep_list .= '<li>';
        $rep_list .= write_all_link($va_type, $va_area['rep_name_plural']);
        $rep_list .= '</li>';
    }
    $rep_type =  str_replace(' ', '-', strtolower($va_area['rep_name_plural']));
    $out = '<ul class="rep-list ' . $rep_type . '" ';
    if ($photo==1) $out .= ' id="photo"';
    if (array_key_exists('small', $options) && $options['small']) $out .= ' style="font-size:83%"';
    $out .= '>';
    $out .= $rep_list . '</ul>';
    return $out;
}

function display_reps_one_type($va_type, $va_area, $representatives, $rep_count) {
    global $representatives_info, $cobrand, $va_council_child_types;

    $text = ''; // a string of html containing the main rep names and links
    $col_after = ''; // a string of html containing extra links, like help and TWFY

    if ($rep_count > 1 && !skip_write_all()) {
        // $text .= '<p>' . write_all_link($va_type, $va_area['rep_name_plural']) . '</p>';
        $col_after .= '<p>' . write_all_link($va_type, $va_area['rep_name_plural']) . '</p>';
    }

    if($va_type == 'WMC' && $rep_count > 0 && file_exists('mpphotos/'.$representatives[0].'.jpg')) {
        $representatives_info[$representatives[0]]['image'] = "/mpphotos/" . $representatives[0] . ".jpg";
    }
    $text .= display_reps($va_type, $representatives, $va_area, array());

    if ($va_type == 'WMC') {
        $col_after .= extra_mp_text($rep_count, $va_area, $representatives);
    } elseif ($va_type == 'EUR') {
        $col_after = 'EUR';

    }

    if (in_array($va_type, $va_council_child_types) && cobrand_display_councillor_correction_link($cobrand)) {
        // $text .= '<p><small><a href="corrections?id='.$va_area['id'].'">Correct a mistake in this list</a></small></p>';
        $col_after .= '<p><a href="corrections?id='.$va_area['id'].'">Correct a mistake in this list</a></p>';
    }

    return array($text, $col_after);
}

function display_reps_two_types($va_types, $va_area, $representatives, $rep_count, $rep_counts) {
    $text = '';// a string of html containing the main rep names and links
    $col_after = ''; // a string of html containing extra links, like help and TWFY

    $skip_write_all = skip_write_all();
    if ($rep_count && $rep_counts[0]>1 && !$skip_write_all) {
        // $text .= write_all_link($va_types[0], $va_area[0]['rep_name_plural']);
        $col_after .= '<p>' . write_all_link($va_types[0], $va_area[0]['rep_name_plural']) . '</p>';
    }
    if ($rep_count) {
        $text .= display_reps($va_types[0], $representatives[0], $va_area[0], array());
    }
    $text .= '<p>';
    if ($va_types[1] == 'LAE') {
        $text .= "$rep_counts[1] London Assembly list members also represent you";
    } elseif ($rep_count && $rep_counts[1] > 1) {
        $text .= "$rep_counts[1] {$va_area[1]['name']} {$va_area[1]['type_name']} {$va_area[1]['rep_name_plural']} also represent you";
    } else {
        $text .= "One {$va_area[1]['name']} {$va_area[1]['type_name']} {$va_area[1]['rep_name']} also represents you";
    }
    if ($va_types[1] == 'SPE') {
        $text .= '; if you are writing on a constituency matter or similar <strong>local or personal problem</strong>, please
write to your <strong>constituency MSP</strong> above, or pick just <strong>one</strong> of your regional MSPs.
Only <strong>one</strong> MSP is allowed to help you at a time';
    }
    $text .= '.</p>';

    if ($rep_count && $rep_counts[1]>1 && $va_types[1] != 'SPE' && $va_types[1] != 'LAE' && !$skip_write_all) {
        // $text .= '<p>' . write_all_link($va_types[1], $va_area[1]['rep_name_plural']) . '</p>';
        $col_after .= '<p>' . write_all_link($va_types[1], $va_area[1]['rep_name_plural']) . '</p>';
    }

    if ($rep_count) {
        $text .= display_reps($va_types[1], $representatives[1], $va_area[1], array());
    }

    if ($rep_count && $rep_counts[1]>1 && ($va_types[1] == 'SPE' || $va_types[1] == 'LAE') && !$skip_write_all) {
        // $text .= '<p>' . write_all_link($va_types[1], $va_area[1]['rep_name_plural']) . '</p>';
        $col_after .= '<p>' . write_all_link($va_types[1], $va_area[1]['rep_name_plural']) . '</p>';
    }
    return array($text, $col_after);
}

function skip_write_all() {
    global $cobrand;
    $options = cobrand_rep_list_options($cobrand);
    if (array_key_exists('include_write_all', $options) && $options['include_write_all']) {
         return true;
    }
    return false;
}

function extra_mp_text($rep_count, $va_area, $representatives) {
    global $representatives_info, $area_types;
    $text = '';
    if ($rep_count) {
        $name = $representatives_info[$representatives[0]]['name'];
        $text = '<p><a href="http://www.theyworkforyou.com/mp/?c=' . urlencode($va_area['name']) . '">See ' . $name . '&rsquo;s voting record and speeches at TheyWorkForYou</a></p>';

        // A/B Testing Hack!
        if (isset($_GET['t']) && $_GET['t'] == 'who-b' && !$area_types) {

            $text .= '</div>';
            $text .= '<h3 class="rep-heading lords v-b">House of Lords</h3>';
            $text .= '<div class="rep-blurb"><p>Lords are not elected by you, but they still get to vote in Parliament just like your MP. You may want to write to a Lord (<a href="about-lords">more info</a>).</p></div>';
            $text .= '<div class="rep-list v-b"><ul class="rep-list lords"><li><a href="/lords">Write to a Lord</a></li></ul></div>';
            $text .= '<div class="rep-more">';
        }
    }
    return $text;
}

function check_area_status( $va_alone ) {
    $parent_status = dadem_get_area_status($va_alone['parent_area']);
    dadem_check_error($parent_status);
    $status = dadem_get_area_status($va_alone['id']);
    dadem_check_error($status);
    if ($parent_status == 'none' && $status == 'none') {
        return false;
    }
    if ($status == "boundary_changes" || $parent_status == "boundary_changes") {
        $text = "<p>There have been boundary changes at the last election that
        means we can’t yet say who your representative is. We hope to get our
        boundary database updated as soon as we can, but at the moment we have no
        definite time of completion.</p>";
    } elseif ($status == "recent_election" || $parent_status == "recent_election") {
        $text = "<p>Due to the recent election, we don’t yet have details for
        this representative. We generally rely on data from external sources,
        and will be updating as soon as we can, but at the moment we have no
        definite time of completion.</p>";
    } elseif ($status == "pending_election" || $parent_status == "pending_election") {
        /* Only around election time
        if ($va_alone['type'] == 'WMC') {
            $text = "<p>Parliament has been dissolved, and so there are no MPs
until after the upcoming election. We’ll be adding your new representative as
soon as we can after the election. For more information, see
<a href='http://www.parliament.uk/get-involved/contact-your-mp/contacting-your-mp/'>Parliament’s website</a>.</p>";
        } else { */
            $text = "<p>There’s an upcoming election.  We’ll be adding your new
                    representative as soon as we can after the election.</p>";
        /* } */
    } else {
        $text = "Representative details are not available for an unknown reason.";
    }
    return $text;
}


