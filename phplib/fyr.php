<?php
/*
 * fyr.php:
 * General purpose functions specific to FYR.
 * 
 * Copyright (c) 2004 UK Citizens Online Democracy. All rights reserved.
 * Email: francis@mysociety.org; WWW: http://www.mysociety.org
 *
 * $Id: fyr.php,v 1.10 2004-12-17 16:45:50 chris Exp $
 * 
 */

// Set location of configuration file in MYSOCIETY_FYR_CONFIG_FILE
require_once "../conf/general";

require_once "../../phplib/utility.php";
require_once "../../phplib/ratty.php";
require_once "../../phplib/template.php";

template_set_style("../templates/style-fymp");

/* fyr_rate_limit IMPORTANT
 * Invoke the rate limiter with the given IMPORTANT variables (e.g. postcode,
 * representative ID, etc.), as well as the script's URL and the calling IP
 * address, and return an error page if the request trips a rate limit;
 * otherwise do nothing. */
function fyr_rate_limit($important_vars) {
    $important_vars['IPADDR'] = $_SERVER['REMOTE_ADDR'];
    $important_vars['SERVER'] = $_SERVER['SERVER_NAME'];
    $important_vars['PAGE'] = $_SERVER['SCRIPT_NAME'];
    $important_vars['SITE'] = 'fyr';

    $ret = ratty_test($important_vars);
    if (isset($ret)) {
        list($rule, $error_message) = $ret;
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
    return preg_match('#^http://([a-z0-9-]+\.)*\Q' . OPTION_WEB_DOMAIN . '\E/#i', $r) ? 1 : 0;
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
    $r = $_SERVER['HTTP_REFERER'];
    if (isset($r) && preg_match('#^(https?://|news:)#', $r) && !fyr_is_internal_url($r))
        return $r;
    else
        return null;
}

?>
