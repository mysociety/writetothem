<?php
/*
 * dadem.php:
 * Interact with DaDem. Roughly speaking, look up representatives in
 * office for a voting area.
 * 
 * Copyright (c) 2004 UK Citizens Online Democracy. All rights reserved.
 * Email: chris@mysociety.org; WWW: http://www.mysociety.org
 *
 * $Id: dadem.php,v 1.22 2004-10-08 14:05:04 chris Exp $
 * 
 */

include_once('votingarea.php');
include_once('simplexmlrpc.php');
include_once('utility.php');
include_once('../conf/config.php');

/* Error codes */
define('DADEM_UNKNOWN_AREA', 1);        /* unknown area */
define('DADEM_REP_NOT_FOUND', 2);       /* unknown representative id */
define('DADEM_AREA_WITHOUT_REPS', 3);   /* not an area for which representatives are returned */

$dadem_error_strings = array(
    DADEM_UNKNOWN_AREA      =>  'Unknown voting area',
    DADEM_REP_NOT_FOUND     =>  'Representative not found',
    DADEM_AREA_WITHOUT_REPS =>  'Not an area type for which representatives are returned'
);

define('DADEM_CONTACT_FAX', 101);
define('DADEM_CONTACT_EMAIL', 102);

/* dadem_is_error R
 * Does R (the return value from another DaDem function) indicate an error? */
function dadem_is_error($e) {
    return is_integer($e);
}

/* dadem_strerror CODE
 * Return a human-readable string describing CODE. */
function dadem_strerror($e) {
    global $dadem_error_strings;
    if (!is_integer($e))
        return "Success";
    else if (!array_key_exists($e, $dadem_error_strings))
        return "Unknown DaDem error";
    else
        return $dadem_error_strings[$e];
}

/* dadem_get_error R
 * Return FALSE if R indicates success, or an error string otherwise. */
function dadem_get_error($e) {
    if (is_array($e))
        return FALSE;
    else
        return dadem_strerror($e);
}

/* dadem_get_representatives VOTING_AREA_ID
 * Return an array of IDs for the representatives for the given voting
 * area on success, or an error code on failure. */
function dadem_get_representatives($va_id) {
    debug("DADEM", "Looking up representatives for voting area id $va_id");
    return sxr_call(OPTION_DADEM_HOST, OPTION_DADEM_PORT, OPTION_DADEM_PATH, 'DaDem.get_representatives', array($va_id));
}

/* dadem_get_representative_info ID
 * On success, returns an array giving information about the representative
 * with the given ID. This array contains elements type, the type of the area
 * for which they're elected (and hence what type of representative they are);
 * name, their name; contact_method, either 'fax' or 'email', and either an
 * element 'email' or 'fax' giving their address or number respectively. 
 * voting_area, the id of the voting area they represent.
 * On failure, returns an error code. */
function dadem_get_representative_info($rep_id) {
    debug("DADEM", "Looking up info on representative id $rep_id");
    return sxr_call(OPTION_DADEM_HOST, OPTION_DADEM_PORT, OPTION_DADEM_PATH, 'DaDem.get_representative_info', array($rep_id));
}

?>
