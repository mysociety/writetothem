<?php
/*
 * fyr.php:
 * General purpose functions specific to FYR.
 *
 * Copyright (c) 2004 UK Citizens Online Democracy. All rights reserved.
 * Email: francis@mysociety.org; WWW: http://www.mysociety.org
 *
 * $Id: fyr.php,v 1.77 2009-12-11 16:52:30 matthew Exp $
 *
 */

// Load configuration file
$dir = dirname(__FILE__);
require_once $dir . "/../conf/general";

require_once $dir . "/../commonlib/phplib/error.php";
require_once $dir . "/../commonlib/phplib/ratty.php";
require_once $dir . "/../commonlib/phplib/template.php";
require_once $dir . "/../commonlib/phplib/utility.php";
require_once $dir . "/../commonlib/phplib/auth.php";
require_once $dir . "/../commonlib/phplib/crosssell.php";
require_once $dir . "/../commonlib/phplib/votingarea.php";
require_once $dir . "/../phplib/cobrand.php";

// Types which require no postcode authentication (e.g. House of Lords)
$postcodeless_child_types = array('HOC');

/* Output buffering: PHP's output buffering is broken, because it does not
 * affect headers. However, it's worth using it anyway, because in the common
 * case of outputting an HTML page, it allows us to clear any output and
 * display a clean error page when something goes wrong. Obviously if we're
 * displaying an error, a redirect, an image or anything else this will break
 * horribly.*/
ob_start();

template_set_style($dir . "/../templates/website");

# syndication type, read from domain name
global $cobrand;
$cobrand = null;
if (array_key_exists('HTTP_HOST', $_SERVER)) {
    $host_parts = explode('.', $_SERVER['HTTP_HOST'], 2);
    if ($host_parts[1] == OPTION_WEB_DOMAIN && $host_parts[0] != 'www') {
        $cobrand = $host_parts[0];
    }
}

if (is_dir("../templates/$cobrand")) {
    template_set_style("../templates/$cobrand", true);
}

global $cocode;
$cocode = get_http_var('cocode');
if ($cobrand) {
    if (!cobrand_cocode_allowed($cobrand, $cocode)) {
        $cocode = '';
    }
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
            href='/about-contact'>contact us</a> to let us know.");
    }
}

err_set_handler_display('fyr_display_error');

/* fyr_get_host
 * Return the host from which a page was requested, or an
 * empty string if the host is not available
 */
function fyr_get_host() {
    $host = '';
    if (array_key_exists('HTTP_HOST', $_SERVER)) {
         $host = $_SERVER['HTTP_HOST'];
     }
     return $host;
}

/* fyr_format_message_body_for_preview MESSAGE_BODY
 * Format a message body for HTML preview - handle leading spaces,
 * add HTML linebreaks and convert special characters to entities. */
function fyr_format_message_body_for_preview($message_body) {
  /* Horrid. We need to turn leading spaces into non-breaking spaces, so
   * that indentation appears roughly the same in the preview as it will
   * in the final fax. So we need to delve into the exciting world of
   * PHP's preg_replace. Because the text will get escaped for HTML
   * entities later, present those leading spaces as U+0000A0 NO-BREAK
   * SPACE. But we can't use the obvious combination of preg_replace, the
   * "e modifier" and str_repeat, because preg_replace with the "e
   * modifier" is not safe, since the subexpressions are injected into
   * the expression by textual substitution(!). So instead we perform
   * repeated substitutions until there are no further changes. This is
   * a complete pain, but then that's what you get for using a language
   * with a rubbish API and no functional features. */
    $original_body = null;
    do {
        $original_body = $message_body;
        $message_body = preg_replace('/^((?: )*)( )/m', '\1 ', $original_body);
    } while ($message_body != $original_body);
    $message_body = str_replace("\n", "<br>\n", htmlspecialchars($message_body));
    return $message_body;
}

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
    if (preg_match('#^https?://([a-z0-9-]+\.)*\Q' . OPTION_WEB_DOMAIN .  '\E/#i', $url)) {
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
        '<abbr title="Member of Parliament">MP</abbr>' => array('WMC'),
        '<abbr title="Members of the European Parliament">MEPs</abbr>' => array('EUR'),
        '<abbr title="Members of the Scottish Parliament">MSPs</abbr>' => array('SPE', 'SPC'),
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
        $am_str .= ' <abbr title="Assembly Members">AMs</abbr>';
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
                    'Choose representative',
                    'Write message',
                    'Send message'
                );
    } elseif ($type == 'lords') {
        $steps = array(
                    'Choose a Lord',
                    'Write message',
                    'Send message'
                );
    }
    /* Ideally we'd like the numbers to appear as a result of this being a
     * list, but that's beyond CSS's tiny capabilities, so put them in
     * explicitly. That means that two numbers will appear in non-CSS
     * browsers. */
    $str = '<ol class="small-block-grid-' . count($steps) . '">';
    for ($i = 0; $i < sizeof($steps); ++$i) {
        if ($i < $num - 1)
            $str .= "<li class=\"done\">";
        else if ($i == $num - 1)
            $str .= "<li class=\"current\">";
        else
            $str .= "<li>";
        $str .= htmlspecialchars($steps[$i]);
        $str .= "</li>";
    }
    $str .= "</ol>";
    return $str;
}


