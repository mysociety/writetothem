<?
/*
 * Page where they enter details, write their message, and preview it
 * 
 * Copyright (c) 2004 UK Citizens Online Democracy. All rights reserved.
 * Email: francis@mysociety.org. WWW: http://www.mysociety.org
 *
 * $Id: write.php,v 1.18 2004-10-25 15:21:31 francis Exp $
 * 
 */

require_once "../phplib/forms.php";

include_once "../conf/config.php";
include_once "../phplib/queue.php";
include_once "../../phplib/mapit.php";
include_once "../../phplib/votingarea.php";
include_once "../../phplib/dadem.php";
include_once "../../phplib/utility.php";

function default_body_text() {
        global $fyr_representative;
        return 'Dear ' .  $fyr_representative['name'] . ',';
}

class RuleAlteredBodyText extends HTML_QuickForm_Rule {
    function validate($value, $options) {
        return $value != default_body_text();
    }
}

// Class representing form they enter message of letter in
function buildWriteForm()
{
    $form = new HTML_QuickForm('writeForm', 'post', 'write.php');

    global $fyr_values, $fyr_postcode, $fyr_who;
    global $fyr_representative, $fyr_voting_area, $fyr_date;

    // TODO: CSS this:
    $stuff_on_left = <<<END
            <B>Now Write Your Fax:</B><BR><BR>
            <B>${fyr_voting_area['rep_prefix']}
            ${fyr_representative['name']}
            ${fyr_voting_area['rep_suffix']}
            <BR>${fyr_voting_area['name']}
            <BR><BR>$fyr_date
END;

    // special formatting for letter-like code, TODO: how do this // properly with QuickHtml?
    $form->addElement("html", "<tr><td valign=top>$stuff_on_left</td><td align=right>\n<table>"); // CSSify

    $form->addElement('text', 'writer_name', "your name:", array('size' => 20, 'maxlength' => 255));
    $form->addRule('writer_name', 'Please enter your name', 'required', null, null);
    $form->applyFilter('writer_name', 'trim');

    $form->addElement('text', 'writer_address1', "address 1:", array('size' => 20, 'maxlength' => 255));
    $form->addRule('writer_address1', 'Please enter your address', 'required', null, null);
    $form->applyFilter('writer_address1', 'trim');

    $form->addElement('text', 'writer_address2', "address 2:", array('size' => 20, 'maxlength' => 255));
    $form->applyFilter('writer_address2', 'trim');

    $form->addElement('text', 'writer_town', "town:", array('size' => 20, 'maxlength' => 255));
    $form->addRule('writer_town', 'Please enter your town', 'required', null, null);
    $form->applyFilter('writer_town', 'trim');

    $form->addElement('static', 'staticpc', 'postcode:', $fyr_postcode);

    $form->addElement('text', 'writer_email', "email:", array('size' => 20, 'maxlength' => 255));
    $form->addRule('writer_email', 'Please enter your email', 'required', null, null);
    $form->addRule('writer_email', 'Choose a valid email address', 'email', null, null);
    $form->applyFilter('writer_email', 'trim');

    $form->addElement('text', 'writer_phone', "phone:", array('size' => 20, 'maxlength' => 255));
    $form->applyFilter('writer_phone', 'trim');

    // special formatting for letter-like code, TODO: how do this // properly with QuickHtml?
    $form->addElement("html", "</table>\n</td></tr>"); // CSSify

    $form->addElement('textarea', 'body', null, array('rows' => 15, 'cols' => 62, 'maxlength' => 5000));
    $form->addRule('body', 'Please enter your message', 'required', null, null);
    $form->addRule('body', 'Please enter your message', new RuleAlteredBodyText(), null, null);
    $form->applyFilter('body', 'convert_to_unix_newlines');

    add_all_variables_hidden($form, $fyr_values);

    $buttons[0] =& HTML_QuickForm::createElement('static', 'static1', null, 
            "<b>Ready? Press the \"Preview\" button to continue --></b>"); // TODO: remove <b>  from here
    $buttons[1] =& HTML_QuickForm::createElement('submit', 'submitPreview', 'preview your Fax >>');
    $form->addGroup($buttons, 'previewStuff', '', '&nbsp;', false);

    return $form;
}

function buildPreviewForm()
{
    $form = new HTML_QuickForm('previewForm', 'post', 'write.php');

    global $fyr_values;
    add_all_variables_hidden($form, $fyr_values);

    $buttons[0] =& HTML_QuickForm::createElement('submit', 'submitWrite', '<< edit this Fax');
    $buttons[1] =& HTML_QuickForm::createElement('submit', 'submitSendFax', 'Continue >>');
    $form->addGroup($buttons, 'buttons', '', '&nbsp;', false);

    return $form;
}

