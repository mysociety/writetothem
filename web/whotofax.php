<?
/*
 * index.php:
 * Page to ask which representative they would like to contact
 * 
 * Copyright (c) 2004 UK Citizens Online Democracy. All rights reserved.
 * Email: francis@mysociety.org. WWW: http://www.mysociety.org
 *
 * $Id: whotofax.php,v 1.3 2004-10-05 11:57:22 francis Exp $
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

$fyr_representatives = array();

foreach ($voting_areas as $va_type => $va_value) {
    list($va_specificid, $va_specificname) = $va_value;
    $va_typename = $va_name[$va_type];
    $rep_suffix = $va_rep_suffix[$va_type];
    $rep_name = $va_rep_name[$va_type];

    $parent_va_type = $va_inside[$va_type];;
    $parent_va_typename = $va_name[$parent_va_type];
    $parent_va_specificname = $voting_areas[$parent_va_type][1];
    # print "<p>$va_specificname is a $va_typename representatives: \n";

    if ($va_type == VA_DIS) {
        $va_description = "Your District Council is responsible for local services and policy,
            including planning, council housing, building regulation, rubbish
            collection, and local roads. Some responsibilities, such as
            recreation facilities, are shared with the County Council.";
    }
    else if ($va_type == VA_CTY) {
        $va_description = "Your County Council is responsible for local
        services, including education, social services, transport and
        libraries.";
    }
    else if ($va_type == VA_WMP) {
        $va_description = "The House of Commons is responsible for
        making laws in the UK and for overall scrutiny of all aspects of
        government.";
    }
    else if ($va_type == VA_EUR) {
        $va_description = "They scrutinise European laws (called
        \"directives\") and the budget of the European Union, and provides
        oversight of the other decision-making bodies of the Union,
        including the Council of Ministers and the Commission.";
    }
    
    $left_column = "<h4>Your $rep_name</h4>
        <p>Your $rep_name represent you on $parent_va_specificname.  $va_description.</p>
        ";

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
#        print $fyr_error_message;
#        include "templates/generalerror.html";
#        exit;
    }
    else {
        $repcount = count($representatives);
        $right_column = "<p>In your $va_typename,
        <b>$va_specificname</b>, you are represented by $repcount $rep_name.
            Please choose one $rep_name to contact.</p>
            <table style=\"float: left;\">";
        $c = 0;
        foreach ($representatives as $reprecord) {
            ++$c;
            list($rep_specificname, $rep_specificcontactmethod, $rep_specificaddress) = $reprecord;
            # print "$rep_specificname $va_rep_suffix is a $rep_name contactable at $rep_specificaddress";
    
            $right_column .= <<<END
                    <tr>
                        <td valign="top"><input type="radio" name="who" value="$va_typename-$c"></td>
                        <td><b>$rep_specificname</b><br><!--Unknown Party--></td>
                    </tr>
END;
        }
        $right_column .= "<td><input style=\"float: right\" type=\"submit\" value=\"Next &gt;&gt;\"> </td></table>";
        array_push($fyr_representatives, array($left_column, $right_column));
    }
}

/* va_responsibility
 * Description of responsibility of areas. */
$va_name = array(
        VA_DIS => "Your District Council is responsible for local services
            and policy, including planning, council housing, building
            regulation, rubbish collection, and local roads. Some
            responsibilities, such as recreation facilities, are shared with
            the County Council.",

        VA_CTY => "Your County Councillor represents you on the
            Cambridgeshire County Council. Your County Council is
            responsible for local services, including education, social
            services, transport and libraries.", 

        
    );


include "templates/whotofax.html";

?>

