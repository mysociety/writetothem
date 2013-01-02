<?
/*
 * write.php:
 * Page where they enter details, write their message, and preview it
 *
 * Copyright (c) 2012 UK Citizens Online Democracy. All rights reserved.
 * Email: matthew@mysociety.org. WWW: http://www.mysociety.org
 *
 */

require_once "../phplib/fyr.php";
require_once "../phplib/forms.php";
require_once "../phplib/queue.php";
require_once "../commonlib/phplib/mapit.php";
require_once "../commonlib/phplib/dadem.php";
require_once "../commonlib/phplib/votingarea.php";
require_once "../commonlib/phplib/utility.php";

function fix_dear_lord_address($name) {
    /* Lords are addressed specially at the start of letters:
     * http://www.parliament.uk/directories/house_of_lords_information_office/address.cfm */
    
    # RT ticket 10264 
    # https://secure.mysociety.org/rt/Ticket/Display.html?id=10264
    if ($name == 'Baroness Sarah Ludford' || $name == 'Baroness Ludford')
        return 'Baroness Ludford';

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

function group_address() {
    /* Generate a comma-separated list of correct address forms
     * for a list of representatives */
    global $stash;
    $i = 0;
    $names = array();
    foreach ($stash['valid_reps'] as $rep) {
        $names[$i] = $rep['name'];
        $i++;
    }
    $address = implode( ', ', array_slice($names, 0, $i-1)) . ' and ' . $names[$i-1] ;
    return $address;
}

function is_postcode_editable($rep_type) {
    global $postcodeless_child_types;
    if (in_array($rep_type, $postcodeless_child_types)) {
        return true;
    }
    return false;
}

/* Generate an error string for when contact details for
 * a representative are not available */
function bad_contact_error_msg($eb_area) {
    global $va_council_parent_types;
    $via_error = '';
    if (in_array($eb_area['type'], $va_council_parent_types))
        $via_error = '; or we might only have a central contact for the council, which similarly might not be working';

    $general_prep = array(
        'LBO' => "",
        'LAS' => "the",
        'CTY' => "",
        'DIS' => "",
        'UTA' => "",
        'MTD' => "",
        'COI' => "",
        'LGD' => "",
        'SPA' => "the",
        'WAS' => "the",
        'NIA' => "the",
        'WMP' => "the",
        'HOL' => "the",
        'EUP' => "the",
    );
    $type_display_name = $general_prep[$eb_area['type']] . " " . $eb_area['name'];
    $type_display_phone = '';
    if ($type_display_name == "the House of Commons") {
        $type_display_name = ' the MP, the House of Commons';
        $type_display_phone = ', 020 7219 3000';
    }
    $error_msg = "
    Sorry, we <strong>do not currently have contact details for this representative</strong>, and are unable to send
    them a message. We may have had details in the past, which are currently not working (perhaps their mailbox is
    full) or incorrect$via_error.

    We'd be <em>really</em> grateful if you could <strong>spend five minutes on the website of
    $type_display_name</strong> (or even the phone$type_display_phone), finding out the contact details.
    Then <a href='/about-contact'>contact us</a> with the email address or fax number of
    your representative.
    ";
    return $error_msg;
}

function shame_error_msg($fyr_voting_area, $fyr_representative) {
    /* Generate an error string for when a representative has
     * requested not to be contacted */
    global $fyr_values;
    if ($fyr_voting_area['type'] == 'WMC') {
        $url = 'http://findyourmp.parliament.uk/postcodes/' . urlencode(str_replace(' ', '', $fyr_values['pc']));
        $error_msg = <<<EOF
$fyr_voting_area[rep_prefix] $fyr_representative[name] $fyr_voting_area[rep_suffix]
has told us not to deliver any messages from the constituents of
$fyr_voting_area[name]. Instead you can try looking them up on
<a href="$url">the Parliament website</a>. There you will get a phone number, a
postal address, and for some MPs a website or way to contact them by email.
EOF;

    } else {
        $error_msg = <<<EOF
$fyr_voting_area[rep_prefix] $fyr_representative[name] $fyr_voting_area[rep_suffix]
has told us not to deliver any messages from the constituents of
$fyr_voting_area[name].
EOF;

    }
    return $error_msg;
}

function correct_address() {
    /* Generate a correct form of address for a representative
     * or group of reprsentatives */

    global $fyr_representative, $stash;
    $address = "";
    if ($stash['group_msg']) {
        $address = group_address();
    } else {
        $address = fix_dear_lord_address($fyr_representative['name']);
    }
    return $address;
}

function default_body_text() {     
    return "Dear " . correct_address() . ",\n\n\n\nYours sincerely,\n\n";
}

function default_body_regex() {
    return '^Dear ' . correct_address() . ',\s+Yours sincerely,\s+';
}

function default_body_notsigned() {
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
        return validate_postcode(canonicalise_postcode($value));
    }
}

