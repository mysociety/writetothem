<?php
/*
 * fyr.php:
 * General purpose functions specific to FYR.
 * 
 * Copyright (c) 2004 UK Citizens Online Democracy. All rights reserved.
 * Email: francis@mysociety.org; WWW: http://www.mysociety.org
 *
 * $Id: fyr.php,v 1.48 2007-08-22 20:08:55 matthew Exp $
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
            href=\"mailto:team@writetothem.com\">email us</a> to let us know.");
    }
}

err_set_handler_display('fyr_display_error');

/* fyr_rate_limit IMPORTANT
 * Invoke the rate limiter with the given IMPORTANT variables (e.g. postcode,
 * representative ID, etc.), as well as the script's URL and the calling IP
 * address, and return an error page if the request trips a rate limit;
 * otherwise do nothing. */
function fyr_rate_limit($important_vars) {
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
        $advert_shown = crosssell_display_advert('wtt', $values['sender_email'], $values['sender_name'], $values['sender_postcode']);
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

# Special case West Midlands Conservative MEPs
function euro_check(&$area_reps, $wmc) {
    if (!isset($area_reps[11809])) return;
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
    foreach ($meps as $id => $wmcs) {
        if (in_array($wmc, $wmcs)) {
	    foreach ($area_reps[11809] as $k => $v) {
	        if ($v != $id && in_array($v, array_keys($meps)))
		    unset($area_reps[11809][$k]);
	    }
	    break;
	}
    }
}

?>
