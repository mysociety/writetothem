<?php
/*
 * fyr.php:
 * General purpose functions specific to FYR.
 * 
 * Copyright (c) 2004 UK Citizens Online Democracy. All rights reserved.
 * Email: francis@mysociety.org; WWW: http://www.mysociety.org
 *
 * $Id: fyr.php,v 1.53 2008-01-31 15:45:06 matthew Exp $
 * 
 */

// Load configuration file
require_once "../conf/general";

require_once "../../phplib/error.php";
require_once "../../phplib/ratty.php";
require_once "../../phplib/template.php";
require_once "../../phplib/utility.php";
require_once "../../phplib/auth.php";
require_once "../../phplib/crosssell.php";
require_once "../../phplib/tracking.php";

// Disable these types (due to elections / pending elections etc.)
$disabled_child_types = array();
#$disabled_child_types = array('CED', 'WMC');
#$disabled_child_types = array();

// Types which require no postcode authentication (e.g. House of Lords)
$postcodeless_child_types = array('HOC'); 

/* Output buffering: PHP's output buffering is broken, because it does not
 * affect headers. However, it's worth using it anyway, because in the common
 * case of outputting an HTML page, it allows us to clear any output and
 * display a clean error page when something goes wrong. Obviously if we're
 * displaying an error, a redirect, an image or anything else this will break
 * horribly.*/
ob_start();

template_set_style("../templates/website");
# syndication type, read from domain name
if (array_key_exists('HTTP_HOST', $_SERVER)) {
    $syn = str_replace('.writetothem.com', '', $_SERVER['HTTP_HOST']);
} else {
    $syn = 'www';
}

# get rid of a testharness subdomain
$syn = str_replace('.testharness', '', $syn);

$syn = str_replace('.'.OPTION_WEB_DOMAIN, '', $syn);
# or override for debugging
if (array_key_exists('syn', $_GET)) {
    $syn = $_GET['syn'];
}
$cobrand_allowed = array('cheltenham'=>1, 'animalaid'=>1);
if (array_key_exists($syn, $cobrand_allowed)) {
    template_set_style("../templates/$syn", true);
    global $cobrand;
    $cobrand = $syn;
} 

/* fyr_display_error NUMBER MESSAGE
 * Display an error message to the user. */
function fyr_display_error($num, $message, $file, $line, $context) {
    /* Nuke any existing page output to display the error message. */
    if (OPTION_PHP_DEBUG_LEVEL == 0)
        ob_clean();
    if (OPTION_FYR_REFLECT_EMAILS) {
        template_show_error("<strong>$message</strong> in $file:$line");
    } else {
        /* Message will be in log file, don't display it for cleanliness */
        template_show_error("Please try again later, or <a
            href=\"mailto:team&#64;writetothem.com\">email us</a> to let us know.");
    }
}

err_set_handler_display('fyr_display_error');

/* fyr_rate_limit IMPORTANT
 * Invoke the rate limiter with the given IMPORTANT variables (e.g. postcode,
 * representative ID, etc.), as well as the script's URL and the calling IP
 * address, and return an error page if the request trips a rate limit;
 * otherwise do nothing. */
function fyr_rate_limit($important_vars) {
    // Disabled for now, as not used, and slowed things down making the Ratty call
    return;

    $important_vars['IPADDR'] = array($_SERVER['REMOTE_ADDR'], "IP address");
    $important_vars['SERVER'] = array($_SERVER['SERVER_NAME'], "Web server");
    $important_vars['PAGE'] = array($_SERVER['SCRIPT_NAME'], "Web page");

    $ret = ratty_test("fyr-web", $important_vars);
    if (isset($ret)) {
        list($rule, $error_message, $title) = $ret;
        if ($error_message == "") {
            $error_message = "Sorry, we are experiencing technical difficulties.  Please try again later.";
        }
        $error_message .= "\n<!-- ratty the rate limiter rule #$rule limit exceeded -->\n";
        template_show_error($error_message);
    }
}

/* fyr_is_internal_url URL
 * Is URL an internal HTTP URL? */
function fyr_is_internal_url($url) {
    /* XXX nasty and approximate; should parse properly */
    if (preg_match('#^http://([a-z0-9-]+\.)*\Q' . OPTION_WEB_DOMAIN .  '\E/#i', $url)) {
        return true;
    } else {
        return false;
    }
}

/* fyr_external_referrer
 * Obtain external referrer information from the request. If the request
 * contains saved external referrer information, return that; otherwise, if the
 * request contains a Referer: header with a non-FYR URL, then return that.
 * NB spelling of "referrer" in function name. */
