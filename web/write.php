<?
/*
 * Page where they enter details, write their message, and preview it
 * 
 * Copyright (c) 2004 UK Citizens Online Democracy. All rights reserved.
 * Email: francis@mysociety.org. WWW: http://www.mysociety.org
 *
 * $Id: write.php,v 1.59 2005-01-13 15:15:38 francis Exp $
 * 
 */

require_once "../phplib/fyr.php";
require_once "../phplib/forms.php";
require_once "../phplib/queue.php";

require_once "../../phplib/mapit.php";
require_once "../../phplib/votingarea.php";
require_once "../../phplib/dadem.php";
require_once "../../phplib/utility.php";

function default_body_text() {
        global $fyr_representative;
        return "Dear " .  $fyr_representative['name'] . ",\n\n\n\nYours sincerely,\n\n";
}

class RuleAlteredBodyText extends HTML_QuickForm_Rule {
    function validate($value, $options) {
        return $value != default_body_text();
    }
}

// Class representing form they enter message of letter in
function buildWriteForm()
{
    $form = new HTML_QuickForm('writeForm', 'post', 'write');

    global $fyr_values, $fyr_postcode, $fyr_who;
    global $fyr_representative, $fyr_voting_area, $fyr_date;

    // TODO: CSS this:
    $stuff_on_left = <<<END
            <strong>Now Write Your Message:</strong><br><br>
            ${fyr_voting_area['rep_prefix']}
            ${fyr_representative['name']}
            ${fyr_voting_area['rep_suffix']}
            <br>${fyr_voting_area['name']}
            <br><br>$fyr_date
END;

    // special formatting for letter-like code, TODO: how do this // properly with QuickHtml?
    $form->addElement("html", "<tr><td valign=top>$stuff_on_left</td><td align=right>\n<table>"); // CSSify

    $form->addElement('text', 'writer_name', "Your name:", array('size' => 20, 'maxlength' => 255));
    $form->addRule('writer_name', 'Please enter your name', 'required', null, null);
    $form->applyFilter('writer_name', 'trim');

    $form->addElement('text', 'writer_address1', "Address 1:", array('size' => 20, 'maxlength' => 255));
    $form->addRule('writer_address1', 'Please enter your address', 'required', null, null);
    $form->applyFilter('writer_address1', 'trim');

    $form->addElement('text', 'writer_address2', "Address 2:", array('size' => 20, 'maxlength' => 255));
    $form->applyFilter('writer_address2', 'trim');

    $form->addElement('text', 'writer_town', "Town:", array('size' => 20, 'maxlength' => 255));
    $form->addRule('writer_town', 'Please enter your town', 'required', null, null);
    $form->applyFilter('writer_town', 'trim');

    $form->addElement('static', 'staticpc', 'Postcode:', htmlentities($fyr_postcode));

    $form->addElement('text', 'writer_email', "Email:", array('size' => 20, 'maxlength' => 255));
    $form->addRule('writer_email', 'Please enter your email', 'required', null, null);
    $form->addRule('writer_email', 'Choose a valid email address', 'email', null, null);
    $form->applyFilter('writer_email', 'trim');

    $form->addElement('text', 'writer_phone', "Phone: <a href=\"/about-qa#address\" target=\"_blank\">(?)</a>", array('size' => 20, 'maxlength' => 255));
    $form->addRule('writer_phone', 'Please enter a phone number', 'regex', '/^[\d() +]+$/', null);
    $form->applyFilter('writer_phone', 'trim');

    // special formatting for letter-like code, TODO: how do this // properly with QuickHtml?
    $form->addElement("html", "</table>\n</td></tr>"); // CSSify

    $form->addElement('textarea', 'body', null, array('rows' => 15, 'cols' => 62));
    $form->addRule('body', 'Please enter your message', 'required', null, null);
    $form->addRule('body', 'Please enter your message', new RuleAlteredBodyText(), null, null);
    $form->addRule('body', 'Your message is a bit too long for us to send', 'maxlength', OPTION_MAX_BODY_LENGTH);
    $form->applyFilter('body', 'convert_to_unix_newlines');

    add_all_variables_hidden($form, $fyr_values);

    $buttons[0] =& HTML_QuickForm::createElement('static', 'static1', null, 
            "<b>Ready? Press the \"Preview\" button to continue</b><br>"); // TODO: remove <b>  from here
    $buttons[1] =& HTML_QuickForm::createElement('submit', 'submitPreview', 'preview your Message >>');
    $form->addGroup($buttons, 'previewStuff', '', '&nbsp;', false);

    return $form;
}

