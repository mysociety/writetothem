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
require_once $dir . "/../commonlib/phplib/mapit.php";
require_once $dir . "/../commonlib/phplib/votingarea.php";
require_once $dir . "/../commonlib/phplib/utility.php";

require_once $dir . "/../commonlib/phplib/HTML/QuickForm.php";
require_once $dir . "/../commonlib/phplib/HTML/QuickForm/Rule.php";
require_once $dir . "/../commonlib/phplib/HTML/QuickForm/Renderer/Default.php";

class ADMIN_PAGE_REPS {
    function ADMIN_PAGE_REPS () {
        $this->id = "reps";
        $this->navname= "Representative Data";
    }

    function get_token() {
        $secret = dadem_get_secret();
        dadem_check_error($secret);
        $token = sha1(http_auth_user() . $secret);
        return $token;
    }

    function render_reps($self_link, $reps, $bad_link = false) {
        if (!$reps) return '';

        $html = "";
        $info = dadem_get_representatives_info($reps);
        dadem_check_error($info);

        $areas = array();
        foreach ($reps as $rep)
            $areas[] = $info[$rep]['voting_area'];
        $area_info = mapit_call('areas', $areas);
        mapit_check_error($area_info);

        $generation = 0;
        $generations = mapit_call('generations'));
        foreach ($generations as $g) {
            if ($g['active'] && $g['id'] > $generation) {
                $generation = $g['id'];
            }
        }

