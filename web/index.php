<?
/*
 * Main page of FaxYourRepresentative, where you enter your postcode
 * 
 * Copyright (c) 2004 UK Citizens Online Democracy. All rights reserved.
 * Email: francis@mysociety.org. WWW: http://www.mysociety.org
 *
 * $Id: index.php,v 1.18 2004-11-18 17:36:12 francis Exp $
 * 
 */

require_once "../phplib/fyr.php";
require_once "../phplib/forms.php";

require_once "../../phplib/mapit.php";

fyr_rate_limit(array());

$form = new HTML_QuickForm('postcodeForm', 'get', 'index');
$buttons[0] =& HTML_QuickForm::createElement('text', 'pc', null, array('size' => 10, 'maxlength' => 255));
$buttons[1] =& HTML_QuickForm::createElement('submit', 'go', 'Go');
$form->addGroup($buttons, 'stuff', '<b>Type Your UK Postcode:</b>', '&nbsp', false); // TODO: don't have bold tags here!
$form->addRule('pc', 'Please enter your postcode', 'required', null, null);

// Validate postcode, and prepare appropriate page
$pc = get_http_var("pc");
$template = "index-index";
if ($pc != "") {
    $voting_areas = mapit_get_voting_areas($pc);
    if (!rabx_is_error($voting_areas)) {
        header("Location: who?pc=$pc");
        exit;
    }
    if ($voting_areas->code == MAPIT_BAD_POSTCODE) {
        $fyr_error_message = "Sorry, we need your complete UK postcode to identify your MP.";
        $template = "index-advice";
    }
    else if ($voting_areas->code == MAPIT_POSTCODE_NOT_FOUND) {
        $fyr_error_message = "We're not quite sure why, but we can't seem to recognise your postcode.";
        $template = "index-advice";
    }
    else {
        $fyr_error_message = $voting_ares->text;
        $template = "index-advice";
    }
}

$fyr_form_renderer = new HTML_QuickForm_Renderer_mySociety();
$form->accept($fyr_form_renderer);

$html = $fyr_form_renderer->toHtml();
template_draw($template, array("form" => $html, "error" => $fyr_error_message));

?>

