<?php
/*
 * mapit.php:
 * Interact with MapIt.
 * 
 * Copyright (c) 2004 Chris Lightfoot. All rights reserved.
 * Email: chris@ex-parrot.com; WWW: http://www.ex-parrot.com/~chris/
 *
 * $Id: mapit.php,v 1.4 2004-10-04 17:07:31 chris Exp $
 * 
 */

include('votingarea.php');

/* Error codes */
define('MAPIT_BAD_POSTCODE', 1);    /* not in the format of a postcode */
define('MAPIT_NOT_FOUND', 2);       /* postcode not found */

/* get_voting_areas POSTCODE
 * On success, return an array mapping voting/administrative area type to
 * voting area ID and name. On failure, return an error code. */
function get_voting_areas($postcode) {
    /* remove spaces */
    $postcode = strtoupper(preg_replace('/ +/', '', $postcode, -1));

    if (!preg_match('/[A-Z]{1,2}[0-9]{1,4}[A-Z]{1,2}/', $postcode)) {
        return MAPIT_BAD_POSTCODE;
    } else if ($postcode == 'CB41EP') {
        return array(VA_CTY => array(1, 'Cambridgeshire County Council'),
                     VA_CED => array(2, 'West Chesterton ED'),

                     VA_DIS => array(3, 'Cambridge District Council'),
                     VA_DIW => array(4, 'West Chesterton Ward'),

                     VA_WMP => $va_name(VA_WMP),
                     VA_WMC => array(5, 'Cambridge'),
            
                     VA_EUP => $va_name(VA_EUP),
                     VA_EUR => array(6, 'Eastern Euro Region')
                );
    } else {
        return MAPIT_NOT_FOUND;
    }
}

?>
