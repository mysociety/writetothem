<?php
/*
 * dadem.php:
 * Interact with DaDem.
 * 
 * Copyright (c) 2004 Chris Lightfoot. All rights reserved.
 * Email: chris@ex-parrot.com; WWW: http://www.ex-parrot.com/~chris/
 *
 * $Id: dadem.php,v 1.3 2004-10-04 17:49:26 chris Exp $
 * 
 */

include_once('votingarea.php');

/* $rep_name
 * Long names of representatives for the different VA_s. */
$rep_name = array(
        VA_LBW  => 'Councillor',

        VA_GLA  => 'Mayor', /* "of London"? */
        VA_LAC  => 'Assembly Member',
        
        VA_CED  => 'County Councillor',
        
        VA_DIW  => 'District Councillor',
        
        VA_UTE  => 'Councillor',
        VA_UTW  => 'Councillor',
        
        VA_MTW  => 'Councillor',
        
        VA_SPE  => 'Member of the Scottish Parliament',
        VA_SPC  => 'Member of the Scottish Parliament',
        
        VA_WAE  => 'Welsh Assembly Member',
        VA_WAC  => 'Welsh Assembly Member',

        VA_WMC  => 'Member of Parliament',

        VA_EUR  => 'Member of the European Parliament'
    );

/* $rep_suffix
 * Suffix/short name. */
$rep_suffix = array(
        VA_LBW  => 'Cllr',

        VA_GLA  => 'Mayor', /* "of London"? */
        VA_LAC  => 'AM',
        
        VA_CED  => 'Cllr',
        
        VA_DIW  => 'Cllr',
        
        VA_UTE  => 'Cllr',
        VA_UTW  => 'Cllr',
        
        VA_MTW  => 'Cllr',
        
        VA_SPE  => 'MSP',
        VA_SPC  => 'MSP',
        
        VA_WAE  => 'AM',
        VA_WAC  => 'AM',

        VA_WMC  => 'MP',

        VA_EUR  => 'MEP'
    );


define('DADEM_BAD_TYPE', 1);  /* bad area type */
define('DADEM_UNKNOWN', 2);   /* unknown area */

define('DADEM_CONTACT_FAX', 101);
define('DADEM_CONTACT_EMAIL', 102);

/* dadem_get_representatives TYPE ID
 * Return an array of (name, type of contact information, contact information)
 * for the representatives for the given TYPE of area with the given ID on
 * success, or an error code on failure. */
function dadem_get_representatives($type, $id) {
    if ($type == VA_CED) {
        return array(
                array('Maurice Leeke', DADEM_CONTACT_EMAIL, 'Maurice.Leeke@cambridgeshire.gov.uk')
            );
    } else if ($type == VA_DIW) {
        return array(
                array('Diane Armstrong', DADEM_CONTACT_EMAIL, 'diane_armstrong@tiscali.co.uk'),
                array('Max Boyce', DADEM_CONTACT_EMAIL, 'maxboyce@cix.co.uk'),
                array('Ian Nimmo-Smith', DADEM_CONTACT_EMAIL, 'ian@monksilver.com')
            );
    } else if ($type == VA_WMC) {
        return array(
                array('Anne Campbell', DADEM_CONTACT_FAX, '+441223311315')
            );
    }
}

?>