function compare_email_addrs($F) {
    global $cobrand;
    if (!isset($F['writer_email2']) || !isset($F['writer_email']) || $F['writer_email'] != $F['writer_email2']) {
        $error_message = cobrand_mismatched_emails_message($cobrand);
        if (!$error_message) {
             $error_message = "The two email addresses you've entered differ;<br>please check them carefully for mistakes";
        }
        return array('writer_email' => $error_message);
    }
    return true;
}


// Class representing form they enter message of letter in
function buildWriteForm($options) {
    global $fyr_values, $stash;
    global $fyr_voting_area;
    global $cobrand, $cocode;

    $form_action = cobrand_url($cobrand, '/write', $cocode);
    $form = new HTML_QuickForm('writeForm', 'post', $form_action);
    
    if ($fyr_voting_area['name']=='United Kingdom')
        $fyr_voting_area['name'] = 'House of Lords';
 
    $write_header = '';
    if ($options['include_write_header']){
        $write_header = "<strong>Now Write Your Message:</strong> <small>(* means required)</small><br><br>";
    }

    if ($options['include_fao']){
        $write_header = '<strong>For the attention of:</strong>';
    }

    $stuff_on_left = <<<END
            <div class="letter-header">
            ${write_header}
            ${stash['rep_text']}
            <span>${fyr_voting_area['name']}</span>
            <span>${stash['date']}</span>
            </div>
END;
    // special formatting for letter-like code, TODO: how do this properly with QuickHtml?
    if ($options['table_layout']){
        $form->addElement("html", "<tr><td valign=\"top\">$stuff_on_left</td><td align=\"right\">\n<table>"); // CSSify
    } else {
        $form->addElement("html", "<div class=\"highlight\">$stuff_on_left<ul class=\"data-input\">");
    }  

    $form->addElement('text', 'name', "Your name:<sup>*</sup>", array('size' => 20, 'maxlength' => 255));
    $form->addRule('name', 'Please enter your name', 'required', null, null);
    $form->applyFilter('name', 'trim');

    $form->addElement('text', 'writer_address1', "Address 1:<sup>*</sup>", array('size' => 20, 'maxlength' => 255));
    $form->addRule('writer_address1', 'Please enter your address', 'required', null, null);
    $form->applyFilter('writer_address1', 'trim');

    $form->addElement('text', 'writer_address2', "Address 2:", array('size' => 20, 'maxlength' => 255));
    $form->applyFilter('writer_address2', 'trim');

    $form->addElement('text', 'writer_town', "Town/City:<sup>*</sup>", array('size' => 20, 'maxlength' => 255));
    $form->addRule('writer_town', 'Please enter your town/city', 'required', null, null);
    $form->applyFilter('writer_town', 'trim');

    # Call it state so that Google Toolbar (and presumably others) can auto-fill.
    $form->addElement('text', 'state', 'County:', array('size' => 20, 'maxlength' => 255));
    $form->applyFilter('state', 'trim');

    if (is_postcode_editable($fyr_voting_area['type'])) {
        // House of Lords
        $form->addElement('text', 'pc', "UK postcode:<sup>*</sup>", array('size' => 20, 'maxlength' => 255));
        $form->addRule('pc', 'Please enter a UK postcode (<a href="/about-lords#ukpostcode" target="_blank">why?</a>)', 'required', null, null);
        $form->addRule('pc', 'Choose a valid UK postcode (<a href="/about-lords#ukpostcode" target="_blank">why?</a>)', new RulePostcode(), null, null);
        $form->applyFilter('pc', 'trim');
    } else {
        // All other representatives (postcode fixed as must be in constituency)
        $form->addElement('static', 'staticpc', 'UK postcode:', htmlentities($fyr_values['pc']));
    }

    $form->addElement('text', 'writer_email', "Your email:<sup>*</sup>", array('size' => 20, 'maxlength' => 255));
    $form->addRule('writer_email', 'Please enter your email address', 'required', null, null);
    $invalid_email_message = cobrand_invalid_email_message($cobrand);
    if (!$invalid_email_message) {
         $invalid_email_message = 'Choose a valid email address';
    }
    $form->addRule('writer_email', $invalid_email_message, 'email', null, null);
    $form->applyFilter('writer_email', 'trim');

    $form->addElement('text', 'writer_email2', "Confirm email:<sup>*</sup>", array('size' => 20, 'maxlength' => 255));
    $form->addRule('writer_email2', 'Please re-enter your email address', 'required', null, null);
    $form->applyFilter('writer_email2', 'trim');
    $form->addFormRule('compare_email_addrs');

    /* add additional text explaining why we ask for email address twice? */

    #    $form->addElement("html", "</td><td colspan=2><p style=\"margin-top: 0em; margin-bottom: -0.2em\"><em style=\"font-size: 75%\">Optional, to let your {$fyr_voting_area['rep_name']} contact you more easily:</em>"); // CSSify

    $form->addElement('text', 'writer_phone', "Phone:", array('size' => 20, 'maxlength' => 255));
    $form->applyFilter('writer_phone', 'trim');

    // special formatting for letter-like code, TODO: how do this properly with QuickHtml?
    if ($options['table_layout']){
        $form->addElement("html", "</table>\n</td></tr>");
    } else {
        $form->addElement("html", "</ul>");
    }

    $form->addElement('textarea', 'body', null, array('rows' => 15, 'cols' => 62));
    $form->addRule('body', 'Please enter your message', 'required', null, null);
    $form->addRule('body', 'Please enter your message', new RuleAlteredBodyText(), null, null);
    $form->addRule('body', 'Please sign at the bottom with your name, or alter the "Yours sincerely" signature', new RuleSigned(), null, null);
    $form->addRule('body', 'Your message is a bit too long for us to send', 'maxlength', OPTION_MAX_BODY_LENGTH);
    if (!$options['table_layout']){
        $form->addElement("html", "</div>");
    }

    add_all_variables_hidden($form, $fyr_values, $options);
    $preview_text = cobrand_preview_text($cobrand);
    if (!$preview_text) {
        $preview_text = 'Ready? Press the "Preview" button to continue:';
    }
    $preview_button_text = cobrand_preview_button_text($cobrand);
    if (!$preview_button_text) {   
        $preview_button_text = 'preview your Message';
    }
    $buttons[0] =& HTML_QuickForm::createElement('static', 'staticpreview', null,"<p class=\"action\" id=\"preview-submit\">$preview_text"); 
    $buttons[2] =& HTML_QuickForm::createElement('submit', 'submitPreview', $preview_button_text);
    $buttons[3] =& HTML_QuickForm::createElement('static', 'staticpreview', null, "</p>");     
    $form->addGroup($buttons, 'previewStuff', '', '', false);

    return $form;
}

