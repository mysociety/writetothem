<?
/*
 * corrections.php:
 * Page where they make councillor corrections
 * 
 * Copyright (c) 2004 UK Citizens Online Democracy. All rights reserved.
 * Email: matthew@mysociety.org. WWW: http://www.mysociety.org
 *
 * $Id: corrections.php,v 1.1 2005-02-04 13:53:59 matthew Exp $
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
    $diff = array_diff($area_reps, array_keys($fyr_names));
    $diff2 = array_diff($area_reps, array_keys($fyr_parties));
    if (sizeof($diff) || sizeof($diff2)) {
        template_show_error('There was a problem with that submission; the submitted rows did not match the rows in the database.');
        exit;
    }
    foreach ($area_reps as $rep) {
        $oldname = $reps_info[$rep]['name'];
        $oldparty = $reps_info[$rep]['party'];
        $newname = $fyr_names[$rep];
        $newparty = $fyr_parties[$rep];
        if ($oldname != $newname || $oldparty != $newparty) {
            $out .= "Wanting to change $oldname,$oldparty to $newname,$newparty<br>";
        }
    }
    $fyr_new = (isset($fyr_values['new'])) ? $fyr_values['new'] : array();
    if (isset($fyr_new['name']) && isset($fyr_new['party']) && $fyr_new['name'] && $fyr_new['party']) {
        $out .= "Wanting to add a councillor: $fyr_new[name] $fyr_new[party]<br>";
    }
    $fyr_delete = (isset($fyr_values['delete'])) ? $fyr_values['delete'] : array();
    foreach ($fyr_delete as $rep_id => $dummy) {
        $out .= "Delete: $rep_id<br>";
    }
    $fyr_url = (isset($fyr_values['url'])) ? $fyr_values['url'] : '';
    if ($fyr_url == 'http://') $fyr_url = '';
    $fyr_notes = (isset($fyr_values['notes'])) ? $fyr_values['notes'] : '';
    $fyr_email = (isset($fyr_values['email'])) ? $fyr_values['email'] : '';
    $out .= "Submitted URL:$fyr_url, notes:$fyr_notes, email:$fyr_email<br>";
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
