<?
/*
 * corrections.php:
 * Page where they make councillor corrections
 * 
 * Copyright (c) 2004 UK Citizens Online Democracy. All rights reserved.
 * Email: matthew@mysociety.org. WWW: http://www.mysociety.org
 *
 * $Id: corrections.php,v 1.3 2005-02-04 15:00:00 matthew Exp $
 * 
 */

require_once "../phplib/fyr.php";
require_once "../phplib/forms.php";
require_once "../phplib/queue.php";

require_once "../../phplib/mapit.php";
require_once "../../phplib/votingarea.php";
require_once "../../phplib/dadem.php";
require_once "../../phplib/utility.php";

// Get all fyr_values
$fyr_values = get_all_variables();
debug("FRONTEND", "All variables:", $fyr_values);
if (!array_key_exists('id', $fyr_values) || $fyr_values['id'] == '' || !ctype_digit($fyr_values['id'])) {
    template_show_error('Please <a href="/">start from the beginning</a>.');
    exit;
}

$fyr_vatype = $fyr_values['id'];

$va_info = mapit_get_voting_area_info($fyr_vatype);
mapit_check_error($va_info);
debug_timestamp();
$area_reps = dadem_get_representatives(array($fyr_vatype));
dadem_check_error($area_reps);
debug_timestamp();
$area_reps = $area_reps[$fyr_vatype];
$area_reps = array_values($area_reps);
$reps_info = dadem_get_representatives_info($area_reps);
dadem_check_error($reps_info);
debug_timestamp();

$out = '';
if (isset($fyr_values['name']) && isset($fyr_values['party'])) {
    # Changes have been submitted
    $fyr_names = $fyr_values['name'];
    $fyr_parties = $fyr_values['party'];
    $fyr_new = (isset($fyr_values['new'])) ? $fyr_values['new'] : array();
    $fyr_delete = (isset($fyr_values['delete'])) ? $fyr_values['delete'] : array();
    $fyr_url = (isset($fyr_values['url'])) ? $fyr_values['url'] : '';
    if ($fyr_url == 'http://') $fyr_url = '';
    $fyr_notes = (isset($fyr_values['notes'])) ? $fyr_values['notes'] : '';
    $fyr_email = (isset($fyr_values['email'])) ? $fyr_values['email'] : '';

    # Just because I'm outputting it straight back out again
    # Pseudo SQL to show the sort of thing I think is wanted
    $fyr_url = htmlspecialchars($fyr_url);
    $fyr_notes = htmlspecialchars($fyr_notes); if ($fyr_url) $fyr_notes = "$fyr_url\n\n$fyr_notes";
    $fyr_email = htmlspecialchars($fyr_email);
    
    $diff = array_diff($area_reps, array_keys($fyr_names));
    $diff2 = array_diff($area_reps, array_keys($fyr_parties));
    if (sizeof($diff) || sizeof($diff2)) {
        template_show_error('There was a problem with that submission; the submitted rows did not match the rows in the database.');
        exit;
    }

    foreach ($area_reps as $rep) {
        $oldname = $reps_info[$rep]['name'];
        $oldparty = $reps_info[$rep]['party'];
        $newname = htmlspecialchars($fyr_names[$rep]);
        $newparty = htmlspecialchars($fyr_parties[$rep]);
        if ($oldname != $newname || $oldparty != $newparty) {
            $out .= "INSERT INTO some_table (rep_id, change, name, party, notes, email) VALUES ($rep, 'modify', \"$newname\", \"$newparty\", \"$fyr_notes\", \"$fyr_email\")<br>";
        }
    }

    if (isset($fyr_new['name']) && isset($fyr_new['party']) && $fyr_new['name'] && $fyr_new['party']) {
        $out .= "INSERT INTO some_table (rep_id, change, name, party, notes, email) VALUES (NULL, 'add', \"".htmlspecialchars($fyr_new['name']).'", "'.htmlspecialchars($fyr_new['party'])."\", \"$fyr_notes\", \"$fyr_email\")<br>";
    }

    foreach ($fyr_delete as $rep_id => $dummy) {
        if (!in_array($rep_id, $area_reps)) {
            template_show_error('Trying to delete a rep not in this area?');
            exit;
        }
        $out .= "INSERT INTO some_table (rep_id, change, notes, email) VALUES ($rep_id, 'delete', \"$fyr_notes\", \"$fyr_email\")<br>";
    }
}

$on_page = 'corrections';
$error = '';
if ($out) $error = $out;

if ($on_page == 'corrections') {
    template_draw('corrections', array('id'=>$fyr_vatype, 'va_info'=>$va_info, 'reps_info'=>$reps_info, 'error'=>$error));
} else {
    template_show_error(
            'Sorry. An error has occurred: on_page "'
                . htmlspecialchars($on_page) .
            '". Please get in touch with us at
            <a href="mailto:help@writetothem.com">help@writetothem.com</a>,
            quoting this message. You can <a href="/">try again from the
            beginning</a>.'
        );
}

?>
