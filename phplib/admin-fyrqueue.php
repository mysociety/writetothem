<?php
/*
 * PHP info admin page.
 * 
 * Copyright (c) 2004 UK Citizens Online Democracy. All rights reserved.
 * Email: francis@mysociety.org. WWW: http://www.mysociety.org
 *
 * $Id: admin-fyrqueue.php,v 1.6 2004-12-18 13:21:55 francis Exp $
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

    function print_messages($messages) {
?>

<table border=1
width=100%><tr><th>Created</th><th>ID</th><th>Last State
Change</th><th>State</th><th>Postcode</th><th>Sender</th><th>Recipient</th><th>Message
length (chars)</th></tr>
<?
            foreach ($messages as $message) {
                print "<tr>";
                print "<td>" . strftime('%Y-%m-%d %H:%M:%S', $message['created']) . "</td>";
                print "<td><a href=\"" . $this->self_link . "&id=" .  urlencode($message['id']) . "\">" .  substr($message['id'],0,10) . "<br/>" .  substr($message['id'],10) . "</a></td>";
                print "<td>" . strftime('%Y-%m-%d %H:%M:%S', $message['laststatechange']) . "</td>";
                print "<td>";
                print $message['state'];
                print "<br>". $message['numactions'] . " attempts";
                if ($message['frozen']) {
                    print "<br><b>frozen</b>";
                }
                if ($message['lastaction'] > 0) {
                    print "<br>Last: " .  strftime('%Y-%m-%d %H:%M:%S', $message['lastaction']);
                }
                print "</td>";
                print "<td>" . $message['sender_postcode'] . "</td>";
                print "<td>" . 
                        $message['sender_name'] . "<br>" .
                        $message['sender_addr'] . "<br>" .
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
    }

    function print_message($message) {
        $this->print_messages(array($message));
    }


    function print_events($recents) {
?>
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

    function display($self_link) {
        $this->self_link = $self_link;

        #print "<pre>"; print_r($_POST); print "</pre>";
        $filter = get_http_var('filter');
        if ($filter == "0") $filter = 0; else $filter = 1;
        $id = get_http_var("id");
        
        // Display general statistics
        $stats = msg_admin_get_stats();
        if (msg_get_error($stats)) {
            print "Error contacting queue:";
            print_r($stats);
        }

?>
<h2>
Summary statistics: 
<b><?=$stats["created_1"]?></b> new in last hour,
<b><?=$stats["created_24"]?></b> new in last day.
All time stats:
</h2>
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
        // Display about id
        if ($id) {
            // Freeze or thaw messages
            if (get_http_var('freeze')) {
                print "<p><b><i>Message $id frozen</i></b></p>";
                $result = msg_admin_freeze_message($id);
                msg_check_error($result);
            } else if (get_http_var('thaw')) {
                print "<p><b><i>Message $id thawed</i></b></p>";
                $result = msg_admin_thaw_message($id);
                msg_check_error($result);
            } else if (get_http_var('error')) {
                print "<p><b><i>Message $id moved to error state</i></b></p>";
                $result = msg_admin_error_message($id);
                msg_check_error($result);
            } else if (get_http_var('failed')) {
                print "<p><b><i>Message $id moved to failed state</i></b></p>";
                $result = msg_admin_failed_message($id);
                msg_check_error($result);
            }

            print "<h2>Message id $id <a href=\"$self_link\">[back to message list]</a>:</h2>";

            $message = msg_admin_get_message($id);
            if (msg_get_error($message)) {
                print "Error contacting queue:";
                print_r($message);
            }
            $this->print_message($message);

            if (get_http_var('body')) {
                print "<h2>Body text of message (only read if you really need to)</h2>";
                print "<pre>";
                print $message['message'];
                print "</pre>";
            }
 
            print "<h2>All events for this message:</h2>";
            $recents = msg_admin_message_events($id);
            if (msg_get_error($recents)) {
                print "Error contacting queue:";
                print_r($recents);
                $recents = array();
            }
            $this->print_events($recents);

            $form = new HTML_QuickForm('messageForm', 'post', $self_link);
            if ($message['frozen']) {
                if ($message['state'] != 'error' and $message['state'] != 'failed')
                    $actiongroup[] = &HTML_QuickForm::createElement('submit', 'error', 'Error');
                if ($message['state'] != 'failed')
                    $actiongroup[] = &HTML_QuickForm::createElement('submit', 'failed', 'Failed');
                $actiongroup[] = &HTML_QuickForm::createElement('submit', 'thaw', 'Thaw');
            }
            else
                $actiongroup[] = &HTML_QuickForm::createElement('submit', 'freeze', 'Freeze');
            if (!get_http_var('body'))
                $actiongroup[] = &HTML_QuickForm::createElement('submit', 'body', 'View Body (only if you have to)');
            else
                $actiongroup[] = &HTML_QuickForm::createElement('submit', 'nobody', 'Hide Body');
            $form->addElement('hidden', 'id', $id);
            $form->addGroup($actiongroup, "actiongroup", "",' ', false);
            admin_render_form($form);
?>
<p>
<b>freeze</b> stops delivery to representative, but other stuff
(such as confirmation message) still happens
<br><b>thaw</b> undoes a freeze, so message gets delivered.
<br><b>error</b> rejects a message, sending a "could not deliver" email to constituent.
<br><b>failed</b> rejects a message, with no email to the constituent.
<br><b>view body</b> should only be done if you have good reason to believe it is an abuse of our service.
<?
         } else {
            // Display important messages in queue
            $messages = msg_admin_get_queue($filter);
            if (msg_get_error($messages)) {
                print "Error contacting queue:";
                print_r($messages);
                $messages = array();
            }
            if ($filter == 1) 
                $description = "Messages which may need attention: <a href=\"$self_link&filter=0\">[all messages]</a>";
            else
                $description = "All messages in reverse order of
                    creation: <a href=\"$self_link&filter=1\">[important
                    messages only]</a>";

            print "<h2><?=$description?></h2>";
            $this->print_messages($messages);

            if ($filter == 1) {
                $messages = msg_admin_get_queue(2);
                if (msg_get_error($messages)) {
                    print "Error contacting queue:";
                    print_r($messages);
                } else {
                    $description = "Messages which have changed recently: <a href=\"$self_link&filter=0\">[all messages]</a>";
                    $this->print_messages($messages);
                }
            }
        }
    }
}

?>
