<?php
/*
 * queue.php:
 * Interface to the fax/email queue.
 * 
 * Copyright (c) 2004 UK Citizens Online Democracy. All rights reserved.
 * Email: chris@mysociety.org; WWW: http://www.mysociety.org/
 *
 * $Id: queue.php,v 1.1 2004-10-19 11:43:13 chris Exp $
 * 
 */

/* msg_create
 * Return a string ID for a new outgoing fax/email message. */
function msg_create() {
    return sprintf("D%08x%08xYXX", rand(), rand();
}

/* msg_write ID SENDER RECIPIENT TEXT
 * Queue a new message for sending. ID is a message-ID obtained from
 * msg_create; SENDER is an associative array containing information about the
 * sender including elements: name, the sender's full name; email, their email
 * address; address, their full postal address; and optionally phone, their
 * phone number. RECIPIENT is the DaDem ID number of the recipient of the
 * message; and TEXT is the text of the message, formatted in HTML. All strings
 * must be encoded in UTF-8.
 * 
 * Returns true on success or false on failure. */
function msg_write($id, $sender, $recipient_id, $text) {
    return true;
}

/* msg_state ID [STATE]
 * Get/set the state of the message with the given ID. */
function msg_state($id, $state = null) {
    return 'pending';
}

?>
