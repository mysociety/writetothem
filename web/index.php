<?
/*
 * Main page of FaxYourRepresentative
 * 
 * Copyright (c) 2004 UK Citizens Online Democracy. All rights reserved.
 * Email: francis@mysociety.org. WWW: http://www.mysociety.org
 *
 * $Id: index.php,v 1.8 2004-10-18 07:34:31 francis Exp $
 * 
 */

require_once "../phplib/forms.php";

$form = new HTML_QuickForm('postcodeForm', 'get', 'who.php');
$form->addElement('text', 'pc', "Type Your UK Postcode:", array('size' => 10, 'maxlength' => 255));
$form->addElement('submit', 'go', 'Go');
$form->applyFilter('pc', 'trim');
$form->addRule('pc', 'Please enter your postcode', 'required', null, null);
$form->setDefaults(array('pc' => "CB4 1XP"));

$fyr_form_renderer = new HTML_QuickForm_Renderer_mySociety();
$fyr_form_renderer->markSameRowStart('pc');
$fyr_form_renderer->markSameRowEnd('go');
$form->accept($fyr_form_renderer);

$fyr_title = "Fax or Email Your Democratic Representatives For Free";
include "templates/index.html";

?>