function buildPreviewForm()
{
    $form = new HTML_QuickForm('previewForm', 'post', 'write');

    global $fyr_values;
    add_all_variables_hidden($form, $fyr_values);

    $buttons[0] =& HTML_QuickForm::createElement('submit', 'submitWrite', '<< edit this Message');
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
    debug("FRONTEND", "Form values:", $fyr_values);

    // Make HTML
    $fyr_form = $renderer->toHtml();

    global $fyr_preview, $fyr_representative, $fyr_voting_area, $fyr_date;
    $our_values = array_merge($fyr_values, array('representative' => $fyr_representative, 
            'voting_area' => $fyr_voting_area, 'form' => $fyr_form, 
            'date' => $fyr_date));

    if ($pageName == "writeForm") {
        template_draw("write-write", $our_values);
    } else { // previewForm
        // Generate preview
        $fyr_preview = template_string("fax-content", $our_values);
        template_draw("write-preview", array_merge($our_values, array('preview' => $fyr_preview)));
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

    // check message not too long
    if (strlen($fyr_values['body']) > OPTION_MAX_BODY_LENGTH) {
        template_show_error("Sorry, but your message is a bit too long
        for our service.  Please make it shorter, or contact your
        representative by some other means.");
    }

    $result = msg_write($msgid, 
            array( 
            'name' => $fyr_values['writer_name'],
            'email' => $fyr_values['writer_email'], 
            'address' => $address,
            'postcode' => $fyr_values['pc'],
            'phone' => $fyr_values['writer_phone'], 
            'referrer' => $fyr_values['fyr_extref'],
            'ipaddr' =>  $_SERVER['REMOTE_ADDR']
            ),
            $fyr_values['who'], 
            $fyr_values['signedbody']);

    /* $result is an RABX error, the name of a template to redirect to, or null
     * on success. */
    if (isset($result)) {
        if (rabx_is_error($result)) {
            if ($result->code == FYR_QUEUE_MESSAGE_ALREADY_QUEUED) 
                template_show_error("You've already sent this message.  To send a new message, please <a href=\"/\">start again</a>.");
            else
                template_show_error("Sorry, an error has occured. Please contact <a href=\"mailto:help@writetothem.com\">help@writetothem.com</a>.");
        } else {
            /* Result is the name of a template page to be shown to the user.
             * XXX For the moment assume that we can just redirect to it. */
            header("Location: /$result");
            exit;
        }
    }

    if (!isset($result)) {
        global $fyr_representative, $fyr_voting_area, $fyr_date;
        $our_values = array_merge($fyr_values, array('representative' => $fyr_representative, 
                    'voting_area' => $fyr_voting_area, 'date' => $fyr_date));
        template_draw("write-checkemail", $our_values);
    } else
        template_show_error("Failed to queue the mesage"); // TODO improve this error message
}

// Get all fyr_values
$fyr_values = get_all_variables();
debug("FRONTEND", "All variables:", $fyr_values);
if (!array_key_exists('pc', $fyr_values) || $fyr_values['pc'] == "") {
    template_show_error("Please <a href=\"/\">start from the beginning</a>.");
    exit;
}
$fyr_values['pc'] = strtoupper(trim($fyr_values['pc']));
if (!isset($fyr_values['fyr_extref']))
    $fyr_values['fyr_extref'] = fyr_external_referrer();

// Various display and used fields
$fyr_postcode = $fyr_values['pc'];
$fyr_who = $fyr_values['who'];
$fyr_date = strftime('%A %e %B %Y');

if (!isset($fyr_who)) {
    header("Location: who?pc=" . urlencode($fyr_postcode) . "&err=1\n");
    exit;
}

// Rate limiter
$limit_values = array('postcode' => array($fyr_postcode, "Postcode that's been typed in"), 
                     'who' => array($fyr_who, "Representative id from DaDem"));
if (array_key_exists('body', $fyr_values) and strlen($fyr_values['body']) > 0) {
    $limit_values['body'] = array($fyr_values['body'], "Body text of message");
}
fyr_rate_limit($limit_values);

// Message id for transaction with fax queue
if (array_key_exists('fyr_msgid', $fyr_values))
    $msgid = $fyr_values['fyr_msgid'];
else {
    $msgid = msg_create();
    msg_check_error($msgid);
    $fyr_values['fyr_msgid'] = $msgid;
}

// Information specific to this representative
debug("FRONTEND", "Representative $fyr_who");
$fyr_representative = dadem_get_representative_info($fyr_who);
dadem_check_error($fyr_representative);
// The voting area is the ward/division. e.g. West Chesterton Electoral Division
$fyr_voting_area = mapit_get_voting_area_info($fyr_representative['voting_area']);
mapit_check_error($fyr_voting_area);

// Reverify that the representative represents this postcode
$verify_voting_area_map = mapit_get_voting_areas($fyr_values['pc']);
if (is_array($verify_voting_area_map)) 
    $verify_voting_areas = array_values($verify_voting_area_map);
else 
    $verify_voting_areas = array();
if (!in_array($fyr_representative['voting_area'], $verify_voting_areas)) {
   template_show_error("There's been a mismatch error.  Sorry about
       this, <a href=\"/\">please start again</a>.");
}

// Check the contact method exists
$success = msg_recipient_test($fyr_values['who']);
if (rabx_is_error($success)) {
    if ($success->code == FYR_QUEUE_MESSAGE_BAD_ADDRESS_DATA) 
        template_show_error("Sorry, we do not have contact
        details for this representative, so cannot send them a
        message. Please <a href=\"mailto:help@writetothem.com\">email us</a> to let us know.
        Details: " . $success->text);
    if ($success->code == FYR_QUEUE_MESSAGE_SHAME) 
    {
        if ($fyr_voting_area['type'] == 'WMC') {
            template_show_error(
            $fyr_voting_area['rep_prefix'] . " " .
            $fyr_representative['name'] . " " .
            $fyr_voting_area['rep_suffix'].  " has told us not to
            deliver any messages from the constituents of " .
            $fyr_voting_area['name'] . ".
            Instead you can try contacting them via the 
            <a
            href=\"http://www.locata.co.uk/cgi-bin/phpdriver?MIval=hoc_search&postcode="
            . urlencode($fyr_postcode) . "\">the Parliament
            website</a>.  There you will get a phone
            number, a postal address, and for some MPs a way to contact
            them by email.
            ");
        } else {
            template_show_error(
            $fyr_voting_area['rep_prefix'] . " " . $fyr_representative['name'] . " " .
            $fyr_voting_area['rep_suffix'].  " has told us not to deliver any
            messages from the constituents of " . $fyr_voting_area['name'] . ".
            Please <a href=\"mailto:help@writetothem.com\">email us</a> to
            let us know what you think about this.");
        }
    }
    template_show_error($success->text);
}

// Generate signature
if (array_key_exists('writer_email', $fyr_values) && array_key_exists('body', $fyr_values)) {
    $fyr_values['signature'] = sha1($fyr_values['writer_email']);
    $fyr_values['signedbody'] = $fyr_values['body'] . "\n\n" .  $fyr_values['signature'] .  "\n(Signed with an electronic signature in accordance with subsection 7(3) of the Electronic Communications Act 2000.)";
} else if (array_key_exists('body', $fyr_values))
    $fyr_values['signedbody'] = $fyr_values['body'];

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
    template_show_error("On an unknown page");
}

?>
