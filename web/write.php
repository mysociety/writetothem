<?
/*
 * write.php:
 * Page where they enter details, write their message, and preview it
 *
 * Copyright (c) 2004 UK Citizens Online Democracy. All rights reserved.
 * Email: francis@mysociety.org. WWW: http://www.mysociety.org
 *
 * $Id: write.php,v 1.119 2007-06-04 13:27:40 matthew Exp $
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

function group_address(){
    /* Generate a comma-separated list of correct address forms
     * for a list of representatives */

    global $fyr_valid_reps;
    $i = 0;
    $names = array();
    foreach($fyr_valid_reps as $rep){
        $names[$i] = $rep['name'];
        $i++;
    }
    $address = implode( ', ', array_slice($names, 0, $i-1)) . ' and ' . $names[$i-1] ;
    return $address;
}

function is_postcode_editable($rep_type){
    /* Is the postcode editable for a rep. type? */
    global $postcodeless_child_types;
    $postcode_editable = false;
    if (in_array($rep_type, $postcodeless_child_types)) {
        $postcode_editable = true;
    }
    return $postcode_editable;
}

function verify_rep_postcode($postcode, $rep_info){

    /* Verify that representative represents this postcode */
    global $fyr_postcode_editable;
    global $verify_voting_area_map;   

    $verify_voting_area_map = mapit_get_voting_areas($postcode);
    if (is_array($verify_voting_area_map))
        $verify_voting_areas = array_values($verify_voting_area_map);
    else
       $verify_voting_areas = array();

    if (!$fyr_postcode_editable) {
        if (!in_array($rep_info['voting_area'], $verify_voting_areas)) {
           template_show_error("There's been a mismatch error.  Sorry about
               this, <a href=\"/\">please start again</a>.");
        }
    }

}

function redirect_if_disabled($type, $group_msg){
    /* Go back to the 'choose your rep' stage if 
     * the rep. type is disabled, or if the request is to send
     * a group mail to all reps. for an area and the rep. type
     * does not represent an area */
    global $fyr_postcode;
    global $disabled_child_types;
    global $postcodeless_child_types;
    // For URLs like http://writetothem.com/?a=WMC;pc=XXXXX    
    if (in_array($type, $disabled_child_types) || ($group_msg && in_array($type, $postcodeless_child_types))) {
        header("Location: who?pc=" . urlencode($fyr_postcode) . "&err=1\n");
        exit;
    }
}

function bad_contact_error_msg($eb_area_info){
    /* Generate an error string for when contact details for
     * a representative are not available */

    $type_display_name = $eb_area_info['general_prep'] . " " . $eb_area_info['name'];
    if ($type_display_name == "the House of Commons")
        $type_display_name .= " (020 7219 3000)";
    $error_msg = "
    Sorry, we <strong>do not currently have contact details for this representative</strong>, and are unable to send
    them a message. We may have had details in the past, which have since proven to be erroneous.

    Please <a href=\"mailto:team&#64;writetothem.com\">email us</a> to encourage us to find the contact details.

    We'd be <em>really</em> grateful if you could <strong>spend five minutes on the phone
    to $type_display_name</strong>, asking for the contact details.
    Then <a href=\"mailto:team&#64;writetothem.com\">email us</a> with the email address or fax number of
    your representative.
    ";
    return $error_msg;
}

function shame_error_msg($fyr_voting_area, $fyr_representative){
    /* Generate an error string for when a representative has
     * requested not to be contacted */
    global $fyr_postcode;
    if ($fyr_voting_area['type'] == 'WMC') {
        $url = 'http://www.locata.co.uk/cgi-bin/phpdriver?MIval=hoc_search&postcode=' . urlencode($fyr_postcode);
        $error_msg = <<<EOF
$fyr_voting_area[rep_prefix] $fyr_representative[name] $fyr_voting_area[rep_suffix]
has told us not to deliver any messages from the constituents of
$fyr_voting_area[name]. Instead you can try contacting them via
<a href="$url">the Parliament website</a>. There you will get a phone number, a
postal address, and for some MPs a way to contact them by email.
EOF;

    } else {
        $error_msg = <<<EOF
$fyr_voting_area[rep_prefix] $fyr_representative[name] $fyr_voting_area[rep_suffix]
has told us not to deliver any messages from the constituents of
$fyr_voting_area[name]. Please <a href="mailto:team&#64;writetothem.com">email
us</a> to let us know what you think about this.
EOF;

    }
    return $error_msg;
}

