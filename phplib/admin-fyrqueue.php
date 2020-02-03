<?php
/*
 * admin-fyrqueue.php:
 * FYR queue admin page.
 *
 * Copyright (c) 2004 UK Citizens Online Democracy. All rights reserved.
 * WWW: https://www.mysociety.org
 *
 */

require_once "queue.php";
require_once "../commonlib/phplib/utility.php";

$state_help_notes_map = array(
    'new' => 'About to send confirmation email to constituent',
    'pending' => 'Waiting for confirmation from constituent',
    'ready' => 'Attempting to send to representative',
    'bounce_wait' => 'Waiting for possible email delivery failure message',
    'bounce_confirm' => 'Email delivery failure received, admin',
    'error' => 'About to tell constituent that delivery failed',
    'sent' => 'Delivery to representative succeeded',
    'finished' => 'Delivery succeeded, message/IP/referrer scrubbed',
    'anonymised' => 'Delivery succeeded, personal data has been scrubbed',
    'failed' => 'Delivery to representative failed',
    'failed_closed' => 'Delivery failed, admin has dealt with it / timed out',
);

function make_mailto_link($email, $subject, $body, $link)
{
    print '<a href="mailto:' . rawurlencode($email) .
        "?subject=" . rawurlencode($subject) .
        "&amp;body=" . rawurlencode($body) .
    "\">$link</a> ";
}

function get_token()
{
    $secret = msg_secret();
    $token = sha1((http_auth_user() . $secret));
    return $token;
}

class ADMIN_PAGE_FYR_QUEUE
{
    public function __construct()
    {
        $this->id = "fyrqueue";
        $this->navname = "Message Queue";
    }

    private function stateHelpNotes($state)
    {
        global $state_help_notes_map;
        return $state_help_notes_map[$state];
    }

    private function renderBar($view, $reverse, $id)
    {
        if ($id) {
            $view = "none";
        }

        // Quick referrers
        print "<p>";
        $freq_referrers_day = msg_admin_get_popular_referrers(60 * 60 * 24);
        # for testing
        /*$freq_referrers_day = array(
        array("http://www.mouse.com/youandyourmp.php3", 7),
        array("http://www.google.co.uk/search?hl=en&q=fax+your+mp&meta=", 4),
        array("http://www.stophumantraffic.org/writemp.html", 2),
        array("http://www.google.co.uk/search?hl=en&q=faxyourmp&meta=", 2),
        array("http://www.google.co.uk/search?hl=en&client=firefox-a&rls=org.mozilla%3Aen-GB%3Aofficial_s&q=local+mp&btnG=Search&meta=", 2)
        );*/
        if (msg_get_error($freq_referrers_day)) {
            print "Error contacting queue:";
            print_r($freq_referrers_day);
        }
        print "top referrers in day: ";
        $topref = array();
        foreach ($freq_referrers_day as $row) {
            if (!preg_match('#^https?://(www\.)?(google|faxyourmp|writetothem|theyworkforyou)\.#i', $row[0])) {
                if ($row[1] > 1 && $row[0] != "") {
                    $topref[] = trim_url_to_domain($row[0]) . " $row[1]";
                }
            }
        }
        print join(", ", $topref);
        print " ... <a href=\"$this->self_link&amp;view=statistics\">more stats</a> ";
        print "</p>";

        // Bar to change view
        $qmenu = "";
        if ($view == 'needattention') {
            $qmenu .= "[Need Attention] ";
        } else {
            $qmenu .= "<a href=\"$this->self_link&amp;view=needattention\">[Need Attention]</a> ";
        }

        if ($view != 'failing') {
            $qmenu .= "<a href=\"$this->self_link&amp;view=failing\">[Failing]</a> ";
        } else {
            $qmenu .= "[Failing] ";
        }

        if ($view != 'recentcreated') {
            $qmenu .= "<a href=\"$this->self_link&amp;view=recentcreated\">[Recent Created]</a> ";
        } else {
            $qmenu .= "[Recent Created] ";
        }

        if ($view != 'recentchanged') {
            $qmenu .= "<a href=\"$this->self_link&amp;view=recentchanged\">[Recent Changed]</a> ";
        } else {
            $qmenu .= "[Recent Changed] ";
        }

        $qmenu .= "[Contains ";

        if ($view != 'statistics') {
            $qmenu2 = "<a href=\"$this->self_link&amp;view=statistics\">[Statistics]</a> ";
        } else {
            $qmenu2 = "[Statistics] ";
        }

        $form = new HTML_QuickForm('searchForm', 'post', $this->self_link);
        $searchgroup = array();
        $searchgroup[] = $form->createElement('static', null, null, "<b>$qmenu</b>");
        $searchgroup[] = $form->createElement('text', 'query', null, array('size'=>12));
        $searchgroup[] = $form->createElement('submit', 'search', 'Search');
        $searchgroup[] = $form->createElement('static', null, null, "<b>]</b>");
        $searchgroup[] = $form->createElement('static', null, null, "<b>$qmenu2</b>");
        $form->addGroup($searchgroup, "actiongroup", "", ' ', false);
        admin_render_form($form);
    }

