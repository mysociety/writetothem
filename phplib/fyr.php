<?php
/*
 * General purpose functions specific to FYR.
 * 
 * Copyright (c) 2004 UK Citizens Online Democracy. All rights reserved.
 * Email: francis@mysociety.org; WWW: http://www.mysociety.org
 *
 * $Id: fyr.php,v 1.1 2004-10-28 10:53:19 francis Exp $
 * 
 */

require_once "../conf/config.php";
require_once "../../phplib/ratty.php";
require_once "../../phplib/utility.php";

function fyr_rate_limit($important_vars) {
    if (!ratty_test($important_vars)) {
        $fyr_error_message = "Please limit your use of this website.";
        include "templates/generalerror.html";
        exit;
    }
}

?>