        for ($i = 0; $i < count($reps); $i++) {
            $rep = $reps[$i];
            $repinfo = $info[$rep];
            $ainfo = $area_info[$repinfo['voting_area']];
            if ($ainfo) {
                $html .= "<!-- gen ".$ainfo['generation_low']."-".$ainfo['generation_high']." -->";
                if ($generation < $ainfo['generation_low'] || $generation > $ainfo['generation_high'])
                    $html .= "<i>out of generation</i> ";
            } else {
                $html .= '<i>area no longer exists</i> ';
            }

            if ($repinfo['deleted'])
                $html .= "<i>deleted</i> ";
            elseif ($repinfo['last_editor'] == 'fyr-queue') 
                $html .= "<i>failed</i> ";
            if (array_key_exists('type', $repinfo))
                $html .= $repinfo['type'] . " ";
            else
                $html .= $repinfo['area_type'] . " ";
            $link_extra = "";
            if ($bad_link && $i < count($reps) - 1) 
                $link_extra = "&nextbad=".urlencode($reps[$i+1]);
            $html .= "<a href=\"$self_link&pc=" .  urlencode(get_http_var('pc')). "&rep_id=" . $rep .  "$link_extra\">" . $repinfo['name'] . " (". $repinfo['party'] . ")</a> \n";
            $html .= "prefer " . $repinfo['method'];
            if ($repinfo['email']) 
                $html .= ", " .  $repinfo['email'];
            if ($repinfo['fax']) 
                $html .= ", " .  $repinfo['fax'];
            $html .= "<br>";
        }
        return $html;
    }

    function render_area($self_link, $area_id, $area_info, $pc, $add_link=false) {
        global $va_type_name;
        $url = $self_link . '&pc=' . urlencode($pc);
        $html = "<p><strong><a href='$url&va_id=$area_id'>$area_info[name]</a>";
        $html .= " (" .  $va_type_name[$area_info['type']] . ")</strong>";
        if ($add_link) $html .= " &ndash; <a href='$url&new_in_va_id=$area_id'>Add new representative</a>";
        $html .= '</p>';
        return $html;
    }

    function get_next_bad_contact($rep_id) {
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

    function display($self_link) {
        // Input data
        $rep_id = get_http_var('rep_id');
        $va_id = get_http_var('va_id');
        $ds_va_id = get_http_var('ds_va_id');
        $bad_contacts = get_http_var('bad_contacts');
        $user_corrections = get_http_var('user_corrections');
        // Make new rep in this voting area
        $new_in_va_id = get_http_var('new_in_va_id');

        if (!$rep_id && $ds_va_id) {
            // Democratic services
            $ds_vainfo = dadem_get_representatives($ds_va_id);
            dadem_check_error($ds_vainfo);
            if (isset($ds_vainfo[0])) {
                $rep_id = $ds_vainfo[0];
            } else {
                $new_in_va_id = $ds_va_id;
            }
        }
        // Postcode
        $pc = get_http_var('pc');
        // Search
        $search = null;
        if (get_http_var('gos')) {
            if (validate_postcode(get_http_var('search'))) {
                $pc = get_http_var('search');
                $rep_id = null;
            } else {
                $search = get_http_var('search');
                $rep_id = null;
            }
        }
        if (get_http_var('cancel') != "") 
            $rep_id = null;
        if (get_http_var('done') != "") {
            if (get_http_var('token') != $this->get_token()) {
                print "<p><i>Token not found</i></p>";
            } else {
                $newdata['name'] = get_http_var('name');
                $newdata['party'] = get_http_var('party');
                $newdata['method'] = get_http_var('method');
                $newdata['email'] = get_http_var('email');
                $newdata['fax'] = get_http_var('fax');
                if (!$rep_id) {
                    // Making a new representative, put in type and id
                    $newdata['area_id'] = $new_in_va_id;
                    $vainfo = mapit_call('area', $new_in_va_id);
                    mapit_check_error($vainfo);
                    $newdata['area_type'] = $vainfo['type'];
                }
                $result = dadem_admin_edit_representative($rep_id, $newdata, http_auth_user(), get_http_var('note'));
                dadem_check_error($result);
                $rep_id = $result;
                $new_in_va_id = null;
                print "<p><i>Successfully updated representative ". htmlspecialchars($rep_id) . "</i></p>";

                if (get_http_var('nextbad')) {
                    $rep_id = get_http_var('nextbad');
                    $url = $self_link . "&nextbad=" . urlencode($this->get_next_bad_contact($rep_id)) . "&just_done_bad=1&rep_id=" . urlencode($rep_id);
                    header("Location: $url");
                    exit;
                } else {
                    $rep_id = null;
                }
            }
        }
        if (get_http_var('just_done_bad')) {
            print "<p><i>Moved on to next bad contact</i></p>";
        }
        if (get_http_var('delete') != "") {
            if (get_http_var('token') != $this->get_token()) {
                print "<p><i>Token not found</i></p>";
            } else { 
                $result = dadem_admin_edit_representative($rep_id, null, http_auth_user(), get_http_var('note'));
                dadem_check_error($result);
                print "<p><i>Successfully deleted representative ". htmlspecialchars($rep_id) . "</i></p>";
                $rep_id = null;
            }
        }
        if (get_http_var('ucclose') != "") {
            $result = dadem_admin_done_user_correction(get_http_var('ucid'));
            dadem_check_error($result);
            print "<p><i>Successfully closed correction ". htmlspecialchars(get_http_var('ucid')) . "</i></p>";
        }
        if (get_http_var('vaupdate') != "") {
            $result = dadem_admin_set_area_status(get_http_var('va_id'), get_http_var('new_status'));
            dadem_check_error($result);
            print "<p><i>Successfully updated voting area status ". htmlspecialchars(get_http_var('va_id')) . " to " . htmlspecialchars(get_http_var('new_status')) . "</i></p>";
        }

        // Postcode and search box
        $form = new HTML_QuickForm('adminRepsSearchForm', 'get', $self_link);
        $form->addElement('header', '', 'Search');
        $buttons[] =& HTML_QuickForm::createElement('text', 'search', null, array('size' => 20, 'maxlength' => 255));
        $buttons[] =& HTML_QuickForm::createElement('submit', 'gos', 'postcode or query');
        $form->addElement('hidden', 'page', $this->id);
        $form->addGroup($buttons, 'stuff', null, '&nbsp', false);
        admin_render_form($form);

        // Conditional parts: 
        if ($rep_id or $new_in_va_id) {
            $form = new HTML_QuickForm('adminRepsEditForm', 'post', $self_link);
            $form->addElement('hidden', 'page', $this->id);
            $form->addElement('hidden', 'token', $this->get_token());
           
            // Edit representative
            $sameperson = null;
            if ($rep_id) {
                $repinfo = dadem_get_representative_info($rep_id);
                dadem_check_error($repinfo);
                if ($repinfo['parlparse_person_id']) {
                    $sameperson = dadem_get_same_person($repinfo['parlparse_person_id']);
                    dadem_check_error($sameperson);
                }
            }
            $va_id = $rep_id ? $repinfo['voting_area'] : $new_in_va_id;
            $vainfo = mapit_call('area', $va_id);
            mapit_check_error($vainfo);
            if ($vainfo['parent_area']) {
                $parentinfo = mapit_call('area', $vainfo['parent_area']);
                mapit_check_error($parentinfo);
            } else 
                $parentinfo = null;
            $rephistory = $rep_id ? dadem_get_representative_history($rep_id) : array();
            dadem_check_error($rephistory);
            // Reverse postcode lookup
            if (!$pc) {
                $pc = mapit_call('area/example_postcode', $va_id);
                if (!mapit_get_error($pc)) {
                    $form->addElement('static', 'note1', null, "Example postcode for testing: " .
                        "<a href='" . OPTION_BASE_URL . '/who?pc=' . urlencode($pc) . "'>"
                        . htmlentities($pc) ."</a> (<a href='?search=" . urlencode($pc) . "&amp;gos=postcode+or+query&amp;page=reps'>all reps here</a>)");
                } else {
                    $pc = '';
                }
            }

            if ($rep_id) {
                $form->setDefaults(
                    array('name' => $repinfo['name'],
                    'party' => $repinfo['party'],
                    'method' => $repinfo['method'],
                    'email' => $repinfo['email'],
                    'fax' => $repinfo['fax']));
            }
    
            // Councillor types are not edited here, but in match.cgi interface
            global $va_council_child_types, $va_type_name, $va_rep_name;
            $editable_here = true;
            if (OPTION_ADMIN_SERVICES_CGI && in_array($vainfo['type'], $va_council_child_types)) {
                $editable_here = false;
            }
            $readonly = $editable_here ? null : "readonly";

            if ($rep_id) {
                $form->addElement('header', '', 'Edit Representative');
                if ($repinfo['deleted']) {
                    $form->addElement('static', 'notedeleted', null, "<strong style=\"color: red\">Deleted representative</strong>, click 'Done' to undelete");
                }
            } else
                $form->addElement('header', '', 'New Representative');
            if ($rep_id and $editable_here) {
                $form->addElement('static', 'note1', null, "
                Edit only the values which you need to.  If a representative
                has changed delete them and make a new one.  Do not just edit
                their values, as this would ruin our reponsiveness stats.");
            }
            if ($rep_id && $sameperson) {
                $html = '';
                foreach ($sameperson as $samerep) {
                    if ($samerep == $rep_id) continue;
                    $html .= "<a href=\"$self_link&pc=" .  urlencode(get_http_var('pc')). "&rep_id=" . $samerep .  "\">" . $samerep. "</a> \n";
                }
                if ($html) {
                    $html = '(Note that these other representatives are the same person: ' . trim($html) . ')';
                    $form->addElement('static', 'sameperson', null, $html);
                }
            }

            $form->addElement('static', 'office', 'Office:',
                htmlspecialchars($va_rep_name[$vainfo['type']] . " for " .
                htmlspecialchars($vainfo['name']) . " " . htmlspecialchars($va_type_name[$vainfo['type']]) .
                ($parentinfo ? " in " . 
                htmlspecialchars($parentinfo['name']) . " " . htmlspecialchars($va_type_name[$parentinfo['type']]) : "" ));
            $form->addElement('text', 'name', "Full name:", array('size' => 60, $readonly => 1));
            $form->addElement('text', 'party', "Party:", array('size' => 60, $readonly => 1));
            $form->addElement('static', 'note2', null, "Make sure you update contact method when you change email or fax numbers.");
            $form->addElement('select', 'method', "Contact method:", 
                    array(
                        #'either' => 'Fax or Email',
                        'fax' => 'Fax only', 
                        'email' => 'Email only',
                        'shame' => "Shame! Doesn't want contacting",
                        'via' => 'Contact via electoral body (e.g. Democratic Services)',
                        'unknown' => "We don't know contact details"
                    ));
            $form->addElement('text', 'email', "Email:", array('size' => 60, $readonly => 1));
            $form->addElement('text', 'fax', "Fax:", array('size' => 60, $readonly => 1));
            $form->addElement('textarea', 'note', "Notes for log:", array('rows' => 3, 'cols' => 60, $readonly => 1));
            $form->addElement('hidden', 'pc', $pc);
            if (get_http_var('nextbad'))
                $form->addElement('hidden', 'nextbad', get_http_var('nextbad'));
            if ($rep_id) 
                $form->addElement('hidden', 'rep_id', $rep_id);
            else
                $form->addElement('hidden', 'new_in_va_id', $new_in_va_id);

            if ($editable_here) {
                $finalgroup[] = &HTML_QuickForm::createElement('submit', 'done', 'Done');
                $finalgroup[] = &HTML_QuickForm::createElement('submit', 'cancel', 'Cancel');
                if ($rep_id) {
                    $finalgroup[] = &HTML_QuickForm::createElement('static', 'newlink', null,
                        "<a href=\"$self_link&pc=" .  urlencode(get_http_var('pc')). "&new_in_va_id=" . 
                        $va_id .  "\">" . 
                        "Make new " . 
                        htmlspecialchars($vainfo['name']) . " rep". 
                        "</a> \n");
                    if ($repinfo['deleted']) {
                        $finalgroup[] = &HTML_QuickForm::createElement('static', 'staticspacer', null, '&nbsp; Deleted rep, no longer in office, just click done to undelete');
                    } else {
                        $finalgroup[] = &HTML_QuickForm::createElement('static', 'staticspacer', null, '&nbsp; No longer in office? --->');
                        $finalgroup[] = &HTML_QuickForm::createElement('submit', 'delete', 'Delete');
                    }
                }
                $form->addGroup($finalgroup, "finalgroup", "",' ', false);
            } else {
                $form->addElement('static', 'note3', null, 
                    '<a href="'.OPTION_ADMIN_SERVICES_CGI.'match.cgi?page=councilinfo;area_id='
                    . $vainfo['parent_area'] . '">To edit Councillors please use the match.cgi interface</a>'.
                    '<br><a href="'.$self_link.'&ds_va_id='
                    . $vainfo['parent_area'] . '">... or edit Democratic Services for this council</a>');
                $finalgroup[] = &HTML_QuickForm::createElement('submit', 'done', 'Done');
                $finalgroup[] = &HTML_QuickForm::createElement('submit', 'cancel', 'Cancel');
                $form->addGroup($finalgroup, "finalgroup", "",' ', false);
            }
            if ($rep_id) {
                $search_links = "Search for: ";
                $search_links .= "<a href=\"$self_link&page=fyrqueue&rep_id=" . $rep_id .  "\">WriteToThem messages</a> | ";
                foreach (array(
                    "tel ". $repinfo['name'],
                    "fax ". $repinfo['name'],
                    "tel ". $repinfo['name'] . " " . $va_rep_name[$vainfo['type']],
                    "fax ". $repinfo['name'] . " " . $va_rep_name[$vainfo['type']]
                    ) as $searchq) 
                    $search_links .= "<a href=\"http://search.yahoo.com/search?p=".htmlspecialchars($searchq)."\"> ".htmlspecialchars($searchq)."</a> | ";
                $form->addElement('static', 'newlink', null, $search_links);

                if ($repinfo['parlparse_person_id']) {
                    $form->addElement('static', 'person', 'parlparse person_id:', $repinfo['parlparse_person_id']);
                }
            }
    
            $form->addElement('header', '', 'Historical Changes');
            $html = "<table border=1>";
            $html .= "<th>Order</th><th>Date</th><th>Editor</th><th>Note</th>
                <th>Name</th> <th>Party</th> <th>Method</th> <th>Email</th>
                <th>Fax</th><th>Active</th>";

            $previous_row = null;
            foreach ($rephistory as $row) {
                $html .= "<tr>";
                foreach (array('order_id', 'whenedited', 'editor', 'note', 
                    'name', 'party', 'method', 'email', 'fax', 'deleted') as $field) {

                    If ($row['deleted'] && ($field == 'email' || $field == 'fax' || $field == 'method')) {
                        $display_value = 'deleted';
                        $html .= "<td>-</td>\n";
                        continue;
                    }

                    $value = $row[$field];
                    if ($field == 'note')
                        $display_value = make_ids_links($value);
                    elseif ($field == 'whenedited')
                        $display_value = strftime('%Y-%m-%d %H:%M:%S', $value);
                    elseif ($field == 'deleted') 
                        $display_value = $value ? 'deleted' : 'yes';
                    else
                        $display_value = $value;
                    if ($field != "order_id" && $field != "whenedited" &&
                        $field != "editor" && $field != "note" &&
                        $previous_row && $previous_row[$field] != $value) 
                        $display_value = "<strong>$display_value</strong>";

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
            $form->addElement('static', 'bytype', null, $html);
            admin_render_form($form);
        } elseif ($va_id) {
            // One voting area
            $form = new HTML_QuickForm('adminVotingArea', 'get', $self_link);
            $area_info = mapit_call('area', $va_id);
            mapit_check_error($area_info);
            $reps = dadem_get_representatives($va_id);
            dadem_check_error($reps);
            $reps = array_values($reps);
            $html = $this->render_area($self_link, $va_id, $area_info, $pc); 
            $html .= $this->render_reps($self_link, $reps);
            $form->addElement('static', 'bytype', null, $html);
            $form->addElement('hidden', 'page', $this->id);
            $form->addElement('hidden', 'token', $this->get_token());
            $form->addElement('hidden', 'va_id', $va_id);
            $select = $form->addElement('select', 'new_status', null, 
                    array(
                        'none' => 'No special status', 
                        'pending_election' => 'Pending election, rep data not valid', 
                        'recent_election' => 'Recent election, our rep data not yet updated',
                    ),
                    array()
            );
            $status = dadem_get_area_status($va_id);
            dadem_check_error($status);
            $select->setSelected($status);
 
            $form->addElement('submit', 'vaupdate', 'Update');
            admin_render_form($form);
        } elseif ($search) {
            $form = new HTML_QuickForm('adminRepsSearchResults', 'get', $self_link);

            $html = '';
            $areas = mapit_call('areas', $search);
            mapit_check_error($areas);
            global $va_inside;
            foreach (array_keys($areas) as $va_id) {
                $area_info = mapit_call('area', $va_id);
                mapit_check_error($area_info);
                $reps = dadem_get_representatives($va_id);
                dadem_check_error($reps);
                $reps = array_values($reps);
                $html .= $this->render_area($self_link, $va_id, $area_info, $pc, isset($va_inside[$area_info['type']]));
                $html .= $this->render_reps($self_link, $reps);
            }
            // Search reps
            $reps = dadem_search_representatives($search);
            dadem_check_error($reps);
            $html .= '<hr>' . $this->render_reps($self_link, $reps);
            $form->addElement('static', 'bytype', null, $html);

            admin_render_form($form);
        } elseif ($pc) {
            $form = new HTML_QuickForm('adminRepsSearchResults', 'get', $self_link);
            
            // Postcode search
            $voting_areas = mapit_call('postcode', $pc);
            mapit_check_error($voting_areas);
            $areas_info = $voting_areas['areas'];
            $html = "";
            // Display in order council, ward, council, ward...
            global $va_display_order, $va_inside;
            $our_order = array();
            foreach ($va_display_order as $row) {
                if (!is_array($row))
                    $row = array($row);
                if (!in_array($va_inside[$row[0]], $our_order)) {
                    $our_order[] = $va_inside[$row[0]];
                }
                foreach ($row as $va_type) {
                    $our_order[] = $va_type;
                }
            }
            // Render everything in the order
            foreach ($our_order as $va_type) {
                foreach ($areas_info as $area=>$area_info) {
                    if ($va_type <> $area_info['type']) 
                        continue;
                    $va_id = $area;

                    // One voting area
                    $reps = dadem_get_representatives($va_id);
                    dadem_check_error($reps);
                    $reps = array_values($reps);
                    $html .= $this->render_area($self_link, $va_id, $area_info, $pc, isset($va_inside[$va_type]));
                    $html .= $this->render_reps($self_link, $reps);
                }
            }
            $form->addElement('static', 'bytype', null, $html);

            admin_render_form($form);
        } elseif ($bad_contacts) {
            // Bad contacts
            $form = new HTML_QuickForm('adminRepsBad', 'post', $self_link);
            $badcontacts = dadem_get_bad_contacts();
            dadem_check_error($badcontacts);
            $form->addElement('header', '', 'Bad Contacts ' . count($badcontacts));
            $html = $this->render_reps($self_link, $badcontacts, true);
            $form->addElement('static', 'badcontacts', null, $html);
            admin_render_form($form);
        } elseif ($user_corrections) {
            // User submitted corrections
            $form = new HTML_QuickForm('adminRepsCorrectionsHeader', 'post', $self_link);
            $corrections = dadem_get_user_corrections();
            dadem_check_error($corrections);
            $form->addElement('header', '', 'User Submitted Corrections ' . count($corrections));
            $form->addElement('hidden', 'token', $this->get_token());
            admin_render_form($form);
            // Get all the data for areas and their parents in as few call as possible
            $vaids = array();
            foreach ($corrections as $correction) {
                array_push($vaids, $correction['voting_area_id']);
            }
            $info1 = mapit_call('areas', $vaids);
            mapit_check_error($info1);
            $vaids = array();
            foreach ($info1 as $key=>$value) {
                array_push($vaids, $value['parent_area']);
            }
            $info2 = mapit_call('areas', $vaids);
            
            foreach ($corrections as $correction) {
                $form = new HTML_QuickForm('adminRepsCorrections', 'post', $self_link);
                $html = "";
                $rep = $correction['representative_id'];

                $html .= "<p>";
                $html .= strftime('%Y-%m-%d %H:%M:%S', $correction['whenentered']) . " ";
                if ($correction['user_email'])
                    $html .= " by " . htmlspecialchars($correction['user_email']);
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

                    $html .= "<a href=\"$self_link&pc=" .  urlencode(get_http_var('pc')). "&rep_id=" . $rep .  "\">" . htmlspecialchars($repinfo['name']) . " (". htmlspecialchars($repinfo['party']) . ")</a> \n";
                    if ($correction['alteration'] != "delete") {
                        $html .= " to ";
                    }
                }
                if ($correction['alteration'] != "delete") {
                    $html .= htmlspecialchars($correction['name']) .  " (" . htmlspecialchars($correction['party']) . ")";
                }
                if ($correction['user_notes'])
                    $html .= "<br>Notes: " . htmlspecialchars($correction['user_notes']);

                $usercorr = array();
                $usercorr[] =& HTML_QuickForm::createElement('static', 'usercorrections', null, $html);
                // You can't do this with element type "hidden" as it only allows one value in a
                // page for variable named ucid.  So once again I go to raw HTML.  Remind me not
                // to use HTML_QuickForm again...
                $usercorr[] =& HTML_QuickForm::createElement('html', 
                    '<input name="ucid" type="hidden" value="'. $correction['user_correction_id'] . '" />');
                $usercorr[] =& HTML_QuickForm::createElement('submit', 'ucclose', 'hide (done)');
                $form->addGroup($usercorr, 'stuff', null, '&nbsp', false);
                admin_render_form($form);
            }
        } else {
            print '<p><a href="?page=reps&bad_contacts=1">Bad contacts</a> (please fix these!)';
            // General info
            if (OPTION_ADMIN_SERVICES_CGI) {
                print '<br><a href="?page=reps&user_corrections=1">User corrections</a> (just for your interest, as sent automatically to GovEval)';
            }
        }
   }
}


?>
