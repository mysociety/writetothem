<?
/*
 * Page to ask which representative they would like to contact
 * 
 * Copyright (c) 2004 UK Citizens Online Democracy. All rights reserved.
 * Email: francis@mysociety.org. WWW: http://www.mysociety.org
 *
 * $Id: who.php,v 1.18 2004-10-28 10:53:19 francis Exp $
 * 
 */

$fyr_title = "Now Choose The Representative Responsible for the Topic";

require_once "../phplib/forms.php";
require_once "../phplib/fyr.php";

require_once "../../phplib/utility.php";
require_once "../../phplib/dadem.php";
require_once "../../phplib/mapit.php";

// Destroy session data
session_start();
session_destroy();

// Input data
$fyr_postcode = get_http_var('pc');
debug("FRONTEND", "postcode is $fyr_postcode");
fyr_rate_limit(array('pc' => $fyr_postcode));

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
    // The voting area is the ward/division. e.g. West Chesterton Electoral Division
    debug("FRONTEND", "voting area is type $va_type id $va_specificid");
    $va_info = mapit_get_voting_area_info($va_specificid);
    if ($fyr_error_message = mapit_get_error($va_info)) {
        include "templates/generalerror.html";
        exit;
    }

    // The elected body is the overall entity. e.g. Cambridgeshire County Council.
    $eb_type = $va_inside[$va_type];
    $eb_specificid = $voting_areas[$eb_type];
    debug("FRONTEND", "electoral body is type $eb_type id $eb_specificid");
    $eb_info = mapit_get_voting_area_info($eb_specificid);
    if ($fyr_error_message = mapit_get_error($eb_info)) {
        // No parent elected body - for now we skip it
        // (Will have to do something about London mayor)
        continue;
    }
    
    // Lookup table of long description XXX should copy these out of Whittaker's
    // Almanac or whatever.
    if ($eb_type == VA_DIS) {
        $eb_info['description'] = "Your District Council is responsible for
            local services and policy, including planning, council housing,
            building regulation, rubbish collection, and local roads. Some
            responsibilities, such as recreation facilities, are shared with
            the County Council.";
    } else if ($eb_type == VA_MTD) {
        $eb_info['description'] = "Your Metropolitan District Council is
            responsible for all aspects of local services and policy, including
            planning, transport, education, social services and libraries.";
    } else if ($eb_type == VA_CTY) {
        $eb_info['description'] = "Your County Council is responsible for local
            services, including education, social services, transport and
            libraries.";
    } else if ($eb_type == VA_WMP) {
        $eb_info['description'] = "The House of Commons is responsible for
            making laws in the UK and for overall scrutiny of all aspects of
            government.";
    } else if ($eb_type == VA_EUP) {
        $eb_info['description'] = "They scrutinise European laws (called
            \"directives\") and the budget of the European Union, and provides
            oversight of the other decision-making bodies of the Union,
            including the Council of Ministers and the Commission.";
    }

    // Count representatives
    $representatives = dadem_get_representatives($va_specificid);
    if ($fyr_error_message = dadem_get_error($representatives)) {
        include "templates/generalerror.html";
        exit;
    }
    $rep_count = count($representatives);
    
    // Create HTML
    if ($rep_count > 1) {
        $left_column = "<h4>Your ${va_info['rep_name_long_plural']}</h4><p>";
        $left_column .= "Your ${va_info['rep_name_plural']} represent you ${eb_info['attend_prep']} ";
    } else {
        $left_column = "<h4>Your ${va_info['rep_name_long']}</h4><p>";
        $left_column .= "Your ${va_info['rep_name']} represents you ${eb_info['attend_prep']} ";
    }
    $left_column .= "${eb_info['name']}.  ${eb_info['description']}.</p>";

    $form = new HTML_QuickForm('whoForm', 'post', 'write.php');

    $right_column = "<p>In your ${va_info['type_name']},
        <b>${va_info['name']}</b>, you are represented by ";
    if ($rep_count > 1) {
        $right_column .= "$rep_count ${va_info['rep_name_plural']}.
            Please choose one ${va_info['rep_name']} to contact.";
    } else {
        $right_column .= "one ${va_info['rep_name']}.";
    }

    // Rest of representatives
    foreach ($representatives as $rep_specificid) {
        ++$c;
        $rep_info = dadem_get_representative_info($rep_specificid);
        if ($fyr_error_message = dadem_get_error($rep_info)) {
            include "templates/generalerror.html";
            exit;
        }
        $form->addElement('radio', 'who', null, "&nbsp;<b>" .  $rep_info['name'] . '</b><br>'
            . $rep_info['party'], $rep_specificid);
    }

    $form->addElement('hidden', 'pc', $fyr_postcode);
    $form->addElement('submit', 'next', 'Next >>');
    $fyr_form_renderer = new HTML_QuickForm_Renderer_mySociety();
    $form->accept($fyr_form_renderer);
    $right_column .= $fyr_form_renderer->toHtml();

    array_push($fyr_representatives, array($left_column, $right_column));
}

// Display page, using all the fyr_* variables set above.
include "templates/who.html";

?>

