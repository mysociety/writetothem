<?
/*
 * Page where they enter details, write their message, and preview it
 * 
 * Copyright (c) 2004 UK Citizens Online Democracy. All rights reserved.
 * Email: francis@mysociety.org. WWW: http://www.mysociety.org
 *
 * $Id: write.php,v 1.1 2004-10-05 20:35:51 francis Exp $
 * 
 */

$fyr_title = "Now Write Your Fax To X MP for X";

include_once "../lib/utility.php";
include_once "../lib/votingarea.php";
include_once "../lib/dadem.php";

// Input data
$fyr_postcode = get_http_var('pc');
$who = get_http_var('who');

$matches = array();
if (!preg_match("/([0-9]+)-([0-9]+)/", $who, $matches)) {
    $fyr_error_message = "Parameter who didn't match correct format.";
    include "templates/generalerror.html";
    exit;
}
list($dummy_all, $va_id, $rep_id) = $matches;
debug("FRONTEND", "Voting area $va_id representative $rep_id");

// The voting area is the ward/division. e.g. West Chesterton Electoral Division
list($va_specificid, $va_specificname) = $va_value;
$va_typename = $va_name[$va_type];
$rep_suffix = $va_rep_suffix[$va_type];
$rep_name = $va_rep_name[$va_type];

// The elected body is the overall entity. e.g. Cambridgeshire County Council.
$eb_type = $va_inside[$va_type];
$eb_typename = $va_name[$eb_type];
$eb_specificname = $voting_areas[$eb_type][1];



include "templates/write.html";

?>

