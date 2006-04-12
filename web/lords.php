<?
/*
 * index.php:
 * Main page of FaxYourRepresentative, where you enter your postcode
 * 
 * Copyright (c) 2004 UK Citizens Online Democracy. All rights reserved.
 * Email: francis@mysociety.org. WWW: http://www.mysociety.org
 *
 * $Id: lords.php,v 1.1 2006-04-12 15:10:16 francis Exp $
 * 
 */
require_once "../phplib/fyr.php";
require_once "../../phplib/utility.php";
require_once "../../phplib/mapit.php";
require_once '../../phplib/dadem.php';
require_once "../../phplib/votingarea.php";

// Random Lord
if (get_http_var("random_lord")) {
    $all_lords = dadem_get_representatives($HOC_AREA_ID);
    dadem_check_error($all_lords);
    $random_lord = $all_lords[rand(0, count($all_lords) - 1)];
    header('Location: ' . new_url('write', true, 'fyr_extref', fyr_external_referrer(), 'cocode', get_http_var('cocode'), 'who', $random_lord));
}

// Front page form
$ref = fyr_external_referrer();
$form_extra = '';
if (isset($ref))
    $form_extra .= '<input type="hidden" name="fyr_extref" value="'.htmlentities($ref).'">';
$cocode = get_http_var('cocode');
if ($cocode)
    $form_extra .= '<input type="hidden" name="cocode" value="'.htmlentities($cocode).'">';

template_draw('lords-index', array(
        "title" => "Email or fax a member of the House of Lords in the UK Parliament",
        "form_extra" => $form_extra, 
    ));

?>