    private function printMessagesHeader()
    {
?>

<table border="1" width="100%">
    <tr>
        <th>Created</th>
        <th>Message ID /<br>Group ID</th>
        <th>Last state change</th>
        <th>State /<br>Last action</th>
        <th>Sender</th>
        <th>Recipient</th>
        <th>Client IP / <br> Referrer / Cobrand</th>
        <th>Length (chars)</th>
        <th>Questionnaire</th>
        <th>Tick</th>
    </tr>
    <form action="<?=htmlspecialchars(url_new("", false, 'view', get_http_var('view'), 'simto', get_http_var('simto'), 'page', get_http_var('page'))) ?>" method="post">
<?php
    }

    /* printMessages MESSAGES [ID]
     * Print a table giving information about the MESSAGES (array of
     * associative arrays of message data). If ID is given, it is the ID of the
     * message being compared against in a "similar messages" search. */
    private function printMessages($messages, $msgid = null)
    {
        $this->printMessagesHeader();
        $c = 1;
        foreach ($messages as $message) {
            print '<tr' . ($c==1 ? ' class="v"' : '') . '>';
            print "<td>" . strftime('%Y-%m-%d %H:%M:%S', $message['created']) . "</td>";
            print '<td><a href="'
                    . $this->self_link . "&id=".urlencode($message['id'])
                    . '">'
                    .  $message['id'] . '</a>'
                    . "<br>${message['group_id']}"
                    . '</td>';
            print "<td>" . strftime('%Y-%m-%d %H:%M:%S', $message['laststatechange']) . "</td>";
            print "<td>";
            print add_tooltip($message['state'], $this->stateHelpNotes($message['state']));
            /* Only show frozen flag if the message is in a state this can
             * affect. */
            if ($message['frozen']) {
                if ($message['state'] == 'new' or $message['state'] == 'pending' or $message['state'] == 'ready') {
                    print "<br><b>frozen</b>";
                } else {
                    print "<br>frozen";
                }
            }

            if ($message['numactions'] > 0) {
                if ($message['state'] == 'pending') {
                    print "<br>" .  ($message['numactions'] - 1) . " ".
                    make_plural(($message['numactions'] - 1), 'reminder');
                } elseif ($message['state'] == 'sent' || $message['state'] == 'failed_closed') {
                    print "<br>". $message['numactions'] . " " .
                    make_plural($message['numactions'], 'day');
                } elseif ($message['state'] == 'ready') {
                    print "<br>". $message['numactions'] . " " .
                    make_plural($message['numactions'], 'failure');
                } else {
                    print "<br>". $message['numactions'] . " " .
                    make_plural($message['numactions'], 'attempt');
                }
            }

            if ($message['lastaction'] > 0 && $message['state'] != 'failed_closed') {
                print "<br>" .  strftime('%Y-%m-%d %H:%M:%S', $message['lastaction']);
            }

            print "</td>";
            print "<td>"
                    . htmlspecialchars($message['sender_name']) . "<br>"
                    . str_replace(
                        $message['sender_postcode'],
                        '<a href="'.OPTION_BASE_URL.'/who?pc=' . urlencode($message['sender_postcode']) . '">'
                            . $message['sender_postcode'].'</a>',
                        htmlspecialchars($message['sender_addr'])
                    ) . "<br>"
                    . htmlspecialchars($message['sender_email'])
                    . "</td>";

            $display_name = $message['recipient_name'];
            if (!isset($display_name) || $display_name == "") {
                $display_name = "scrubbed, id " .
                $message['recipient_id'] . ", " .
                $message['recipient_type'];
            }
            print '<td><a href="'
                    . htmlspecialchars(url_new('', false, 'page', 'reps', 'rep_id', $message['recipient_id'], 'pc', $message['sender_postcode']))                   . '">' . htmlspecialchars($display_name) . "</a>"
                    . " (<a href=\"" . htmlspecialchars(url_new('', false, 'page', 'fyrqueue', 'rep_id', $message['recipient_id']))                   . '">msgs</a>)'
                    ."<br>";
            if ($message['recipient_via']) {
                $repinfo = dadem_get_representative_info($message['recipient_id']);
                if (!dadem_get_error($repinfo)) {
                    $vainfo = mapit_call('area', $repinfo['voting_area']);
                    $parentinfo = mapit_call('area', $vainfo['parent_area']);
                    mapit_check_error($parentinfo);
                    print '<a href="' .
                       htmlspecialchars(url_new('', false, 'page', 'reps', 'ds_va_id', $vainfo['parent_area'], 'pc', $message['sender_postcode']))  . '">' .
                        htmlspecialchars("via " . $parentinfo['name']) . "</a>:<br>";
                } else {
                    print 'recipient_via contact, but rep id not found ';
                }
            }
            if ($message['recipient_email']) {
                print htmlspecialchars($message['recipient_email']) . "<br>";
            }
            if ($message['recipient_fax']) {
                print htmlspecialchars($message['recipient_fax']) . "<br>";
            }
            print "</td>";

            print "<td><a rel=\"noreferrer\" href=\"https://whatismyipaddress.com/ip/${message['sender_ipaddr']}\">${message['sender_ipaddr']}</a>".
                "<br>" . trim_url($message['sender_referrer']) .
                "<br>" . $message['cobrand'] . " " . $message['cocode'] .
                "</td>";
            print "<td>${message['message_length']}</td>";

            $outof0 = ($message['questionnaire_0_no'] + $message['questionnaire_0_yes']);
            $outof1 = ($message['questionnaire_1_no'] + $message['questionnaire_1_yes']);
            print '<td>';
            if ($outof0) {
                print 'responded:';
                print $message['questionnaire_0_yes'] .'/'. $outof0;
            }
            if ($outof0 && $outof1) {
                print '<br>';
            }
            if ($outof1) {
                print ' firsttime:';
                print $message['questionnaire_1_yes'] .'/'. $outof1;
            }
            if ($message['no_questionnaire']) {
                print "<br>no quest";
            }
            if (!$outof0 && !$outof1) {
                print '&nbsp;';
            }
            print '</td>';

            /* Javascript code changes shading of table row for checked
             * messages to make them look "selected". */
            print '<td><input type="checkbox" name="check_'
                    . $message['id']
                    . '" onclick="this.parentNode.parentNode.className = this.checked ? \'h\' : \''
                        . ($c == 1 ? 'v' : '')
                        . '\'" >';
            print '</td>';

            print "</tr>";
            # this.checked this .className=

            if (array_key_exists('diff', $message)) {
                /* Each element of the array consists either of an array of
                 * two strings, which are the strings unique to the "from"
                 * and "to" strings; or a string, representing a common
                 * part; or null, indicating an elided part. */
                print '<tr'.($c==1?' class="v"':'').'><td colspan = "9">';
                print "<b>Differences: </b> ";
                if (isset($msgid) and $message['id'] == $msgid) {
                    print 'This is the message being compared against. <span class="difffrom">Text that appears only in this message, </span><span class="diffto">or only in the other message.</span>';
                } else {
                    foreach ($message['diff'] as $elem) {
                        if (!isset($elem)) {
                            print '<span class="diffsnipped">[ ... snipped ... ]</span>';
                        } elseif (is_array($elem)) {
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
        <input name="token" value="<?=get_token() ?>" type="hidden" />
        <input size="20" name="notebody" type="text" />
        <input name="note" value="Note" type="submit" />
        &nbsp; <b>Action:</b>
        <input name="freeze" value="Freeze" type="submit" />
        <input name="thaw" value="Thaw" type="submit" />
        <input name="error" value="Error with email" type="submit" />
        <input name="failed" value="Fail silently" type="submit" />
</td><tr>
<?php
        }
?>
</form>
</table>
<?php
    }

    /* printMessage MESSAGE
     * Print a single message, as for printMessages above. */
    private function printMessage($message)
    {
        $this->printMessages(array($message));
    }


    /* printEvents EVENTS
     * Print a list of logged EVENTS in a table. */
    private function printEvents($recents)
    {
        print "<p>
<table border=1 width=100%><tr><th>Time</th><th>Host</th><th>ID</th><th>State</th><th>Event</th></tr>";
        foreach ($recents as $recent) {
            print "<tr>";
            print "<td>" . strftime('%Y-%m-%d %H:%M:%S', $recent['whenlogged']) . "</td>";
            print "<td>" . $recent['hostname'] . "</td>";
            print "<td>" . substr($recent['message_id'], 0, 10) .  "<br/>"
                . substr($recent['message_id'], 10) . "</td>";
            print "<td>" . add_tooltip($recent['state'], $this->stateHelpNotes($recent['state'])) . "</td>";
            print "<td>" . make_ids_links($recent['message']) . "</td>";
            print "</tr>";
        }
?>
</table>
</p>
<?php
    }

    private function doActions($id)
    {
        // Freeze or thaw messages
        if (get_http_var('token') != get_token()) {
            return;
        }
        if (get_http_var('freeze')) {
            $result = msg_admin_freeze_message($id, http_auth_user());
            msg_check_error($result);
            print "<p><b><i>Message $id frozen</i></b></p>";
        } elseif (get_http_var('thaw')) {
            $result = msg_admin_thaw_message($id, http_auth_user());
            msg_check_error($result);
            print "<p><b><i>Message $id thawed</i></b></p>";
        } elseif (get_http_var('no_questionnaire')) {
            $result = msg_admin_no_questionnaire_message($id, http_auth_user());
            msg_check_error($result);
            print "<p><b><i>Message $id now won't send questionnaire, and has had existing responses deleted</i></b></p>";
        } elseif (get_http_var('yes_questionnaire')) {
            $result = msg_admin_yes_questionnaire_message($id, http_auth_user());
            msg_check_error($result);
            print "<p><b><i>Message $id now will send questionnaire</i></b></p>";
        } elseif (get_http_var('error')) {
            $result = msg_admin_set_message_to_error($id, http_auth_user());
            msg_check_error($result);
            print "<p><b><i>Message $id moved to error state</i></b></p>";
        } elseif (get_http_var('failed')) {
            $result = msg_admin_set_message_to_failed($id, http_auth_user());
            msg_check_error($result);
            print "<p><b><i>Message $id moved to failed state</i></b></p>";
        } elseif (get_http_var('bounce_wait')) {
            $result = msg_admin_set_message_to_bounce_wait($id, http_auth_user());
            msg_check_error($result);
            print "<p><b><i>Message $id moved to bounce_wait state</i></b></p>";
        } elseif (get_http_var('ready')) {
            $result = msg_admin_set_message_to_ready($id, http_auth_user());
            msg_check_error($result);
            print "<p><b><i>Message $id moved to ready state</i></b></p>";
        } elseif (get_http_var('note')) {
            $result = msg_admin_add_note_to_message($id, http_auth_user(), get_http_var('notebody'));
            msg_check_error($result);
            print "<p><b><i>Note added to message $id</i></b></p>";
        } elseif (get_http_var('body')) {
            $result = msg_admin_add_note_to_message($id, http_auth_user(), 'viewed body of message in admin interface');
            msg_check_error($result);
            print "<p><b><i>Logged that you are viewing body of message $id</i></b></p>";
        } elseif (get_http_var('wire_emails')) {
            $result = msg_admin_add_note_to_message(
                $id,
                http_auth_user(),
                'viewed body of message (via wire text of emails) in admin interface'
            );
            msg_check_error($result);
            print "<p><b><i>Logged that you are viewing body of message (via wire text of emails) $id</i></b></p>";
        } elseif (get_http_var('scrub_data')) {
            $result = msg_admin_scrub_data($id, http_auth_user());
            msg_check_error($result);
            print "<p><b><i>Scrubbed all personal data</i></b></p>";
        }
    }

    private function displayStateTable($stats, $prefix)
    {
        print "<table border=1>\n";
        $t = array();
        $types = array();
        $state_totals = array();
        $type_totals = array();
        global $state_help_notes_map;
        foreach ($stats as $k => $v) {
            if (stristr($k, $prefix)) {
                list($type, $state) = explode(" ", str_replace($prefix, "", $k));
                $t[$state][$type] = $v;
                if (!array_key_exists($state, $state_totals)) {
                    $state_totals[$state] = 0;
                }
                if (!array_key_exists($type, $type_totals)) {
                    $type_totals[$type] = 0;
                }
                $state_totals[$state] += $v;
                $type_totals[$type] += $v;
                if (!array_key_exists($state, $state_help_notes_map)) {
                    die("missing entry from state_help_notes_map '$state'");
                }
                $types[$type] = 1;
            }
        }
        $states = array_keys($state_help_notes_map);
        $types = array_keys($types);
        sort($types);
        print "<tr><td>&nbsp;</td>";
        foreach ($states as $state) {
            print "<td><b>";
            print add_tooltip($state, $this->stateHelpNotes($state));
            print "</b></td>";
        }
        print "<td><b>Total</b></td>";
        print "</tr>";
        global $va_type_name, $va_inside;
        foreach ($types as $type) {
            print "<tr>";
            print "<td><b>";
            if (array_key_exists($type, $va_inside)) {
                print $va_type_name[$va_inside[$type]];
            }
            print " (".$type.")";
            print "</b></td>";
            foreach ($states as $state) {
                print "<td style=\"text-align: right\">";
                if (array_key_exists($state, $t)) {
                    if (array_key_exists($type, $t[$state])) {
                        print $t[$state][$type];
                    }
                }
                print "</td>";
            }
            print "<td style=\"text-align: right\"><b>";
            print $type_totals[$type];
            print "</b></td>";
            print "</tr>\n";
        }
        print "<tr><td><b>Total:</b></td>";
        foreach ($states as $state) {
            print "<td style=\"text-align: right\"><b>";
            if (array_key_exists($state, $state_totals)) {
                print $state_totals[$state];
            } else {
                print "&nbsp;";
            }
            print "</b></td>";
        }
        $type_grand_total = array_sum(array_values($type_totals));
        $state_grand_total = array_sum(array_values($state_totals));
        if ($type_grand_total != $state_grand_total) {
            die("type_grand_total != state_grand_total");
        }
        print "<td><b>".$type_grand_total."</b></td>";
        print "</tr>";
?>
</table>
<?php
    }

    public function display($self_link)
    {
        $this->self_link = $self_link;

        #print "<pre>"; print_r($_POST); print "</pre>";
        $view = get_http_var('view', 'needattention');
        $id = get_http_var("id");
        $rep_id = get_http_var("rep_id");
        $page = intval(get_http_var('p'));
        $page = $page ?: 1;

        // Display about id
        if ($id) {
            $this->doActions($id);

            // Navigation bar
            $this->renderBar($view, false, $id);

            // Display general information
            print "<h2>Message id " . make_ids_links($id) . ":</h2>";

            $message = msg_admin_get_message($id);
            msg_check_error($message);
            $this->printMessage($message);

            // Commands
            $form = new HTML_QuickForm('messageForm', 'post', $self_link);
            $actiongroup = array();
            if (!get_http_var('note')) {
                $actiongroup[] = $form->createElement('text', 'notebody', null, array('size'=>30));
                $actiongroup[] = $form->createElement('submit', 'note', 'Note');
            }
            $actiongroup[] = $form->createElement('static', null, null, " <b>Action:</b>");
            if ($message['frozen']) {
                if ($message['state'] != 'error' and $message['state'] != 'failed'
                    and $message['state'] != 'failed_closed') {
                    $actiongroup[] = $form->createElement('submit', 'error', 'Error with email');
                }
                if ($message['state'] != 'failed' and $message['state'] != 'failed_closed') {
                    $actiongroup[] = $form->createElement('submit', 'failed', 'Fail silently');
                }
                if ($message['state'] != 'error' and $message['state'] != 'failed'
                    and $message['state'] != 'failed_closed') {
                    $actiongroup[] = $form->createElement('submit', 'thaw', 'Thaw');
                }
            } else {
                if ($message['state'] != 'error' and $message['state'] != 'failed'
                    and $message['state'] != 'failed_closed') {
                    $actiongroup[] = $form->createElement('submit', 'freeze', 'Freeze');
                }
            }
            if ($message['no_questionnaire']) {
                $actiongroup[] = $form->createElement('submit', 'yes_questionnaire', 'Allow Questionnaire');
            } else {
                $actiongroup[] = $form->createElement('submit', 'no_questionnaire', 'No Questionnaire');
            }
            if ($message['state'] == 'pending') {
                $actiongroup[] = $form->createElement('submit', 'ready', 'Confirm');
            } elseif ($message['state'] == 'failed' || $message['state'] == 'failed_closed') {
                $actiongroup[] = $form->createElement('submit', 'ready', 'Retry');
            }

            if (!get_http_var('body')) {
                $actiongroup[] = $form->createElement('submit', 'body', 'View Body');
            } else {
                $actiongroup[] = $form->createElement('submit', 'nobody', 'Hide Body');
            }
            if (!get_http_var('wire_emails')) {
                $actiongroup[] = $form->createElement('submit', 'wire_emails', 'View Emails');
            } else {
                $actiongroup[] = $form->createElement('submit', 'no_wire_emails', 'Hide Emails');
            }
            $actiongroup[] = $form->createElement(
                'submit', 'scrub_data', 'Delete all user data', array(
                    'onclick' => "return confirm('Are you sure?')",
                ));
            $form->addElement('hidden', 'id', $id);
            $form->addElement('hidden', 'token', get_token());
            $form->addGroup($actiongroup, "actiongroup", "", ' ', false);

            admin_render_form($form);
            print 'Similar messages: ';
            print '<a href="'
                    .  htmlspecialchars(url_new("", true, 'view', 'similarbody', 'simto', $id, 'id', null))
                    .  '">different rep</a>, ';
            print '<a href="'
                    .  htmlspecialchars(url_new("", true, 'view', 'similarbodysamerep', 'simto', $id, 'id', null))
                    .  '">same rep</a> ';

            // Links to send messages to sender
            print " Email sender: <small>";
            make_mailto_link(
                $message['sender_email'],
                "Your message to " . $message['recipient_name'] . " has not been sent",
                "Hi " . $message['sender_name']. ",

Unfortunately, your message to " . $message['recipient_name'] . " has not been sent. You can only use our service to write to your own elected representatives, not to representatives for other places.  Here is a full explanation as to why we have this policy:
    https://www.writetothem.com/about-qa#onlyrep

There's a copy of your message below, so you can send it another way, if you like.

-------------------------------------------------------------\n\n" .
                $message['message'],
                "write-to-own-reps-only"
            );
            make_mailto_link(
                $message['sender_email'],
                "Your message to " . $message['recipient_name'],
                "Hi " . $message['sender_name']. ",



-------------------------------------------------------------\n\n".
                $message['message'],
                "blank-mail-quoting-message"
            );
            print "</small>";

            // Body text if enabled
            if (get_http_var('body')) {
                print "<h2>Body text of message (only read if you really need to)</h2>";
                print "<blockquote>";
                print nl2br(htmlspecialchars($message['message']));
                print "</blockquote>";
            }

            // Body text if enabled
            if (get_http_var('wire_emails')) {
                foreach (array('representative', 'confirm', 'confirm-reminder', 'failure', 'questionnaire',
                         'questionnaire-reminder') as $type) {
                    print "<h2>Wire text of email - $type</h2>";
                    $wire = msg_admin_get_wire_email($message['id'], $type);
                    if (msg_get_error($wire)) {
                        print "Error contacting queue:";
                        print_r($wire);
                    } else {
                        print "<pre>";
                        print ms_make_clickable(htmlspecialchars($wire));
                        print "</pre>";
                    }
                }
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
                        . htmlspecialchars(url_new('', true, 'allevents', null))
                        . '">View only important events</a>'
                    : '<a href="'
                        . htmlspecialchars(url_new('', true, 'allevents', 1))
                        . '">View all events</a>');

            $recents = msg_admin_message_events($id, !$allevents);
            if (msg_get_error($recents)) {
                print "Error contacting queue:";
                print_r($recents);
                $recents = array();
            }

            $this->printEvents($recents);

            if (count($message['bounces']) > 0) {
                print "<h2>Bounce Messages</h2>";
                foreach ($message['bounces'] as $bounce) {
                    print "<hr>";
                    print "<blockquote>"
                        . nl2br(htmlspecialchars($bounce, ENT_SUBSTITUTE | ENT_COMPAT | ENT_HTML401))
                        .  "</blockquote>";
                }
                print "<hr>";
                if ($message['state'] == 'bounce_confirm') {
                    $form = new HTML_QuickForm('bounceForm', 'post', $self_link);
                    $bouncegroup = array();
                    $bouncegroup[] = $form->createElement('submit', 'error', 'Fatal Delivery Error');
                    $bouncegroup[] = $form->createElement('submit', 'bounce_wait', 'Temporary Problem');
                    $bouncegroup[] = $form->createElement(
                        'submit',
                        'ready',
                        'Fatal Delivery Error, but should retry with same details'
                    );
                    $form->addGroup($bouncegroup, "bouncegroup", "Which kind of bounce message is this?", ' ', false);
                    $form->addElement('hidden', 'id', $id);
                    $form->addElement('hidden', 'token', get_token());
                    admin_render_form($form);
                }
            }
        } elseif ($view == 'statistics') {
            // Display general statistics
            $stats = msg_admin_get_stats(1);
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
            $this->renderBar($view, false, $id);

?>
<h2>Queue statistics</h2>
<p>
<b><?=$stats["created_1"]?></b> new in last hour,
<b><?=$stats["created_24"]?></b> new in last day,
<b><?=$stats["created_168"]?></b> new in last week
<br>last fax sent <b><?=strftime('%e %b %Y, %H:%M', $stats["last_fax_time"])?></b>,
last email sent <b><?=strftime('%e %b %Y, %H:%M', $stats["last_email_time"])?></b>
</p>
<h2>Messages in each state by type (created in last day)</h2>
<?php $this->displayStateTable($stats, "day "); ?>
<h2>Messages in each state by type (created in last week)</h2>
<?php $this->displayStateTable($stats, "week "); ?>
<h2>Messages in each state by type (created in last four weeks)</h2>
<?php $this->displayStateTable($stats, "four "); ?>
<h2>Messages in each state by type (all time)</h2>
<?php $this->displayStateTable($stats, "alltime "); ?>

<h2>Top referrers in last day</h2>
<table border=1>
    <?php
    foreach ($freq_referrers_day as $row) {
        if ($row[1] > 1 && $row[0] != "") {
            print "<tr><td>" . trim_url($row[0]) . "</td><td>$row[1]</td></tr>";
        }
    }
    ?>
</table>
<h2>Top referrers in last week</h2>
<table border=1>
    <?php
    foreach ($freq_referrers_week as $row) {
        if ($row[1] > 1 && $row[0] != "") {
            print "<tr><td>" . trim_url($row[0]) . "</td><td>$row[1]</td></tr>";
        }
    }
    ?>
</table>
<?php
        } elseif ($rep_id) {
            $repinfo = dadem_get_representative_info($rep_id);
            dadem_check_error($repinfo);
            $sameperson = null;
            if ($repinfo['parlparse_person_id']) {
                $sameperson = dadem_get_same_person($repinfo['parlparse_person_id']);
                dadem_check_error($sameperson);
            }
            if (!$sameperson) {
                $sameperson = array($rep_id);
            }

            $this->renderBar("rep_id", true, $id);
            $params = array(
                'rep_ids' => $sameperson,
                'page' => $page,
            );
            $messages = msg_admin_get_queue('rep_id', $params);
            if (msg_get_error($messages)) {
                print "Error contacting queue:";
                print_r($messages);
                $messages = array();
            }
            $rep_ids = join(' ', $sameperson);

            $by_year = array();
            foreach ($messages as $message) {
                if ($message['dispatched']) {
                    $year = strftime('%Y', $message['dispatched']);
                } else {
                    $year = strftime('%Y', $message['created']);
                }

                $by_year[$year][] = $message;
            }

            $years = array_keys($by_year);
            rsort($years);
            foreach ($years as $year) {
                $year_array = $by_year[$year];
                print "<h2>Year $year for rep ids $rep_ids";
                print " (" . count($year_array) . " of them): </h2>";

                $q_by_email = array();
                $q_by_email_yes = array();
                $dispatched_by_email = array();
                $sent_by_email = array();
                $q_0_no = 0;
                $q_0_yes = 0;
                $q_1_no = 0;
                $q_1_yes = 0;
                $dispatched = 0;
                foreach ($year_array as $message) {
                    if (!array_key_exists($message['sender_email'], $q_by_email)) {
                        $q_by_email[$message['sender_email']] = 0;
                        $q_by_email_yes[$message['sender_email']] = 0;
                        $sent_by_email[$message['sender_email']] = 0;
                    }
                    $q_by_email[$message['sender_email']] += $message['questionnaire_0_no'];
                    $q_by_email[$message['sender_email']] += $message['questionnaire_0_yes'];
                    $q_by_email_yes[$message['sender_email']] += $message['questionnaire_0_yes'];
                    $q_0_no += $message['questionnaire_0_no'];
                    $q_0_yes += $message['questionnaire_0_yes'];
                    $q_1_no += $message['questionnaire_1_no'];
                    $q_1_yes += $message['questionnaire_1_yes'];
                    if ($message['dispatched'] && ($message['state'] == 'sent' || $message['state'] == 'finished')) {
                        $sent_by_email[$message['sender_email']] = $sent_by_email[$message['sender_email']] + 1;
                        $dispatched++;
                        $dispatched_by_email[$message['sender_email']] = true;
                    }
                }
                print "Dispatched: $dispatched (unique: " . count($dispatched_by_email) . ")";
                if ($q_0_yes + $q_0_no > 0) {
                    print " Responsiveness: $q_0_yes / " . ($q_0_no + $q_0_yes);
                }
                if ($q_1_yes + $q_1_no > 0) {
                    print " First time: $q_1_yes / " . ($q_1_no + $q_1_yes);
                }

                $html = '';
                foreach ($q_by_email as $email => $q_count) {
                    $sent_count = $sent_by_email[$email];
                    $q_count_yes = $q_by_email_yes[$email];
                    if ($q_count > 1 || $sent_count > 1) {
                        $html .= "<tr><td>$email</td><td>$sent_count</td><td>$q_count_yes / $q_count</td></tr>";
                    }
                }
                if ($html) {
                    print '<table border="1">';
                    print '<th>Multiple mailers</th><th>Message dispatched</th><th>Responses to first question</th>';
                    print $html;
                    print '</table>';
                } else {
                    print ' Nobody succesfully sent more than one message to this rep using same email.';
                }
                print '<p><a href="?' . url_new('', true, 'p', $page + 1) . '">Next page</a></p>';

                $this->printMessages($year_array, null);
            }
        } else {
            // Perform actions on checked items
            $sender_emails = array();
            $sender_full = array();
            foreach (array_keys($_POST) as $k) {
                if (stristr($k, "check_")) {
                    $checkid = str_replace("check_", "", $k);
                    $this->doActions($checkid);
                    $message = msg_admin_get_message($checkid);
                    msg_check_error($message);
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
            $params = array(
                'page' => $page,
            );
            $reverse = false;
            if (stristr($view, "_rev")) {
                $view = str_replace("_rev", "", $view);
                $reverse = true;
            }
            if (get_http_var('search')) {
                $view = "search";
            }

            // Set up additional parameters for view if necessary.
            if ($view == "similarbody" || $view == 'similarbodysamerep') {
                $params['msgid'] = get_http_var('simto');
            } elseif ($view == "search" || $view == "logsearch") {
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
            $this->renderBar($view, $reverse, $id);

            // Display messages
            print "<h2>Messages which";
            if ($view == "similarbody") {
                print " have similar bodies to " . make_ids_links(get_http_var('simto'));
            } elseif ($view == "similarbodysamerep") {
                print " are to the same representative and have similar bodies to "
                    . make_ids_links(get_http_var('simto'));
            } elseif ($view == "search") {
                print " match search query '" . htmlspecialchars(get_http_var('query')) . "'";
            } elseif ($view == "logsearch") {
                print " whose log matches '" . htmlspecialchars(get_http_var('query')) . "'";
            } else {
                print " are $view";
            }
            print " (" . count($messages) . " messages): </h2>";
            if ($reverse) {
                $messages = array_reverse($messages);
            }
            $this->printMessages(
                $messages,
                ($view == 'similarbody' || $view == 'similarbodysamerep') ? $params['msgid'] : null
            );
            if ($view == 'recentchanged' or $view == 'recentcreated') {
                print "<p>..."; /* indicate that this isn't all the messages... */
            }

            // Help
            ?>
            <h2>Help &mdash; what views/searches are there?</h2>
            <p>
            <b>Need Attention:</b> Message which are frozen, most likely
            due to possible abuse, or need bounce messages classifying.
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
            representative type ('WMC') you must enter the whole word, case
            sensitive.  Otherwise queries are case insensitive.  Yes, you
            can query on the referrer URL.  If you have one, you can enter
            a confirmation or questionnaire token from an email, such as
            cqyv7yrisjugc5i5rfz4w75tmnxzi.  Examples: '<b>ready WMC</b>' - all messages to MPs
            which are ready to be sent.  '<b>francis theyworkforyou</b>' - probably
            all messages written by someone called Francis who came to WTT via
            theyworkforyou.com.
            </p>
            <?php
        }
        if ($view != "statistics") {
?>
<h2>Help &mdash; what do the buttons do?</h2>
        <?php
        if (!$id) {
        ?>
<p>They apply to all items you have checked.</p>
        <?php
        }
        ?>
<p>
<b>note</b> adds the text entered as a remark in the message's log
<br><b>freeze</b> stops delivery to representative, but other stuff
(such as confirmation message) still happens
<br><b>thaw</b> undoes a freeze, so message gets delivered.
<br><b>no questionnaire</b> makes the message one for which no questionnaire is sent.
<br><b>error with email</b> rejects a message, sending a "could not deliver" email to constituent.
<br><b>fail silently</b> rejects a message, with no email to the constituent.
<br><b>confirm</b> moves 'pending' to 'ready', the same as user clicking confirm link in email
<br><b>retry</b> moves a failed message back to 'ready', restarting the sending process
<br><b>view body</b> should only be done if you have good reason to believe it is an abuse of our service.
<br><b>edit contact details</b> by clicking on the recipient name
</p>
<p>To find out <b>state meanings</b>, point the mouse to find out what they are
</p>
            <?php
            if (!$id) {
            ?>
<h2>Help &mdash; what do the states mean?</h2>
<p>Here is a diagram of state changes:</p>
<p><img src="queue-state-machine.png"></p>

<p>Description of states in the normal lifetime of a message:</p>

<dl>
<dt>new</dt>
<dd>The message has been created by the user but it has not yet been sent to
them for confirmation.</dd>

<dt>pending</dt>
<dd>The message has been sent to the user but not confirmed yet. A reminder
copy of the message is sent if the user does not confirm it within a day.</dd>

<dt>ready</dt>
<dd>The message has been confirmed by the user but it has not yet been
successfully sent; <em>or</em> it has been sent by email but encountered a
fatal bounce for a transient error condition (such as the recipient's mailbox
being full).</dd>

<dt>bounce_wait <em>email only</em></dt>
<dd>The message has been sent, but we hang on to it for a little while in case
a bounce message arrives. Bounce messages are either automatically classified
(where they meet the RFC1892 standard for delivery status notifications) or
passed into the bounce_confirm state for manual classification.</dd>

<dt>sent</dt>
<dd>The message has been sent (and, in case of an email, no bounce message has
arrived within the set time). This is the state in which the questionnaire and
questionnaire reminder are sent.</dd>

<dt>finished</dt>
<dd>All our processing of the message has completed successfully.</dd>
</dl>

</dl>

            <?php
            }
        }
    }
}

?>
