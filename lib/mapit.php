<?php
/*
 * mapit.php:
 * Interact with MapIt.
 * 
 * Copyright (c) 2004 Chris Lightfoot. All rights reserved.
 * Email: chris@ex-parrot.com; WWW: http://www.ex-parrot.com/~chris/
 *
 * $Id: mapit.php,v 1.1 2004-10-04 16:28:18 chris Exp $
 * 
 */

/* Error codes */
define('MAPIT_BAD_POSTCODE', 1);    /* not in the format of a postcode */
define('MAPIT_NOT_FOUND', 2);       /* postcode not found */

/* get_voting_areas POSTCODE
 * Return an array of all the voting area IDs at POSTCODE, or an error code
 * on failure. */
function get_voting_areas($postcode) {
    /* remove spaces */
    $postcode = strtoupper(preg_replace('/ +/', '', $postcode, -1));

    if (!preg_match('/[A-Z]{1,2}[0-9]{1,4}[A-Z]{1,2}/', $postcode)) {
        return MAPIT_BAD_POSTCODE;
    } else if ($postcode == 'CB41EP') {
        return array(CTY => 1,
                     CED => 2,

                     DIS => 3,
                     DIW => 4,

                     WMC => 5,

                     EUR => 6);
    } else {
        return MAPIT_NOT_FOUND;
    }
}

?>
