<?php
/*
 * Interact with MapIt.  Roughly speaking, postcode lookup of voting
 * areas.
 * 
 * Copyright (c) 2004 UK Citizens Online Democracy. All rights reserved.
 * Email: chris@mysociety.org; WWW: http://www.mysociety.org
 *
 * $Id: mapit.php,v 1.14 2004-10-14 14:14:24 chris Exp $
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
    global $mapit_error_strings;
    if (!is_integer($e))
        return "Success";
    else if (!array_key_exists($e, $mapit_error_strings))
        return "Unknown MaPit error";
    else
        return $mapit_error_strings[$e];
}

/* mapit_get_error R
 * Return FALSE if R indicates success, or an error string otherwise. */
function mapit_get_error($e) {
    if (is_string($e))
        print "Shouldn't have string here: $e";
    if (is_array($e))
        return FALSE;
    else
        return mapit_strerror($e);
}

/* mapit_get_voting_areas POSTCODE
 * On success, return an array mapping voting/administrative area type to
 * voting area ID. On failure, returns an error code. */
function mapit_get_voting_areas($postcode) {
    debug("MAPIT", "Looking up areas for postcode $postcode");
    return sxr_call(OPTION_MAPIT_HOST, OPTION_MAPIT_PORT, OPTION_MAPIT_PATH, 'MaPit.get_voting_areas', array($postcode));
}

/* mapit_get_voting_area_info ID
 * On success, returns an array giving information about the
 * voting/administrative area ID. This array contains elements type, the type
 * of the area (e.g. "VA_CTY"); and name, the name of the area (e.g., "Norfolk
 * County Council"). On failure, returns an error code. */
function mapit_get_voting_area_info($va_id) {
    debug("MAPIT", "Looking up info on area $va_id");
    return sxr_call(OPTION_MAPIT_HOST, OPTION_MAPIT_PORT, OPTION_MAPIT_PATH, 'MaPit.get_voting_area_info', array($va_id));
}

?>
