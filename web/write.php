<?
/*
 * Page where they enter details, write their message, and preview it
 * 
 * Copyright (c) 2004 UK Citizens Online Democracy. All rights reserved.
 * Email: francis@mysociety.org. WWW: http://www.mysociety.org
 *
 * $Id: write.php,v 1.7 2004-10-14 15:49:22 francis Exp $
 * 
 */

$fyr_title = "Now Write Your Fax To X MP for X";

include_once "../conf/config.php";
include_once "../../phplib/mapit.php";
include_once "../../phplib/votingarea.php";
include_once "../../phplib/dadem.php";
include_once "../../phplib/utility.php";


// Input data
$fyr_postcode = get_http_var('pc');
$fyr_who = get_http_var('who');

// Find out which representative 
$matches = array();
if (!preg_match("/^([0-9]+)$/", $fyr_who, $matches)) {
    $fyr_error_message = "Parameter who didn't match correct format.";
    include "templates/generalerror.html";
    exit;
}
list($dummy_all, $rep_id) = $matches;
debug("FRONTEND", "Representative $rep_id");

// Check to see if previewing or another mode
$action = get_http_var('act');
$fyr_preview = false;
$fyr_complete = false;
if ($action == "preview") {
    $fyr_preview = true;

    $fyr_writer_name = get_http_var('writer_name');
    $fyr_writer_name_valid = (isset($fyr_writer_name) && strlen($fyr_writer_name) > 0);
    $fyr_writer_address1 = get_http_var('writer_address1');
    $fyr_writer_address1_valid = (isset($fyr_writer_address1) && strlen($fyr_writer_address1) > 0);
    $fyr_writer_address2 = get_http_var('writer_address2');
    $fyr_writer_address2_valid = true;
    $fyr_writer_town = get_http_var('writer_town');
    $fyr_writer_town_valid = (isset($fyr_writer_town) && strlen($fyr_writer_town) > 0);
    // $fyr_postcode
    $fyr_writer_email = get_http_var('writer_email');
    $fyr_writer_email_valid = (isset($fyr_writer_email) && strlen($fyr_writer_email) > 0);
    $fyr_writer_phone = get_http_var('writer_phone');
    $fyr_writer_phone_valid = true;
    
    if ($fyr_writer_name_valid && $fyr_writer_address1_valid &&
        $fyr_writer_town_valid && $fyr_writer_email_valid) {
        $fyr_complete = true;
    }

    $fyr_body = get_http_var('body');
}

// Information specific to this representative
$fyr_representative = dadem_get_representative_info($rep_id);
if ($fyr_error_message = dadem_get_error($fyr_representative)) {
    include "templates/generalerror.html";
    exit;
}
print_r($fyr_representative);
$va_id = $fyr_representative['voting_area'];

// The voting area is the ward/division. e.g. West Chesterton Electoral Division
$fyr_voting_area = mapit_get_voting_area_info($va_id);
if ($fyr_error_message = mapit_get_error($fyr_voting_area)) {
    include "templates/generalerror.html";
    exit;
}
$va_type = $fyr_voting_area['type'];
$fyr_voting_area['type_name'] = $va_name[$va_type];

$fyr_representative['short_office'] = $va_rep_suffix[$va_type];
$fyr_representative['office'] = $va_rep_name[$va_type];

// The elected body is the overall entity. e.g. Cambridgeshire County Council.
// $eb_type = $va_inside[$va_type];
// $eb_typename = $va_name[$eb_type];
// $eb_specificname = $voting_areas[$eb_type][1];


include "templates/write.html";

?>

