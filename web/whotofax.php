<?
/*
 * index.php:
 * Page to ask which representative they would like to contact
 * 
 * Copyright (c) 2004 UK Citizens Online Democracy. All rights reserved.
 * Email: francis@mysociety.org. WWW: http://www.mysociety.org
 *
 * $Id: whotofax.php,v 1.2 2004-10-04 18:14:10 francis Exp $
 * 
 */

include_once "../lib/mapit.php";
include_once "../lib/votingarea.php";
include_once "../lib/dadem.php";
include_once "../lib/utility.php";

$fyr_postcode = get_http_var('pc');
$voting_areas = mapit_get_voting_areas($fyr_postcode);
print "postcode $fyr_postcode\n";

if (is_integer($voting_areas)) {
    if ($voting_areas == MAPIT_BAD_POSTCODE) {
        $fyr_error_message = "'$fyr_postcode' is not a valid postcode.";
    }
    else if ($voting_areas == MAPIT_NOT_FOUND) {
        $fyr_error_message = "'$fyr_postcode' not found";
    }
    else {
        $fyr_error_message = "Unknown error looking up postcode.";
    }
    include "templates/generalerror.html";
    exit;
}

foreach ($voting_areas as $va_type => $va_value) {
    list($va_specificid, $va_specificname) = $va_value;
    $va_typename = $va_name[$va_type];
    print "<p>$va_specificname is a $va_typename representatives: \n";

    $representatives = dadem_get_representatives($va_type, $va_specificid);
    if (is_integer($representatives)) {
        if ($representatives == DADEM_BAD_TYPE) {
            $fyr_error_message = "Bad representative type $va_type.";
        }
        else if ($representatives == DADEM_UNKNOWN) {
            $fyr_error_message = "Unknown representative.";
        }
        else {
            $fyr_error_message = "Unknown error looking up representatives.";
        }
        print $fyr_error_message;
#        include "templates/generalerror.html";
#        exit;
    }
    else {
        foreach ($representatives as $reprecord) {
            list($rep_specificname, $rep_specificcontactmethod, $rep_specificaddress) = $reprecord;
            $rep_typesuffix = $rep_suffix[$va_type];
            $rep_typename = $rep_name[$va_type];
            print "$rep_specificname $rep_typesuffix is a $rep_typename contactable at $rep_specificaddress";
        }
    }
}

include "templates/whotofax.html";

?>

