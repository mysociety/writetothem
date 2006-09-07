<?
/*
 * write.php:
 * Page where they enter details, write their message, and preview it
 *
 * Copyright (c) 2004 UK Citizens Online Democracy. All rights reserved.
 * Email: francis@mysociety.org. WWW: http://www.mysociety.org
 *
 * $Id: write.php,v 1.108 2006-09-07 12:52:00 francis Exp $
 *
 */

require_once "../phplib/fyr.php";
require_once "../phplib/forms.php";
require_once "../phplib/queue.php";

require_once "../../phplib/mapit.php";
require_once "../../phplib/dadem.php";
require_once "../../phplib/votingarea.php";
require_once "../../phplib/utility.php";

function fix_dear_lord_address($name) {
    // Lords are addressed specially at the start of letters:
    // http://www.parliament.uk/directories/house_of_lords_information_office/address.cfm
    $name = str_replace("Baroness ", "Lady ", $name);
    $name = str_replace("Viscount ", "Lord ", $name);
    $name = str_replace("Countess ", "Lady ", $name);
    $name = str_replace("Marquess ", "Lord ", $name);
    $name = preg_replace('#^The #', '', $name);
    $name = preg_replace("#^Bishop #", "Lord Bishop ", $name);
    $name = str_replace("Earl ", "Lord ", $name);
    # If anyone thinks of a counter-example to this, please let me know.
    $name = str_replace('Lord of ', 'Lord ', $name);
    $name = str_replace('Lady of ', 'Lady ', $name);
    return $name;
}

function default_body_text() {
        global $fyr_representative;
        return "Dear " .  fix_dear_lord_address($fyr_representative['name']) . ",\n\n\n\nYours sincerely,\n\n";
}
function default_body_regex() {
        global $fyr_representative;
        return '^Dear ' .  fix_dear_lord_address($fyr_representative['name']) . ',\s+Yours sincerely,\s+';
}
function default_body_notsigned() {
        global $fyr_representative;
        return 'Yours sincerely,\s+$';
}


class RuleAlteredBodyText extends HTML_QuickForm_Rule {
    function validate($value, $options) {
        return !preg_match('#'.default_body_regex().'#', $value);
    }
}

class RuleSigned extends HTML_QuickForm_Rule {
    function validate($value, $options) {
        return !preg_match('#'.default_body_notsigned().'#', $value);
    }
}

class RulePostcode extends HTML_QuickForm_Rule {
    function validate($value, $options) {
        return validate_postcode($value);
    }
}

function compare_email_addrs($F) {
    if (!isset($F['writer_email2']) || !isset($F['writer_email']) || $F['writer_email'] != $F['writer_email2'])
        return array('writer_email' => "The two email addresses you've entered differ;<br>please check them carefully for mistakes");
    return true;
}

