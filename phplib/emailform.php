<?php
/*
 * emailform.php:
 * Email Form for contacting site administrators.
 *
 * Copyright (c) 2007 UK Citizens Online Democracy. All rights reserved.
 * Email: angie@mysociety.org; WWW: http://www.mysociety.org
 *
 * $Id: emailform.php,v 1.10 2008-02-29 11:22:56 matthew Exp $
 *
 */
require_once "../phplib/fyr.php";
require_once '../commonlib/phplib/evel.php';

/* setup the fields that are wanted on the contact form,
 *    this will be dynamically built using emailform_display,
 * it will also be checked by emailform_test_message, if you want to add another field stick it in here.
 */
$radiovals = array(
    array ('label' => 'The WriteToThem team', 'value' => 'us'),
    array ('label' => 'Your representative', 'value' => 'rep'),
    array ('label' => 'Other', 'value' => 'other')
);
$emailformfields = array (
    array ('label'        => 'Name',
           'inputname'    => 'name',
           'inputtype'    => 'text',
           'size'         => '30',
           'spamcheck'    => "1",
           'errormessage' => 'Please enter your name',
           'required'     => 1),
    array ('label'       => 'Email Address',
           'inputname'   => 'emailaddy',
           'inputtype'   => 'text',
           'size'        => '30',
           'validate'    => "emailaddress",
           'required'    => 1,
           'emailoutput' => 'sender'),
    array ('label'     => 'Subject',
           'inputname' => 'subject',
           'inputtype' => 'text',
           'size'      => '30',
           'spamcheck' => "1",
           'emailoutput'  => 'subject'),
    array ('intro_text'   => 'Who is your message for',
           'inputname'    => 'dest',
           'inputtype'    => 'radioset',
           'values'       => $radiovals,
           'nomail'       => 1,
           'errormessage' => 'Please say who your message is for',
           'required'     => 1),
    array ('label'        => 'Message',
           'inputname'    => 'notjunk',
           'inputtype'    => 'textarea',
           'size'         => '10,29',
           'spamcheck'    => "1",
           'errormessage' => 'Please enter your message',
           'required'     => 1),
    array ('label'     => '',
           'inputname' => 'send',
           'inputtype' => 'submit',
           'value'     => 'Send us your thoughts'),
);

function fyr_display_emailform () {
    $messages = array();
    if (isset($_POST['action'])  && $_POST['action']== 'testmess') {
        $messages = emailform_test_message();
        if ($messages) {
            emailform_display($messages);
            return;
        } else {
            $dest = get_http_var('dest');
            $contact_message = get_http_var('notjunk');
            if ($dest == 'rep') {
              $problem = 'You cannot contact your representative by filling in the WriteToThem contact form. To contact your representative, please visit <a href="/">www.writetothem.com</a> and enter your postcode. We have printed your message below so you can copy and paste it into the WriteToThem message box.';
              wrongcontact_display($problem, $contact_message);
            } elseif ($dest == 'other') {
              $problem = 'You can only contact the team behind WriteToThem using our contact form. Your message is printed below so you can copy and paste it to wherever you want to send it.';
              wrongcontact_display($problem, $contact_message);
            } else {
              if (emailform_send_message()) {
                  $messages['messagesent'] = 'Thanks, your message has been sent to ' . OPTION_WEB_DOMAIN;
              } else {
                  $messages['messagenotsent'] = 'Sorry, there was a problem';
              }
              emailform_display($messages);
            }
        }
    } else {
        emailform_display($messages);
    }
}

function render_formfield ($defs, $messages) {
  $input = '';
  $value='';
  if (isset($defs['value']))
      $value = $defs['value'];
  if (isset($_POST[$defs['inputname']]))
      $value = $_POST[$defs['inputname']];
  $htmlvalue = htmlentities($value, ENT_QUOTES, 'UTF-8');

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
  if ($defs['inputtype'] == 'radioset') {
      $radiovalues = $defs['values'];
      foreach ($radiovalues as $radvalue) {
          $element_id = $defs['inputname'] . '_' . $radvalue['value'];
          $checked = ($value == $radvalue['value']) ? ' checked' : '';
          $input .= '<input name="' . $defs['inputname'] . '" id="' . $element_id . '" type="radio" value="' . $radvalue['value'] . '" ' . $checked . '> ';
          $input .= '<label class="inline-label" for="' . $element_id . '">' . $radvalue['label'] . '</label><br>';
      }
  }

  if (isset($defs['label'])){
    $label = $defs['label'];
    if (isset($defs['required']) && $defs['required']) {
        $label .= ' (required)';
    }
    if ($label) $label .= ':';
    $out = '<label for="' . $defs['inputname'] . '"';
    if (isset($messages[$defs['inputname']]))
        $out .= ' class="repwarning"';
    $out .= '>' . $label . '</label>' . $input ;
  }else{
    $intro_text = $defs['intro_text'];
    if (isset($defs['required']) && $defs['required']) {
        $intro_text .= ' (required)';
    }
    if ($intro_text) $intro_text .= ':';
    $out = '<span class="intro-text';
    if (isset($messages[$defs['inputname']]))
        $out .= ' repwarning';
    $out .= '">' . $intro_text . '</span>' . $input ;
  }
  return '<p>' . $out . '</p>';
}

function wrongcontact_display($problem, $contact_message) {
  print '<div id ="sendmess">';
  print '<div class="wrong-contact">';
  print $problem;
  print '<hr>';
  print fyr_format_message_body_for_preview($contact_message);
  print '</div>';
  print '</div>';
}

function emailform_display ($messages) {
    global $emailformfields;
    if ($messages && isset($messages['messagesent'])){
      print '<p class="alertsuccess">' . $messages['messagesent']  . '</p>';
      return;
    }
    print '<div id ="sendmess">';
    print '<h3>Tell us what you think</h3>';
    if ($messages) {

      print '<ul class="repwarning">';
      foreach ($messages as $inp => $mess) {
          print '<li>' . $mess;
      }
      print '</ul>';

    }
    print '<form action="about-contactresponse" accept-charset="utf8" method="post">';
    print '<input name="action" type="hidden" value="testmess">';

    foreach ($emailformfields as $defs) {
        print render_formfield($defs, $messages);
    }
    print '</form>';
    print '</div>';
}

function emailform_test_message () {
    global $emailformfields;
    $errors = array ();
    foreach ($emailformfields as $defs) {
        if (isset($defs['required']) && $defs['required'] && (!isset($_POST[$defs['inputname']]) || !$_POST[$defs['inputname']])) {
            if (isset($defs['errormessage'])){
              $errors[$defs['inputname']] = $defs['errormessage'];
            } else {
              $errors[$defs['inputname']] = "Please enter your " . $defs['label'];
            }
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
        if (isset($defs['nomail']) && $defs['nomail']) continue;
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
            'To' => array(array($sendto, 'WriteToThem')),
        );

        $result = evel_send($spec, $sendto);
        $error = evel_get_error($result);
        if ($error)
            error_log("fyr_send_email_internal: " . $error);
        $success = $error ? FALSE : TRUE;

    }
    return $success;
}

