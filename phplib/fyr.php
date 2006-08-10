<?php
/*
 * fyr.php:
 * General purpose functions specific to FYR.
 * 
 * Copyright (c) 2004 UK Citizens Online Democracy. All rights reserved.
 * Email: francis@mysociety.org; WWW: http://www.mysociety.org
 *
 * $Id: fyr.php,v 1.43 2006-08-10 09:35:20 francis Exp $
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

    $aliases = array(
            /* Councillors of whatever sort */
            'council' => 'DIW,CED,LBW,COP,LGE,MTW,UTE,UTW',
            /* MPs */
            'westminstermp' => 'WMC',
            /* Devolved assembly members / MSPs */
            'regionalmp' => 'SPC,SPE,WAC,WAE,LAC,LAE,NIE',
            /* MEPs */
            'mep' => 'EUR'
        );
    if (array_key_exists($types, $aliases))
        $types = $aliases[$types];
   
    $a = array();
    $n = 0;
    foreach (explode(',', $types) as $t) {
        if (strlen($t) != 3
            /* Parent types, which we suppress here. */
            || preg_match('/^(LBO|LAS|LGD|CTY|DIS|UTA|MTD|COI|SPA|WAS|NIA|WMP|EUP)$/', $t))
            continue;
        $a[$t] = 1;
        ++$n;
    }

    if ($n > 0)
        return $a;
    else
        return null;
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
        $str .= ($i + 1) . ". " . htmlspecialchars($steps[$i]);
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

?>