// Class representing form they enter message of letter in
function buildWriteForm()
{
    $form = new HTML_QuickForm('writeForm', 'post', 'write');
    global $fyr_values, $fyr_postcode, $fyr_who;
    global $fyr_representative, $fyr_voting_area, $fyr_date;
    global $fyr_postcode_editable;

    if ($fyr_voting_area['name']=='United Kingdom')
    	$fyr_voting_area['name'] = 'House of Lords';

    // TODO: CSS this:
    $stuff_on_left = <<<END
            <strong>Now Write Your Message:</strong> <small>(* means required)</small><br><br>
            ${fyr_voting_area['rep_prefix']}
            ${fyr_representative['name']}
            ${fyr_voting_area['rep_suffix']}
            <br>${fyr_voting_area['name']}
            <br><br>$fyr_date
END;

    // special formatting for letter-like code, TODO: how do this properly with QuickHtml?
    $form->addElement("html", "<tr><td valign=\"top\">$stuff_on_left</td><td align=\"right\">\n<table>"); // CSSify

    $form->addElement('text', 'writer_name', "Your name:<sup>*</sup>", array('size' => 20, 'maxlength' => 255));
    $form->addRule('writer_name', 'Please enter your name', 'required', null, null);
    $form->applyFilter('writer_name', 'trim');

    $form->addElement('text', 'writer_address1', "Address 1:<sup>*</sup>", array('size' => 20, 'maxlength' => 255));
    $form->addRule('writer_address1', 'Please enter your address', 'required', null, null);
    $form->applyFilter('writer_address1', 'trim');

    $form->addElement('text', 'writer_address2', "Address 2:", array('size' => 20, 'maxlength' => 255));
    $form->applyFilter('writer_address2', 'trim');

    $form->addElement('text', 'writer_town', "Town/City:<sup>*</sup>", array('size' => 20, 'maxlength' => 255));
    $form->addRule('writer_town', 'Please enter your town', 'required', null, null);
    $form->applyFilter('writer_town', 'trim');

    $form->addElement('text', 'writer_county', 'County:', array('size' => 20, 'maxlength' => 255));
    $form->applyFilter('writer_county', 'trim');

    if ($fyr_postcode_editable) {
        // House of Lords
        $form->addElement('text', 'pc', "UK Postcode:<sup>*</sup>", array('size' => 20, 'maxlength' => 255));
        $form->addRule('pc', 'Please enter a UK postcode (<a href="/about-lords#ukpostcode" target="_blank">why?</a>)', 'required', null, null);
        $form->addRule('pc', 'Choose a valid UK postcode (<a href="/about-lords#ukpostcode" target="_blank">why?</a>)', new RulePostcode(), null, null);
        $form->applyFilter('pc', 'trim');
    } else {
        // All other representatives (postcode fixed as must be in constituency)
        $form->addElement('static', 'staticpc', 'UK Postcode:', htmlentities($fyr_postcode));
    }

    $form->addElement('text', 'writer_email', "Email:<sup>*</sup>", array('size' => 20, 'maxlength' => 255));
    $form->addRule('writer_email', 'Please enter your email address', 'required', null, null);
    $form->addRule('writer_email', 'Choose a valid email address', 'email', null, null);
    $form->applyFilter('writer_email', 'trim');

    $form->addElement('text', 'writer_email2', "Confirm email:<sup>*</sup>", array('size' => 20, 'maxlength' => 255));
    $form->addRule('writer_email2', 'Please re-enter your email address', 'required', null, null);
    $form->addFormRule('compare_email_addrs');

    /* add additional text explaining why we ask for email address twice? */

    #    $form->addElement("html", "</td><td colspan=2><p style=\"margin-top: 0em; margin-bottom: -0.2em\"><em style=\"font-size: 75%\">Optional, to let your {$fyr_voting_area['rep_name']} contact you more easily:</em>"); // CSSify

    $form->addElement('text', 'writer_phone', "Phone:", array('size' => 20, 'maxlength' => 255));
    $form->applyFilter('writer_phone', 'trim');

    // special formatting for letter-like code, TODO: how do this properly with QuickHtml?
    $form->addElement("html", "</table>\n</td></tr>");

    $form->addElement('textarea', 'body', null, array('rows' => 15, 'cols' => 62));
    $form->addRule('body', 'Please enter your message', 'required', null, null);
    $form->addRule('body', 'Please enter your message', new RuleAlteredBodyText(), null, null);
    $form->addRule('body', 'Please sign at the bottom with your name, or alter the "Yours sincerely" signature', new RuleSigned(), null, null);
    $form->addRule('body', 'Your message is a bit too long for us to send', 'maxlength', OPTION_MAX_BODY_LENGTH);

    add_all_variables_hidden($form, $fyr_values);

    $form->addElement("html", '<script type="text/javascript">document.write(\'<tr><td><input name="doSpell" type="button" value="Check spelling" onClick="openSpellChecker(document.writeForm.body);"/></td></tr>\')</script>');

    $buttons[0] =& HTML_QuickForm::createElement('static', 'staticpreview', null,
            "<b>Ready? Press the \"Preview\" button to continue</b><br>"); // TODO: remove <b>  from here
    $buttons[2] =& HTML_QuickForm::createElement('submit', 'submitPreview', 'preview your Message');
    $form->addGroup($buttons, 'previewStuff', '', '&nbsp;', false);

    return $form;
}

function buildPreviewForm() {
    global $fyr_values;
    $form = '<form method="post" action="write" id="previewForm" name="previewForm"><div id="buttonbox">';
    $form .= add_all_variables_hidden_nonQF($fyr_values);
    $form .= '<input type="submit" name="submitWrite" value="Re-edit this message">
<input type="submit" name="submitSendFax" value="Send Message">
</div></form>';
    return $form;
}

