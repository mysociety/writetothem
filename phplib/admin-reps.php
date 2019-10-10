<?php
/*
 * Representatives admin page.
 *
 * Copyright (c) 2012 UK Citizens Online Democracy. All rights reserved.
 * Email: matthew@mysociety.org. WWW: http://www.mysociety.org
 *
 */

$dir = dirname(__FILE__);
require_once $dir . "/../commonlib/phplib/dadem.php";
require_once $dir . "/../phplib/mapit.php";
require_once $dir . "/../commonlib/phplib/votingarea.php";
require_once $dir . "/../commonlib/phplib/utility.php";

require_once $dir . "/../commonlib/phplib/HTML/QuickForm.php";
require_once $dir . "/../commonlib/phplib/HTML/QuickForm/Rule.php";
require_once $dir . "/../commonlib/phplib/HTML/QuickForm/Renderer/Default.php";

class ADMIN_PAGE_REPS
{
    public function __construct()
    {
        $this->id = "reps";
        $this->navname= "Representative Data";
    }

    private function getToken()
    {
        $secret = dadem_get_secret();
        dadem_check_error($secret);
        $token = sha1(http_auth_user() . $secret);
        return $token;
    }

    private function renderReps($reps, $bad_link = false)
    {
        if (!$reps) {
            return '';
        }

        $html = "";
        $info = dadem_get_representatives_info($reps);
        dadem_check_error($info);

        $areas = array();
        foreach ($reps as $rep) {
            $areas[] = $info[$rep]['voting_area'];
        }
        $area_info = mapit_areas($areas);
        mapit_check_error($area_info);

        $generation = 0;
        $generations = mapit_call('generations', '');
        foreach ($generations as $g) {
            if ($g['active'] && $g['id'] > $generation) {
                $generation = $g['id'];
            }
        }

        for ($i = 0; $i < count($reps); $i++) {
            $rep = $reps[$i];
            $repinfo = $info[$rep];
            if (isset($area_info[$repinfo['voting_area']])) {
                $ainfo = $area_info[$repinfo['voting_area']];
                $html .= "<!-- gen ".$ainfo['generation_low']."-".$ainfo['generation_high']." -->";
                if ($generation < $ainfo['generation_low'] || $generation > $ainfo['generation_high']) {
                    $html .= "<i>out of generation</i> ";
                }
            } else {
                $html .= '<i>area no longer exists</i> ';
            }

            if ($repinfo['deleted']) {
                $html .= "<i>deleted</i> ";
            } elseif ($repinfo['last_editor'] == 'fyr-queue') {
                $html .= "<i>failed</i> ";
            }
            if (array_key_exists('type', $repinfo)) {
                $html .= $repinfo['type'] . " ";
            } else {
                $html .= $repinfo['area_type'] . " ";
            }
            $link_extra = "";
            if ($bad_link && $i < count($reps) - 1) {
                $link_extra = "&nextbad=".urlencode($reps[$i+1]);
            }
            $html .= "<a href=\"$this->self_link&pc=" . urlencode($this->params['pc']) . "&rep_id=" . $rep
                .  "$link_extra\">" . $repinfo['name'] . " (". $repinfo['party'] . ")</a> \n";
            $html .= "prefer " . $repinfo['method'];
            if ($repinfo['email']) {
                $html .= ", " .  $repinfo['email'];
            }
            if ($repinfo['fax']) {
                $html .= ", " .  $repinfo['fax'];
            }
            $html .= "<br>";
        }
        return $html;
    }

    private function renderArea($area_info, $add_link = false)
    {
        global $va_type_name;
        if (!isset($va_type_name[$area_info['type']])) {
            return;
        }
        $url = $this->self_link . '&pc=' . urlencode($this->params['pc']);
        $html = "<p><strong><a href='$url&va_id=$area_info[id]'>$area_info[name]</a>";
        $html .= " (" .  $va_type_name[$area_info['type']] . ")</strong>";
        if ($add_link) {
            $html .= " &ndash; <a href='$url&new_in_va_id=$area_info[id]'>Add new representative</a>";
        }
        $html .= '</p>';
        return $html;
    }

