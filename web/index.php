<?
/*
 * Main page of FaxYourRepresentative, where you enter your postcode
 * 
 * Copyright (c) 2004 UK Citizens Online Democracy. All rights reserved.
 * Email: francis@mysociety.org. WWW: http://www.mysociety.org
 *
 * $Id: index.php,v 1.16 2004-11-15 18:35:23 francis Exp $
 * 
 */

require_once "../phplib/fyr.php";
require_once "../phplib/forms.php";

fyr_rate_limit(array());

$form = new HTML_QuickForm('postcodeForm', 'get', 'who');
$buttons[0] =& HTML_QuickForm::createElement('text', 'pc', null, array('size' => 10, 'maxlength' => 255));
$buttons[1] =& HTML_QuickForm::createElement('submit', 'go', 'Go');
$form->addGroup($buttons, 'stuff', '<b>Type Your UK Postcode:</b>', '&nbsp', false); // TODO: don't have bold tags here!
$form->addRule('pc', 'Please enter your postcode', 'required', null, null);

$fyr_form_renderer = new HTML_QuickForm_Renderer_mySociety();
$form->accept($fyr_form_renderer);

$fyr_title = "Fax or Email Your Democratic Representatives For Free";
$html = $fyr_form_renderer->toHtml();
template_draw("index", array("form" => $html));

?>

