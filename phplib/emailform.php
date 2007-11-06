<?php
/*
 * emailform.php:
 * Email Form for contacting site administrators.
 * 
 * Copyright (c) 2007 UK Citizens Online Democracy. All rights reserved.
 * Email: angie@mysociety.org; WWW: http://www.mysociety.org
 *
 * $Id: emailform.php,v 1.7 2007-11-06 15:35:29 angie Exp $
 * 
 */

/* setup the fields that are wanted on the contact form, 
 *    this will be dynamically built using emailform_entry_output,
 * it will also be checked by emailform_test_message, if you want to add another field stick it in here.
 */
$emailformfields = array (
    array ('label' => 'Name', 'inputname' => 'name', 'inputtype' => 'text', 'size' => '30', 'spamcheck' => "1", 'required' => 1),
    array ('label' => 'Email Address', 'inputname' => 'emailaddy', 'inputtype' => 'text', 'size' => '30', 'validate' => "emailaddress", 'required' => 1, 'emailoutput' => 'sender'),
    array ('label' => 'Subject', 'inputname' => 'subject', 'inputtype' => 'text', 'size' => '30', 'spamcheck' => "1", 'emailoutput' => 'subject'),
    array ('label' => 'Message', 'inputname' => 'notjunk', 'inputtype' => 'textarea', 'size' => '10,29', 'spamcheck' => "1", 'required' => 1),
    array ('label' => '', 'inputname' => 'send', 'inputtype' => 'submit', 'value' => 'Send us your thoughts'),
);

function fyr_display_emailform () {
    $messages = array();
    if (isset($_POST['action'])  && $_POST['action']== 'testmess') {
        $messages = emailform_test_message();
        if ($messages) {
            emailform_display($messages);
            return;
        } else {
            if (emailform_send_message()) {
                $messages['messagesent'] = 'Thanks, your message has been sent to ' . OPTION_WEB_DOMAIN;
            } else {
                $messages['messagenotsent'] = 'Sorry, there was a problem';
            }
            emailform_display($messages);
        }
    } else {
        emailform_display($messages);
    }
}

function emailform_display ($messages) {
    global $emailformfields;

    if ($messages) {
        if (isset($messages['messagesent'])) {
            print '<p class="alertsuccess">' . $messages['messagesent']  . '</p>';
        } else {
            print '<ul class="repwarning">';
            foreach ($messages as $inp => $mess) {
                print '<li>' . $mess;
            }
            print '</ul>';
        }
    }
    
    print '<form action="about-contactresponse" accept-charset="utf8" method="post">';
    print '<input name="action" type="hidden" value="testmess">';
    
    foreach ($emailformfields as $defs) {
        $input = '';
        $value='';
        if (isset($defs['value']))
            $value = $defs['value'];
        if (isset($_POST[$defs['inputname']]))
            $value = $_POST[$defs['inputname']];
        $htmlvalue = htmlentities($value, ENT_QUOTES, 'UTF-8');
        //$htmlvalue = mb_convert_encode($value,'HTML-ENTITIES','UTF-8');
        //$htmlvalue = $value;
        
        if ($defs['inputtype'] == 'text') {
            $input = '<input type="text" name="' . $defs['inputname'] . '" id="' . $defs['inputname'] . '" size="' . $defs['size'] . '" value="' . $htmlvalue . '">';
        }
        if ($defs['inputtype'] == 'textarea') {
            $sizes = explode(",", $defs['size']);
            $input = '<textarea name="' . $defs['inputname'] . '" id="' . $defs['inputname'] . '" rows="' . $sizes[0] . '" cols="' . $sizes[1] . '">' . $htmlvalue . '</textarea>';
        }
        if ($defs['inputtype'] == 'submit') {
            $input = '<input name="' . $defs['inputname'] . '" id="' . $defs['inputname'] . '" type="submit" value="' . $defs['value'] . '">';
        }
        if ($defs['inputtype'] == 'radio') {
            $radiovalues = $defs['values'];
            foreach ($radiovalues as $radvalue) {
                $checked = ($value == $radvalue) ? ' checked' : '';
                $input .= '<input name="' . $defs['inputname'] . '" id="' . $defs['inputname'] . '" type="radio" value="' . $radvalue . '" ' . $checked . '> ' . $radvalue . '<br>';
            }
        }
        $label = $defs['label'];
        if (isset($defs['required']) && $defs['required']) {
            $label .= ' (required)';
        }
        
        if ($label) $label .= ':';
        $out = '<p><label for="' . $defs['inputname'] . '"';
        if (isset($messages[$defs['inputname']]))
            $out .= ' class="repwarning"';
        $out .= '>' . $label . '</label>' . $input . '</p>';
        print $out;
    }
    print '</form>';
}


function emailform_test_message () {
    global $emailformfields;
    $errors = array ();
    foreach ($emailformfields as $defs) {
        if (isset($defs['required']) && $defs['required'] && !$_POST[$defs['inputname']]) {
            $errors[$defs['inputname']] = "Please enter your " . $defs['label'];
        }
        if (isset($defs['spamcheck']) && $defs['spamcheck']) {
            $ermess = emailform_test_spam($defs['inputname']);
            if ($ermess) {
                $errors[$defs['inputname']] = $defs['label'] . $ermess;
            }
            
        }
        if (isset($defs['validate']) && $defs['validate'] == 'emailaddress') {
            if (! validate_email($_POST[$defs['inputname']]) ) {
                $ermess = 'Please enter a valid email address';
                if ($ermess) {
                    $errors[$defs['inputname']] = $ermess;
                }
            }
        }
        
        
    }
    return $errors;
}

function emailform_test_spam ($inputname) {
    $searchpat = '/(?:<\/?\w+((\s+\w+(\s*=\s*(?:\".*?\\\"|.*?|[^">\s]+))?)+\s*|\s*)\/?>|\[\/url\])/s';
    if (isset($_POST[$inputname]) && preg_match($searchpat, $_POST[$inputname])) {
        return " contains HTML or spam";
    }
}


// we could put the testing of the message in here, but not doing so allows us to test that messages can be sent without having to check everything first.
function emailform_send_message () {
    global $emailformfields;
    $sendto = OPTION_CONTACT_EMAIL;
    $mailbody = '';
    $subject = 'no subject';
    $sender = '';
    $messagesent = 0;
    foreach ($emailformfields as $defs) {
    // loop through emailformfields and fill in the mail body
        if (isset($defs['emailoutput']) && isset($_POST[$defs['inputname']])) {
            // this value goes into the subject or sender    
            if ($defs['emailoutput'] == 'subject') {
                $subject = 'Message from ' . OPTION_WEB_DOMAIN . ': '. $_POST[$defs['inputname']];
            }
            if ($defs['emailoutput'] == 'sender') {
                $sender = $_POST[$defs['inputname']];
            }
        } else {
            if (isset($_POST[$defs['inputname']]) && $defs['inputtype'] != 'submit') {
                $mailbody .= $defs['label'] . ': ' . $_POST[$defs['inputname']] . "\n\n";
            }
        }
    }

	$success = FALSE;
    if ($sender && $mailbody) {
		$from = $_POST['name'] ? array ($sender, $_POST['name']) : $sender;
		$spec = array(
			'_unwrapped_body_' => $mailbody,
			'Subject' => $subject,
			'From' =>$from,
			'To' => array($sendto, 'WriteToThem'),
		);
		
		$result = evel_send($spec, $sendto);
		$error = evel_get_error($result);
		if ($error) 
			error_log("fyr_send_email_internal: " . $error);
		$success = $error ? FALSE : TRUE;
	
    }
	return $success;
}

