<?php
/*
 * PHP info admin page.
 * 
 * Copyright (c) 2004 UK Citizens Online Democracy. All rights reserved.
 * Email: francis@mysociety.org. WWW: http://www.mysociety.org
 *
 * $Id: admin-fyrqueue.php,v 1.4 2004-12-15 19:02:58 francis Exp $
 * 
 */

require_once "queue.php";
require_once "../../phplib/utility.php";

class ADMIN_PAGE_FYR_QUEUE {
    function ADMIN_PAGE_FYR_QUEUE () {
        $this->id = "fyrqueue";
        $this->name = "Message Queue";
        $this->navname = "Message Queue";
    }

    function display($self_link) {
        #print "<pre>"; print_r($_POST); print "</pre>";
        $filter = get_http_var('filter');
        if ($filter == "0") $filter = 0; else $filter = 1;
        
        $stats = msg_admin_get_stats();
        if (msg_get_error($stats)) {
            print "Error contacting queue:";
            print_r($stats);
        }

?>
<p>
Summary statistics: 
<b><?=$stats["created_1"]?></b> new in last hour,
<b><?=$stats["created_24"]?></b> new in last day.
All time stats:
</p>
<table>

<tr><td>
<table border=1>
<?
    foreach ($stats as $k=>$v) {
        if (stristr($k, "state ")) print "<tr><td>$k</td><td>$v</td></tr>\n";
    }
    print "<tr><td>Total:</td><td>" . $stats['message_count'] .  "</td></tr>\n";
?>
</table>
</td>
<td>
<table border=1>
<?
    foreach ($stats as $k=>$v) {
        if (stristr($k, "type ")) print "<tr><td>$k</td><td>$v</td></tr>\n";
    }
    print "<tr><td>Total:</td><td>" . $stats['message_count'] .  "</td></tr>\n";
?>
</td></tr>
</table>

</table>

<?
        $messages = msg_admin_get_queue($filter);
        if (msg_get_error($messages)) {
            print "Error contacting queue:";
            print_r($messages);
            $messages = array();
        }
        if ($filter == 1) 
            $description = "Messages which may need attention: <a href=\"$self_link&filter=0\">[all messages]</a>";
        else
            $description = "Current active messages in reverse order of
                creation: <a href=\"$self_link&filter=1\">[important
                messages only]</a>";
?>

<p><?=$description?>
</p>
<table border=1
width=100%><tr><th>Created</th><th>ID</th><th>Last State Change</th><th>State</th><th>Postcode</th><th>Sender</th><th>Recipient</th><th>Message length (chars)</th></tr>
<?
            foreach ($messages as $message) {
                print "<tr>";
                print "<td>" . strftime('%Y-%m-%d %H:%M:%S', $message['created']) . "</td>";
                print "<td><a href=\"$self_link&id=" .  urlencode($message['id']) . "\">" .  substr($message['id'],0,10) . "<br/>" .  substr($message['id'],10) . "</a></td>";
                print "<td>" . strftime('%Y-%m-%d %H:%M:%S', $message['laststatechange']) . "</td>";
                print "<td>" . $message['state'] . 
                    "<br>". $message['numactions'] . " attempts";
                if ($message['lastaction'] > 0) {
                    print "<br>Last: " .  strftime('%Y-%m-%d %H:%M:%S', $message['lastaction']);
                }
                print "</td>";
                print "<td>" . $message['sender_postcode'] . "</td>";
                print "<td>" . 
                        $message['sender_name'] . "<br>" .
                        $message['sender_email'] . "<br>" .
                 "</td>";
                print "<td>" . 
                        $message['recipient_name'] . "<br>";
                if ($message['recipient_email']) print $message['recipient_email'] . "<br>";
                if ($message['recipient_fax']) print $message['recipient_fax'] . "<br>";
                print "</td>";
                print "<td>" . $message['message_length'] . "</td>";
                print "</tr>";
            }
?>
</table>
<?
        $id = get_http_var("id");
        if ($id) {
            $recents = msg_admin_message_events($id);
            $description = "Events for message id $id <a href=\"$self_link\">[all events]</a>:";
        } else {
            $events_count = 20;
            $recents = msg_admin_recent_events($events_count);
            $description = "Recent $events_count events on queue:";
        }
        if (msg_get_error($recents)) {
            print "Error contacting queue:";
            print_r($recents);
            $recents = array();
        }

?>

<p><?=$description?>
</p>
<table border=1
width=100%><tr><th>Time</th><th>ID</th><th>State</th><th>Event</th></tr>
<?
            foreach ($recents as $recent) {
                print "<tr>";
                print "<td>" . strftime('%Y-%m-%d %H:%M:%S', $recent['whenlogged']) . "</td>";
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