function fyr_external_referrer() {
    $r = get_http_var('fyr_extref');
    if (isset($r) && preg_match('#^(https?://|news:)#', $r) && !fyr_is_internal_url($r))
        return $r;
    else if (array_key_exists('HTTP_REFERER', $_SERVER) && isset($_SERVER['HTTP_REFERER'])) {
        $r = $_SERVER['HTTP_REFERER'];
        if (preg_match('#^(https?://|news:)#', $r) && !fyr_is_internal_url($r))
            return $r;
    } else
        return null;
}

/* fyr_parse_area_type_list TYPES
 * Parse a comma-separated list of area TYPES, or a known alias for same, and
 * return an array whose keys are the valid listed TYPES, or null if there are
 * none or if TYPES is null or empty. Known aliases are "council", for
 * councillors of any type; "westminstermp", for MP; "regionalmp" for devolved
 * assembly members/MSPs; or "mep" for MEPs. */
function fyr_parse_area_type_list($types) {
    if (!isset($types) || $types == '')
        return null;

    global $va_aliases;
    if (array_key_exists($types, $va_aliases))
        $types = join(",",$va_aliases[$types]);
   
    global $va_child_types, $va_inside;
    $a = array();
    $n = 0;
    foreach (explode(',', $types) as $t) {
        $t = strtoupper(trim($t));
        if (!in_array($t, $va_child_types))
            continue;
        if ($t == 'HOC') // House of Lords doesn't depend on postcode
            continue;
        $a[$t] = 1;
        ++$n;
        // add sibling types
        $parent = $va_inside[$t];
        foreach ($va_inside as $k=>$v) {
            if ($v == $parent) 
                $a[$k] =1;
        }
    }

    if ($n > 0)
        return $a;
    else
        return null;
}

/* Given a hash of area types, returns a human readable description
of that list. In some cases, summarises types like all councillors as
"councillors" etc. */
function fyr_describe_area_type_list($area_types) {
    $ret = array();
    $area_types = array_keys($area_types);
    global $va_aliases;

    // Most aggregate types
    $ordered_descs_array = array(
        'Councillors' => $va_aliases['council'],
        '<acronym title="Member of Parliament">MP</acronym>' => array('WMC'),
        '<acronym title="Members of the European Parliament">MEPs</acronym>' => array('EUR'),
        '<acronym title="Members of the Scottish Parliament">MSPs</acronym>' => array('SPE', 'SPC'),
    );
    foreach ($ordered_descs_array as $k => $v) {
        if (count(array_diff($v, $area_types)) == 0) {
            $ret[] = $k;
            $area_types = array_diff($area_types, $v);
        }
    }

    // Assembly members
    $am = array();
    $am_descs_array = array(
        'Northern Ireland' => array('NIE'),
        'Welsh' => array('WAC', 'WAE'),
        'London' => array('LAC', 'LAE'),
    );
    foreach ($am_descs_array as $k => $v) {
        if (count(array_diff($v, $area_types)) == 0) {
            $am[] = $k;
            $area_types = array_diff($area_types, $v);
        }
    }
    if (count($am) > 0) {
        $am_str = join(", ", array_slice($am, 0, -1));
        if (count($am) > 1)
            $am_str .= " and ";
        $am_str .= $am[count($am) - 1];
        $am_str .= ' <acronym title="Assembly Members">AMs</acronym>';
        $ret[] = $am_str;
    }

    // Others left over
    global $va_precise_names;
    foreach ($area_types as $type) {
        $ret[] = $va_precise_names[$type];
    }

    if (count($ret) > 0) {
        $ret_str = join(", ", array_slice($ret, 0, -1));
        if (count($ret) > 1)
            $ret_str .= ", or ";
        $ret_str .= $ret[count($ret) - 1];
    }
    return $ret_str;
}

/* fyr_breadcrumbs NUMBER [TYPE]
 * Numbered "breadcrumbs" trail for current user; NUMBER is the (1-based)
 * number of the step to hilight. TYPE can be 'default' or 'lords'. */
function fyr_breadcrumbs($num, $type = 'default') {
    if ($type == 'default') {
        $steps = array(
                    'Enter postcode',
                    'Pick representative',
                    'Write message',
                    'Check message',
                    'Confirm email'
                );
    } elseif ($type == 'lords') {
        $steps = array(
                    'Choose a Lord',
                    'Write message',
                    'Check message',
                    'Confirm email'
                );
    }
    /* Ideally we'd like the numbers to appear as a result of this being a
     * list, but that's beyond CSS's tiny capabilities, so put them in
     * explicitly. That means that two numbers will appear in non-CSS
     * browsers. */
    $str = '<ol id="breadcrumbs">';
    for ($i = 0; $i < sizeof($steps); ++$i) {
        if ($i == $num - 1)
            $str .= "<li class=\"hilight\"><em>";
        else
            $str .= "<li>";
        $str .= '<!--[if lte IE 6]>' . ($i+1) . '. <![endif]-->';
        $str .= htmlspecialchars($steps[$i]);
        if ($i == $num - 1)
            $str .= "</em>";
        $str .= "</li>";
    }
    $str .= "</ol>";
    return $str;
}