function buildPreviewForm($options) {
    global $fyr_values, $cobrand, $cocode;

    $form_action = cobrand_url($cobrand, '/write', $cocode);
    $form = '<form method="post" action="' . $form_action . '" id="previewForm" name="previewForm">';
    if ($options['inner_div']){
        $form .= '<div id="buttonbox">';
    }
    $form .= add_all_variables_hidden_nonQF($fyr_values);
    $form .= '<input type="submit" name="submitWrite" value="Re-edit this message">
<input type="submit" name="submitSendFax" value="Send Message">';
    if ($options['inner_div']){
        $form .= '</div>';
    }
    $form .= '</form>';
    return $form;
}

function renderForm($form, $pageName, $options)
{
    global $fyr_form, $fyr_values, $stash;
    global $fyr_representative, $fyr_voting_area;
    global $cobrand, $cocode;
    debug("FRONTEND", "Form values:", $fyr_values);
    
    // $renderer =& $page->defaultRenderer();
    if (is_object($form)) {
        $renderer =& $options['renderer'];
        if ($options['table_layout']){
            $renderer->setGroupTemplate('<TR><TD ALIGN=right colspan=2> {content} </TD></TR>', 'previewStuff'); // TODO CSS this
            $renderer->setElementTemplate('
            <!-- BEGIN error -->
            <TR><TD colspan=2>
            <span class="error">{error}:</span>
            </TD></TR>                                                   
            <!-- END error -->
            <TR><TD colspan=2>
            {element}
            </TD></TR>', 'body');
        } else {
            $renderer->setElementTemplate('
            <!-- BEGIN error -->
            <p class="error">{error}:</p>
            <!-- END error -->
            {element}', 'body');
        }
        $renderer->setElementTemplate('{element}', 'previewStuff');
        $form->accept($renderer);
    // Make HTML
        $fyr_form = $renderer->toHtml();
        if ($options['table_layout']){     
            $fyr_form = preg_replace('#(<form.*?>)(.*?)(</form>)#s','$1<div id="writebox">$2</div>$3',$fyr_form);
        }
    } else {
        $fyr_form = $form;
    }

    // Add time-shift warning if in debug mode
    if (OPTION_FYR_REFLECT_EMAILS) {
        $fyr_today = strftime('%Y-%m-%d', $stash['time']);
        if ($fyr_today != date('Y-m-d')) {
            $fyr_form = "<p style=\"text-align: center; color: #ff0000; \">Note: On this test site, the date is faked to be $fyr_today</p>" . $fyr_form;
        }
    }

    $prime_minister = false;
    if ($fyr_values['who'] == 47292) { # Hardcoded
        $prime_minister = true;
    }

    $cobrand_letter_help = false;
    if ($cobrand && $pageName == 'writeForm') {
        $cobrand_letter_help = cobrand_get_letter_help($cobrand, $fyr_values);
    }

    $our_values = array_merge($fyr_values, $stash, array(
            'representative' => $fyr_representative,
            'voting_area' => $fyr_voting_area,
            'form' => $fyr_form,
            'prime_minister' => $prime_minister,
            'cobrand_letter_help' => $cobrand_letter_help, 
            'cobrand' => $cobrand,
            'host' => fyr_get_host()
    ));

    if ($stash['group_msg']) {
        # check if there are any reps whose message will be sent via somewhere 
        # else
        $any_via = false;
        foreach ($stash['valid_reps'] as $rep) {
            if ($rep['method'] == 'via') {
                $any_via = true;
            }
        }
        $our_values['any_via'] = $any_via;
        $our_values['title_text'] = "your " . $fyr_voting_area['rep_name_long_plural'] ;
    } else {
        $our_values['title_text'] = trim($fyr_voting_area['rep_prefix'] . " " .
            $fyr_representative['name'] . " " . $fyr_voting_area['rep_suffix']) . ", " .
            $fyr_voting_area['name'];
    }
    if ($pageName == "writeForm") {
        template_draw("write-write", $our_values);
    } elseif ($pageName == 'previewForm') {
        // Generate preview
        $formatted_body = fyr_format_message_body_for_preview($our_values['body']);
        $our_values['body_indented'] = $formatted_body;
        $fyr_preview = template_string("fax-content", $our_values);
        template_draw("write-preview", array_merge($our_values, array('preview' => $fyr_preview)));
    } else {
        $message = cobrand_generic_error_message($cobrand, $cocode, $pageName);
        if (!$message) {
             $message = 'Sorry. An error has occurred: pageName "'
                    . htmlspecialchars($pageName) .
                '". Please <a href="/about-contact">get in touch with us</a>,
                quoting this message. You can <a href="/">try again from the
                beginning</a>.';
        }
        template_show_error($message);
    }
}

function submitFaxes() {

    /* Submit a group of messages or an individual message 
     * and show the results to the user */

    global $grpid, $msgid_list, $msgid;
    global $repid_list;
    global $representatives_info, $fyr_voting_area;
    global $fyr_values, $cobrand;
    
    // Set up some brief error descriptions
    $errors = cobrand_message_sending_errors($cobrand);
    if (!$errors) { 
        $errors = array("problem-generic" => "Message Rejected", 
                        "problem-lords" => "You have sent too many messages to Lords", 
                        "problem-lords-similar" => "Too many similar messages have been sent",
                        "problem-postcodes" => "You seem to be sending messages with several different postcodes", 
                        "problem-similar" => "Your message is near-identical with others sent previously");
    }
 
    // send the message to each representative
    $any_success = false;
    $error_msg = "";
    
    if ($grpid) {

        // No questionnaire for group mails
        $no_questionnaire = true;

        // check the group id  
        if (!preg_match("/^[0-9a-f]{20}$/i", $grpid)) {
            template_show_error('Sorry, but your browser seems to be transmitting
            erroneous data to us. Please try again, or <a href="/about-contact">contact us</a>.');
        }
        // double check that the group_id isn't already being used
        // This could mean that these messages have already been
        // queued or (with a very small probability) that someone
        // else got the same group id 
        $result = msg_check_group_unused($grpid);    
        if (isset($result)) {
            $error_msg .= rabx_mail_error_msg($result->code, $result->text) . "<br>";        
            template_show_error("Sorry, we were unable to send your messages for the following reasons: <br>" . $error_msg);
        }   
    } else {
        $no_questionnaire = false;
        $msgid_list = array($msgid);
        $repid_list = array($fyr_values['who']);
    }      
        
    # set up the address      
    $address = prepare_address();
    check_message_length();
    # check the msgids
    foreach ($msgid_list as $msgid) {
        check_message_id($msgid);
    }
    $cocode = $fyr_values['cocode'];
    if (!$cocode)
        $cocode = null;
    $message_array = prepare_message_array($address);
    $result = msg_write_messages($msgid_list,
                                     $message_array,
                                     $repid_list,
                                     $fyr_values['signedbody'],
                                     $cobrand, $cocode, $grpid, $no_questionnaire);       

    #check for error    
    if (rabx_is_error($result)) {
        template_show_error(rabx_mail_error_msg($result->code, $result->text));
    } 

    foreach (array_keys($result) as $id) {
        $res = $result[$id];
        $rep_id = $res['recipient_id'];        
        $abuse_res = $res['abuse_result'];
        $status = $res['status_code'];
        $err = $res['error_text'];
        $code = $res['error_code'];
        if ($status != 0) {
            $rep_name = "<strong>" . $fyr_voting_area['rep_prefix'] . " " .
            $representatives_info[$rep_id]['name'] . " " . $fyr_voting_area['rep_suffix'] . "</strong>";
            
            if ($status == 1) {
                # FYR Error code
                if ($grpid) { 
                    $error_msg .= $rep_name . ": " . rabx_mail_error_msg($code, $err) . "<br>";
                } else {
                    template_show_error(rabx_mail_error_msg($code, $err));
                }
            } elseif ($status == 2) {
                # flagged for abuse
                if ($grpid) {
                    if (array_key_exists($abuse_res, $errors)) {
                        $error_msg .= "<p>" . $rep_name . ": " . $errors[$abuse_res]
                            . " <a href=\"/"  . $abuse_res . "\">read more</a></p>";
                    } else {
                        $error_msg .= "<p>" .$rep_name . ": Message Rejected</p>";
                    }
                } else {
                    template_draw($abuse_res,  $fyr_values);
                    exit;
                }
            }
        } else {
            $any_success = true;
        }   

    }
          
    if (!$any_success) {
        // None of the messages could be sent
        template_show_error("Sorry, we were unable to send your messages for the following reasons: <br>" . $error_msg);
    } elseif ($error_msg) {
        // Some problems 
        $error_msg = "
    <p style=\"text-align: center; color: #ff0000; \">Note: 
    Some of your messages could not be sent for the following reasons: </p>
    " . $error_msg;
        show_check_email($error_msg);
    } else {
        //no problems
        show_check_email($error_msg);
    } 
 
}

function rabx_mail_error_msg($code, $text) { 
    global $cobrand, $cocode;
     /* Return an appropriate error message for a RABX error code. 
      * Log errors other than multiple send attempts */
     
    $error_msg = "";
    $base_url = cobrand_url($cobrand, '/', $cocode);
    if ($code == FYR_QUEUE_MESSAGE_ALREADY_QUEUED) {
        
        $error_msg = "You've already sent this message.  To send a new message, please <a href=\"$base_url\">start again</a>.";
    } elseif ($code == FYR_QUEUE_GROUP_ALREADY_QUEUED) {
        $error_msg = "You've already sent these messages.  To send a new message, please <a href=\"$base_url\">start again</a>."; 
    } else {
        error_log("write.php msg_write error: ". $code . " " . $text);
        $error_msg = "Sorry, an error has occurred. Please <a href='/about-contact'>contact us</a>.";
    }  
    return $error_msg;
}
 
function show_check_email($error_msg) {
     
    /* Show them the "check your email and click the link" template. */
     global $fyr_representative, $fyr_voting_area; 
     global $fyr_values, $stash, $cobrand;
     $our_values = array_merge($fyr_values, array('representative' => $fyr_representative,
            'voting_area' => $fyr_voting_area, 'date' => $stash['date'], 'group_msg' => $stash['group_msg'],
            'error_msg' => $error_msg, 'cobrand' => $cobrand, 'host' => fyr_get_host()));
     template_draw("write-checkemail", $our_values); 
}

function prepare_address() {
        /* Format the message sender's address */
    global $fyr_values;
    $address =
        $fyr_values['writer_address1'] . "\n" .
        $fyr_values['writer_address2'] . "\n" .
        $fyr_values['writer_town'] . "\n" .
        $fyr_values['state'] . "\n" .
        $fyr_values['pc'] . "\n";
    $address = str_replace("\n\n", "\n", $address);
    return $address;
}

function check_message_length() {
        /* check message not too long */
    global $fyr_values;
    if (strlen($fyr_values['body']) > OPTION_MAX_BODY_LENGTH) {
        template_show_error("Sorry, but your message is a bit too long
        for our service.  Please make it shorter, or contact your
        representative by some other means.");
    }
}

function prepare_message_array($address) {
        /* Set up an array of information about the message
           sender */
    global $fyr_values;
    return array(
    'name' => $fyr_values['name'],
    'email' => $fyr_values['writer_email'],
    'address' => $address,
    'postcode' => $fyr_values['pc'],
    'phone' => $fyr_values['writer_phone'],
    'referrer' => $fyr_values['fyr_extref'],
    'ipaddr' =>  $_SERVER['REMOTE_ADDR']
    );
}

function check_message_id($msgid) {

    /* Check that they've come back with a valid message ID. Really we should
     * be verifying all the data that we've retrieved from the browser with a
     * hash, but in this case it doesn't matter. */
    if (!preg_match("/^[0-9a-f]{20}$/i", $msgid)) {
        template_show_error('Sorry, but your browser seems to be transmitting
            erroneous data to us. Please try again, or <a href="/about-contact">contact us</a>.');
    }

}

# ---

$fyr_values = get_all_variables();
set_up_variables($fyr_values);

// Various display and used fields, global variables
$stash = array();
$stash['time'] = msg_get_time();
msg_check_error($stash['time']);
$stash['date'] = strftime('%A %e %B %Y', $stash['time']);

if (!isset($fyr_values['who']) || ($fyr_values['who'] == "all" && !isset($fyr_values['type']))) {
    back_to_who();
}

# Determine if this is a message to be sent to a group of representatives
$stash['group_msg'] = false;
if ($fyr_values['who'] == 'all')
    $stash['group_msg'] = true;

rate_limit($fyr_values);

// For a group mail, get a group_id for transaction with the fax queue now
// and generate message ids later
if ($stash['group_msg']) {
    if (array_key_exists('fyr_grpid', $fyr_values)) {
        $grpid = $fyr_values['fyr_grpid'];
    } else {
        $grpid = msg_create_group();
        msg_check_error($grpid);
        $fyr_values['fyr_grpid'] = $grpid;
    }

} else {

    $grpid = null;
    // Message id for transaction with fax queue
    if (array_key_exists('fyr_msgid', $fyr_values))
        $msgid = $fyr_values['fyr_msgid'];
    else {
        $msgid = msg_create();
        msg_check_error($msgid);
        $fyr_values['fyr_msgid'] = $msgid;
    }
}

if ($stash['group_msg']) {
    //Message intended for group of representatives

    /* Go back to the 'choose your rep' stage if the request is to send a group
     * mail to all reps for an area and the rep type does not represent an area
     */
    if (in_array($fyr_values['type'], $postcodeless_child_types)) {
        back_to_who();
    }

    //Get the electoral body information
    $voting_areas = mapit_call('postcode', $fyr_values['pc']);
    mapit_check_error($voting_areas);
    $area_ids = array_keys($voting_areas['areas']);

    if (!array_key_exists($fyr_values['type'], $va_inside)) mismatch_error();
    $eb_type = $va_inside[$fyr_values['type']];
    $eb_area = null; $fyr_voting_area = null;
    foreach ($voting_areas['areas'] as $id => $arr) {
        if ($arr['type'] == $fyr_values['type']) {
            $fyr_voting_area = add_area_vars($arr);
        }
        if ($arr['type'] == $eb_type) {
            $eb_area = $arr;
        }
    }
    if (!$eb_area || !$fyr_voting_area) mismatch_error();

    // Data bad due to election etc?
    check_area_status($eb_area, $fyr_voting_area);

    // Get the representative info
    $area_representatives = dadem_get_representatives($fyr_voting_area['id']);
    dadem_check_error($area_representatives);  
    debug("FRONTEND", "area representatives $area_representatives");
    $area_representatives = array($fyr_voting_area['id'] => $area_representatives);
    euro_check($area_representatives, $area_ids);
    $all_representatives = array_values($area_representatives[$fyr_voting_area['id']]);
    $representatives_info = dadem_get_representatives_info($all_representatives);
    dadem_check_error($representatives_info);
    debug_timestamp();

    debug("FRONTEND", "representatives info $representatives_info");

    //Check the contact method exists for each representative
    $any_contacts = false;
    $error_msg = "";
    $stash['valid_reps'] = array();
    # randomize the order that representatives will be displayed in
    shuffle($all_representatives);
    foreach ($all_representatives as $rep_specificid) {
       
        $success = msg_recipient_test($rep_specificid);
        $rep_name = "<strong>" . $fyr_voting_area['rep_prefix'] . " " .  
            $representatives_info[$rep_specificid]['name'] . " " . $fyr_voting_area['rep_suffix'] . "</strong>";

        if (rabx_is_error($success)) {    
            list($rep_error_type, $rep_error_msg) = recipient_test_error($success, $eb_area, $fyr_voting_area, $representatives_info[$rep_specificid]);
            $error_msg .= "<p>$rep_name: $rep_error_msg</p>";
        } else {
            $any_contacts = true;
            $stash['valid_reps'][$rep_specificid] = $representatives_info[$rep_specificid];
        }

    }
 
    if (!$any_contacts) {
        // None of the group of representatives can be contacted
        template_show_error("Sorry, we are unable to contact any of these representatives for the following reasons: <br> " . $error_msg);
    } elseif ($error_msg) {
        // Some problems, but some reps can be contacted, proceed with a note
        $stash['warning_text'] = "<strong>Note:</strong> Some of these representatives cannot be contacted for the following reasons: <br> " . $error_msg;
    }

    // Assemble the name string 
    $stash['rep_text'] = "<ul>";
    foreach ($stash['valid_reps'] as $rep) {
        $stash['rep_text'] .= "<li>" . $fyr_voting_area['rep_prefix'] . " " . $rep['name'] . " " . $fyr_voting_area['rep_suffix'] . "</li>";
    }
    $stash['rep_text'] .= "</ul>";

    debug("FRONTEND", "Valid reps $stash[valid_reps]");

    // Set a msgid for each rep in the list
    assign_message_ids();

} else {

    // Message intended for individual representative
    $fyr_representative = get_rep($fyr_values['who']);
    $fyr_voting_area = get_area($fyr_representative['voting_area']);

    if (is_postcode_editable($fyr_representative['type'])) {
        $eb_area = mapit_call('area', $fyr_voting_area['parent_area']);
    } else {
        // Check that the representative represents this postcode
        if (!$fyr_values['pc']) mismatch_error();
        $postcode_areas = mapit_call('postcode', $fyr_values['pc']);
        $area_ids = array_keys($postcode_areas['areas']);
        if (!in_array($fyr_representative['voting_area'], $area_ids)) {
            mismatch_error();
        }
        $eb_type = $va_inside[$fyr_voting_area['type']];
        foreach ($postcode_areas['areas'] as $id => $arr) {
            if ($arr['type'] == $eb_type) {
                $eb_area = $arr;
                break;
            }
        }
    }

    // Data bad due to election etc?
    check_area_status($eb_area, $fyr_voting_area);

    //Check the contact method exists
    $success = msg_recipient_test($fyr_values['who']);
    if (rabx_is_error($success)) {
        list($error_type, $error_msg) = recipient_test_error($success, $eb_area, $fyr_voting_area, $fyr_representative);
        $values = array('error_message' => $error_msg);
        if ($error_type == 'shame') {
            $values['title'] = 'Oh no! What a shame!';
        }
        template_draw("error-general", $values);
        exit(1);
    }

    //Assemble the name string
    $stash['rep_text'] = $fyr_voting_area['rep_prefix'] . " " . $fyr_representative['name'] . " " . $fyr_voting_area['rep_suffix'];
}

generate_signature($fyr_values);

// Work out which page we are on, using which submit button was pushed
// to get here
$on_page = on_page($fyr_values);

if ($on_page == 'preview' || $on_page == 'write') {
    $options = cobrand_write_form_options($cobrand);
    $writeForm = buildWriteForm($options);
}

// Error in form when trying to submit for preview
if ($on_page == 'preview' && !$writeForm->validate()) {
    $on_page = "write";
}

// Display it
if ($on_page == "write") {
    $writeForm->setDefaults(array('body' => default_body_text()));
    $writeForm->setConstants($fyr_values);
    renderForm($writeForm, "writeForm", $options);
} elseif ($on_page == "preview") {
    $options = cobrand_preview_form_options($cobrand);
    $previewForm = buildPreviewForm($options);
    renderForm($previewForm, "previewForm", $options);
} elseif ($on_page == "sendfax") {
    submitFaxes();
} else {
    template_show_error(
            'Sorry. An error has occurred: on_page "'
                . htmlspecialchars($on_page) .
            '". Please <a href="/about-contact">get in touch with us</a>,
            quoting this message. You can <a href="/">try again from the
            beginning</a>.'
        );
}

# ---

function set_up_variables(&$fyr_values) {
    # Form field name changes
    if (array_key_exists('writer_name', $fyr_values)) {
        $fyr_values['name'] = $fyr_values['writer_name'];
        unset($fyr_values['writer_name']);
    }
    if (array_key_exists('writer_county', $fyr_values)) {
        $fyr_values['state'] = $fyr_values['writer_county'];
        unset($fyr_values['writer_county']);
    }

    // Normalise text part of message here, before we modify it.
    if (array_key_exists('body', $fyr_values))
        $fyr_values['body'] = convert_to_unix_newlines($fyr_values['body']);

    if (!array_key_exists('pc', $fyr_values)) {
        $fyr_values['pc'] = '';
    }

    debug("FRONTEND", "All variables:", $fyr_values);
    $fyr_values['pc'] = canonicalise_postcode($fyr_values['pc']);
    if (!isset($fyr_values['fyr_extref']))
        $fyr_values['fyr_extref'] = fyr_external_referrer();
    if (!isset($fyr_values['cocode']))
        $fyr_values['cocode'] = get_http_var('cocode');
}

function rate_limit($fyr_values) {
    $limit_values = array('postcode' => array($fyr_values['pc'], "Postcode that's been typed in"));
    if ($fyr_values['who'] != 'all') {
        $limit_values['who'] = array($fyr_values['who'], "Representative id from DaDem");
    }
    if (array_key_exists('body', $fyr_values) and strlen($fyr_values['body']) > 0) {
        $limit_values['body'] = array($fyr_values['body'], "Body text of message");
    }
    fyr_rate_limit($limit_values);
}

function add_area_vars($area) {
    global $va_rep_prefix, $va_rep_suffix, $va_rep_name, $va_rep_name_long_plural, $va_rep_name_plural;
    $area['rep_prefix'] = isset($va_rep_prefix[$area['type']]) ? $va_rep_prefix[$area['type']] : '';
    $area['rep_suffix'] = isset($va_rep_suffix[$area['type']]) ? $va_rep_suffix[$area['type']] : '';
    $area['rep_name'] = $va_rep_name[$area['type']];
    $area['rep_name_plural'] = $va_rep_name_plural[$area['type']];
    $area['rep_name_long_plural'] = $va_rep_name_long_plural[$area['type']];
    return $area;
}

function generate_signature(&$fyr_values) {
    if (array_key_exists('writer_email', $fyr_values) && array_key_exists('body', $fyr_values)) {
        $fyr_values['signature'] = sha1($fyr_values['writer_email']);
        $fyr_values['signature'] = substr_replace($fyr_values['signature'], '/', 20, 0);
        $fyr_values['signedbody'] = <<<EOF
$fyr_values[body]

$fyr_values[signature]
(Signed with an electronic signature in accordance with subsection 7(3) of the Electronic Communications Act 2000.)
EOF;
    } elseif (array_key_exists('body', $fyr_values)) {
        $fyr_values['signedbody'] = $fyr_values['body'];
    }
}

function on_page(&$fyr_values) {
    foreach(array('Write', 'Preview', 'SendFax') as $page) {
        if (isset($fyr_values['submit' . $page])) {
            unset($fyr_values['submit' . $page]);
            return strtolower($page);
        }
    }
    return 'write';
}

function back_to_who() {
    global $fyr_values;
    $location = "/who?pc=" . urlencode($fyr_values['pc']);
    $a = get_http_var('a');
    if ($a) {
       $location .= "&a=" . urlencode($a);
    }
    header("Location: " . $location);
    exit;
}

function mismatch_error() {
    global $cobrand, $cocode;
    $url = cobrand_url($cobrand, "/", $cocode);
    template_show_error("There's been a mismatch error.  Sorry about
        this, <a href=\"$url\">please start again</a>.");
}

function check_area_status($eb_area, $fyr_voting_area) {
    global $cobrand;
    $parent_status = dadem_get_area_status($eb_area['id']);
    dadem_check_error($parent_status);
    $status = dadem_get_area_status($fyr_voting_area['id']);
    dadem_check_error($status);
    if ($parent_status != 'none' || $status != 'none'){
        $election_error = cobrand_election_error_message($cobrand);
        if (!$election_error) {
             $election_error = 'Sorry, an election is forthcoming or has recently happened here.';
        }
        template_show_error($election_error);
    }
}

function get_rep($id) {
    debug("FRONTEND", "Single representative $id");
    $rep = dadem_get_representative_info($id);
    if (dadem_get_error($rep)) {
        back_to_who();
    }
    return $rep;
}

function get_area($id) {
    // The voting area is the ward/division. e.g. West Chesterton Electoral Division
    $area = mapit_call('area', $id);
    if (mapit_get_error($area)) {
        back_to_who();
    }
    debug("FRONTEND", "FYR voting area $area");
    $area = add_area_vars($area);
    return $area;
}

function recipient_test_error($error, $eb_area, $fyr_voting_area, $fyr_representative) {
    global $cobrand;
    if ($error->code == FYR_QUEUE_MESSAGE_BAD_ADDRESS_DATA) {
        $error_msg = cobrand_bad_contact_error_msg($cobrand, $eb_area);
        if (!$error_msg) {
            $error_msg = bad_contact_error_msg($eb_area);
        }
        return array('bad', $error_msg);
    } elseif ($error->code == FYR_QUEUE_MESSAGE_SHAME) {
        $error_msg = cobrand_shame_error_msg($cobrand, $fyr_voting_area, $fyr_representative);
        if (!$error_msg) {
            $error_msg = shame_error_msg($fyr_voting_area, $fyr_representative);
        }
        return array('shame', $error_msg);
    } else {
        return array('unknown', $error->text);
    }
}

function assign_message_ids() {
    global $fyr_values, $stash, $msgid_list, $repid_list;
    if (array_key_exists('fyr_msgid_list', $fyr_values) && array_key_exists('fyr_repid_list', $fyr_values)) {
        $msgid_list = explode('_', $fyr_values['fyr_msgid_list']);
        $repid_list = explode('_', $fyr_values['fyr_repid_list']);
    } else {
        $repid_list = array();
        $msgid_list = array();

        foreach (array_keys($stash['valid_reps']) as $repid) {
            $msgid = msg_create();
            msg_check_error($msgid);
            array_push($msgid_list, $msgid);
            array_push($repid_list, $repid);
        }

        $fyr_values['fyr_msgid_list'] = implode('_', $msgid_list);
        $fyr_values['fyr_repid_list'] = implode('_', $repid_list);
    }
}
