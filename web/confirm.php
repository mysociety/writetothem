<?php
/*
 * Confirmation from the constituent that they want to send the
 * fax/email.  This page is linked to from the email which confirms the
 * constituent's email address.
 * 
 * Copyright (c) 2004 UK Citizens Online Democracy. All rights reserved.
 * Email: francis@mysociety.org. WWW: http://www.mysociety.org
 *
 * $Id: confirm.php,v 1.24 2009-12-07 11:20:57 louise Exp $
 * 
 */

require_once "../phplib/fyr.php";
require_once "../phplib/queue.php";
require_once "../phplib/forms.php";

require_once "../commonlib/phplib/utility.php";

fyr_rate_limit(array());

function buildAnalysisForm($values) {
    global $cobrand, $cocode;
    $options = cobrand_write_form_options($cobrand);
    $form_action = cobrand_url($cobrand, '/survey', $cocode);
    $form = new HTML_QuickForm('analysisForm', 'post', $form_action);
    $form->setAttribute('class', 'analysis-form');
    $form->addElement('textarea', 'msg_summary', "Can you tell us in a sentence what your message was about?", array('class' => 'msg-summary'));

    $casework_element = $form->createElement('radio', 'reason', null, '', 'casework');
    $campaigning_element = $form->createElement('radio', 'reason', null, '', 'campainging');

    $casework_id = $casework_element->getAttribute('id');
    $campaigning_id = $campaigning_element->getAttribute('id');
    $legend_id = 'reason_legend';

    // Creates a fieldset with radio options and legend
    $form->addElement('html', "
    <fieldset>
        <legend id=\"{$legend_id}\">Which of the following best describes why you are writing to your representative?</legend>
        <div class=\"input-card-grid\">
            <div class=\"radio-card\">
                <input name=\"reason\" value=\"casework\" type=\"radio\" id=\"{$casework_id}\" aria-labelledby=\"{$legend_id}\">
                <label for=\"{$casework_id}\">Casework <span class=\"label-hint\">Trying to resolve a problem you or another person is having</span></label>
            </div>
            <div class=\"radio-card\">
                <input name=\"reason\" value=\"campainging\" type=\"radio\" id=\"{$campaigning_id}\" aria-labelledby=\"{$legend_id}\">
                <label for=\"{$campaigning_id}\">Campaigning<span class=\"label-hint\">Seeking to persuade or inform your representative about a wider issue</span></label>
            </div>
        </div>
    </fieldset>
    ");

    $form->addElement('html', '<input class="button radius success" name="submit" value="Submit" type="submit">');
    add_all_variables_hidden($form, $values, $options);
    $r = new HTML_QuickForm_Renderer_mySociety();
    $form->accept($r);
    return $r->toHtml();
}

$ad = get_http_var('ad');
if ($ad) {
    $values = array(
        'recipient_via' => null, 'recipient_name' => 'Recipient Name', 'recipient_type' => 'Type',
        'sender_name' => 'Sender Name', 'sender_email' => 'email', 'sender_postcode' => 'SW1A1AA',
        'group_id' => '', 'advert' => $ad, 'cobrand' => $cobrand, 'host' => fyr_get_host(),
        'form' => buildAnalysisForm(array()),
    );
    template_draw("confirm-accept", $values);
    exit;
}

$template_params = array('host' => fyr_get_host(), 'cobrand' => $cobrand);
$token = get_http_var('token');
if (!$token) {

    $missing_token_message = cobrand_missing_token_message($cobrand);
    if (!$missing_token_message) {
         $missing_token_message = "Please make sure you copy the URL from your email properly. The token was missing.";
    }
    template_show_error($missing_token_message);
}

$result = msg_confirm_email($token);
if (rabx_is_error($result)) {

    if ($result->code == FYR_QUEUE_MESSAGE_EXPIRED) {
        $url = cobrand_url($cobrand, "/", $cocode);
        $text = <<<EOF
You took so long to confirm your message that under our privacy policy 
your message has already been removed from our database. 
If you’d still like to write a message, you can <a href="$url">try again from the
beginning
EOF;
        template_show_error($text);
    } else {
        template_show_error($result->text);
    }
}
if (!$result) {
    template_draw("confirm-trouble", $template_params);
} else {
    $values = msg_admin_get_message($result);
    if (rabx_is_error($values)) {
        template_show_error($values->text);
    } elseif ($values['cobrand'] && cobrand_post_letter_send($values)) {
        // Do nothing - cobrand_post_letter_send must do the special action e.g. header or template_draw etc.
    } else {
        $values['cobrand'] = $cobrand;
        $values['host'] = fyr_get_host();
        $values['form'] = buildAnalysisForm(array("msg_id" => $result));
        template_draw("confirm-accept", $values);
    }
}

?>

