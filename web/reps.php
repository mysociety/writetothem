<?
/*
 * Interface for representatives - forwarding messages, changing email
 * vs. fax preference and so on.
 * 
 * Copyright (c) 2004 UK Citizens Online Democracy. All rights reserved.
 * Email: francis@mysociety.org. WWW: http://www.mysociety.org
 *
 * $Id: reps.php,v 1.2 2004-11-18 14:29:36 francis Exp $
 * 
 */

require_once "../phplib/forms.php";
require_once "../phplib/fyr.php";

require_once "../../phplib/utility.php";
require_once "../../phplib/dadem.php";
require_once "../../phplib/mapit.php";

// Input data
$fyr_who = 2000002;
$messages = array(
    array(name => "Sammy Streetwise", body => "I'm writing to complain
    about litter not being collected.  Every week I put out more and
    more rubbish, but the council does not come.  It's a disgrace that I
    pay my council tax but a simple thing like litter collection doesn't
    happen.  Can you please sort it out?", id => 0),
    array(name => "Freddy Frantic", body => "I'm writing to say that I
    think MPs aren't paid enough expenses to do their job properly.  I
    am sick and tired of newspapers claiming that MPs expenses are just
    to line their pockets.  They are not, they are to help them do their
    job.  Just thought I'd let you know how I felt.", id
    => 1),);
$fyr_postcode = "zz99zz";
debug("FRONTEND", "who is $fyr_who");
debug("FRONTEND", "postcode is $fyr_postcode");
fyr_rate_limit(array('postcode' => $fyr_postcode, 'who' => $fyr_who));

// What to do
$action = get_http_var('action');
if ($action == "") $action = "index";

// Look up info about the representative who is using the interface
$representative = dadem_get_representative_info($fyr_who);
$voting_area = mapit_get_voting_area_info($representative['voting_area']);

// Add extra fields
$newmessages = array();
foreach ($messages as $message) {
    $message['short_body'] = trim_characters($message['body'], 0, 200);
    $message['url'] = "reps?action=forward&id=" . $message['id'];
    $newmessages[] = $message;
}
$messages = $newmessages;

// Perform specified action
if ($action == 'index') {
    // Display page, using all the variables set above.
   template_draw("reps-index", array("representative" => $representative, 
        "voting_area" => $voting_area, 
        "messages" => $messages));
} else {
    $id = get_http_var('id');
    $constituent = $messages[$id];
    $message = $messages[$id];

    if ($action == 'doforward') {
       template_draw("reps-forwardok", array("representative" => $representative, 
            "voting_area" => $voting_area, 
            "constituent" => $constituent, 
            "message" => $message));
    } else if ($action == 'forward') {
        // Find all the districts/constituencies and so on (we call them "voting
        // areas") for the postcode
        $voting_areas = mapit_get_voting_areas($fyr_postcode);
        if ($fyr_error_message = mapit_get_error($voting_areas)) {
            template_show_error();
        }
        $voting_areas_info = mapit_get_voting_areas_info(array_values($voting_areas));
        if ($fyr_error_message = mapit_get_error($voting_areas_info)) {
            template_show_error();
        }
        $area_representatives = dadem_get_representatives(array_values($voting_areas));
        if ($fyr_error_message = dadem_get_error($area_representatives)) {
            template_show_error();
        }
        $all_representatives = array();
        foreach (array_values($area_representatives) as $rr) {
            $all_representatives = array_merge($all_representatives, $rr);
        }
        $representatives_info = dadem_get_representatives_info($all_representatives);
        if ($fyr_error_message = dadem_get_error($representatives_info)) {
            template_show_error();
        }

        debug_timestamp();

        // For each voting area in order, find all the representatives.  Put
        // descriptive text and form text in an array for the template to
        // render.
        $fyr_representatives = array();
        foreach ($va_display_order as $va_type) {
            if (!array_key_exists($va_type, $voting_areas))
                continue;
            $va_specificid = $voting_areas[$va_type];

            // The voting area is the ward/division. e.g. West Chesterton Electoral Division
            debug("FRONTEND", "voting area is type $va_type id $va_specificid");
            $va_info = $voting_areas_info[$va_specificid];

            // The elected body is the overall entity. e.g. Cambridgeshire County
            // Council. 
            $eb_type = $va_inside[$va_type];
            $eb_specificid = $voting_areas[$eb_type];
            debug("FRONTEND", "electoral body is type $eb_type id $eb_specificid");
            $eb_info = $voting_areas_info[$eb_specificid];
            
            // Description of areas of responsibility
            $eb_info['description'] = $va_responsibility_description[$eb_type];

            // Count representatives
            $representatives = $area_representatives[$va_specificid];
            $rep_count = count($representatives);

            // Create HTML
            if ($rep_count > 1) {
                $left_column = "<h4>${constituent['name']}'s ${va_info['rep_name_long_plural']}</h4><p>";
                $left_column .= "${constituent['name']}'s
                ${va_info['rep_name_plural']} are representatives on ${eb_info['attend_prep']} ";
            } else {
                $left_column = "<h4>${constituent['name']}'s ${va_info['rep_name_long']}</h4><p>";
                $left_column .= "${constituent['name']}'s ${va_info['rep_name']}
                represents them ${eb_info['attend_prep']} ";
            }
            $left_column .= "${eb_info['name']}.  ${eb_info['description']}</p>";

            $form = new HTML_QuickForm('whoForm', 'post', 'reps');

            $right_column = "<p>In ${constituent['name']}'s ${va_info['type_name']},
                <b>${va_info['name']}</b>, they are represented by ";
            if ($rep_count > 1) {
                $right_column .= "$rep_count ${va_info['rep_name_plural']}.
                    Please choose one ${va_info['rep_name']} to contact.";
            } else {
                $right_column .= "one ${va_info['rep_name']}.";
            }

            // Rest of representatives
            foreach ($representatives as $rep_specificid) {
                ++$c;
                $rep_info = $representatives_info[$rep_specificid];
                if ($rep_specificid == $fyr_who)
                    $form->addElement('static', '', null, $rep_info['name'] . ' is you!', $rep_specificid);
                else
                    $form->addElement('radio', 'who', null, "&nbsp;<b>" .  $rep_info['name'] . '</b><br>'
                        . $rep_info['party'], $rep_specificid);
            }

            $form->addElement('hidden', 'action', 'doforward');
            $form->addElement('hidden', 'id', $id);
            $form->addElement('submit', 'next', 'Forward Message >>');
            $fyr_form_renderer = new HTML_QuickForm_Renderer_mySociety();
            $form->accept($fyr_form_renderer);
            $right_column .= $fyr_form_renderer->toHtml();

            array_push($fyr_representatives, array($left_column, $right_column));

            debug_timestamp();
        }

        // Display page, using all the fyr_* variables set above.
        template_draw("reps-forward", array("representative" => $representative, 
            "voting_area" => $voting_area, 
            "constituent" => $constituent, 
            "message" => $message, 
            "forwardreps" => $fyr_representatives));
    }
}

?>

