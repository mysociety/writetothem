<?
/*
 * index.php:
 * Main page of FaxYourRepresentative, where you enter your postcode
 * 
 * Copyright (c) 2004 UK Citizens Online Democracy. All rights reserved.
 * Email: francis@mysociety.org. WWW: http://www.mysociety.org
 *
 * $Id: index.php,v 1.27 2004-12-22 13:16:01 francis Exp $
 * 
 */

require_once "../phplib/fyr.php";
require_once "../phplib/forms.php";

require_once "../../phplib/mapit.php";

$pc = get_http_var("pc");
fyr_rate_limit(array("postcode" => $pc));

$form = new HTML_QuickForm('postcodeForm', 'get', './');
$buttons[0] = &HTML_QuickForm::createElement('text', 'pc', null, array('size' => 10, 'maxlength' => 255));
$buttons[1] = &HTML_QuickForm::createElement('submit', '', 'Go');

/* Record referer. We want to pass this onto the queue later, as an anti-abuse
 * measure, so it should be propagated through all the later pages. Obviously
 * this only has value against a naive attacker; also, there is no point in
 * trying to obscure this data. */
$ref = fyr_external_referrer();
if (isset($ref))
    $buttons[2] = &HTML_QuickForm::createElement('hidden', 'fyr_extref', $ref);

$form->addGroup($buttons, 'stuff', '<b>Type Your UK Postcode:</b>', '&nbsp', false); // TODO: don't have bold tags here!
$form->addRule('pc', 'Please enter your postcode', 'required', null, null);

// Validate postcode, and prepare appropriate page
$template = "index-index";
if ($pc != "") {
    /* Test for various special-case postcodes which lie outside the UK. Many
     * of these aren't valid UK postcode formats, so do a special-case test
     * here rather than passing them down to MaPit. See
     * http://en.wikipedia.org/wiki/Postcode#Overseas_Territories */
    $pc2 = preg_replace('/\\s+/', '', strtoupper($pc));
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
    } else if (preg_match('/^JE/', $pc2)) {
        $ot = 'jersey';
    } else if (preg_match('/^GY/', $pc2)) {
        $ot = 'guernsey';
    } else if (preg_match('/^IM/', $pc2)) {
        $ot = 'isleofman';
    }

    if (isset($ot)) {
        header("Location: about-overseasterritories#$ot");
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

$fyr_form_renderer = new HTML_QuickForm_Renderer_mySociety();
$form->accept($fyr_form_renderer);

$html = $fyr_form_renderer->toHtml();
template_draw($template, array("form" => $html, "error" => $error_message));

?>

