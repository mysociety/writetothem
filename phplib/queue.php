<?php
/*
 * queue.php:
 * Interface to the fax/email queue.
 * 
 * Copyright (c) 2004 UK Citizens Online Democracy. All rights reserved.
 * Email: chris@mysociety.org; WWW: http://www.mysociety.org/
 *
 * $Id: queue.php,v 1.32 2005-01-13 11:29:26 francis Exp $
 * 
 */

include_once('../../phplib/rabx.php');
include_once('../../phplib/utility.php');

/* Error codes */
define('FYR_QUEUE_MESSAGE_ALREADY_QUEUED', 4001);  
define('FYR_QUEUE_MESSAGE_ALREADY_CONFIRMED', 4002);
define('FYR_QUEUE_MESSAGE_BAD_ADDRESS_DATA', 4003);
define('FYR_QUEUE_MESSAGE_SHAME', 4004); /* Representative does not want to be contacted */

/* msg_get_error R
 * Return FALSE if R indicates success, or an error string otherwise. */
function msg_get_error($e) {
    if (!rabx_is_error($e))
        return FALSE;
    else
        return $e->text;
}

/* msg_check_error R
 * If R indicates failure, displays error message and stops processing. */
function msg_check_error($data) {
    if ($error_message = msg_get_error($data))
        err($error_message);
}


$fyr_queue_client = new RABX_Client(OPTION_FYR_QUEUE_URL);

/* msg_create
 * Return a string ID for a new outgoing fax/email message. */
function msg_create() {
    global $fyr_queue_client;
    debug("QUEUE", "Getting new message ID");
    $result = $fyr_queue_client->call('FYR.Queue.create', array());
    debug("QUEUE", "New ID is", $result);
    return $result;
}

/* msg_write ID SENDER RECIPIENT TEXT
 * Queue a new message for sending. ID is a message-ID obtained from
 * msg_create; SENDER is an associative array containing information about the
 * sender including elements: name, the sender's full name; email, their email
 * address; address, their full postal address; postcode, their postcode; and
 * optionally phone, their phone number. RECIPIENT is the DaDem ID number of
 * the recipient of the message; and TEXT is the text of the message, with only
 * "\n" characters for line breaks. All strings must be encoded in UTF-8.
 * Returns null if the message was successfully queued (even if it was frozen),
 * or, if the message was rejected, the name of a template to which the user
 * should be directed to explain what was wrong with their message. */
function msg_write($id, $sender, $recipient_id, $text) {
    global $fyr_queue_client;
    /* XXX check contents of sender array */
    debug("QUEUE", "Writing new message id $id to $recipient_id", $sender);
    $result = $fyr_queue_client->call('FYR.Queue.write', array($id, $sender, $recipient_id, $text), 1);
    debug("QUEUE", "Result:", $result);
    return $result;
}

/* msg_recipient_test RECIPIENT
 * Tests fax/email exist for contact method, throws errors on failure.
 * RECIPIENT is the DaDem ID number of the recipient of the message.*/
function msg_recipient_test($recipient_id) {
    global $fyr_queue_client;
    $result = $fyr_queue_client->call('FYR.Queue.recipient_test', array($recipient_id));
    return $result;
}
  
/* msg_record_questionnaire_answer TOKEN QUESTION ANSWER
 * Record the response to a questionnaire. TOKEN is the user-supplied token;
 * QUESTION should be 0, and ANSWER must be "YES" or "NO". */
function msg_record_questionnaire_answer($token, $qn, $answer) {
    global $fyr_queue_client;
    debug("QUEUE", "Recording answer");
    $result = $fyr_queue_client->call('FYR.Queue.record_questionnaire_answer', array($token, $qn, $answer));
    debug("QUEUE", "Result: ", $result);
    return $result;
}

/* msg_secret
 * Return some secret data suitable for use in verifying transactions
 * associated with a message. */
function msg_secret() {
    global $fyr_queue_client;
    debug("QUEUE", "Getting secret");
    $result = $fyr_queue_client->call('FYR.Queue.secret', array());
    debug("QUEUE", "Retrieved (very hush-hush) secret");
    return $result;
}

