<?php
/*
 * dadem.php:
 * Interact with DaDem.
 * 
 * Copyright (c) 2004 Chris Lightfoot. All rights reserved.
 * Email: chris@ex-parrot.com; WWW: http://www.ex-parrot.com/~chris/
 *
 * $Id: dadem.php,v 1.10 2004-10-06 11:08:12 francis Exp $
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