function renderForm($form, $pageName)
{
    global $fyr_form, $fyr_values;
    debug("FRONTEND", "Form values:", $fyr_values);

    // $renderer =& $page->defaultRenderer();
    if (is_object($form)) {
        $renderer =& new HTML_QuickForm_Renderer_mySociety();
        $renderer->setGroupTemplate('<TR><TD ALIGN=right colspan=2> {content} </TD></TR>', 'previewStuff'); // TODO CSS this
        $renderer->setElementTemplate('{element}', 'previewStuff');
        $renderer->setElementTemplate('
        <!-- BEGIN error -->
        <TR><TD colspan=2>
        <span style="color: #ff0000"><br>{error}:</span>
        </TD></TR>
        <!-- END error -->
        <TR><TD colspan=2>
        {element}
        </TD></TR>', 'body');
        $form->accept($renderer);

    // Make HTML
        $fyr_form = $renderer->toHtml();
        $fyr_form = preg_replace('#(<form.*?>)(.*?)(</form>)#s','$1<div id="writebox">$2</div>$3',$fyr_form);
    } else {
        $fyr_form = $form;
    }

    // Add time-shift warning if in debug mode
    $fyr_today = msg_get_date();
    msg_check_error($fyr_today);
    if ($fyr_today != date('Y-m-d')) {
        $fyr_form = "<p style=\"text-align: center; color: #ff0000; \">Note: On this test site, the date is faked to be $fyr_today</p>" . $fyr_form;
    }

    $prime_minister = false;
    global $fyr_preview, $fyr_representative, $fyr_voting_area, $fyr_date;
    if ($fyr_values['who'] == 1702) {
        $prime_minister = true;
    }

    $cobrand_letter_help = false;
    global $cobrand;
    if ($pageName == 'writeForm' && $cobrand) {
        if ($cobrand == 'animalaid' && $fyr_values['cocode']) {
            $cobrand_letter_help = file_get_contents("http://www.animalaiduk.com/functions/custom/action_snippet.php?id=" . $fyr_values['cocode']);
            $cobrand_letter_help = str_replace('<h1>', '<h2>', $cobrand_letter_help);
            $cobrand_letter_help = str_replace('</h1>', '</h2>', $cobrand_letter_help);
        }
    }
    
    $our_values = array_merge($fyr_values, array('representative' => $fyr_representative,
            'voting_area' => $fyr_voting_area, 'form' => $fyr_form,
            'date' => $fyr_date, 'prime_minister' => $prime_minister,
            'cobrand_letter_help' => $cobrand_letter_help));

    $our_values['spell'] = "true";

    if ($pageName == "writeForm") {
        template_draw("write-write", $our_values);
    } else if ($pageName == 'previewForm') {
        // Generate preview
        /* Horrid. We need to turn leading spaces into non-breaking spaces, so
         * that indentation appears roughly the same in the preview as it will
         * in the final fax. So we need to delve into the exciting world of
         * PHP's preg_replace. Because the text will get escaped for HTML
         * entities later, present those leading spaces as U+0000A0 NO-BREAK
         * SPACE. But we can't use the obvious combination of preg_replace, the
         * "e modifier" and str_repeat, because preg_replace with the "e
         * modifier" is not safe, since the subexpressions are injected into
         * the expression by textual substitution(!). So instead we perform
         * repeated substitutions until there are no further changes. This is
         * a complete pain, but then that's what you get for using a language
         * with a rubbish API and no functional features. */
        $t1 = $our_values['body'];
        $t2 = null;
        do {
            $t2 = $t1;
            $t1 = preg_replace('/^((?: )*)( )/m', '\1 ', $t2);
        } while ($t1 != $t2);
        $our_values['body_indented'] = $t1;
        $fyr_preview = template_string("fax-content", $our_values);
        template_draw("write-preview", array_merge($our_values, array('preview' => $fyr_preview)));
    } else {
        template_show_error(
                'Sorry. An error has occurred: pageName "'
                    . htmlspecialchars($pageName) .
                '". Please get in touch with us at
                <a href="mailto:team@writetothem.com">team@writetothem.com</a>,
                quoting this message. You can <a href="/">try again from the
                beginning</a>.'
            );
    }
}

function submitFax() {
    global $fyr_values, $msgid;

    $address =
        $fyr_values['writer_address1'] . "\n" .
        $fyr_values['writer_address2'] . "\n" .
        $fyr_values['writer_town'] . "\n" .
        $fyr_values['writer_county'] . "\n" .
        $fyr_values['pc'] . "\n";
    $address = str_replace("\n\n", "\n", $address);

    // check message not too long
    if (strlen($fyr_values['body']) > OPTION_MAX_BODY_LENGTH) {
        template_show_error("Sorry, but your message is a bit too long
        for our service.  Please make it shorter, or contact your
        representative by some other means.");
    }

    /* Check that they've come back with a valid message ID. Really we should
     * be verifying all the data that we've retrieved from the browser with a
     * hash, but in this case it doesn't matter. */
    if (!preg_match("/^[0-9a-f]{20}$/i", $msgid)) {
        template_show_error('Sorry, but your browser seems to be transmitting
            erroneous data to us. Please try again, or contact us at
            <a href="mailto:team@writetothem.com">team@writetothem.com</a>.');
    }

    global $cobrand;
    $cocode = $fyr_values['cocode'];
    if (!$cocode)
        $cocode = null;
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
            $fyr_values['signedbody'],
            $cobrand, $cocode
    );

    /* $result is an RABX error, the name of a template to redirect to, or null
     * on success. */
    if (isset($result)) {
        if (rabx_is_error($result)) {
            if ($result->code == FYR_QUEUE_MESSAGE_ALREADY_QUEUED)
                template_show_error("You've already sent this message.  To send a new message, please <a href=\"/\">start again</a>.");
            else
                error_log("write.php msg_write error: " . $result->text);
                template_show_error("Sorry, an error has occured. Please contact <a href=\"mailto:team&#64;writetothem.com\">team&#64;writetothem.com</a>.");
        } else {
            /* Result is the name of a template page to be shown to the user.
             * XXX For the moment assume that we can just redirect to it. */
            header("Location: /$result");
        }
    } else {
        /* Show them the "check your email and click the link" template. */
        global $fyr_representative, $fyr_voting_area, $fyr_date;
        $our_values = array_merge($fyr_values, array('representative' => $fyr_representative,
                    'voting_area' => $fyr_voting_area, 'date' => $fyr_date));
        template_draw("write-checkemail", $our_values);
    }
}

// Get all fyr_values
$fyr_values = get_all_variables();

// Normalise text part of message here, before we modify it.
if (array_key_exists('body', $fyr_values))
    $fyr_values['body'] = convert_to_unix_newlines($fyr_values['body']);

if (!array_key_exists('pc', $fyr_values) || $fyr_values['pc'] == "") {
    $fyr_values['pc'] = "";
}

debug("FRONTEND", "All variables:", $fyr_values);
$fyr_values['pc'] = strtoupper(trim($fyr_values['pc']));
if (!isset($fyr_values['fyr_extref']))
    $fyr_values['fyr_extref'] = fyr_external_referrer();
if (!isset($fyr_values['cocode']))
    $fyr_values['cocode'] = get_http_var('cocode');

// Various display and used fields
$fyr_postcode = $fyr_values['pc'];
if (array_key_exists('who', $fyr_values))
    $fyr_who = $fyr_values['who'];
$fyr_time = msg_get_time();
msg_check_error($fyr_time);
$fyr_date = strftime('%A %e %B %Y', $fyr_time);

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

// For URLs like http://writetothem.com/?a=WMC;pc=XXXXX
if (in_array($fyr_representative['type'], $disabled_child_types)) {
    header("Location: who?pc=" . urlencode($fyr_postcode) . "&err=1\n");
    exit;
}

// Reverify that the representative represents this postcode
$verify_voting_area_map = mapit_get_voting_areas($fyr_values['pc']);
if (is_array($verify_voting_area_map))
    $verify_voting_areas = array_values($verify_voting_area_map);
else
    $verify_voting_areas = array();
$fyr_postcode_editable = false;
if (in_array($fyr_representative['type'], $postcodeless_child_types)) {
    $fyr_postcode_editable = true;
}
if (!$fyr_postcode_editable) {
    if (!in_array($fyr_representative['voting_area'], $verify_voting_areas)) {
       template_show_error("There's been a mismatch error.  Sorry about
           this, <a href=\"/\">please start again</a>.");
    }
}

// Get the electoral body information
$eb_type = $va_inside[$fyr_voting_area['type']];
if (array_key_exists($eb_type, $verify_voting_area_map)) {
    $eb_id = $verify_voting_area_map[$eb_type];
} else {
    $eb_id = $fyr_voting_area['parent_area_id'];
}
$eb_area_info = mapit_get_voting_area_info($eb_id);
mapit_check_error($eb_area_info);

// Check the contact method exists
$success = msg_recipient_test($fyr_values['who']);
if (rabx_is_error($success)) {
    if ($success->code == FYR_QUEUE_MESSAGE_BAD_ADDRESS_DATA) {
        $type_display_name = $eb_area_info['general_prep'] . " " . $eb_area_info['type_name'];
        if ($type_display_name == "the House of Commons")
            $type_display_name .= " (020 7219 3000)";
        template_show_error("
Sorry, we <strong>do not currently have contact details for this representative</strong>, and are unable to send
them a message. We may have had details in the past, which have since proven to be erroneous. 

Please <a href=\"mailto:team&#64;writetothem.com\">email us</a> to encourage us to find the contact details.

We'd be <em>really</em> grateful if you could <strong>spend five minutes on the phone
to $type_display_name</strong>, asking for the contact details.
Then <a href=\"mailto:team&#64;writetothem.com\">email us</a> with the email address or fax number of
your representative.
"
             # htmlspecialchars($success->text) # not helpful to user
        );
    } else if ($success->code == FYR_QUEUE_MESSAGE_SHAME) {
        if ($fyr_voting_area['type'] == 'WMC') {
            $url = 'http://www.locata.co.uk/cgi-bin/phpdriver?MIval=hoc_search&postcode=' . urlencode($fyr_postcode);
            template_show_error(<<<EOF
$fyr_voting_area[rep_prefix] $fyr_representative[name] $fyr_voting_area[rep_suffix]
has told us not to deliver any messages from the constituents of
$fyr_voting_area[name]. Instead you can try contacting them via
<a href="$url">the Parliament website</a>. There you will get a phone number, a
postal address, and for some MPs a way to contact them by email.
EOF
                );
        } else {
            template_show_error(<<<EOF
$fyr_voting_area[rep_prefix] $fyr_representative[name] $fyr_voting_area[rep_suffix]
has told us not to deliver any messages from the constituents of
$fyr_voting_area[name]. Please <a href="mailto:team&#64;writetothem.com">email
us</a> to let us know what you think about this.
EOF
                );
        }
    }
    template_show_error($success->text);
}

// Generate signature
if (array_key_exists('writer_email', $fyr_values) && array_key_exists('body', $fyr_values)) {
    $fyr_values['signature'] = sha1($fyr_values['writer_email']);
    $fyr_values['signature'] = substr_replace($fyr_values['signature'], '/', 20, 0);
    $fyr_values['signedbody'] = <<<EOF
$fyr_values[body]

$fyr_values[signature]
(Signed with an electronic signature in accordance with subsection 7(3) of the Electronic Communications Act 2000.)
EOF;
} else if (array_key_exists('body', $fyr_values))
    $fyr_values['signedbody'] = $fyr_values['body'];

// Work out which page we are on, using which submit button was pushed
// to get here
$on_page = "write";
if (isset($fyr_values['submitWrite'])) {
    $on_page = "write";
    unset($fyr_values['submitWrite']);
} else if (isset($fyr_values['submitPreview'])) {
    unset($fyr_values['submitPreview']);
    $writeForm = buildWriteForm();
    if ($writeForm->validate()) {
        $on_page = "preview";
    } else {
        $on_page = "write";
    }
} else if (isset($fyr_values['submitSendFax'])) {
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
} else if ($on_page == "preview") {
    $previewForm = buildPreviewForm();
#    $previewForm->setConstants($fyr_values); # WHAT DID THIS LINE DO?
    renderForm($previewForm, "previewForm");
} else if ($on_page =="sendfax") {
    submitFax();
} else {
    template_show_error(
            'Sorry. An error has occurred: on_page "'
                . htmlspecialchars($on_page) .
            '". Please get in touch with us at
            <a href="mailto:team@writetothem.com">team@writetothem.com</a>,
            quoting this message. You can <a href="/">try again from the
            beginning</a>.'
        );
}

?>