function fyr_display_advert($values) {
    global $track;
    $advert_shown = 'none';
    if (array_key_exists('sender_email', $values))
        $advert_shown = crosssell_display_advert('wtt', $values['sender_email'], $values['sender_name'], $values['sender_postcode'],
            array(
                array('twfy_alerts', '<h2>Get emailed every time your MP says something in Parliament</h2> <p style="font-size:150%">Keep an eye on them for free</p> [form]Sign me up![/form]'),
                array('twfy_alerts', '<h2>Get emailed every time your MP says something in Parliament</h2> [button]Keep an eye on them for free![/button]'),
                array('hfymp', '<h2>Have a long term relationship with your MP</h2>[form]Sign up to HearFromYourMP[/form]'),
                array('hfymp', '<h2 style="margin-bottom:0">Get email from your MP in the future</h2> <p style="font-size:120%;margin-top:0;">and have a chance to discuss what they say in a public forum [form]Sign up to HearFromYourMP[/form]'),
                array('gny', '<h2>Help us build a map of the world&rsquo;s local communities &ndash;<br><a href="http://www.groupsnearyou.com/">Add one to GroupsNearYou</a></h2>'),
                array('gny', '<h2>Are you a member of a local group&hellip;</h2> &hellip;which uses the internet to coordinate itself, such as a neighbourhood watch? If so, please help the charity that runs WriteToThem by <a href="http://www.groupsnearyou.com/">adding some information about it</a> to our new site, GroupsNearYou.'),
                array('fms', 'Got a local problem like potholes or flytipping in your street?<br><a href="http://www.fixmystreet.com/">Report it at FixMyStreet</a>'),
                array('fms', '<a href="http://www.fixmystreet.com/">Find out what problems people are reporting in your local area</a>'),
            )
        );
    $track = 'advert=' . $advert_shown;
}

function parse_date($date) {
        $now = time();
        $date = preg_replace('#\b([a-z]|on|an|of|in|the|year of our lord)\b#i','',$date);
        if (!$date)
                return null;

        $epoch = 0;
        $day = null;
        $year = null;
        $month = null;
        if (preg_match('#(\d+)/(\d+)/(\d+)#',$date,$m)) {
                $day = $m[1]; $month = $m[2]; $year = $m[3];
                if ($year<100) $year += 2000;
        } elseif (preg_match('#(\d+)/(\d+)#',$date,$m)) {
                $day = $m[1]; $month = $m[2]; $year = date('Y');
        } elseif (preg_match('#^([0123][0-9])([01][0-9])([0-9][0-9])$#',$date,$m)) {
                $day = $m[1]; $month = $m[2]; $year = $m[3];
        } else {
                $dayofweek = date('w'); # 0 Sunday, 6 Saturday
                        if (preg_match('#next\s+(sun|sunday|mon|monday|tue|tues|tuesday|wed|wednes|wednesday|thu|thur|thurs|thursday|fri|friday|sat|saturday)\b#i',$date,$m)) {
                                $date = preg_replace('#next#i','this',$date);
                                if ($dayofweek == 5) {
                                        $now = strtotime('3 days', $now);
                                } elseif ($dayofweek == 4) {
                                        $now = strtotime('4 days', $now);
                                } else {
                                        $now = strtotime('5 days', $now);
                                }
                        }
                $t = strtotime($date,$now);
                if ($t != -1) {
                        $day = date('d',$t); $month = date('m',$t); $year = date('Y',$t); $epoch = $t;
                }
        }
        if (!$epoch && $day && $month && $year) {
                $t = mktime(0,0,0,$month,$day,$year);
                $day = date('d',$t); $month = date('m',$t); $year = date('Y',$t); $epoch = $t;
        }

        if ($epoch == 0)
                return null;
        return array('iso'=>"$year-$month-$day", 'epoch'=>$epoch, 'day'=>$day, 'month'=>$month, 'year'=>$year);
}

