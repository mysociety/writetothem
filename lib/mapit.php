<?php
/*
 * mapit.php:
 * Interact with MapIt.
 * 
 * Copyright (c) 2004 Chris Lightfoot. All rights reserved.
 * Email: chris@ex-parrot.com; WWW: http://www.ex-parrot.com/~chris/
 *
 * $Id: mapit.php,v 1.8 2004-10-05 20:35:51 francis Exp $
 * 
 */

include_once('votingarea.php');

/* Error codes */
define('MAPIT_BAD_POSTCODE', 1);    /* not in the format of a postcode */
define('MAPIT_POSTCODE_NOT_FOUND', 2);       /* postcode not found */
define('MAPIT_VOTING_AREA_NOT_FOUND', 3);    /* not a valid voting area id */

/* mapit_get_voting_areas POSTCODE
 * On success, return an array mapping voting/administrative area type to
 * voting area ID and name. On failure, return an error code. */
function mapit_get_voting_areas($postcode) {
    global $va_name;

    /* remove spaces */
    $postcode = strtoupper(preg_replace('/ +/', '', $postcode, -1));

    if (!preg_match('/[A-Z]{1,2}[0-9]{1,4}[A-Z]{1,2}/', $postcode)) {
        debug("MAPIT", "Badly formed postcode $postcode");
        return MAPIT_BAD_POSTCODE;
    } else if ($postcode == 'CB41XP') {
        $ret = array(VA_CTY => 1,
                     VA_CED => 2,
                     VA_DIS => 3,
                     VA_DIW => 4,
                     VA_WMP => 5,
                     VA_WMC => 6,
                     VA_EUP => 7,
                     VA_EUR => 8,
                );
        debug("MAPIT", "Looked up postcode $postcode");
        debug("MAPITRESULT", "Results:", $ret);
        return $ret;
    } else {
        debug("MAPIT", "Postcode not found $postcode");
        return MAPIT_POSTCODE_NOT_FOUND;
    }
}

function mapit_get_voting_area_info($va_id) {
    $stub_data = array(1 => array(VA_CTY, 'Cambridgeshire County Council'),
                 2 => array(VA_CED, 'West Chesterton ED'),
                 3 => array(VA_DIS, 'Cambridge District Council'),
                 4 => array(VA_DIW, 'West Chesterton Ward'),
                 5 => array(VA_WMP, $va_name[VA_WMP]),
                 6 => array(VA_WMC, 'Cambridge'),
                 7 => array(VA_EUP, $va_name[VA_EUP]),
                 8 => array(VA_EUR, 'Eastern Euro Region'));
    $ret = $stub_data[$va_id];
    if (!isset($ret)) {
        debug("MAPIT", "Voting area not found id $va_id");
        return MAPIT_VOTING_AREA_NOT_FOUND;
    }
    debug("MAPIT", "Looked up voting area info $va_id");
    debug("MAPITRESULT", "Results:", $ret);
    return $ret; 
}

?>
