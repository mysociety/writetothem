<?php
/*
 * fyr.php:
 * General purpose functions specific to FYR.
 * 
 * Copyright (c) 2004 UK Citizens Online Democracy. All rights reserved.
 * Email: francis@mysociety.org; WWW: http://www.mysociety.org
 *
 * $Id: fyr.php,v 1.8 2004-11-22 11:24:09 francis Exp $
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

    global $fyr_error_message;
    $ret = ratty_test($important_vars);
    if (isset($ret)) {
        list($rule, $fyr_error_message) = $ret;
        if ($fyr_error_message == "") {
            $fyr_error_message = "Sorry, we are experiencing technical difficulties.  Please try again later.";
        }
        $fyr_error_message .= "\n<!-- ratty the rate limiter rule #$rule limit exceeded -->\n";
        template_show_error();
    }
    print_r($fyr_error_message);
}

?>
