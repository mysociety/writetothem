<?php
/*
 * dadem.php:
 * Interact with DaDem.
 * 
 * Copyright (c) 2004 Chris Lightfoot. All rights reserved.
 * Email: chris@ex-parrot.com; WWW: http://www.ex-parrot.com/~chris/
 *
 * $Id: dadem.php,v 1.9 2004-10-05 20:35:51 francis Exp $
 * 
 */

include_once('votingarea.php');

define('DADEM_BAD_TYPE', 1);  /* bad area type */
define('DADEM_UNKNOWN_AREA', 2);   /* unknown area */
define('DADEM_REPRESENTATIVE_NOT_FOUND', 3);   /* unknown representative id */

define('DADEM_CONTACT_FAX', 101);
define('DADEM_CONTACT_EMAIL', 102);

// TODO: In theory, this shouldn't take the type
/* dadem_get_representatives TYPE ID
 * Return an array of (ID, name, type of contact information, contact
 * information) for the representatives for the given TYPE of area with the
 * given ID on success, or an error code on failure. */
function dadem_get_representatives($va_type, $va_id) {
    if ($va_type == VA_CED) {
        $ret = array(
                1,
            );
    } else if ($va_type == VA_DIW) {
        $ret = array(
                2,
                3,
                4,
            );
    } else if ($va_type == VA_WMC) {
        $ret = array(
                5,
            );
    } else if ($va_type == VA_EUR) {
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
        debug("DADEM", "Looked up representatives for voting area type $va_type id $va_id");
        debug("DADEMRESULT", "Results:", $ret);
        return $ret;
    }
    debug("DADEM", "Representative bad type $type id $va_id");
    return DADEM_BAD_TYPE;
}

function dadem_get_representative_info($rep_id) {
    $stub_data = array(
        1 => array(VA_CED, 'Maurice Leeke', DADEM_CONTACT_EMAIL, 'Maurice.Leeke@cambridgeshire.gov.uk'),
        2 => array(VA_DIW, 'Diane Armstrong', DADEM_CONTACT_EMAIL, 'diane_armstrong@tiscali.co.uk'),
        3 => array(VA_DIW, 'Max Boyce', DADEM_CONTACT_EMAIL, 'maxboyce@cix.co.uk'),
        4 => array(VA_DIW, 'Ian Nimmo-Smith', DADEM_CONTACT_EMAIL, 'ian@monksilver.com'),
        5 => array(VA_WMC, 'Anne Campbell', DADEM_CONTACT_FAX, '+441223311315'),
        6 => array(VA_EUR, 'Geoffrey Van Orden', DADEM_CONTACT_FAX, '+3222849332'),
        7 => array(VA_EUR, 'Jeffrey Titford', DADEM_CONTACT_FAX, '+441245252071'),
        8 => array(VA_EUR, 'Richard Howitt', DADEM_CONTACT_EMAIL, 'richard.howitt@geo2.poptel.org.uk'),
        9 => array(VA_EUR, 'Robert Sturdy', DADEM_CONTACT_EMAIL, 'rsturdy@europarl.eu.int'),
        10 => array(VA_EUR, 'Andrew Duff', DADEM_CONTACT_EMAIL, 'mep@andrewduffmep.org'),
        11 => array(VA_EUR, 'Christopher Beazley', DADEM_CONTACT_FAX, '+441920485805'),
        12 => array(VA_EUR, 'Tom Wise', DADEM_CONTACT_EMAIL, 'ukipeast@globalnet.co.uk')
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
