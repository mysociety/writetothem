<?php
/*
 * admin-fyrqueue.php:
 * FYR queue admin page.
 * 
 * Copyright (c) 2004 UK Citizens Online Democracy. All rights reserved.
 * Email: francis@mysociety.org. WWW: http://www.mysociety.org
 *
 * $Id: admin-fyrqueue.php,v 1.80 2005-02-23 14:59:44 francis Exp $
 * 
 */

require_once "queue.php";
require_once "../../phplib/utility.php";

class ADMIN_PAGE_FYR_QUEUE {
    function ADMIN_PAGE_FYR_QUEUE () {
        $this->id = "fyrqueue";
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
        $text = htmlspecialchars($text);
        // Message ids e.g. 0361593135850d75745e
        $text = preg_replace("/([a-f0-9]{20})/", 
                "<a href=\"" .  $this->self_link . "&id=\$1\">\$1</a>", 
                $text);
        // Ratty rules e.g. rule #10
        $text = preg_replace("/rule #([0-9]+)/",
                "<a href=\"?page=ratty-fyr-abuse&action=editrule&rule_id=\$1\">rule #\$1</a>",
                $text);
        return $text;
    }

    function render_bar($view, $reverse, $id) {
        if ($id) $view = "none";
    
        // Activity level
        $stats = msg_admin_get_stats();
        if (msg_get_error($stats)) {
            print "Error contacting queue:";
            print_r($stats);
        }
        print "<p>";
        print "<b>" . $stats["created_1"] . "</b> new in hour, ";
        print "<b>" . $stats["created_24"] . "</b> new in day... ";

        // Quick referrers
        $freq_referrers_day = msg_admin_get_popular_referrers(60 * 60 * 24);
/*      # for testing  
        $freq_referrers_day = array(
        array("http://www.faxyourmp.com/youandyourmp.php3", 7),
        array("http://www.google.co.uk/search?hl=en&q=fax+your+mp&meta=", 4),
        array("http://www.stophumantraffic.org/writemp.html", 2),
        array("http://www.google.co.uk/search?hl=en&q=faxyourmp&meta=", 2),
        array("http://www.google.co.uk/search?hl=en&client=firefox-a&rls=org.mozilla%3Aen-GB%3Aofficial_s&q=local+mp&btnG=Search&meta=", 2)
        );*/
        if (msg_get_error($freq_referrers_day)) {
            print "Error contacting queue:";
            print_r($freq_referrers_day);
        }
        print "top referrers: ";
        foreach ($freq_referrers_day as $row) {
            if (!preg_match('#^http://(www\.)?(google|faxyourmp|writetothem)\.#i', $row[0])) {
                if ($row[1] > 1 && $row[0] != "") {
                    print trim_url_to_domain($row[0]) . " $row[1], ";
                }
            }
        }
        print " <a href=\"$this->self_link&amp;view=statistics\">more stats...</a> ";

        // Bar to change view
        $qmenu = "";
        if ($view == 'frozen')
            $qmenu .= "[Frozen] ";
        else
            $qmenu .= "<a href=\"$this->self_link&amp;view=frozen\">[Frozen]</a> ";

        if ($view != 'failing')
            $qmenu .= "<a href=\"$this->self_link&amp;view=failing\">[Failing]</a> ";
        else
            $qmenu .= "[Failing] ";

        if ($view != 'recentcreated')
            $qmenu .= "<a href=\"$this->self_link&amp;view=recentcreated\">[Recent Created]</a> ";
        else
            $qmenu .= "[Recent Created] ";
            
        if ($view != 'recentchanged')
            $qmenu .= "<a href=\"$this->self_link&amp;view=recentchanged\">[Recent Changed]</a> ";
        else
            $qmenu .= "[Recent Changed] ";

        $qmenu .= "[Contains ";

        $form = new HTML_QuickForm('searchForm', 'post', $this->self_link);
        $searchgroup[] = &HTML_QuickForm::createElement('static', null, null, "<b>$qmenu</b>");
        $searchgroup[] = &HTML_QuickForm::createElement('text', 'query', null, array('size'=>12));
        $searchgroup[] = &HTML_QuickForm::createElement('submit', 'search', 'Search');
        $searchgroup[] = &HTML_QuickForm::createElement('static', null, null, "<b>]</b>");
        $form->addGroup($searchgroup, "actiongroup", "",' ', false);
        admin_render_form($form);

    }