    private function getNextBadContact($rep_id)
    {
        $badcontacts = dadem_get_bad_contacts();
        dadem_check_error($badcontacts);
        $prev = null;
        array_push($badcontacts, null);
        foreach ($badcontacts as $badcontact) {
            if ($prev == $rep_id) {
                $rep_id = $badcontact;
                break;
            }
            $prev = $badcontact;
        }
        return $rep_id;
    }

    private function getInputData()
    {
        $rep_id = get_http_var('rep_id');
        $va_id = get_http_var('va_id');
        $new_in_va_id = get_http_var('new_in_va_id'); // Make new rep in this voting area

        $ds_va_id = get_http_var('ds_va_id');
        if ($ds_va_id) {
            // Democratic services
            $ds_vainfo = dadem_get_representatives($ds_va_id);
            dadem_check_error($ds_vainfo);
            if (isset($ds_vainfo[0])) {
                $rep_id = $ds_vainfo[0];
            } else {
                $new_in_va_id = $ds_va_id;
            }
        }

        if (get_http_var('cancel')) {
            $rep_id = null;
        }

        $pc = get_http_var('pc'); // Postcode
        $search = null;
        if (validate_postcode(get_http_var('search'))) {
            $pc = get_http_var('search');
        } else {
            $search = get_http_var('search');
        }

        $this->params = array(
            'rep_id' => $rep_id,
            'va_id' => $va_id,
            'new_in_va_id' => $new_in_va_id,
            'pc' => $pc,
            'search' => $search,
        );
    }

    private function updateRep()
    {
        if (get_http_var('token') != $this->getToken()) {
            print "<p><i>Token not found</i></p>";
        } else {
            $newdata = array(
                'method' => get_http_var('method'),
            );
            if ($this->vainfo['editable_here']) {
                $newdata['name'] = get_http_var('name');
                $newdata['party'] = get_http_var('party');
                $newdata['email'] = get_http_var('email');
                if (!$this->params['rep_id']) {
                    // Making a new representative, put in type and id
                    $newdata['area_id'] = $this->params['new_in_va_id'];
                    $newdata['area_type'] = $this->vainfo['type'];
                }
            }
            $result = dadem_admin_edit_representative(
                $this->params['rep_id'],
                $newdata,
                http_auth_user(),
                get_http_var('note')
            );
            dadem_check_error($result);
            $this->params['rep_id'] = null;
            $this->params['new_in_va_id'] = null;
            print "<p><i>Successfully updated representative ". htmlspecialchars($result) . "</i></p>";

            if (get_http_var('nextbad')) {
                $rep_id = get_http_var('nextbad');
                $url = $this->self_link . "&nextbad=" . urlencode($this->getNextBadContact($rep_id))
                    . "&just_done_bad=1&rep_id=" . urlencode($rep_id);
                header("Location: $url");
                exit;
            }
        }
    }

    private function deleteRep()
    {
        if (get_http_var('token') != $this->getToken()) {
            print "<p><i>Token not found</i></p>";
        } else {
            $rep_id = $this->params['rep_id'];
            $result = dadem_admin_edit_representative($rep_id, null, http_auth_user(), get_http_var('note'));
            dadem_check_error($result);
            print "<p><i>Successfully deleted representative ". htmlspecialchars($rep_id) . "</i></p>";
            $this->params['rep_id'] = null;
        }
    }

