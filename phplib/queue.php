<?php
/*
 * queue.php:
 * Interface to the fax/email queue.
 * 
 * Copyright (c) 2004 UK Citizens Online Democracy. All rights reserved.
 * Email: chris@mysociety.org; WWW: http://www.mysociety.org/
 *
 * $Id: queue.php,v 1.5 2004-10-20 12:43:56 chris Exp $
 * 
 */

include_once('simplexmlrpc.php');
include_once('utility.php');

/* msg_create
 * Return a string ID for a new outgoing fax/email message. */
function msg_create() {
    debug("QUEUE", "Getting new message ID");
    $result = sxr_call(OPTION_QUEUE_HOST, OPTION_QUEUE_PORT, OPTION_QUEUE_PATH, 'FYR.Queue.create', array());
    debug("QUEUE", "New ID is $result");
    return $result;
}

/* msg_write ID SENDER RECIPIENT TEXT
 * Queue a new message for sending. ID is a message-ID obtained from
 * msg_create; SENDER is an associative array containing information about the
 * sender including elements: name, the sender's full name; email, their email
 * address; address, their full postal address; and optionally phone, their
 * phone number. RECIPIENT is the DaDem ID number of the recipient of the
 * message; and TEXT is the text of the message, with only "\n"
 * characters for line breaks. All strings must be encoded in UTF-8.
 * Returns true on success or false on failure. */
function msg_write($id, $sender, $recipient_id, $text) {
    debug("QUEUE", "Writing new message");
    $result = sxr_call(OPTION_QUEUE_HOST, OPTION_QUEUE_PORT, OPTION_QUEUE_PATH, 'FYR.Queue.write', array($id, $sender, $recipient_id, $text));
    debug("QUEUE", "Result is $result");
    return $result;
}

/* msg_secret
 * Return some secret data suitable for use in verifying transactions
 * associated with a message. */
function msg_secret() {
    debug("QUEUE", "Getting secret");
    $result = sxr_call(OPTION_QUEUE_HOST, OPTION_QUEUE_PORT, OPTION_QUEUE_PATH, 'FYR.Queue.secret', array());
    debug("QUEUE", "Retrieved (very hush-hush) secret");
    return $result;
}

/* msg_confirm_email TOKEN
 * Pass the TOKEN, which has been supplied by the user in a URL parameter or
 * whatever, to the queue to confirm the user's email address. Returns true on
 * success or false on failure. */
function msg_confirm_email($token) {
    debug("QUEUE", "Confirming email");
    $result = sxr_call(OPTION_QUEUE_HOST, OPTION_QUEUE_PORT, OPTION_QUEUE_PATH, 'FYR.Queue.confirm_email', array($token));
    debug("QUEUE", "Result is $result");
    return $result;
}

?>
