<?php
/*
 * mapit.php:
 * Interact with MapIt.
 * 
 * Copyright (c) 2004 Chris Lightfoot. All rights reserved.
 * Email: chris@ex-parrot.com; WWW: http://www.ex-parrot.com/~chris/
 *
 * $Id: mapit.php,v 1.11 2004-10-06 11:31:45 chris Exp $
 * 
 */

include_once('votingarea.php');

/* Error codes */
define('MAPIT_BAD_POSTCODE', 1);        /* not in the format of a postcode */
define('MAPIT_POSTCODE_NOT_FOUND', 2);  /* postcode not found */
define('MAPIT_AREA_NOT_FOUND', 3);      /* not a valid voting area id */

$mapit_error_strings = array(
    MAPIT_BAD_POSTCODE          => 'Not in the correct format for a postcode',
    MAPIT_POSTCODE_NOT_FOUND    => 'Postcode not found',
    MAPIT_AREA_NOT_FOUND        => 'Area not found'
);

/* mapit_is_error R
 * Does R (the return value from another MaPit function) indicate an error? */
function mapit_is_error($e) {
    return is_integer($e);
}

/* mapit_strerror CODE
 * Return a human-readable string describing CODE. */
function mapit_strerror($e) {
    if (!is_integer($e))
        return "Success";
    else if (!array_key_exists($e, $dadem_error_strings))
        return "Unknown MaPit error";
    else
        return $dadem_error_strings($e);
}

/* mapit_get_error R
 * Return FALSE if R indicates success, or an error string otherwise. */
function mapit_get_error($e) {
    if (is_array($e))
        return FALSE;
    else
        return mapit_strerror($e);
}

/* mapit_get_voting_areas POSTCODE
 * On success, return an array mapping voting/administrative area type to
 * voting area ID. On failure, returns an error code. */
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

/* mapit_get_voting_area_info ID
 * On success, returns an array giving information about the
 * voting/administrative area ID. This array contains elements type, the type
 * of the area (e.g. "VA_CTY"); and name, the name of the area (e.g., "Norfolk
 * County Council"). On failure, returns an error code. */
function mapit_get_voting_area_info($va_id) {
    $stub_data = array(1 => array('type' => VA_CTY, 'name' => 'Cambridgeshire County Council'),
                 2 => array('type' => VA_CED, 'name' => 'West Chesterton ED'),
                 3 => array('type' => VA_DIS, 'name' => 'Cambridge District Council'),
                 4 => array('type' => VA_DIW, 'name' => 'West Chesterton Ward'),
                 5 => array('type' => VA_WMP, 'name' => $va_name[VA_WMP]),
                 6 => array('type' => VA_WMC, 'name' => 'Cambridge'),
                 7 => array('type' => VA_EUP, 'name' => $va_name[VA_EUP]),
                 8 => array('type' => VA_EUR, 'name' => 'Eastern Euro Region'));
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