function fyr_display_advert($values) {
    $advert_shown = 'none';
    if (array_key_exists('sender_email', $values)) {
        $adverts = array(
                #array('twfy_alerts0', '<h2>Get emailed every time your MP says something in Parliament</h2> <p style="font-size:150%">Keep an eye on them for free</p> [form]Sign me up![/form]'),
                #array('twfy_alerts2', '<h2>Wonder what your MP says in Parliament?</h2> <p>Sign up to get emailed when they speak &ndash; find out what they&rsquo;re saying on your behalf!</p> [form]Sign me up![/form]'),
                #array('hfymp1', '<h2 style="margin-bottom:0">Get email from your MP in the future</h2> <p style="font-size:120%;margin-top:0;">and have a chance to discuss what they say in a public forum [button]Sign up to HearFromYourMP[/button]'),
                array('hfymp2', '<h2 style="margin-bottom:0">Sign up to hear from your MP about local issues</h2> <p style="font-size:120%;margin-top:0;">and to discuss them with other constituents [form]Sign up to HearFromYourMP[/form]'),
                #array('gny0', '<h2>Help us build a map of the world&rsquo;s local communities &ndash;<br><a href="http://www.groupsnearyou.com/add/about/">Add one to GroupsNearYou</a></h2>'),
                #array('gny1', '<h2>Are you a member of a local group&hellip;</h2> &hellip;which uses the internet to coordinate itself, such as a neighbourhood watch? If so, please help the charity that runs WriteToThem by <a href="http://www.groupsnearyou.com/add/about/">adding some information about it</a> to our new site, GroupsNearYou.'),
                #array('fms0', 'Got a local problem like potholes or flytipping in your street?<br><a href="http://www.fixmystreet.com/">Report it at FixMyStreet</a>'),
                #array('fms1', '<a href="http://www.fixmystreet.com/">Find out what problems people are reporting in your local area</a>'),
                #array('demclub0', '<h2 style="margin-bottom:0">Help make the next election the most accountable ever</h2> <p style="font-size:120%;margin-top:0.5em;text-align:center;"><a href="http://www.democracyclub.org.uk/">Join Democracy Club</a> and have fun keeping an eye on your election candidates. <a href="http://www.democracyclub.org.uk/">Sign me up</a>!'),
        );
        if (isset($values['advert'])) {
            $newads = array();
            foreach ($adverts as $ad) { if ($ad[0] == $values['advert']) $newads[] = $ad; }
            $adverts = $newads;
        }
        $advert_shown = crosssell_display_advert('wtt', $values['sender_email'], $values['sender_name'], $values['sender_postcode'], $adverts);
    }
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
function euro_check(&$area_reps, $vas) {
    if (!isset($area_reps[11811]) && !isset($area_reps[11814]))
        return array();

    if (isset($area_reps[11814])) { # South West Conservative MEPs
        $area_id = 11814;
        $meps = array(
            1085 => array( # Chichester
                2249, 2250, 2658, 2617, 2251 # Devon, Cornwall, Plymouth, Torbay, Isles of Scilly
            ),
            45630 => array( # Girling
                2226, 2239, 2608, 2642, 2551 # Gloucestershire, Somerset, S Gloucestershire, N Somerset, B&NES
            ),
            45632 => array( # Fox
                2561, 2245, 2222, 2612, 2555, 2594 # Bristol, Wiltshire, Dorset, Swindon, Bournemouth, Poole
            ),
        );
    } elseif (isset($area_reps[11811])) { # South East Conservative MEPs
        $area_id = 11811;
        $meps = array(
            1072 => array( # Hannan
                # East Sussex: Brighton Kemptown, Brighton Pavilion, Eastbourne, Hove, Lewes (5)
                # West Sussex: Arundel & South Downs, Bognor Regis & Littlehampton, Chichester, E Worthing & Shoreham, Mid Sussex, Worthing West (6)
                # Hampshire: Gosport, E Hants, NE Hants, NW Hants, Havant, New Forest E, New Forest W, Portsmouth N, Portsmouth S, Romsey and Southampton North, Winchester, Isle of Wight (12)
                65844, 65787, 65714, 65691, 66020,
                65784, 65841, 65558, 65780, 65999, 65562,
                65569, 65928, 65556, 65815, 65699, 65729, 65894, 65566, 66014, 65884, 65921, 65791
            ),
            1079 => array( # Elles
                # Bucks, Berks, Oxon (7, 8, 6)
                65739, 65687, 65909, 65801, 65953, 66076, 66010,
                65697, 65901, 65862, 65973, 65982, 65680, 65552, 65774,
                66008, 65786, 66060, 65564, 65638, 65622
            ),
            1082 => array( # Deva
                # Surrey (11)
                # West Sussex: Horsham, Crawley (2)
                # Hampshire: Aldershot, Basingstoke, Eastleigh, Fareham, Southampton Itchen, Southampton Test (6)
                65856, 65803, 66062, 65838, 65693, 66005, 65589, 65678, 65942, 65747, 66039,
                65781, 65717,
                65730, 65623, 65881, 65857, 66016, 65580
            ),
            1101 => array( # Ashworth
                # Kent (17)
                # East Sussex: Bexhill & Battle, Wealden, Hastings & Rye (3)
                65811, 65878, 66075, 66011, 65555, 65764, 65779, 65864, 65944, 65936, 65605, 66043, 65698, 65610, 65829, 65744, 65660,
                65845, 65640, 65961,
            ),
        );
    }
    $keep = array();
    foreach ($meps as $id => $areas) {
        foreach ($vas as $va) {
            if (in_array($va, $areas)) {
                $keep[$id] = 1;
                break;
            }
        }
    }

    $hidden = array();
    foreach ($area_reps[$area_id] as $k => $v) {
        if (!isset($keep[$v]) && in_array($v, array_keys($meps))) {
            unset($area_reps[$area_id][$k]);
            $hidden[] = $v;
        }
    }
    return $hidden;
}

