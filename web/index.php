<?
/*
 * index.php:
 * Main page of FaxYourRepresentative, where you enter your postcode
 * 
 * Copyright (c) 2004 UK Citizens Online Democracy. All rights reserved.
 * Email: francis@mysociety.org. WWW: http://www.mysociety.org
 *
 * $Id: index.php,v 1.32 2005-01-12 09:04:32 francis Exp $
 * 
 */

require_once "../phplib/fyr.php";
require_once "../../phplib/mapit.php";

$pc = get_http_var("pc");
fyr_rate_limit(array("postcode" => $pc));

$form = '<form action="./" method="get" name="postcodeForm" id="postcodeForm">';
$form .= '<label for="pc"><b>Type Your UK Postcode:</b></label>&nbsp;';
$form .= '<input type="text" name="pc" value="'.htmlentities($pc).'" id="pc" size="10" maxlength="255">';
$form .= '&nbsp;<input type="submit" value="Go">';

/* Record referer. We want to pass this onto the queue later, as an anti-abuse
 * measure, so it should be propagated through all the later pages. Obviously
 * this only has value against a naive attacker; also, there is no point in
 * trying to obscure this data. */
$ref = fyr_external_referrer();
if (isset($ref))
	$form .= '<input type="hidden" name="fyr_extref" value="'.htmlentities($ref).'">';
$form .= '</form>';

// Validate postcode, and prepare appropriate page
$template = "index-index";
$error_message = null;
if ($pc != "" or array_key_exists('pc', $_GET)) {
    /* Test for various special-case postcodes which lie outside the UK. Many
     * of these aren't valid UK postcode formats, so do a special-case test
     * here rather than passing them down to MaPit. See
     * http://en.wikipedia.org/wiki/Postcode#Overseas_Territories */
    $pc2 = preg_replace('/\\s+/', '', strtoupper($pc));
    $pc22 = substr($pc2, 0, 2); $pc23 = substr($pc2, 0, 3);
    $otmap = array(
            'FIQQ1ZZ' => 'falklands',
            'SIQQ1ZZ' => 'southgeorgia',
            'STHL1ZZ' => 'sthelena',
            /* For our purposes, St Helena and Ascension are the same, though
             * they are thousands of miles apart.... */
            'ASCN1ZZ' => 'sthelena',
            'BIQQ1ZZ' => 'antarctica'
        );
    $ot = null;
    if (array_key_exists($pc2, $otmap)) {
        $ot = $otmap[$pc2];
    } elseif ($pc22 == 'JE') {
        $ot = 'jersey';
    } elseif ($pc22 == 'GY') {
        $ot = 'guernsey';
    } elseif ($pc22 == 'IM') {
        $ot = 'isleofman';
    }

    if (isset($ot)) {
        header("Location: about-elsewhere#$ot");
        exit;
    }

	$specialmap = array(
		'GIR0AA' => 'giro',
		'G1R0AA' => 'giro',
		'SANTA1' => 'santa'
	);
	$ft = null;
	if (array_key_exists($pc2, $specialmap)) {
		$ft = $specialmap[$pc2];
	} elseif ($pc22 == 'AM') {
		$ft = 'archers';
	} elseif ($pc22 == 'FX') {
		$ft = 'training';
	} elseif ($pc23 == 'RE1') {
		$ft = 'reddwarf';
	} elseif ($pc23 == 'E20') {
		$ft = 'eastenders';
	}

	if (isset($ft)) {
		header("Location: about-special#$ft");
		exit;
	}
    
    $voting_areas = mapit_get_voting_areas($pc);
    if (!rabx_is_error($voting_areas)) {
        header('Location: ' . new_url('who', true));
        exit;
    }
    if ($voting_areas->code == MAPIT_BAD_POSTCODE) {
        $error_message = "Sorry, we need your complete UK postcode to identify your elected representatives.";
        $template = "index-advice";
    }
    else if ($voting_areas->code == MAPIT_POSTCODE_NOT_FOUND) {
        $error_message = "We're not quite sure why, but we can't seem to recognise your postcode.";
        $template = "index-advice";
    }
    else {
        template_show_error($voting_areas->text);
    }
}

template_draw($template, array("form" => $form, "error" => $error_message));

?>