function renderForm($form, $pageName)
{
    // $renderer =& $page->defaultRenderer();
    $renderer =& new HTML_QuickForm_Renderer_mySociety();
    $renderer->setGroupTemplate('<TR><TD ALIGN=right colspan=2> {content} </TD></TR>', 'previewStuff'); // TODO CSS this
    $renderer->setElementTemplate('{element}', 'previewStuff');
    $renderer->setElementTemplate('<TD colspan=2> 
    {element} 
    <!-- BEGIN error --><span style="color: #ff0000"><br>{error}</span><!-- END error --> 
    </TD>', 'body');
    $form->accept($renderer);

    global $fyr_form, $fyr_values;
    $fyr_values['signature'] = sha1($fyr_values['email']);
    debug("FRONTEND", "Form values:", $fyr_values);

    // Make HTML
    $fyr_form = $renderer->toHtml();

    global $fyr_preview, $fyr_representative, $fyr_voting_area, $fyr_date, $fyr_title;
    if ($pageName == "writeForm") {
        $fyr_title = "Now Write Your Fax To ${fyr_representative['name']} ${fyr_voting_area['rep_name']} for ${fyr_voting_area['name']}";
        include "templates/write-write.html";
    } else { // previewForm
        // Generate preview
        $fyr_title = "Check Your Fax Is Right";
        ob_start();
        include "templates/fax-content.html";
        $fyr_preview = ob_get_contents();
        ob_end_clean();
        include "templates/write-preview.html";
    }
}

function submitFax() {
    global $fyr_values, $msgid;

    $address = 
        $fyr_values['writer_address1'] . "\n" .
        $fyr_values['writer_address2'] . "\n" .
        $fyr_values['writer_town'] . "\n" .
        $fyr_values['pc'] . "\n";
    $address = str_replace("\n\n", "\n", $address);

    $success = msg_write($msgid, 
            array( 
            'name' => $fyr_values['writer_name'],
            'email' => $fyr_values['writer_email'], 
            'address' => $address,
            'phone' => $fyr_values['writer_phone'], 
            ),
            $fyr_values['who'], $fyr_values['body']);

    global $fyr_representative, $fyr_voting_area, $fyr_date, $fyr_title;
    if ($success) {
        $fyr_title = "Great! Now Check Your Email";
        include "templates/write-checkemail.html";
    } else {
        $fyr_error_message = "Failed to queue the message";  // TODO improve this error message
        include "templates/generalerror.html";
    }
}

// Get all fyr_values
$fyr_values = get_all_variables();
debug("FRONTEND", "All variables:", $fyr_values);

// Message id for transaction with fax queue
$msgid = $fyr_values['fyr_msgid'];
if (!isset($msgid)) {
    $msgid = msg_create();
    $fyr_values['fyr_msgid'] = $msgid;
}

// Various display and used fields
$fyr_postcode = strtoupper(trim($fyr_values['pc']));
$fyr_who = $fyr_values['who'];
$fyr_date = strftime('%A %e %B %Y');
if (!isset($fyr_postcode) || !isset($fyr_who)) {
    $fyr_error_message = "Please <a href=\"/\">start from the beginning</a>.";
    include "templates/generalerror.html";
    exit;
}

// Information specific to this representative
debug("FRONTEND", "Representative $fyr_who");
$fyr_representative = dadem_get_representative_info($fyr_who);
if ($fyr_error_message = dadem_get_error($fyr_representative)) {
    include "templates/generalerror.html";
    exit;
}
// The voting area is the ward/division. e.g. West Chesterton Electoral Division
$fyr_voting_area = mapit_get_voting_area_info($fyr_representative['voting_area']);
if ($fyr_error_message = mapit_get_error($fyr_voting_area)) {
    include "templates/generalerror.html";
    exit;
}

// Work out which page we are on, using which submit button was pushed
// to get here
$on_page = "write";
if (isset($fyr_values['submitWrite'])) {
    $on_page = "write";
    unset($fyr_values['submitWrite']);
}
else if (isset($fyr_values['submitPreview'])) {
    unset($fyr_values['submitPreview']);
    $writeForm = buildWriteForm();
    if ($writeForm->validate()) {
        $on_page = "preview";
    } else {
        $on_page = "write";
    }
}
else if (isset($fyr_values['submitSendFax'])) {
    $on_page = "sendfax";
    unset($fyr_values['submitSendFax']);
}

// Display it
if ($on_page == "write") {
    if (!isset($writeForm)) 
        $writeForm = buildWriteForm();
    $writeForm->setDefaults(array('body' => default_body_text()));
    $writeForm->setConstants($fyr_values);
    renderForm($writeForm, "writeForm");
}
else if ($on_page == "preview") {
    $previewForm = buildPreviewForm();
    $previewForm->setConstants($fyr_values);
    renderForm($previewForm, "previewForm");
}
else if ($on_page =="sendfax") {
    submitFax();
} else {
    $fyr_error_message = "On an unknown page";
    include "templates/generalerror.html";
    exit;
}

?>
