<?php
/*
 * dadem.php:
 * Interact with DaDem. Roughly speaking, look up representatives in
 * office for a voting area.
 * 
 * Copyright (c) 2004 UK Citizens Online Democracy. All rights reserved.
 * Email: chris@mysociety.org; WWW: http://www.mysociety.org
 *
 * $Id: dadem.php,v 1.20 2004-10-06 16:22:49 francis Exp $
 * 
 */

include_once('votingarea.php');
include_once('simplexmlrpc.php');
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
    /* XXX extend this with further stub data. */
    $stub_data = array(
        1 => array('name' => 'Maurice Leeke', 'type' => VA_CED, 'voting_area' => 2,
                'contact_method' => DADEM_CONTACT_EMAIL, 'email' => 'Maurice.Leeke@cambridgeshire.gov.uk'),
        2 => array('name' => 'Diane Armstrong', 'type' => VA_DIW, 'voting_area' => 4,
                'contact_method' => DADEM_CONTACT_EMAIL, 'email' => 'diane_armstrong@tiscali.co.uk'),
        3 => array('name' => 'Max Boyce','type' => VA_DIW, 'voting_area' => 4,
                'contact_method' => DADEM_CONTACT_EMAIL, 'email' => 'maxboyce@cix.co.uk'),
        4 => array('name' => 'Ian Nimmo-Smith','type' => VA_DIW, 'voting_area' => 4,
                'contact_method' => DADEM_CONTACT_EMAIL, 'email' => 'ian@monksilver.com'),
        5 => array('name' => 'Anne Campbell','type' => VA_WMC, 'voting_area' => 6,
                'contact_method' => DADEM_CONTACT_FAX, 'fax' => '+441223311315'),
        6 => array('name' => 'Geoffrey Van Orden','type' => VA_EUR, 'voting_area' => 8,
                'contact_method' => DADEM_CONTACT_FAX, 'fax' => '+3222849332'),
        7 => array('name' => 'Jeffrey Titford','type' => VA_EUR, 'voting_area' => 8,
                'contact_method' => DADEM_CONTACT_FAX, 'fax' => '+441245252071'),
        8 => array('name' => 'Richard Howitt','type' => VA_EUR, 'voting_area' => 8,
                'contact_method' => DADEM_CONTACT_EMAIL, 'email' => 'richard.howitt@geo2.poptel.org.uk'),
        9 => array('name' => 'Robert Sturdy','type' => VA_EUR, 'voting_area' => 8,
                'contact_method' => DADEM_CONTACT_EMAIL, 'email' => 'rsturdy@europarl.eu.int'),
        10 => array('name' => 'Andrew Duff','type' => VA_EUR, 'voting_area' => 8,
                'contact_method' => DADEM_CONTACT_EMAIL, 'email' => 'mep@andrewduffmep.org'),
        11 => array('name' => 'Christopher Beazley','type' => VA_EUR, 'voting_area' => 8,
                'contact_method' => DADEM_CONTACT_FAX, 'fax' => '+441920485805'),
        12 => array('name' => 'Tom Wise','type' => VA_EUR, 'voting_area' => 8,
                'contact_method' => DADEM_CONTACT_EMAIL, 'email' => 'ukipeast@globalnet.co.uk')
    );
    $ret = $stub_data[$rep_id];
    if (!isset($ret)) {
        debug("DADEM", "Representative not found id $rep_id");
        return DADEM_REP_NOT_FOUND;
    }
    debug("DADEM", "Looked up info for representative id $rep_id");
    debug("DADEMRESULT", "Results:", $ret);
    return $ret;
}

?>