# Special case where MEPs of a party have divided up the region between them
function euro_check(&$area_reps, $wmc) {
    if (!isset($area_reps[11809]) && !isset($area_reps[11814]) && !isset($area_reps[11811])) 
        return;

    if (isset($area_reps[11814])) {
        # South West Conservative MEPs
        $area_id = 11814;
        $meps = array(
            1048 => array( # Parish
                12946,12947,13230,13258,13363,13458,13003,13303, # Dorset
                12960,13356,13408,13449,13485,                   # Somerset
                12908,13491,13476,                               # Somerset (were in Avon - Bath, Weston, Woodspring)
                12964,12965,12966,12967,                         # Bristol - but Bristol NW includes bits of S.Glos, e.g. Filton
                13178,                                           # Kingswood - S.Glos + part of Bristol
                13256,                                           # Northavon - S.Glos but includes outskirts of Bristol
                13438                                            # Wansdyke - most in B&NES, but a bit in S.Glos
            ),
            1064 => array( # Jackson
                13041,13270,13371,13280,13336,13452, # Wiltshire
                12996,13020,13096,13112,13394,13411, # Gloucestershire
                12964,12965,12966,12967,             # Bristol
                13178,                               # Kingswood
                13256,                               # Northavon
                13438                                # Wansdyke
            ),
            1085 => array( # Chichester
                13062,13257,13300,13499,13493,13087,13501,13409,13414,13417,13419, # Devon
                13090,13494,13498,13503,13500                                      # Cornwall
            )
        );
    } elseif (isset($area_reps[11809])) {
        # West Midland Conservative MEPs
        $area_id = 11809;
        $meps = array(
            1071 => array( # Harbour
                # Birmingham
                12920,12921,12922,12923,12924,12925,12926,12927,12928,12929,
                # Hereford, Leominster, Ludlow, Meriden, Newcastle, Solihull, Stoke, Sutton Coldfield
                13147,13191,13209,13224,13245,13355,13386,13387,13388,13403
            ),
            1088 => array( # Bushill-Matthews
                # Aldridge, Bromsgrove, Coventry, Nuneaton, Redditch, Rugby, Stratford, Walsall
                12889,12969,13021,13022,13023,13285,13313,13328,13391,13434,13435,
                # Warley, Warwick, Warwichshire, West Bromwich, Worcester, Worcestershire, Wyre Forest
                13440,13443,13274,13454,13455,13477,13233,13463,13483
            ),
            1089 => array( # Bradbourn
                # Burton, Cannock Chase, Dudley, Halesowen, Lichfield, Shrewsbury, Shropshire, Staffs
                12974,12982,13047,13048,13124,13197,13350,13268,13369,13379,13378,
                # Stone, Stourbridge, Tamworth, Telford, Wolverhampton, Wrekin
                13389,13390,13406,13410,13473,13474,13475,13412
            )
        );
    } elseif (isset($area_reps[11811])) {
        $area_id = 11811;
        $meps = array(
            1072 => array( # Hannan
                # Arundel & South Downs, Bognor Regis & Littlehampton, Brighton Kemptown, Brighton Pavilion, Hove, Chichester, Eastbourne, Lewes, Mid-Sussex, E Worthing & Shoreham, W Worthing Gosport, E Hants, NE Hants, NW Hants, Havant, New Forest E, New Forest W, Portsmouth N, Portsmouth S, Romsey, Winchester, Isle of Wight (23)
                12894, 12938, 12962, 12963, 12999, 13059, 13159,
                13192, 13232, 13067, 13480, 13113, 13063, 13263,
                13277, 13140, 13251, 13252, 13305, 13495, 13323, 13466, 13496
            ),
            1079 => array( # Elles
                # Bucks, Berks, Oxon (21)
                12898, 12899, 12911, 12948, 12972, 12997, 13146,
                13213, 13234, 13265, 13292, 13293, 13244, 13310,
                13311, 13354, 13439, 13467, 13470, 13471, 13482
            ),
            1082 => array( # Deva
                # Surrey, Horsham, Crawley, Aldershot, Basingstoke, Eastleigh, Fareham, Southampton Itchen, Southampton Test (19)
                12888, 12906, 13024, 13060, 13066, 13083, 13086,
                13091, 13121, 13157, 13236, 13315, 13330, 13357,
                13358, 13376, 13377, 13401, 13472
            ),
            1101 => array( # Ashworth
                # Kent, Bexhill & Battle, Wealden, Hastings & Rye. (20)
                12896, 12916, 12983, 12994, 13035, 13046, 13092,
                13095, 13101, 13116, 13139, 13214, 13222, 13272,
                13341, 13351, 13372, 13415, 13421, 13446
            ),
        );
    }
    $keep = array();
    foreach ($meps as $id => $wmcs) {
        if (in_array($wmc, $wmcs))
            $keep[$id] = 1;
    }
    foreach ($area_reps[$area_id] as $k => $v) {
        if (!isset($keep[$v]) && in_array($v, array_keys($meps)))
            unset($area_reps[$area_id][$k]);
    }
}

?>