    /* print_messages MESSAGES [ID]
     * Print a table giving information about the MESSAGES (array of
     * associative arrays of message data). If ID is given, it is the ID of the
     * message being compared against in a "similar messages" search. */
    function print_messages($messages, $msgid = null) {
?>

<table border="1" width="100%">
    <tr>
        <th>Created</th>
        <th>ID</th>
        <th>Last State Change</th>
        <th>State</th>
        <th>Sender</th>
        <th>Recipient</th>
        <th>Client IP / <br> Referrer</th>
        <th>Length (chars)</th>
        <th>Tick</th>
    </tr>
    <form action="<?=htmlspecialchars(new_url("", false, 'view', get_http_var('view'), 'simto', get_http_var('simto'), 'page', get_http_var('page'))) ?>" method="post">
<?
	$c = 1;
            foreach ($messages as $message) {
                print '<tr' . ($c==1 ? ' class="v"' : '') . '>';
                print "<td>" . strftime('%Y-%m-%d %H:%M:%S', $message['created']) . "</td>";
                print '<td><a href="'
                        . $this->self_link . "&id=".urlencode($message['id'])
                        . '">'
                        .  substr($message['id'], 0, 10) . "<br/>" .  substr($message['id'], 10)
                        . '</a></td>';
                print "<td>" . strftime('%Y-%m-%d %H:%M:%S', $message['laststatechange']) . "</td>";
                print "<td>";
                print add_tooltip($message['state'], $this->state_help_notes($message['state']));
                /* Only show frozen flag if the message is in a state this can
                 * affect. */
                if ($message['frozen']) 
                {
                    if ($message['state'] == 'new' or $message['state'] == 'pending' or $message['state'] == 'ready')
                        print "<br><b>frozen</b>";
                    else
                        print "<br>frozen";
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

                if ($message['lastaction'] > 0)
                    print "<br>Last: " .  strftime('%Y-%m-%d %H:%M:%S', $message['lastaction']);

                print "</td>";
                print "<td>"
                        . htmlspecialchars($message['sender_name']) . "<br>"
                        . htmlspecialchars($message['sender_addr']) . "<br>"
                        . htmlspecialchars($message['sender_email'])
                        . "</td>";

                $display_name = $message['recipient_name'];
                if (!isset($display_name) || $display_name == "") {
                    $display_name = "scrubbed, id " .
                    $message['recipient_id'] . ", " .
                    $message['recipient_type'];
                }
                print '<td><a href="'
                        . htmlspecialchars(new_url('', false, 'page', 'reps', 'rep_id', $message['recipient_id'], 'pc', $message['sender_postcode']))                   . '">' . htmlspecialchars($display_name)
                        . "</a><br>";
                if ($message['recipient_via']) {
                    $repinfo = dadem_get_representative_info($message['recipient_id']);
                    dadem_check_error($repinfo);
                    $vainfo = mapit_get_voting_area_info($repinfo['voting_area']);
                    mapit_check_error($vainfo);
                    $parentinfo = mapit_get_voting_area_info($vainfo['parent_area_id']);
                    mapit_check_error($parentinfo);
                    print '<a href="' .
                       htmlspecialchars(new_url('', false, 'page', 'reps', 'ds_va_id', $vainfo['parent_area_id'], 'pc', $message['sender_postcode']))  . '">' . 
                        htmlspecialchars("via " . $parentinfo['name']) . "</a>:<br>";
                }
                if ($message['recipient_email'])
                    print htmlspecialchars($message['recipient_email']) . "<br>";
                if ($message['recipient_fax'])
                    print htmlspecialchars($message['recipient_fax']) . "<br>";
                print "</td>";

                print "<td><a href=\"ipaddrinfo.cgi?ipaddr=${message['sender_ipaddr']}\">${message['sender_ipaddr']}</a><br>"
                        . trim_url($message['sender_referrer']) . "</td>";
                print "<td>${message['message_length']}</td>";

                /* Javascript code changes shading of table row for checked
                 * messages to make them look "selected". */
                print '<td><input type="checkbox" name="check_'
                        . $message['id']
                        . '" onclick="this.parentNode.parentNode.className = this.checked ? \'h\' : \''
                            . ($c == 1 ? 'v' : '')
                            . '\'" >';

                print "</tr>";
                # this.checked this .className=

                if (array_key_exists('diff', $message)) {
                    /* Each element of the array consists either of an array of
                     * two strings, which are the strings unique to the "from"
                     * and "to" strings; or a string, representing a common
                     * part; or null, indicating an elided part. */
                    print '<tr'.($c==1?' class="v"':'').'><td colspan = "9">';
                    print "<b>Differences:</b> ";
                    if (isset($msgid) and $message['id'] == $msgid) {
                        print 'This is the message being compared against. <span class="difffrom">Text that appears only in this message, </span><span class="diffto">or only in the other message.</span>';
                    } else {
                        foreach ($message['diff'] as $elem) {
                            if (!isset($elem)) {
                                print '<span class="diffsnipped">[ ... snipped ... ]</span>';
                            } else if (is_array($elem)) {
                                print '<span class="difffrom">'
                                        . htmlspecialchars($elem[0])
                                        . '</span><span class="diffto">'
                                        . htmlspecialchars($elem[1])
                                        . '</span>';
                            } else {
                                print htmlspecialchars($elem);
                            }
                        }
                    }
                    print "</td></tr>";
                }

                $c = 1 - $c;
            }
            if (count($messages) > 1) {
?><tr><td colspan=9><b>Ticked items:</b>
        <input size="20" name="notebody" type="text" /> 
        <input name="note" value="Note" type="submit" />
        &nbsp; <b>Action:</b>
        <input name="freeze" value="Freeze" type="submit" />
        <input name="thaw" value="Thaw" type="submit" />
        <input name="error" value="Error" type="submit" />
        <input name="failed" value="Fail silently" type="submit" />
        <input name="failed_closed" value="Fail Close" type="submit" /> 
</td><tr>
<?
    }
?>
</form>
</table>
<?
    }

    /* print_message MESSAGE
     * Print a single message, as for print_messages above. */
    function print_message($message) {
        $this->print_messages(array($message));
    }


    /* print_events EVENTS
     * Print a list of logged EVENTS in a table. */
    function print_events($recents) {
?>
<p>
<table border=1
width=100%><tr><th>Time</th><th>ID</th><th>State</th><th>Event</th></tr>
<?
            foreach ($recents as $recent) {
                print "<tr>";
                print "<td>" . strftime('%Y-%m-%d %H:%M:%S', $recent['whenlogged']) . "</td>";
                print "<td>" . substr($recent['message_id'],0,10) .  "<br/>" . substr($recent['message_id'],10) . "</td>";
                print "<td>" . add_tooltip($recent['state'], $this->state_help_notes($recent['state'])) . "</td>";
                print "<td>" . $this->make_ids_links($recent['message']) . "</td>";
                print "</tr>";
            }
?>
</table>
</p>
<?
    }

    function do_actions($id) {
        // Freeze or thaw messages
        $redirect = false;
        if (get_http_var('freeze')) {
            $result = msg_admin_freeze_message($id, http_auth_user());
            msg_check_error($result);
            print "<p><b><i>Message $id frozen</i></b></p>";
            $redirect = true;
        } else if (get_http_var('thaw')) {
            $result = msg_admin_thaw_message($id, http_auth_user());
            msg_check_error($result);
            print "<p><b><i>Message $id thawed</i></b></p>";
            $redirect = true;
        } else if (get_http_var('error')) {
            $result = msg_admin_set_message_to_error($id, http_auth_user());
            msg_check_error($result);
            print "<p><b><i>Message $id moved to error state</i></b></p>";
            $redirect = true;
        } else if (get_http_var('failed')) {
            $result = msg_admin_set_message_to_failed($id, http_auth_user());
            msg_check_error($result);
            print "<p><b><i>Message $id moved to failed state</i></b></p>";
            $redirect = true;
        } else if (get_http_var('failed_closed')) {
            $result = msg_admin_set_message_to_failed_closed($id, http_auth_user());
            msg_check_error($result);
            print "<p><b><i>Message $id moved to failed_closed state</i></b></p>";
            $redirect = true;
        } else if (get_http_var('bounce_wait')) {
            $result = msg_admin_set_message_to_bounce_wait($id, http_auth_user());
            msg_check_error($result);
            print "<p><b><i>Message $id moved to bounce_wait state</i></b></p>";
            $redirect = true;
        } else if (get_http_var('ready')) {
            $result = msg_admin_set_message_to_ready($id, http_auth_user());
            msg_check_error($result);
            print "<p><b><i>Message $id moved to ready state</i></b></p>";
            $redirect = true;
        } else if (get_http_var('note')) {
            $result = msg_admin_add_note_to_message($id, http_auth_user(), get_http_var('notebody'));
            msg_check_error($result);
            print "<p><b><i>Note added to message $id</i></b></p>";
            $redirect = true;
        }
        return $redirect;
    }

    function display($self_link) {
        $this->self_link = $self_link;

        #print "<pre>"; print_r($_POST); print "</pre>";
        $view = get_http_var('view', 'frozen');
        $id = get_http_var("id");

        // Display about id
        if ($id) {
            if ($this->do_actions($id)) {
#               header("Location: ".$_SERVER['REQUEST_URI'] . "\n");
#               exit;
            }

            // Navigation bar
            $this->render_bar($view, false, $id);

            // Display general information
            print "<h2>Message id " . $this->make_ids_links($id) . ":</h2>";

            $message = msg_admin_get_message($id);
            if (msg_get_error($message)) {
                print "Error contacting queue:";
                print_r($message);
            }
            $this->print_message($message);

            // Commands
            $form = new HTML_QuickForm('messageForm', 'post', $self_link);
            if (!get_http_var('note')) {
                $actiongroup[] = &HTML_QuickForm::createElement('text', 'notebody', null, array('size'=>30));
                $actiongroup[] = &HTML_QuickForm::createElement('submit', 'note', 'Note');
            }
            $actiongroup[] = &HTML_QuickForm::createElement('static', null, null, " <b>Action:</b>");
            if ($message['frozen']) {
                if ($message['state'] != 'error' and $message['state'] != 'failed' and $message['state'] != 'failed_closed') 
                    $actiongroup[] = &HTML_QuickForm::createElement('submit', 'error', 'Error');
                if ($message['state'] != 'failed' and $message['state'] != 'failed_closed')
                    $actiongroup[] = &HTML_QuickForm::createElement('submit', 'failed', 'Fail silently');
                if ($message['state'] != 'error' and $message['state'] != 'failed' and $message['state'] != 'failed_closed')
                    $actiongroup[] = &HTML_QuickForm::createElement('submit', 'thaw', 'Thaw');
            }
            else {
                if ($message['state'] != 'error' and $message['state'] != 'failed' and $message['state'] != 'failed_closed')
                    $actiongroup[] = &HTML_QuickForm::createElement('submit', 'freeze', 'Freeze');
            }
            $actiongroup[] = &HTML_QuickForm::createElement('submit', 'failed_closed', 'Fail Close');
            if ($message['state'] == 'pending')
                $actiongroup[] = &HTML_QuickForm::createElement('submit', 'ready', 'Confirm');

            if (!get_http_var('body'))
                $actiongroup[] = &HTML_QuickForm::createElement('submit', 'body', 'View Body');
            else
                $actiongroup[] = &HTML_QuickForm::createElement('submit', 'nobody', 'Hide Body');
            $form->addElement('hidden', 'id', $id);
            $form->addGroup($actiongroup, "actiongroup", "",' ', false);

            admin_render_form($form);
            print '<a href="'
                    .  htmlspecialchars(new_url("", true, 'view', 'similarbody', 'simto', $id, 'id', null))
                    .  '">View similar messages</a> ';

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
            $allevents = get_http_var('allevents', 0);

            print '<h2>' .
                    ($allevents
                        ? 'All events for this message'
                        : 'Important events for this message')
                    . '</h2>';

            print ($allevents
                    ? '<a href="'
                        . htmlspecialchars(new_url('', true, 'allevents', null))
                        . '">View only important events</a>'
                    : '<a href="'
                        . htmlspecialchars(new_url('', true, 'allevents', 1))
                        . '">View all events</a>');
                    
            $recents = msg_admin_message_events($id, !$allevents);
            if (msg_get_error($recents)) {
                print "Error contacting queue:";
                print_r($recents);
                $recents = array();
            }

            $this->print_events($recents);

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
         } elseif ($view == 'statistics') {
            // Display general statistics
            $stats = msg_admin_get_stats();
            if (msg_get_error($stats)) {
                print "Error contacting queue:";
                print_r($stats);
            }

            $freq_referrers_day = msg_admin_get_popular_referrers(60 * 60 * 24);
            if (msg_get_error($freq_referrers_day)) {
                print "Error contacting queue:";
                print_r($freq_referrers_day);
            }

            $freq_referrers_week = msg_admin_get_popular_referrers(60 * 60 * 24 * 7);
            if (msg_get_error($freq_referrers_week)) {
                print "Error contacting queue:";
                print_r($freq_referrers_week);
            }

            // Navigation bar
            $this->render_bar($view, false, $id);
?>
<h2>Queue statistics</h2>
<p>
<b><?=$stats["created_1"]?></b> new in last hour,
<b><?=$stats["created_24"]?></b> new in last day
</p>

<h2>Messages in each state</h2>
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
<h2>Types of representatives</h2>
<table border=1>
<?
    foreach ($stats as $k=>$v) {
        if (stristr($k, "type ")) print "<tr><td>$k</td><td>$v</td></tr>\n";
    }
    print "<tr><td>Total:</td><td>" . $stats['message_count'] .  "</td></tr>\n";
?>
</table>
<h2>Top referrers in last day</h2>
<table border=1>
<?
    foreach ($freq_referrers_day as $row) {
        if ($row[1] > 1 && $row[0] != "") {
            print "<tr><td>" . trim_url($row[0]) . "</td><td>$row[1]</td></tr>";
        }
    }
?>
</table>
<h2>Top referrers in last week</h2>
<table border=1>
<?
    foreach ($freq_referrers_week as $row) {
        if ($row[1] > 1 && $row[0] != "") {
            print "<tr><td>" . trim_url($row[0]) . "</td><td>$row[1]</td></tr>";
        }
    }
?>
</table>
<?
         } else {
            // Perform actions on checked items
            $sender_emails = array();
            $sender_full = array();
            foreach ($_POST as $k=>$v) {
                if (stristr($k, "check_")) {
                    $checkid = str_replace("check_", "", $k);
                    $this->do_actions($checkid);
                    $message = msg_admin_get_message($checkid);
                    array_push($sender_emails, $message['sender_email']);
                    array_push($sender_full, $message['sender_name'] . 
                            " &lt;" .  $message['sender_email'] . "&gt;");
                }
            }
            if (count($sender_emails) > 0) {
                print "<p><b>Email list for BCCing:</b><br>" .
                    implode(",", array_unique($sender_emails));
                print "<p><b>List of names and addresses:</b><br>" .
                    implode("<br>", array_unique($sender_full));
            }

            // Decide what message view to show
            $params = array();
            $reverse = false;
            if (stristr($view, "_rev")) {
                $view = str_replace("_rev", "", $view);
                $reverse = true;
            }
            if (get_http_var('search')) {
                $view = "search";
            }

            // Set up additional parameters for view if necessary.
            if ($view == "similarbody") {
                $params['msgid'] = get_http_var('simto');
            } else if ($view == "search" || $view == "logsearch") {
                $params['query'] = get_http_var('query');
            }
            
            // Get details about view
            $messages = msg_admin_get_queue($view, $params);
            if (msg_get_error($messages)) {
                print "Error contacting queue:";
                print_r($messages);
                $messages = array();
            }

            // Navigation bar
            $this->render_bar($view, $reverse, $id);

            // Display messages
            print "<h2>Messages which";
            if ($view == "similarbody") {
                print " have similar bodies to  " . $this->make_ids_links(get_http_var('simto'));
            } elseif ($view == "search") {
                print " match search query '" . htmlspecialchars(get_http_var('query')) . "'";
            } elseif ($view == "logsearch") {
                print " whose log matches '" . htmlspecialchars(get_http_var('query')) . "'";
            } else {
                print " are $view";
            }
            print " (" . count($messages) . " of them): </h2>";
            if ($reverse) {
                $messages = array_reverse($messages);
            }
            $this->print_messages($messages, $view == 'similarbody' ? $params['msgid'] : null);
            if ($view == 'recentchanged' or $view == 'recentcreated')
                print "<p>..."; /* indicate that this isn't all the messages... */

            // Help
            ?>
            <h2>Help &mdash; what views/searches are there?</h2>
            <p>
            <b>Frozen:</b> Message which are frozen, most likely
            due to possible abuse.
            <br><b>Failing:</b> Messages for which delivery is failing, most like
            incorrect contact details.  Sorted by recipient.
            <br><b>Recently Created:</b> Most recent messages constituents have made.
            <br><b>Recently Changed:</b> Messages which something has happened to recently.
            <br><b>Similar to:</b> Shows messages with bodies similar to a given message.
            Click on "View similar messages" from a message page to get to this view.  Displays
            colourful diffs of the differences.
            <br><b>Contains:</b> Searches the sender details, recipient details and message
            body.  Enter multiple terms separate by spaces, all must be
            present to match.  If you query by state name ('pending') or
            representative type ('EUR') you must enter the whole word, case
            sensitive.  Otherwise queries are case insensitive.  Yes, you
            can query on the referrer URL.  If you have one, you can enter
            a confirmation or questionnaire token from an email, such as
            cqyv7yrisjugc5i5rfz4w75tmnxzi.  Examples: '<b>ready EUR</b>' - all messages to MEPs
            which are ready to be sent.  '<b>francis theyworkforyou</b>' - probably
            all messages written by someone called Francis who came to WTT via
            theyworkforyou.com.
            </p>
            <?
        }
        if ($view != "statistics") {
?>
<h2>Help &mdash; what do the buttons do?</h2>
<?
        if (!$id) {
?>
<p>They apply to all items you have checked.</p>
<?
        }
?>
<p>
<b>note</b> adds the text entered as a remark in the message's log
<br><b>freeze</b> stops delivery to representative, but other stuff
(such as confirmation message) still happens
<br><b>thaw</b> undoes a freeze, so message gets delivered.
<br><b>error</b> rejects a message, sending a "could not deliver" email to constituent.
<br><b>fail</b> rejects a message, with no email to the constituent.
<br><b>fail close</b> marks a failed message so it doesn't appear in important list any more.
<br><b>confirm</b> moves 'pending' to 'ready', the same as user clicking confirm link in email
<br><b>view body</b> should only be done if you have good reason to believe it is an abuse of our service.
<br><b>edit contact details</b> by clicking on the recipient name
</p>
<p>To find out <b>state meanings</b>, point the mouse to find out what they are
</p>
<?
        if (!$id) {
        ?>
<h2>Help &mdash; what do the states mean?</h2>
<p>Here is a diagram of state changes:</p>
<p><img src="queue-state-machine.png"></p>

        <?
        }
        }
    }
}

?>
