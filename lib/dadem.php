<?php
/*
 * dadem.php:
 * Interact with DaDem.
 * 
 * Copyright (c) 2004 Chris Lightfoot. All rights reserved.
 * Email: chris@ex-parrot.com; WWW: http://www.ex-parrot.com/~chris/
 *
 * $Id: dadem.php,v 1.7 2004-10-05 12:28:05 chris Exp $
 * 
 */

include_once('votingarea.php');

define('DADEM_BAD_TYPE', 1);  /* bad area type */
define('DADEM_UNKNOWN', 2);   /* unknown area */

define('DADEM_CONTACT_FAX', 101);
define('DADEM_CONTACT_EMAIL', 102);

/* dadem_get_representatives TYPE ID
 * Return an array of (ID, name, type of contact information, contact
 * information) for the representatives for the given TYPE of area with the
 * given ID on success, or an error code on failure. */
function dadem_get_representatives($type, $id) {
    if ($type == VA_CED) {
        return array(
                array(1, 'Maurice Leeke', DADEM_CONTACT_EMAIL, 'Maurice.Leeke@cambridgeshire.gov.uk')
            );
    } else if ($type == VA_DIW) {
        return array(
                array(2, 'Diane Armstrong', DADEM_CONTACT_EMAIL, 'diane_armstrong@tiscali.co.uk'),
                array(3, 'Max Boyce', DADEM_CONTACT_EMAIL, 'maxboyce@cix.co.uk'),
                array(4, 'Ian Nimmo-Smith', DADEM_CONTACT_EMAIL, 'ian@monksilver.com')
            );
    } else if ($type == VA_WMC) {
        return array(
                array(5, 'Anne Campbell', DADEM_CONTACT_FAX, '+441223311315')
            );
    } else if ($type == VA_EUR) {
        return array(
                array(6, 'Geoffrey Van Orden', DADEM_CONTACT_FAX, '+3222849332'),
                array(7, 'Jeffrey Titford', DADEM_CONTACT_FAX, '+441245252071'),
                array(8, 'Richard Howitt', DADEM_CONTACT_EMAIL, 'richard.howitt@geo2.poptel.org.uk'),
                array(9, 'Robert Sturdy', DADEM_CONTACT_EMAIL, 'rsturdy@europarl.eu.int'),
                array(10, 'Andrew Duff', DADEM_CONTACT_EMAIL, 'mep@andrewduffmep.org'),
                array(11, 'Christopher Beazley', DADEM_CONTACT_FAX, '+441920485805'),
                array(12, 'Tom Wise', DADEM_CONTACT_EMAIL, 'ukipeast@globalnet.co.uk')
            );
    }
    return DADEM_BAD_TYPE;
}

?>