    public function display()
    {
        $this->getInputData();

        if (get_http_var('delete')) {
            $this->deleteRep();
        }
        if (get_http_var('just_done_bad')) {
            print "<p><i>Moved on to next bad contact</i></p>";
        }
        if (get_http_var('ucclose')) {
            $result = dadem_admin_done_user_correction(get_http_var('ucid'));
            dadem_check_error($result);
            print "<p><i>Successfully closed correction ". htmlspecialchars(get_http_var('ucid')) . "</i></p>";
        }
        if (get_http_var('vaupdate')) {
            $result = dadem_admin_set_area_status(get_http_var('va_id'), get_http_var('new_status'));
            dadem_check_error($result);
            print "<p><i>Successfully updated voting area status " . htmlspecialchars(get_http_var('va_id'))
                . " to " . htmlspecialchars(get_http_var('new_status')) . "</i></p>";
        }

        // Need to work this out now as updateRep uses it
        if ($this->params['rep_id'] or $this->params['new_in_va_id']) {
            $this->getRepInfo();
        }

        if (get_http_var('done')) {
            $this->updateRep();
        }

        // Postcode and search box
        $form = new HTML_QuickForm('adminRepsSearchForm', 'get', $this->self_link);
        $form->addElement('header', '', 'Search');
        $buttons = array();
        $buttons[] = $form->createElement('text', 'search', null, array('size' => 20, 'maxlength' => 255));
        $buttons[] = $form->createElement('submit', 'gos', 'postcode or query');
        $form->addElement('hidden', 'page', $this->id);
        $form->addGroup($buttons, 'stuff', null, '&nbsp', false);
        admin_render_form($form);

        // Conditional parts:
        if ($this->params['rep_id'] or $this->params['new_in_va_id']) {
            $this->displayRep();
        } elseif ($this->params['va_id']) {
            $this->displayVotingArea();
        } elseif ($this->params['search']) {
            $this->displaySearch();
        } elseif ($this->params['pc']) {
            $this->displayPostcode();
        } elseif (get_http_var('bad_contacts')) {
            $this->displayBadContacts();
        } elseif (get_http_var('user_corrections')) {
            $this->displayUserCorrections();
        } else {
            print '<p><a href="?page=reps&bad_contacts=1">Bad contacts</a> (please fix these!)';
            // General info
            if (OPTION_ADMIN_SERVICES_CGI) {
                print '<br><a href="?page=reps&user_corrections=1">User corrections</a>
                    (just for your interest, as sent automatically to GovEval)';
            }
        }
    }

    private function getRepInfo()
    {
        if ($this->params['rep_id']) {
            $this->repinfo = dadem_get_representative_info($this->params['rep_id']);
            dadem_check_error($this->repinfo);
            $va_id = $this->repinfo['voting_area'];
        } else {
            $this->repinfo = null;
            $va_id = $this->params['new_in_va_id'];
        }
        $this->vainfo = $this->getAreaInfo($va_id);
    }

    private function getReps($va_id)
    {
        $reps = dadem_get_representatives($va_id);
        dadem_check_error($reps);
        $reps = array_values($reps);
        return $reps;
    }

    private function getAreaInfo($va_id)
    {
        global $va_rep_name, $va_council_child_types;

        $vainfo = mapit_call('area', $va_id);
        mapit_check_error($vainfo);
        if ($vainfo['parent_area']) {
            $parentinfo = mapit_call('area', $vainfo['parent_area']);
            mapit_check_error($parentinfo);
            $vainfo['parent_area'] = $parentinfo;
        }
        $vainfo['rep_type'] = isset($va_rep_name[$vainfo['type']]) ? $va_rep_name[$vainfo['type']] : '';

        // Councillor types are not edited here, but in match.cgi interface
        $vainfo['editable_here'] = true;
        if (OPTION_ADMIN_SERVICES_CGI && in_array($vainfo['type'], $va_council_child_types)) {
            $vainfo['editable_here'] = false;
        }

        return $vainfo;
    }

    private function displayRepPostcodeLink($form)
    {
        $pc = $this->params['pc'];
        if (!$pc) {
            $pc = mapit_call('area/example_postcode', $this->vainfo['id']);
            if (mapit_get_error($pc)) {
                $pc = '';
            }
        }
        $form->addElement('static', 'note1', null, "Example postcode for testing: " .
            "<a href='" . OPTION_BASE_URL . '/who?pc=' . urlencode($pc) . "'>"
            . htmlentities($pc) ."</a> (<a href='?search=" . urlencode($pc)
            . "&amp;page=" . $this->id . "'>all reps here</a>)");
        $form->addElement('hidden', 'pc', $pc);
    }