function correct_address(){
    /* Generate a correct form of address for a representative
     * or group of reprsentatives */

    global $fyr_representative, $fyr_group_msg;    
    $address = "";
     if ($fyr_group_msg){
         $address = group_address();
     }else{
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
    global $fyr_values, $fyr_postcode, $fyr_who, $fyr_type;
    global $fyr_representative, $fyr_voting_area, $fyr_date;
    global $fyr_postcode_editable, $fyr_group_msg, $fyr_valid_reps;
    global $rep_text;

    if ($fyr_voting_area['name']=='United Kingdom')
    	$fyr_voting_area['name'] = 'House of Lords';
 
    // TODO: CSS this:
    $stuff_on_left = <<<END
            <strong>Now Write Your Message:</strong> <small>(* means required)</small><br><br>
            ${rep_text}
            <br>${fyr_voting_area['name']}
            <br><br>$fyr_date
END;

    // special formatting for letter-like code, TODO: how do this properly with QuickHtml?
    $form->addElement("html", "<tr><td valign=\"top\">$stuff_on_left</td><td align=\"right\">\n<table>"); // CSSify

    $form->addElement('text', 'name', "Your name:<sup>*</sup>", array('size' => 20, 'maxlength' => 255));
    $form->addRule('name', 'Please enter your name', 'required', null, null);
    $form->applyFilter('name', 'trim');

    $form->addElement('text', 'writer_address1', "Address 1:<sup>*</sup>", array('size' => 20, 'maxlength' => 255));
    $form->addRule('writer_address1', 'Please enter your address', 'required', null, null);
    $form->applyFilter('writer_address1', 'trim');

    $form->addElement('text', 'writer_address2', "Address 2:", array('size' => 20, 'maxlength' => 255));
    $form->applyFilter('writer_address2', 'trim');

    $form->addElement('text', 'writer_town', "Town/City:<sup>*</sup>", array('size' => 20, 'maxlength' => 255));
    $form->addRule('writer_town', 'Please enter your town', 'required', null, null);
    $form->applyFilter('writer_town', 'trim');

    # Call it state so that Google Toolbar (and presumably others) can auto-fill.
    $form->addElement('text', 'state', 'County:', array('size' => 20, 'maxlength' => 255));
    $form->applyFilter('state', 'trim');

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

    $form->addElement('text', 'writer_email', "Your email:<sup>*</sup>", array('size' => 20, 'maxlength' => 255));
    $form->addRule('writer_email', 'Please enter your email address', 'required', null, null);
    $form->addRule('writer_email', 'Choose a valid email address', 'email', null, null);
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
    global $fyr_form, $fyr_values, $warning_text;
    global $rep_text, $fyr_group_msg, $fyr_valid_reps;
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
    if ($fyr_today != date('Y-m-d') && OPTION_FYR_REFLECT_EMAILS) {
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
            'cobrand_letter_help' => $cobrand_letter_help, 
            'group_msg' => $fyr_group_msg, 'warning_text' => $warning_text));

    if ($fyr_group_msg){
        # check if there are any reps whose message will be sent via somewhere 
        # else
        $any_via = false;
        foreach ($fyr_valid_reps as $rep){
            if ($rep['method'] == 'via'){
                $any_via = true;
            }
        }
        $our_values['any_via'] = $any_via;
        $our_values['title_text'] = "your " . $fyr_voting_area['rep_name_long_plural'] ;
    }else{
        $our_values['title_text'] = trim($fyr_voting_area['rep_prefix'] . " " .
                                    $fyr_representative['name'] . " " .
                                    $fyr_voting_area['rep_suffix']) . ", " .
                                    $fyr_voting_area['name'];
    }
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
        $our_values['rep_text'] = $rep_text;
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


function submitFaxes(){

    /* Submit a group of messages or an individual message 
     * and show the results to the user */

    global $grpid, $msgid_list, $msgid;
    global $repid_list, $fyr_who;
    global $representatives_info, $fyr_voting_area;
    global $fyr_values, $cobrand;
    
    // Set up some brief error descriptions
    $errors = array("problem-generic" => "Message Rejected", 
                    "problem-lords" => "You have sent too many messages to Lords", 
                    "problem-lords-similar" => "Too many similar messages have been sent",
                    "problem-postcodes" => "You seem to be sending messages with several different postcodes", 
                    "problem-similar" => "Your message is near-identical with others sent previously");

 
    // send the message to each representative
    $any_success = false;
    $error_msg = "";
    
    if ($grpid){
	
        // No questionnaire for group mails
        $no_questionnaire = true;
    	    
    	// check the group id  
    	if (!preg_match("/^[0-9a-f]{20}$/i", $grpid)) {
    	template_show_error('Sorry, but your browser seems to be transmitting
        	erroneous data to us. Please try again, or contact us at
        	<a href="mailto:team@writetothem.com">team@writetothem.com</a>.');
    		exit;
    	}
		// double check that the group_id isn't already being used
    	// This could mean that these messages have already been
    	// queued or (with a very small probability) that someone
    	// else got the same group id 
	    $result = msg_check_group_unused($grpid);    
    	if (isset($result)){
        	$error_msg .= rabx_mail_error_msg($result->code, $result->text) . "<br />";        
  		   template_show_error("Sorry, we were unable to send your messages for the following reasons: <br />" . $error_msg);
    	   exit;
    	}   
    }else{
        $no_questionnaire = false;
    	$msgid_list = array($msgid);
    	$repid_list = array($fyr_who);
    }      
        
    # set up the address      
    $address = prepare_address();
    check_message_length();
    # check the msgids
    foreach ($msgid_list as $msgid){
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
    if (rabx_is_error($result)){
        template_show_error(rabx_mail_error_msg($result->code, $result->text));
        exit;

    } 

    foreach (array_keys($result) as $id){
        $res = $result[$id];
        $rep_id = $res['recipient_id'];	
        $abuse_res = $res['abuse_result'];
        $status = $res['status_code'];
        $err = $res['error_text'];
		$code = $res['error_code'];
        if($status != 0){
            $rep_name = "<strong>" . $fyr_voting_area['rep_prefix'] . " " .
            $representatives_info[$rep_id]['name'] . " " . $fyr_voting_area['rep_suffix'] . "</strong>";
            
            if($status == 1){
                # FYR Error code
                if ($grpid){    
                    $error_msg .= $rep_name . ": " . rabx_mail_error_msg($code, $err) . "<br />";
                }else{
                    template_show_error(rabx_mail_error_msg($code, $err));
                    exit;
                }
            }elseif($status == 2){
                # flagged for abuse
                if ($grpid){
			    	if (array_key_exists($abuse_res, $errors)){
                           $error_msg .= "<p>" .$rep_name . ": " . $errors[$abuse_res]
                         . " <a href=\"/"  . $abuse_res . "\">read more</a></p>";
                    }else{
                            $error_msg .= "<p>" .$rep_name . ": Message Rejected</p>";
                    }
                }else{
                    template_draw($abuse_res,  $fyr_values);
                    exit;
                }
            }
        }else{
            $any_success = true;
        }   

    }
          
    if (!$any_success){
        // None of the messages could be sent
        template_show_error("Sorry, we were unable to send your messages for the following reasons: <br />" . $error_msg);
    }else if($error_msg){
        // Some problems 
        $error_msg = "
    <p style=\"text-align: center; color: #ff0000; \">Note: 
    Some of your messages could not be sent for the following reasons: </p>
    " . $error_msg;
        show_check_email($error_msg);
    }else{
        //no problems
        show_check_email($error_msg);
    } 
 
}

function rabx_mail_error_msg($code, $text){   
     /* Return an appropriate error message for a RABX error code. 
      * Log errors other than multiple send attempts */
     
     $error_msg = "";
     if ($code == FYR_QUEUE_MESSAGE_ALREADY_QUEUED){
         $error_msg = "You've already sent this message.  To send a new message, please <a href=\"/\">start again</a>.";
    }else if ($code == FYR_QUEUE_GROUP_ALREADY_QUEUED){
        $error_msg = "You've already sent these messages.  To send a new message, please <a href=\"/\">start again</a>."; 
    }else{
         error_log("write.php msg_write error: ". $code . " " . $text);
         $error_msg = "Sorry, an error has occured. Please contact <a href=\"mailto:team&#64;writetothem.com\">team&#64;writetothem.com</a>.";
     }  
     return $error_msg;
}

 
function show_check_email($error_msg){
     
    /* Show them the "check your email and click the link" template. */
     global $fyr_representative, $fyr_voting_area; 
     global $fyr_values, $fyr_date, $fyr_group_msg;
     $our_values = array_merge($fyr_values, array('representative' => $fyr_representative,
            'voting_area' => $fyr_voting_area, 'date' => $fyr_date, 'group_msg' => $fyr_group_msg,
            'error_msg' => $error_msg));
     template_draw("write-checkemail", $our_values); 
}

function prepare_address(){
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

function check_message_length(){
	/* check message not too long */
    global $fyr_values;
    if (strlen($fyr_values['body']) > OPTION_MAX_BODY_LENGTH) {
        template_show_error("Sorry, but your message is a bit too long
        for our service.  Please make it shorter, or contact your
        representative by some other means.");
    }
}

function prepare_message_array($address){
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

function check_message_id($msgid){

    /* Check that they've come back with a valid message ID. Really we should
     * be verifying all the data that we've retrieved from the browser with a
     * hash, but in this case it doesn't matter. */
    if (!preg_match("/^[0-9a-f]{20}$/i", $msgid)) {
        template_show_error('Sorry, but your browser seems to be transmitting
            erroneous data to us. Please try again, or contact us at
            <a href="mailto:team@writetothem.com">team@writetothem.com</a>.');
    }

}

// Get all fyr_values
$fyr_values = get_all_variables();

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
$fyr_values['pc'] = strtoupper(trim($fyr_values['pc']));
if (!isset($fyr_values['fyr_extref']))
    $fyr_values['fyr_extref'] = fyr_external_referrer();
if (!isset($fyr_values['cocode']))
    $fyr_values['cocode'] = get_http_var('cocode');

// Various display and used fields
$fyr_postcode = $fyr_values['pc'];
if (array_key_exists('who', $fyr_values))
    $fyr_who = $fyr_values['who'];
if (array_key_exists('type', $fyr_values))
    $fyr_type = $fyr_values['type'];
$fyr_time = msg_get_time();
msg_check_error($fyr_time);
$fyr_date = strftime('%A %e %B %Y', $fyr_time);

if (!isset($fyr_who) || ($fyr_who == "all" && !isset($fyr_type))) {
    header("Location: who?pc=" . urlencode($fyr_postcode) . "&err=1\n");
    exit;
}

# Determine if this is a message to be sent to a group of representatives
$fyr_group_msg = false;
if ($fyr_who == 'all')
    $fyr_group_msg = true;

// Rate limiter
$limit_values = array('postcode' => array($fyr_postcode, "Postcode that's been typed in"));
if ($fyr_who != 'all'){
    $limit_values['who'] = array($fyr_who, "Representative id from DaDem");
}
if (array_key_exists('body', $fyr_values) and strlen($fyr_values['body']) > 0) {
    $limit_values['body'] = array($fyr_values['body'], "Body text of message");
}
fyr_rate_limit($limit_values);

// For a group mail, get a group_id for transaction with the fax queue now
// and generate message ids later
if ($fyr_group_msg){
    
    if (array_key_exists('fyr_grpid', $fyr_values))
        $grpid = $fyr_values['fyr_grpid'];
    else {
        $grpid = msg_create_group();
        msg_check_error($grpid);
        $fyr_values['fyr_grpid'] = $grpid;
    }

}else{

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

$warning_text = "";
if ($fyr_group_msg){

    //Message intended for group of representatives
    redirect_if_disabled($fyr_type, $fyr_group_msg);

    //Check if the postcode is editable
    $fyr_postcode_editable = is_postcode_editable($fyr_type);

    //Get the electoral body information
    $voting_areas = mapit_get_voting_areas($fyr_postcode);
    mapit_check_error($voting_areas);
    $eb_type = $va_inside[$fyr_type];

    if (array_key_exists($eb_type, $voting_areas)){
        $eb_id = $voting_areas[$eb_type];
        $eb_area_info = mapit_get_voting_area_info($eb_id);
        mapit_check_error($eb_area_info);
    }else{
        template_show_error("There's been a mismatch error.  Sorry about
               this, <a href=\"/\">please start again</a>.");
    }

     if (array_key_exists($fyr_type, $voting_areas)){
         $va_id = $voting_areas[$fyr_type]; 
         $fyr_voting_area =  mapit_get_voting_area_info($va_id);
         mapit_check_error($fyr_voting_area);
     }else{
         template_show_error("There's been a mismatch error.  Sorry about
                this, <a href=\"/\">please start again</a>.");
     }


    // Get the representative info
    $area_representatives = dadem_get_representatives($va_id);
    $error = dadem_get_error($area_representatives);
    dadem_check_error($area_representatives);  
    debug("FRONTEND", "area representatives $area_representatives");
    $all_representatives = array();
    foreach (array_values($area_representatives) as $rr) {
        $all_representatives = array_merge($all_representatives, $rr);
    }
    $representatives_info = dadem_get_representatives_info($all_representatives);
    dadem_check_error($representatives_info);
    debug_timestamp();

    debug("FRONTEND", "representatives info $representatives_info");

    //Check the contact method exists for each representative
    $any_contacts = false;
    $error_msg = "";
    $fyr_valid_reps = array();
    # randomize the order that representatives will be displayed in
    shuffle($all_representatives);
    foreach ($all_representatives as $rep_specificid) {
       
        $success = msg_recipient_test($rep_specificid);
 	    $rep_name = "<strong>" . $fyr_voting_area['rep_prefix'] . " " .  
        $representatives_info[$rep_specificid]['name'] . " " . $fyr_voting_area['rep_suffix'] . "</strong>";


        if (rabx_is_error($success)) {    

           if ($success->code == FYR_QUEUE_MESSAGE_BAD_ADDRESS_DATA) {
                 $error_msg .= "<p>" . $rep_name . ": " . bad_contact_error_msg($eb_area_info) . "</p>"; 
           } else if ($success->code == FYR_QUEUE_MESSAGE_SHAME) {
                 $error_msg .= "<p>" . shame_error_msg($fyr_voting_area, $representatives_info[$rep_specificid]) . "</p>";
           } else {
                 $error_msg .= "<p>" . $rep_name . ": " . $success->text . "</p>";
           }
 	    }else{
            $any_contacts = true;
            $fyr_valid_reps[$rep_specificid] = $representatives_info[$rep_specificid];
        }
 
     }
 
     // None of the group of representatives can be contacted
     if (!$any_contacts){
         template_show_error("Sorry, we are unable to contact any of these representatives for the following reasons: <br /> " . $error_msg);
        // Some problems, but some reps can be contacted, proceed with a note
     }else if($error_msg){
        $warning_text = "<strong>Note:</strong> Some of these representatives cannot be contacted for the following reasons: <br /> " . $error_msg;
     }

    // Assemble the name string 
    $rep_text = "";
    foreach ($fyr_valid_reps as $rep){
        $rep_text .= $fyr_voting_area['rep_prefix'] . " " . $rep['name'] . " " . $fyr_voting_area['rep_suffix'] . "<br />";    
    }

    debug("FRONTEND", "Valid reps $fyr_valid_reps");
    // Set a msgid for each rep in the list
 
     if (array_key_exists('fyr_msgid_list', $fyr_values) && array_key_exists('fyr_repid_list', $fyr_values)){
 
         $msgid_list = explode('_', $fyr_values['fyr_msgid_list']);
         $repid_list = explode('_', $fyr_values['fyr_repid_list']);
 
     }else{
         
         $repid_list = array();
         $msgid_list = array();
         
         foreach (array_keys($fyr_valid_reps) as $repid){
             $msgid = msg_create();
             msg_check_error($msgid);
             array_push($msgid_list, $msgid);
             array_push($repid_list, $repid);
         }

         $fyr_values['fyr_msgid_list'] = implode('_', $msgid_list);
         $fyr_values['fyr_repid_list'] = implode('_', $repid_list);
     }

}else{

    // Message intended for individual representative
    // Information specific to this representative
    debug("FRONTEND", "Single representative $fyr_who");
    $fyr_representative = dadem_get_representative_info($fyr_who);
    dadem_check_error($fyr_representative);

    // The voting area is the ward/division. e.g. West Chesterton Electoral Division
    $fyr_voting_area = mapit_get_voting_area_info($fyr_representative['voting_area']);
    mapit_check_error($fyr_voting_area);
    debug("FRONTEND", "FYR voting area $fyr_voting_area");
    
    redirect_if_disabled($fyr_representative['type'], $fyr_group_msg);
   
    //Check if the postcode is editable
    $fyr_postcode_editable = is_postcode_editable($fyr_representative['type']);

    //Check that the representative represents this postcode
    verify_rep_postcode($fyr_postcode, $fyr_representative);

    // Get the electoral body information
    $eb_type = $va_inside[$fyr_voting_area['type']];
    if (array_key_exists($eb_type, $verify_voting_area_map)) {
        $eb_id = $verify_voting_area_map[$eb_type];
    } else {
        $eb_id = $fyr_voting_area['parent_area_id'];
    }
    
    $eb_area_info = mapit_get_voting_area_info($eb_id);
    mapit_check_error($eb_area_info);

    //Check the contact method exists
    $success = msg_recipient_test($fyr_values['who']);

    if (rabx_is_error($success)) {
        if ($success->code == FYR_QUEUE_MESSAGE_BAD_ADDRESS_DATA) {
            $error_msg = bad_contact_error_msg($eb_area_info);
        } else if ($success->code == FYR_QUEUE_MESSAGE_SHAME) {
            $error_msg = shame_error_msg($fyr_voting_area, $fyr_representative);
        } else {
            $error_msg= $success->text;
        } 
        template_show_error($error_msg);
    }

    //Assemble the name string
   $rep_text = $fyr_voting_area['rep_prefix'] . " " . $fyr_representative['name'] . " " . $fyr_voting_area['rep_suffix'];

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
    renderForm($previewForm, "previewForm");
} else if ($on_page =="sendfax") {
 	submitFaxes();
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
