<?php
/*
 * FYR queue admin page.
 * 
 * Copyright (c) 2004 UK Citizens Online Democracy. All rights reserved.
 * Email: francis@mysociety.org. WWW: http://www.mysociety.org
 *
 * $Id: admin-fyrqueue.php,v 1.42 2005-01-12 12:56:01 francis Exp $
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

    function state_help_notes($state) {
        $map = array(
        'new' => 'About to send confirmation email to constituent',
        'pending' => 'Waiting for confirmation from constituent',
        'ready' => 'Attempting to send to representative',
        'bounce_wait' => 'Waiting for possible email delivery failure message',
        'bounce_confirm' => 'Email delivery failure received, admin',
        'error' => 'About to tell constituent that delivery failed',
        'sent' => 'Delivery to representative succeeded',
        'failed' => 'Delivery to representative failed, needs admin attention',
        'finished' => 'Delivery succeeded, personal data has been scrubbed',
        'failed_closed' => 'Delivery failed, admin has dealt with it',
        );
        return $map[$state];
    }

    function make_ids_links($text) {
        return preg_replace("/([a-f0-9]{20})/", 
                "<a href=\"" .  $this->self_link . "&id=\$1\">\$1</a>", 
                $text);
    }

    function print_messages($messages) {
?>

<table border=1
width=100%><tr><th>Created</th><th>ID</th><th>Last State
Change</th><th>State</th><th>Sender</th><th>Recipient</th>
<th>Client IP / <br> Referrer</th>
<th>Length (chars)</th>
</tr>
<?
	$c = 1;
            foreach ($messages as $message) {
                print '<tr'.($c==1?' class="v"':'').'>';
                print "<td>" . strftime('%Y-%m-%d %H:%M:%S', $message['created']) . "</td>";
                print "<td><a href=\"" . $this->self_link . "&id=" .  urlencode($message['id']) . "\">" .  substr($message['id'],0,10) . "<br/>" .  substr($message['id'],10) . "</a></td>";
                print "<td>" . strftime('%Y-%m-%d %H:%M:%S', $message['laststatechange']) . "</td>";
                print "<td>";
                print add_tooltip($message['state'], $this->state_help_notes($message['state']));
                if ($message['frozen']) {
                    print "<br><b>frozen</b>";
                }
                if ($message['numactions'] > 0) {
                    if ($message['state'] == 'pending')  {
                        print "<br>" .  ($message['numactions'] - 1) . " ".
                        make_plural(($message['numactions'] - 1), 'reminder');
                    } else {
                        print "<br>". $message['numactions'] . " " .
                        make_plural($message['numactions'], 'attempt');

                    }
                }
                if ($message['lastaction'] > 0) {
                    print "<br>Last: " .  strftime('%Y-%m-%d %H:%M:%S', $message['lastaction']);
                }
                print "</td>";
                print "<td>" . 
                        htmlspecialchars($message['sender_name']) . "<br>" .
                        htmlspecialchars($message['sender_addr']) . "<br>" .
                        htmlspecialchars($message['sender_email']) .
                 "</td>";

                $display_name = $message['recipient_name'];
                if (!isset($display_name) || $display_name == "") {
                    $display_name = "scrubbed, id " .
                    $message['recipient_id'] . ", " .
                    $message['recipient_type'];
                }
                print "<td><a href=\"?page=reps&amp;rep_id=" .
                    urlencode($message['recipient_id']) . "&amp;pc=" .
                    urlencode($message['sender_postcode']) . "\">" . 
                    $display_name . "</a><br>";
                if ($message['recipient_email']) print $message['recipient_email'] . "<br>";
                if ($message['recipient_fax']) print $message['recipient_fax'] . "<br>";
                print "</td>";
                $simple_ref = $message['sender_referrer'];
                $url_bits = parse_url($simple_ref);
                if ($simple_ref != "" && ($url_bits['path']!='/' || array_key_exists('query', $url_bits)) )
                    $simple_ref = $url_bits['scheme'] . "://" .  $url_bits['host'] . "/...";
                $client_name = $message['sender_ipaddr'];
                if ($client_name != "")  {
                    $client_name = gethostbyaddr($client_name);
                }
                $short_client_name = trim_characters($client_name, strlen($client_name) - 27, 30); 

                print "<td>" . add_tooltip($short_client_name, $client_name) .
                        "<br>" .
                        "<a href=\"" .
                        htmlspecialchars($message['sender_referrer']) .  "\">" . 
                        htmlspecialchars($simple_ref) . "</a><br>" .
                 "</td>";
                print "<td>" . $message['message_length'] . "</td>";
                print "</tr>";

                if (array_key_exists('diff', $message)) {
                    print '<tr'.($c==1?' class="v"':'').'>';
                    print "<td colspan=8><b>Differences:</b>";
                    print $message['diff'];
                    print "</td></tr>";
                }

                $c = 1 - $c;
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
                print "<td>" . add_tooltip($recent['state'], $this->state_help_notes($recent['state'])) . "</td>";
                print "<td>" .  $this->make_ids_links(htmlspecialchars($recent['message'])) . "</td>";
                print "</tr>";
            }
?>
</table>
<?
    }

    function display($self_link) {
        $this->self_link = $self_link;

        #print "<pre>"; print_r($_POST); print "</pre>";
        $view = get_http_var('view');
        $id = get_http_var("id");
        
        // Display about id
        if ($id) {
            print "<h2><a href=\"$self_link\">[Back to Message List]</a></h2></h2>";

            // Freeze or thaw messages
            if (get_http_var('freeze')) {
                $result = msg_admin_freeze_message($id, http_auth_user());
                msg_check_error($result);
                print "<p><b><i>Message $id frozen</i></b></p>";
            } else if (get_http_var('thaw')) {
                $result = msg_admin_thaw_message($id, http_auth_user());
                msg_check_error($result);
                print "<p><b><i>Message $id thawed</i></b></p>";
            } else if (get_http_var('error')) {
                $result = msg_admin_set_message_to_error($id, http_auth_user());
                msg_check_error($result);
                print "<p><b><i>Message $id moved to error state</i></b></p>";
            } else if (get_http_var('failed')) {
                $result = msg_admin_set_message_to_failed($id, http_auth_user());
                msg_check_error($result);
                print "<p><b><i>Message $id moved to failed state</i></b></p>";
            } else if (get_http_var('failed_closed')) {
                $result = msg_admin_set_message_to_failed_closed($id, http_auth_user());
                msg_check_error($result);
                print "<p><b><i>Message $id moved to failed_closed state</i></b></p>";
            } else if (get_http_var('bounce_wait')) {
                $result = msg_admin_set_message_to_bounce_wait($id, http_auth_user());
                msg_check_error($result);
                print "<p><b><i>Message $id moved to bounce_wait state</i></b></p>";
            } else if (get_http_var('note')) {
                $result = msg_admin_add_note_to_message($id, http_auth_user(), get_http_var('notebody'));
                msg_check_error($result);
                print "<p><b><i>Note added to message $id</i></b></p>";
            }


            // Display general information
            print "<h2>Message id $id:</h2>";

            $message = msg_admin_get_message($id);
            if (msg_get_error($message)) {
                print "Error contacting queue:";
                print_r($message);
            }
            $this->print_message($message);

            // Commands
            $form = new HTML_QuickForm('messageForm', 'post', $self_link);
            if ($message['state'] == 'failed')
                $actiongroup[] = &HTML_QuickForm::createElement('submit', 'failed_closed', 'Fail Close');
            if ($message['frozen']) {
                if ($message['state'] != 'error' and $message['state'] != 'failed' and $message['state'] != 'failed_closed') 
                    $actiongroup[] = &HTML_QuickForm::createElement('submit', 'error', 'Error');
                if ($message['state'] != 'failed' and $message['state'] != 'failed_closed')
                    $actiongroup[] = &HTML_QuickForm::createElement('submit', 'failed', 'Fail');
                if ($message['state'] != 'error' and $message['state'] != 'failed' and $message['state'] != 'failed_closed')
                    $actiongroup[] = &HTML_QuickForm::createElement('submit', 'thaw', 'Thaw');
            }
            else {
                if ($message['state'] != 'error' and $message['state'] != 'failed' and $message['state'] != 'failed_closed')
                    $actiongroup[] = &HTML_QuickForm::createElement('submit', 'freeze', 'Freeze');
            }
            if (!get_http_var('body'))
                $actiongroup[] = &HTML_QuickForm::createElement('submit', 'body', 'View Body');
            else
                $actiongroup[] = &HTML_QuickForm::createElement('submit', 'nobody', 'Hide Body');
            if (!get_http_var('note')) {
                $actiongroup[] = &HTML_QuickForm::createElement('static', null, null, "&nbsp;");
                $actiongroup[] = &HTML_QuickForm::createElement('text', 'notebody', null, array('size'=>30));
                $actiongroup[] = &HTML_QuickForm::createElement('submit', 'note', 'Add Comment');
            }
            $form->addElement('hidden', 'id', $id);
            $form->addGroup($actiongroup, "actiongroup", "",' ', false);

            admin_render_form($form);
            print "<a href=\"" .  new_url("", true, 'view',
            'similarbody', 'simto', $id, 'id', null) .  "\">View similar messages</a> ";

            // Body text if enabled
            if (get_http_var('body')) {
                print "<h2>Body text of message (only read if you really need to)</h2>";
                print "<blockquote>";
                print nl2br(htmlspecialchars($message['message']));
                print "</blockquote>";
            }

            // Questionnaire answers if there are any
            if (is_array($message['questionnaires']) and count($message['questionnaires']) > 0) {
                print "<h2>Questionnaire Responses</h2>";
                foreach ($message['questionnaires'] as $q) {
                    if ($q['question_id'] == 0) {
                        print "Reply within two/three weeks:";
                    } elseif ($q['question_id'] == 1) {
                        print "First time contacted any representative:";
                    } else {
                        print $q['question_id'] . ":";
                    }
                    print " <b>" . $q['answer'] .  "</b><br>";
                }
            }
 
            // Log of what has happened to message
            print "<h2>All events for this message:</h2>";
            $recents = msg_admin_message_events($id);
            if (msg_get_error($recents)) {
                print "Error contacting queue:";
                print_r($recents);
                $recents = array();
            }
            $this->print_events($recents);
?>
<h2>Help</h2>
<p>
<b>state meanings</b>, point the mouse to find out what they are
<br><b>edit contact details</b> by clicking on the recipient name
<br><b>freeze</b> stops delivery to representative, but other stuff
(such as confirmation message) still happens
<br><b>thaw</b> undoes a freeze, so message gets delivered.
<br><b>error</b> rejects a message, sending a "could not deliver" email to constituent.
<br><b>fail</b> rejects a message, with no email to the constituent.
<br><b>fail close</b> marks a failed message so it doesn't appear in important list any more.
<br><b>view body</b> should only be done if you have good reason to believe it is an abuse of our service.
<?
            if (count($message['bounces']) > 0) {
                print "<h2>Bounce Messages</h2>";
                foreach ($message['bounces'] as $bounce) {
                    print "<hr>";
                    print "<blockquote>" .  nl2br(htmlspecialchars($bounce)) .  "</blockquote>";
                }
                print "<hr>";
                if ($message['state'] == 'bounce_confirm') {
                    $form = new HTML_QuickForm('bounceForm', 'post', $self_link);
                    $bouncegroup[] = &HTML_QuickForm::createElement('submit', 'error', 'Fatal Delivery Error');
                    $bouncegroup[] = &HTML_QuickForm::createElement('submit', 'bounce_wait', 'Temporary Problem');
                    $form->addGroup($bouncegroup, "bouncegroup", "Which kind of bounce message is this?",' ', false);
                    $form->addElement('hidden', 'id', $id);
                    admin_render_form($form);
                }
            }
         } else {
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
        if (stristr($k, "state ")) {
            print "<tr><td>";
            print add_tooltip($k, $this->state_help_notes(str_replace("state ", "", $k)));
            print "</td><td>$v</td></tr>\n";
        }
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
</td></tr>
</table>

<?
            // Decide what message view to show
            $params = array();
            if ($view == "all") {
                $filter = 0;
            } else if ($view == "recentchanged") {
                $filter = 2;
            } else if ($view == "recentcreated") {
                $filter = 3;
            } else if ($view == "similarbody") {
                $filter = 4;
                $params['msgid'] = get_http_var('simto');
            } else { # important
                $filter = 1;
            }
         
            // Show it
            $messages = msg_admin_get_queue($filter, $params);
            if (msg_get_error($messages)) {
                print "Error contacting queue:";
                print_r($messages);
                $messages = array();
            }

            print "<h2>View messages which: ";
            if ($filter != 1)
                print "<a href=\"$self_link&amp;view=important\">[Need Attention]</a> ";
            else
                print "[Need Attention " . count($messages) . "] ";
            if ($filter != 3)
                print "<a href=\"$self_link&amp;view=recentcreated\">[Recently Created]</a> ";
            else
                print "[Recently Created] ";
            if ($filter != 2)
                print "<a href=\"$self_link&amp;view=recentchanged\">[Recently Changed]</a> ";
            else
                print "[Recently Changed] ";
            if ($filter == 4)
                print "[Similar to " .  $this->make_ids_links($params['msgid']) . "] ";


# Too slow, doesn't really work, so disabled for now
#            if ($filter != 0)
#                print "<a href=\"$self_link&amp;view=all\">[Entire Queue]</a> ";
#            else
#                print "[Entire Queue] ";
            print "</h2>";

            $this->print_messages($messages);
            if ($filter == 2)
                print "<p>...";
        ?>
<h2>What do the states mean?</h2>
<p>Point the mouse a state name in the table above for extra description.
Here is a diagram of state changes:
<p><img src="queue-state-machine.png">

        <?
        }
    }
}

?>
