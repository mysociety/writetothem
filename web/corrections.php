<?
/*
 * corrections.php:
 * Page where they make councillor corrections
 * 
 * Copyright (c) 2004 UK Citizens Online Democracy. All rights reserved.
 * Email: matthew@mysociety.org. WWW: http://www.mysociety.org
 *
 * $Id: corrections.php,v 1.6 2005-02-15 11:12:06 francis Exp $
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
$parent_info = mapit_get_voting_area_info($va_info['parent_area_id']);
mapit_check_error($parent_info);
debug_timestamp();
$area_reps = dadem_get_representatives(array($fyr_vatype));
dadem_check_error($area_reps);
debug_timestamp();
$area_reps = $area_reps[$fyr_vatype];
$area_reps = array_values($area_reps);
$reps_info = dadem_get_representatives_info($area_reps);
dadem_check_error($reps_info);
debug_timestamp();

$template_page = 'corrections-index';
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

    # Store URL with notes
    if ($fyr_url) $fyr_notes = "$fyr_url\n\n$fyr_notes";
    
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
            $ret = dadem_store_user_correction($fyr_vatype, $rep, 'modify', $newname, $newparty, $fyr_notes, $fyr_email);
            dadem_check_error($ret);
        }
    }

    if (isset($fyr_new['name']) && isset($fyr_new['party']) && $fyr_new['name'] && $fyr_new['party']) {
        $ret = dadem_store_user_correction($fyr_vatype, null, 'add', $fyr_new['name'], $fyr_new['party'], $fyr_notes, $fyr_email);
        dadem_check_error($ret);
    }

    foreach ($fyr_delete as $rep_id => $dummy) {
        if (!in_array($rep_id, $area_reps)) {
            template_show_error('Trying to delete a rep not in this area?');
            exit;
        }
        if (sizeof($area_reps) == 1) {
            template_show_error('Trying to delete the only representative?');
            exit;
        }
        $ret = dadem_store_user_correction($fyr_vatype, $rep_id, 'delete', '', '', $fyr_notes, $fyr_email);
        dadem_check_error($ret);
    }

    $template_page = 'corrections-thanks';
}

template_draw($template_page, array('id'=>$fyr_vatype, 
    'va_info'=>$va_info, 
    'reps_info'=>$reps_info,
    'parent_info'=>$parent_info,
    ));

?>