/* msg_confirm_email TOKEN
 * Pass the TOKEN, which has been supplied by the user in a URL parameter or
 * whatever, to the queue to confirm the user's email address. Returns true on
 * success or false on failure. */
function msg_confirm_email($token) {
    global $fyr_queue_client;
    debug("QUEUE", "Confirming email");
    $result = $fyr_queue_client->call('FYR.Queue.confirm_email', array($token));
    debug("QUEUE", "Result:", $result);
    return $result;
}

/* msg_admin_recent_events
 * Returns array of hashes of recent queue events. */
function msg_admin_recent_events($count) {
    global $fyr_queue_client;
    $result = $fyr_queue_client->call('FYR.Queue.admin_recent_events', array($count));
    return $result;
}

/* msg_admin_message_events
 * Returns array of hashes of queue events for a particular message id. */
function msg_admin_message_events($id) {
    global $fyr_queue_client;
    $result = $fyr_queue_client->call('FYR.Queue.admin_message_events', array($id));
    return $result;
}

/* msg_admin_get_queue FILTER PARAMS
 * Returns array of hashes of recent queue events. 
 * Set FILTER to 0 for all events, 1 for only important ones, 2 for
 * recently changed ones, 3 for recently created ones.  PARAMS
 * is a hash of parameters for the filter type. */
function msg_admin_get_queue($filter, $params) {
    global $fyr_queue_client;
    $result = $fyr_queue_client->call('FYR.Queue.admin_get_queue', array($filter, $params));
    return $result;
}

/* msg_admin_get_message ID
 * Returns hash of info about message with id ID. */
function msg_admin_get_message($id) {
    global $fyr_queue_client;
    $result = $fyr_queue_client->call('FYR.Queue.admin_get_message', array($id));
    return $result;
}


/* msg_admin_get_stats
 * Returns hash of statistics about queue. */
function msg_admin_get_stats() {
    global $fyr_queue_client;
    $result = $fyr_queue_client->call('FYR.Queue.admin_get_stats', array());
    return $result;
}

/* msg_admin_freeze_message ID USER
 * Stops message being actually sent.*/
function msg_admin_freeze_message($id, $user) {
    global $fyr_queue_client;
    $result = $fyr_queue_client->call('FYR.Queue.admin_freeze_message', array($id, $user));
    return $result;
}

/* msg_admin_thaw_message ID USER
 * Allows message to be sent.*/
function msg_admin_thaw_message($id, $user) {
    global $fyr_queue_client;
    $result = $fyr_queue_client->call('FYR.Queue.admin_thaw_message', array($id, $user));
    return $result;
}

/* msg_admin_set_message_to_error ID USER
 * Abandons all actions, sends delivery failure to constituent.*/
function msg_admin_set_message_to_error($id, $user) {
    global $fyr_queue_client;
    $result = $fyr_queue_client->call('FYR.Queue.admin_set_message_to_error', array($id, $user));
    return $result;
}

/* msg_admin_set_message_to_failed ID USER
 * Abandons all actions. */
function msg_admin_set_message_to_failed($id, $user) {
    global $fyr_queue_client;
    $result = $fyr_queue_client->call('FYR.Queue.admin_set_message_to_failed', array($id, $user));
    return $result;
}

/* msg_admin_set_message_to_failed_closed ID USER
 * Hides a failed message from future admin attention. */
function msg_admin_set_message_to_failed_closed($id, $user) {
    global $fyr_queue_client;
    $result = $fyr_queue_client->call('FYR.Queue.admin_set_message_to_failed_closed', array($id, $user));
    return $result;
}

/* msg_admin_set_message_to_bounce_wait ID USER
 * Says bounce message was non fatal. */
function msg_admin_set_message_to_bounce_wait($id, $user) {
    global $fyr_queue_client;
    $result = $fyr_queue_client->call('FYR.Queue.admin_set_message_to_bounce_wait', array($id, $user));
    return $result;
}

/* msg_admin_add_note_to_message ID USER NOTE
 * Adds a comment to a message. */
function msg_admin_add_note_to_message($id, $user, $note) {
    global $fyr_queue_client;
    $result = $fyr_queue_client->call('FYR.Queue.admin_add_note_to_message', array($id, $user, $note));
    return $result;
}



?>
