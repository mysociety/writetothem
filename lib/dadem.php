<?php
/*
 * Interact with DaDem.  Roughly speaking, look up representatives in
 * office for a voting area.
 * 
 * Copyright (c) 2004 UK Citizens Online Democracy. All rights reserved.
 * Email: chris@mysociety.org; WWW: http://www.mysociety.org
 *
 * $Id: dadem.php,v 1.14 2004-10-06 11:44:43 francis Exp $
 * 
 */

include_once('votingarea.php');

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
    // Hardwired stub dummy data
    if ($va_id == 2) { // VA_CED
        $ret = array(
                1,
            );
    } else if ($va_id == 4) { // VA_DIW
        $ret = array(
                2,
                3,
                4,
            );
    } else if ($va_id == 6) { // VA_WMC
        $ret = array(
                5,
            );
    } else if ($va_id == 8) { // VA_EUR
        $ret = array(
                6,
                7,
                8,
                9,
                10,
                11,
                12,
            );
    }
    if (isset($ret)) {
        debug("DADEM", "Looked up representatives for voting area id $va_id");
        debug("DADEMRESULT", "Results:", $ret);
        return $ret;
    }
    debug("DADEM", "Unknown area id $va_id");
    return DADEM_UNKNOWN_AREA;
}

/* dadem_get_representative_info ID
 * On success, returns an array giving information about the representative
 * with the given ID. This array contains elements type, the type of the area
 * for which they're elected (and hence what type of representative they are);
 * name, their name; contact_method, either 'fax' or 'email', and either an
 * element 'email' or 'fax' giving their address or number respectively. On
 * failure, returns an error code. */
function dadem_get_representative_info($rep_id) {
    /* XXX extend this with further stub data. */
    $stub_data = array(
        1 => array('type' => VA_CED, 'name' => 'Maurice Leeke', 
                'contact_method' => DADEM_CONTACT_EMAIL, 'email' => 'Maurice.Leeke@cambridgeshire.gov.uk'),
        2 => array('type' => VA_DIW, 'name' => 'Diane Armstrong', 
                'contact_method' => DADEM_CONTACT_EMAIL, 'email' => 'diane_armstrong@tiscali.co.uk'),
        3 => array('type' => VA_DIW, 'name' => 'Max Boyce',
                'contact_method' => DADEM_CONTACT_EMAIL, 'email' => 'maxboyce@cix.co.uk'),
        4 => array('type' => VA_DIW, 'name' => 'Ian Nimmo-Smith',
                'contact_method' => DADEM_CONTACT_EMAIL, 'email' => 'ian@monksilver.com'),
        5 => array('type' => VA_WMC, 'name' => 'Anne Campbell',
                'contact_method' => DADEM_CONTACT_FAX, 'fax' => '+441223311315'),
        6 => array('type' => VA_EUR, 'name' => 'Geoffrey Van Orden',
                'contact_method' => DADEM_CONTACT_FAX, 'fax' => '+3222849332'),
        7 => array('type' => VA_EUR, 'name' => 'Jeffrey Titford',
                'contact_method' => DADEM_CONTACT_FAX, 'fax' => '+441245252071'),
        8 => array('type' => VA_EUR, 'name' => 'Richard Howitt',
                'contact_method' => DADEM_CONTACT_EMAIL, 'email' => 'richard.howitt@geo2.poptel.org.uk'),
        9 => array('type' => VA_EUR, 'name' => 'Robert Sturdy',
                'contact_method' => DADEM_CONTACT_EMAIL, 'email' => 'rsturdy@europarl.eu.int'),
        10 => array('type' => VA_EUR, 'name' => 'Andrew Duff',
                'contact_method' => DADEM_CONTACT_EMAIL, 'email' => 'mep@andrewduffmep.org'),
        11 => array('type' => VA_EUR, 'name' => 'Christopher Beazley',
                'contact_method' => DADEM_CONTACT_FAX, 'fax' => '+441920485805'),
        12 => array('type' => VA_EUR, 'name' => 'Tom Wise',
                'contact_method' => DADEM_CONTACT_EMAIL, 'email' => 'ukipeast@globalnet.co.uk')
    );
    $ret = $stub_data[$rep_id];
    if (!isset($ret)) {
        debug("DADEM", "Representative not found id $rep_id");
        return DADEM_REPRESENTATIVE_NOT_FOUND;
    }
    debug("DADEM", "Looked up info for representative id $rep_id");
    debug("DADEMRESULT", "Results:", $ret);
    return $ret;
}

?>