    private function displayRepHeader($form)
    {
        if ($this->repinfo) {
            $form->addElement('header', '', 'Edit Representative');
            if ($this->repinfo['deleted']) {
                $form->addElement(
                    'static',
                    'notedeleted',
                    null,
                    "<strong style=\"color: red\">Deleted representative</strong>, click 'Done' to undelete"
                );
            }
            if ($this->vainfo['editable_here']) {
                $form->addElement('static', 'note1', null, "
                Edit only the values which you need to.  If a representative
                has changed delete them and make a new one.  Do not just edit
                their values, as this would ruin our reponsiveness stats.");
            }
        } else {
            $form->addElement('header', '', 'New Representative');
        }
    }

    private function displayRepSamePerson($form)
    {
        if (!$this->repinfo || !$this->repinfo['parlparse_person_id']) {
            return;
        }

        $sameperson = dadem_get_same_person($this->repinfo['parlparse_person_id']);
        dadem_check_error($sameperson);
        if (!$sameperson) {
            return;
        }

        $html = '';
        foreach ($sameperson as $samerep) {
            if ($samerep == $this->repinfo['id']) {
                continue;
            }
            $html .= "<a href=\"$this->self_link&pc=" .  urlencode($this->params['pc']) . "&rep_id=" . $samerep
                . "\">" . $samerep . "</a> \n";
        }
        if ($html) {
            $html = '(Note that these other representatives are the same person: ' . trim($html) . ')';
            $form->addElement('static', 'sameperson', null, $html);
        }
    }

    private function displayRepMainForm($form)
    {
        global $va_type_name;

        $readonly = $this->vainfo['editable_here'] ? null : "readonly";

        $office = htmlspecialchars($this->vainfo['rep_type']) . " for " .
            htmlspecialchars($this->vainfo['name']) . " " . htmlspecialchars($va_type_name[$this->vainfo['type']]) .
            ($this->vainfo['parent_area'] ? " in " .
            htmlspecialchars($this->vainfo['parent_area']['name']) . " " .
            htmlspecialchars($va_type_name[$this->vainfo['parent_area']['type']]) : "" );

        $form->addElement('static', 'office', 'Office:', $office);
        $form->addElement('text', 'name', "Full name:", array('size' => 60, $readonly => 1));
        $form->addElement('text', 'party', "Party:", array('size' => 60, $readonly => 1));
        $form->addElement('static', 'note2', null, "Make sure you update contact method when you change email.");
        $form->addElement(
            'select',
            'method',
            "Contact method:",
            array(
                    'email' => 'Email only',
                    'shame' => "Shame! Doesn't want contacting",
                    'via' => 'Contact via electoral body (e.g. Democratic Services)',
                    'unknown' => "We don't know contact details"
            )
        );
        $form->addElement('text', 'email', "Email:", array('size' => 60, $readonly => 1));
        $form->addElement('textarea', 'note', "Notes for log:", array('rows' => 3, 'cols' => 60));
        if (get_http_var('nextbad')) {
            $form->addElement('hidden', 'nextbad', get_http_var('nextbad'));
        }
    }

    private function displayRepActions($form)
    {
        $finalgroup = array();
        $finalgroup[] = $form->createElement(
            'static',
            'newlink',
            null,
            "<a href=\"$this->self_link&pc=" .  urlencode($this->params['pc']). "&new_in_va_id=" .
            $this->vainfo['id'] .  "\">" .
            "Make new " .
            htmlspecialchars($this->vainfo['name']) . " rep".
            "</a> \n"
        );
        if ($this->repinfo['deleted']) {
            $finalgroup[] = $form->createElement(
                'static',
                'staticspacer',
                null,
                '&nbsp; Deleted rep, no longer in office, just click done to undelete'
            );
        } else {
            $finalgroup[] = $form->createElement(
                'static',
                'staticspacer',
                null,
                '&nbsp; No longer in office? --->'
            );
            $finalgroup[] = $form->createElement('submit', 'delete', 'Delete');
        }
        return $finalgroup;
    }

    private function displayRepSearchLinks($form)
    {
        $search_links = "Search for: ";
        $search_links .= "<a href=\"$this->self_link&page=fyrqueue&rep_id=" . $this->repinfo['id']
            . "\">WriteToThem messages</a> | ";
        foreach (array(
            "tel ". $this->repinfo['name'],
            "tel ". $this->repinfo['name'] . " " . $this->vainfo['rep_type'],
            ) as $searchq) {
            $search_links .= "<a href=\"http://search.yahoo.com/search?p=" . htmlspecialchars($searchq)
                . "\"> " . htmlspecialchars($searchq)."</a> | ";
        }
        $form->addElement('static', 'newlink', null, $search_links);

        if ($this->repinfo['parlparse_person_id']) {
            $form->addElement('static', 'person', 'parlparse person_id:', $this->repinfo['parlparse_person_id']);
        }
    }

    private function displayRepHistory($form)
    {
        $rephistory = dadem_get_representative_history($this->repinfo['id']);
        dadem_check_error($rephistory);

        $html = "<table border=1>";
        $html .= "<th>Order</th><th>Date</th><th>Editor</th><th>Note</th>
            <th>Name</th> <th>Party</th> <th>Method</th> <th>Email</th>
            <th>Fax</th><th>Active</th>";

        $previous_row = null;
        foreach ($rephistory as $row) {
            $html .= "<tr>";
            foreach (array('order_id', 'whenedited', 'editor', 'note',
                'name', 'party', 'method', 'email', 'fax', 'deleted') as $field) {
                if ($row['deleted'] && ($field == 'email' || $field == 'fax' || $field == 'method')) {
                    $display_value = 'deleted';
                    $html .= "<td>-</td>\n";
                    continue;
                }

                $value = $row[$field];
                if ($field == 'note') {
                    $display_value = make_ids_links($value);
                } elseif ($field == 'whenedited') {
                    $display_value = strftime('%Y-%m-%d %H:%M:%S', $value);
                } elseif ($field == 'deleted') {
                    $display_value = $value ? 'deleted' : 'yes';
                } else {
                    $display_value = $value;
                }
                if ($field != "order_id" && $field != "whenedited" &&
                    $field != "editor" && $field != "note" &&
                    $previous_row && $previous_row[$field] != $value) {
                    $display_value = "<strong>$display_value</strong>";
                }

                # Try and spot stupidity
                if (preg_match('#parl(i|a)ment#', $display_value)) {
                    $display_value = "<span style='color:#00ff00'>$display_value</span>";
                }

                $html .= "<td>" . $display_value. "</td>\n";
            }
            $html .= "</tr>";
            $previous_row = $row;
        }
        $html .= "</table>";
        $form->addElement('header', '', 'Historical Changes');
        $form->addElement('static', 'bytype', null, $html);
    }

    // Add or edit representative
    private function displayRep()
    {
        $form = new HTML_QuickForm('adminRepsEditForm', 'post', $this->self_link);
        $form->addElement('hidden', 'page', $this->id);
        $form->addElement('hidden', 'token', $this->getToken());

        if ($this->repinfo) {
            $form->addElement('hidden', 'rep_id', $this->repinfo['id']);
            $form->setDefaults(
                array('name' => $this->repinfo['name'],
                'party' => $this->repinfo['party'],
                'method' => $this->repinfo['method'],
                'email' => $this->repinfo['email'],
                )
            );
        } else {
            $form->addElement('hidden', 'new_in_va_id', $this->params['new_in_va_id']);
        }

        $this->displayRepPostcodeLink($form);
        $this->displayRepHeader($form);
        $this->displayRepSamePerson($form);
        $this->displayRepMainForm($form);

        $finalgroup = array();
        $finalgroup[] = $form->createElement('submit', 'done', 'Done');
        $finalgroup[] = $form->createElement('submit', 'cancel', 'Cancel');
        if ($this->vainfo['editable_here']) {
            if ($this->repinfo) {
                $finalgroup = array_merge($finalgroup, $this->displayRepActions($form));
            }
        } else {
            $form->addElement(
                'static',
                'note3',
                null,
                '<a href="'.OPTION_ADMIN_SERVICES_CGI.'match.cgi?page=councilinfo;area_id='
                . $this->vainfo['parent_area']['id'] . '">To edit Councillors please use the match.cgi interface</a>'.
                '<br><a href="'.$this->self_link.'&ds_va_id='
                . $this->vainfo['parent_area']['id'] . '">... or edit Democratic Services for this council</a>'
            );
        }
        $form->addGroup($finalgroup, "finalgroup", "", ' ', false);

        if ($this->repinfo) {
            $this->displayRepSearchLinks($form);
            $this->displayRepHistory($form);
        }
        admin_render_form($form);
    }

    private function displayVotingArea()
    {
        $va_id = $this->params['va_id'];
        $form = new HTML_QuickForm('adminVotingArea', 'get', $this->self_link);
        $area_info = mapit_call('area', $va_id);
        mapit_check_error($area_info);
        $reps = $this->getReps($va_id);
        $html = $this->renderArea($area_info);
        $html .= $this->renderReps($reps);
        $form->addElement('static', 'bytype', null, $html);
        $form->addElement('hidden', 'page', $this->id);
        $form->addElement('hidden', 'token', $this->getToken());
        $form->addElement('hidden', 'va_id', $va_id);
        $select = $form->addElement(
            'select',
            'new_status',
            null,
            array(
                   'none' => 'No special status',
                   'pending_election' => 'Pending election, rep data not valid',
                   'recent_election' => 'Recent election, our rep data not yet updated',
                   'boundary_changes' => 'Recent election, had boundary changes',
            ),
            array()
        );
        $status = dadem_get_area_status($va_id);
        dadem_check_error($status);
        $select->setSelected($status);

        $form->addElement('submit', 'vaupdate', 'Update');
        admin_render_form($form);
    }

    private function displaySearch()
    {
        global $va_inside;

        $form = new HTML_QuickForm('adminRepsSearchResults', 'get', $this->self_link);
        $html = '';
        $areas = mapit_areas($this->params['search']);
        mapit_check_error($areas);
        foreach (array_keys($areas) as $va_id) {
            $area_info = mapit_call('area', $va_id);
            mapit_check_error($area_info);
            $reps = $this->getReps($va_id);
            $html .= $this->renderArea($area_info, isset($va_inside[$area_info['type']]));
            $html .= $this->renderReps($reps);
        }
        // Search reps
        $reps = dadem_search_representatives($this->params['search']);
        dadem_check_error($reps);
        $html .= '<hr>' . $this->renderReps($reps);
        $form->addElement('static', 'bytype', null, $html);

        admin_render_form($form);
    }

    private function displayPostcode()
    {
        global $va_display_order, $va_inside;

        $form = new HTML_QuickForm('adminRepsSearchResults', 'get', $this->self_link);

        $voting_areas = mapit_postcode($this->params['pc']);
        mapit_check_error($voting_areas);
        $areas_info = $voting_areas['areas'];
        $html = "";
        // Display in order council, ward, council, ward...
        $our_order = array();
        foreach ($va_display_order as $row) {
            if (!is_array($row)) {
                $row = array($row);
            }
            if (!in_array($va_inside[$row[0]], $our_order)) {
                $our_order[] = $va_inside[$row[0]];
            }
            foreach ($row as $va_type) {
                $our_order[] = $va_type;
            }
        }

        // Render everything in the order
        foreach ($our_order as $va_type) {
            foreach ($areas_info as $va_id => $area_info) {
                if ($va_type <> $area_info['type']) {
                    continue;
                }

                // One voting area
                $reps = $this->getReps($va_id);
                $html .= $this->renderArea($area_info, isset($va_inside[$va_type]));
                $html .= $this->renderReps($reps);
            }
        }
        $form->addElement('static', 'bytype', null, $html);

        admin_render_form($form);
    }

    private function displayBadContacts()
    {
        $form = new HTML_QuickForm('adminRepsBad', 'post', $this->self_link);
        $badcontacts = dadem_get_bad_contacts();
        dadem_check_error($badcontacts);
        $form->addElement('header', '', 'Bad Contacts ' . count($badcontacts));
        $html = $this->renderReps($badcontacts, true);
        $form->addElement('static', 'badcontacts', null, $html);
        admin_render_form($form);
    }

    private function displayUserCorrections()
    {
        $form = new HTML_QuickForm('adminRepsCorrectionsHeader', 'post', $this->self_link);
        $corrections = dadem_get_user_corrections();
        dadem_check_error($corrections);
        $form->addElement('header', '', 'User Submitted Corrections ' . count($corrections));
        $form->addElement('hidden', 'token', $this->getToken());
        admin_render_form($form);
        // Get all the data for areas and their parents in as few call as possible
        $vaids = array();
        foreach ($corrections as $correction) {
            array_push($vaids, $correction['voting_area_id']);
        }
        $info1 = mapit_areas($vaids);
        mapit_check_error($info1);
        $vaids = array();
        foreach ($info1 as $value) {
            array_push($vaids, $value['parent_area']);
        }
        $info2 = mapit_areas($vaids);

        foreach ($corrections as $correction) {
            $form = new HTML_QuickForm('adminRepsCorrections', 'post', $this->self_link);
            $html = "";
            $rep = $correction['representative_id'];

            $html .= "<p>";
            $html .= strftime('%Y-%m-%d %H:%M:%S', $correction['whenentered']) . " ";
            if ($correction['user_email']) {
                $html .= " by " . htmlspecialchars($correction['user_email']);
            }
            $html .= "<br>";
            if ($correction['voting_area_id']) {
                $wardinfo = $info1[$correction['voting_area_id']];
                $vaid = $wardinfo['parent_area'];
                $vainfo = $info2[$vaid];
                // TODO: Make this councilinfo, and give a valid r= return URL
                $html .= '<a href="'.OPTION_ADMIN_SERVICES_CGI.'match.cgi?page=councilinfo;area_id='
                    . $vaid . '&r=' . '">' .
                    htmlspecialchars($vainfo['name']) . "</a>, ";
                $html .= htmlspecialchars($wardinfo['name']);
                $html .= "<br>";
            }
            $html .= $correction['alteration'] . " ";

            if ($rep) {
                $repinfo = dadem_get_representative_info($rep);
                dadem_check_error($repinfo);

                $html .= "<a href=\"$this->self_link&pc=" . urlencode($this->params['pc']). "&rep_id=" . $rep . "\">"
                    . htmlspecialchars($repinfo['name']) . " (". htmlspecialchars($repinfo['party']) . ")</a> \n";
                if ($correction['alteration'] != "delete") {
                    $html .= " to ";
                }
            }
            if ($correction['alteration'] != "delete") {
                $html .= htmlspecialchars($correction['name']) .  " ("
                    . htmlspecialchars($correction['party']) . ")";
            }
            if ($correction['user_notes']) {
                $html .= "<br>Notes: " . htmlspecialchars($correction['user_notes']);
            }

            $usercorr = array();
            $usercorr[] = $form->createElement('static', 'usercorrections', null, $html);
            // You can't do this with element type "hidden" as it only allows one value in a
            // page for variable named ucid.  So once again I go to raw HTML.  Remind me not
            // to use HTML_QuickForm again...
            $usercorr[] = $form->createElement(
                'html',
                '<input name="ucid" type="hidden" value="'. $correction['user_correction_id'] . '" />'
            );
            $usercorr[] = $form->createElement('submit', 'ucclose', 'hide (done)');
            $form->addGroup($usercorr, 'stuff', null, '&nbsp', false);
            admin_render_form($form);
        }
    }
}
