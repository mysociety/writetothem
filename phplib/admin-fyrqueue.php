<?php
/*
 * PHP info admin page.
 * 
 * Copyright (c) 2004 UK Citizens Online Democracy. All rights reserved.
 * Email: francis@mysociety.org. WWW: http://www.mysociety.org
 *
 * $Id: admin-fyrqueue.php,v 1.1 2004-11-15 12:36:56 francis Exp $
 * 
 */

require_once "queue.php";

class ADMIN_PAGE_FYR_QUEUE {
    function ADMIN_PAGE_FYR_QUEUE () {
        $this->id = "fyrqueue";
        $this->name = "Message Queue";
        $this->navname = "Message Queue";
    }

    function display($self_link) {
        #print "<pre>"; print_r($_POST); print "</pre>";
        $messages = msg_admin_get_queue();
        if (msg_get_error($messages)) {
            print "Error contacting queue:";
            print_r($messages);
            $messages = array();
        }
?>

<p>Current active messages in reverse order of creation:
</p>
<table border=1
width=100%><tr><th>Created</th><th>ID</th><th>Last State Change</th><th>State</th><th>Postcode</th><th>Sender</th><th>Recipient</th><th>Message
length (chars)</th></tr>
<?
            foreach ($messages as $message) {
                print "<tr>";
                print "<td>" . strftime('%Y-%m-%d %H:%M:%S', $message['created']) . "</td>";
                print "<td>" . substr($message['id'],0,10) . "<br/>" . substr($message['id'],10) . "</td>";
                print "<td>" . strftime('%Y-%m-%d %H:%M:%S', $message['laststatechange']) . "</td>";
                print "<td>" . $message['state'] . 
                    "<br>". $message['numactions'] . " attempts";
                if ($message['lastaction'] > 0) {
                    print "<br>Last: " . strftime('%Y-%m-%d %H:%M:%S', $message['lastaction']);
                }
                print "</td>";
                print "<td>" . $message['sender_postcode'] . "</td>";
                print "<td>" . 
                        $message['sender_name'] . "<br>" .
                        $message['sender_email'] . "<br>" .
                        $message['sender_addr'] . "<br>" .
                        $message['sender_phone'] . "<br>" .
                 "</td>";
                 print "<td>" . 
                        $message['recipient_name'] . "<br>" .
                        $message['recipient_position'] . "<br>" .
                        $message['recipient_email'] . "<br>" .
                        $message['recipient_fax'] . "<br>" .
                 "</td>";
                 print "<td>" . strlen($message['message']) . "</td>";
                 print "</tr>";
            }
?>
</table>
<?
        $recents = msg_admin_recent_events(100);
        if (msg_get_error($recents)) {
            print "Error contacting queue:";
            print_r($recents);
            $recents = array();
        }

?>

<p>Recent events on queue:
</p>
<table border=1
width=100%><tr><th>Time</th><th>ID</th><th>State</th><th>Event</th></tr>
<?
            foreach ($recents as $recent) {
                print "<tr>";
                print "<td>" . $recent['whenlogged'] . "</td>";
                print "<td>" . substr($recent['message_id'],0,10) .  "<br/>" . substr($recent['message_id'],10) . "</td>";
                print "<td>" . $recent['state'] . "</td>";
                print "<td>" . $recent['message'] . "</td>";
                print "</tr>";
            }
?>
</table>
<?

    }
}

?>
