<?
/*
 * Page to ask which representative they would like to contact
 * 
 * Copyright (c) 2004 UK Citizens Online Democracy. All rights reserved.
 * Email: francis@mysociety.org. WWW: http://www.mysociety.org
 *
 * $Id: who.php,v 1.5 2004-10-06 15:21:42 francis Exp $
 * 
 */

$fyr_title = "Now Choose The Representative Responsible for the Topic";

include_once "../lib/mapit.php";
include_once "../lib/votingarea.php";
include_once "../lib/dadem.php";
include_once "../lib/utility.php";

// Input data
$fyr_postcode = get_http_var('pc');
debug("FRONTEND", "postcode is $fyr_postcode");

// Find all the districts/constituencies and so on (we call them "voting
// areas") for the postcode
$voting_areas = mapit_get_voting_areas($fyr_postcode);
if ($fyr_error_message = mapit_get_error($voting_areas)) {
    include "templates/generalerror.html";
    exit;
}

// For each voting area, find all the representatives.  Put descriptive
// text and form text in an array for the template to render.
$fyr_representatives = array();
foreach ($voting_areas as $va_type => $va_specificid) {
    debug("FRONTEND", "voting area is type $va_type id $va_specificid");

    // The voting area is the ward/division. e.g. West Chesterton Electoral Division
    $va_typename = $va_name[$va_type];
    $info = mapit_get_voting_area_info($va_specificid);
    if ($fyr_error_message = mapit_get_error($info)) {
        include "templates/generalerror.html";
        exit;
    }
    $va_specificname = $info['name'];
    $rep_suffix = $va_rep_suffix[$va_type];
    $rep_name = $va_rep_name[$va_type];

    // The elected body is the overall entity. e.g. Cambridgeshire County Council.
    $eb_type = $va_inside[$va_type];
    debug("FRONTEND", "electoral body type is $eb_type");
    $eb_typename = $va_name[$eb_type];
    $eb_specificid = $voting_areas[$eb_type];
    $eb_info = mapit_get_voting_area_info($eb_specificid);
    if (!($fyr_error_message = mapit_get_error($eb_info))) {
        $eb_specificname = $eb_info['name'];
    }
 
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
        <p>Your $rep_name represent you on $eb_specificname.  $va_description.</p>
        ";

    $fyr_form_start = "
            <form method=\"get\" action=\"write.php\">
            <input type=\"hidden\" name=\"pc\"
            value=\"$fyr_postcode\">";

    $representatives = dadem_get_representatives($va_specificid);
    if ($fyr_error_message = dadem_get_error($representatives)) {
        debug(WARNING, $fyr_error_message);
    }
    else {
        $repcount = count($representatives);
        $right_column = "<p>In your $va_typename,
        <b>$va_specificname</b>, you are represented by $repcount $rep_name.
            Please choose one $rep_name to contact.</p>
            <table style=\"float: left;\">";
        foreach ($representatives as $rep_specificid) {
            ++$c;
            $reprecord = dadem_get_representative_info($rep_specificid);
            if ($fyr_error_message = dadem_get_error($reprecord)) {
                include "templates/generalerror.html";
                exit;
            }
            $rep_specificname = $reprecord['name'];
    
            $right_column .= <<<END
                    <tr>
                        <td valign="top"><input type="radio" name="who" value="$rep_specificid"></td>
                        <td><b>$rep_specificname</b><br><!--Unknown Party--></td>
                    </tr>
END;
        }
        $right_column .= "<td>
            <input style=\"float: right\" type=\"submit\" value=\"Next &gt;&gt;\"> 
            </td></table>";
        array_push($fyr_representatives, array($left_column, $right_column));
    }
}

// Display page, using all the fyr_* variables set above.
include "templates/who.html";

?>

