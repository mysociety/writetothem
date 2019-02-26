<?
/*
 * index.php:
 *
 * Copyright (c) 2012 UK Citizens Online Democracy. All rights reserved.
 * Email: matthew@mysociety.org. WWW: http://www.mysociety.org
 *
 */

require_once "../phplib/fyr.php";
require_once "../phplib/mapit.php";
require_once "../commonlib/phplib/utility.php";
require_once '../commonlib/phplib/dadem.php';
require_once "../commonlib/phplib/votingarea.php";

/* Return the form for entering a postcode.
 * Valid options are:
 * inner_div   - boolean - include a div tag inside the form
 * extra_space - use non-breaking space for laying out inputs
 * bold_labels - use bold for label texts
 * show_errors - display error message if there is one
 */
function postcode_form($pc, $cobrand, $cocode, $a_forward, $error_message, $options) {

    $form = '<form action="/" method="get" name="postcodeForm" id="postcodeForm" onsubmit="trackFormSubmit(this, \'PostcodeSearch\', \'Submit\'); return false;">';

    if ($options['inner_div']){
        $form .= '<div id="postcodebox">';
    }

    $form .= '<label for="pc">';

    if ($options['bold_labels']){
        $form .= '<b>';
    }

    $message = cobrand_enter_postcode_message($cobrand, $cocode);

    if (!$message) {
        $message = 'First, enter your UK postcode';
    }

    $form .= $message;

    if ($options['bold_labels']){
        $form .= '</b>';
    }

    $form .= '</label>';

    if ($options['extra_space']){
        $form .= '&nbsp;';
    }

    if ($options['show_errors'] && $error_message){
        $form .= '<div class="alert-box alert">' . $error_message . '</div>';
    }

    if ($options['extra_space']){
        $form .= '&nbsp;';
    }

    $form .= '<div class="row collapse">
        <div class="small-10 columns">
          <input type="text" id="pc" name="pc" value="'.htmlspecialchars($pc).'" placeholder="SW1H 9NB">
        </div>
        <div class="small-2 columns">
          <input type="submit" class="button success prefix" value="Go">
        </div>
      </div>';

    /* Record referer. We want to pass this onto the queue later, as an anti-abuse
     * measure, so it should be propagated through all the later pages. Obviously
     * this only has value against a naive attacker; also, there is no point in
     * trying to obscure this data. */
    $ref = fyr_external_referrer();
    if (isset($ref))
        $form .= '<input type="hidden" name="fyr_extref" value="'.htmlentities($ref).'">';
    if ($cocode)
        $form .= '<input type="hidden" name="cocode" value="'.htmlentities($cocode).'">';

    if ($a_forward)
        $form .= '<input type="hidden" name="a" value="'.htmlentities($a_forward).'">';

    if ($options['extra_space']){
        $form .= '&nbsp;';
    }

    $form .= '<a href="/about-constituency" class="help-text">What postcode should I use?</a>';

    // End the form
    if ($options['inner_div']){
      $form .= '</div>';
    }
    $form .= '</form>';

    return $form;
}


if ($cobrand) {
    $cocode = cobrand_force_default_cocode($cobrand, $cocode);
}

$pc = canonicalise_postcode(get_http_var('pc'));
fyr_rate_limit(array("postcode" => array($pc, "Postcode that's been typed in")));
if ($new_pc = validate_easily_mistyped_postcode($pc)) {
    header('Location: ' . url_new('', true, 'pc', $new_pc));
    exit;
}

// Redirect from parlparse person identifier
$person = get_http_var("person");
if ($person) {
    $ids = dadem_get_same_person($person);
    dadem_check_error($ids);
    // TODO: Fix up case when a person is a representative multiple times
    // (for now we just take the most recent made one, i.e. last in list)
    $id = $ids[count($ids)-1];
    header('Location: ' . url_new('write', false, 'fyr_extref', fyr_external_referrer(), 'cocode', get_http_var('cocode'), 'who', $id));
    exit;
}

// Pass on any representative type selection
$a_forward = get_http_var("a");
$forced_rep_type = false;
if ($cobrand) {
    $old_a_forward = $a_forward;
    $a_forward = cobrand_force_representative_type($cobrand, $cocode, $a_forward);
    if ($old_a_forward != $a_forward) {
        $forced_rep_type = true;
    }
}

// Validate postcode, and prepare appropriate page
if (isset($_GET['t']))
    $template = 'index-'.$_GET['t'];
else
    $template = "index-index";
$error_message = null;

if ($pc == '' && (array_key_exists('pc', $_GET) || array_key_exists('pc', $_POST))) {
   $error_message = cobrand_empty_postcode_message($cobrand, $cocode);
}

if ($pc) {
    /* Test for various special-case postcodes which lie outside the UK. Many
     * of these aren't valid UK postcode formats, so do a special-case test
     * here rather than passing them down to MaPit. See
     * http://en.wikipedia.org/wiki/Postcode#Overseas_Territories */
    $pc2 = preg_replace('/\\s+/', '', strtoupper($pc));
    $pc22 = substr($pc2, 0, 2); $pc23 = substr($pc2, 0, 3);
    $otmap = array(
            /* For our purposes, St Helena, Ascension, and Tristan da Cunha
               are all the same, though they are thousands of miles apart.... */
            'ASCN1ZZ' => 'sthelena',
            'BBND1ZZ' => 'biot',
            'BIQQ1ZZ' => 'antarctica',
            'FIQQ1ZZ' => 'falklands',
            'PCRN1ZZ' => 'pitcairn',
            'SIQQ1ZZ' => 'southgeorgia',
            'STHL1ZZ' => 'sthelena',
            'TDCU1ZZ' => 'sthelena',
            'TKCA1ZZ' => 'turksandcaicos',
        );
    $ot = null;
    if (array_key_exists($pc2, $otmap)) {
        $ot = $otmap[$pc2];
    } elseif ($pc22 == 'JE') {
        $ot = 'channel';
    } elseif ($pc22 == 'GY') {
        $ot = 'channel';
    } elseif ($pc22 == 'IM') {
        $ot = 'isleofman';
    }

    if (isset($ot)) {
        if (cobrand_other_postcodes($cobrand, $cocode, $pc, $a_forward)) {
            exit;
        }
        header("Location: about-elsewhere#$ot");
        exit;
    }

    $specialmap = array(
        'GIR0AA' => 'giro',
        'G1R0AA' => 'giro',
        'SANTA1' => 'santa',
        'XM45HQ' => 'santa',
    );
    $ft = null;
    if (array_key_exists($pc2, $specialmap)) {
        $ft = $specialmap[$pc2];
    } elseif ($pc22 == 'AM') {
        $ft = 'archers';
    } elseif ($pc22 == 'FX') {
        $ft = 'training';
    } elseif ($pc23 == 'RE1') {
        $ft = 'reddwarf';
    }

    if (isset($ft)) {
        if (cobrand_special_postcodes($cobrand, $cocode, $pc, $a_forward)){
            exit;
        }
        header("Location: about-special#$ft");
        exit;
    }

    $voting_areas = mapit_postcode($pc, array(), array(
        400 => MAPIT_BAD_POSTCODE,
        404 => MAPIT_POSTCODE_NOT_FOUND,
    ));

    if (!rabx_is_error($voting_areas)) {

        $va = array();
        foreach ($voting_areas['areas'] as $id => $arr) {
            $va[$arr['type']] = $id;
        }
        $voting_areas = $va;

        /*
         * Hook for cobrand to perform any extra checks */
        if (cobrand_check_areas($cobrand, $cocode, $voting_areas, $pc, $a_forward)){
            exit;
        }

        /* Possibly a deep link (from another site which knows its users'
         * postcodes) specifying one or more particular area types. */
        if ($area_types = fyr_parse_area_type_list($a_forward)) {
            /* At this point we need to check whether we have only one
             * representative. If so grab their ID and redirect through to
             * write.php. Otherwise punt to who.php with the appropriate a
             * parameter. */
            $id = null;

            $a = array();
            foreach (array_keys($area_types) as $t) {
                if (array_key_exists($t, $voting_areas))
                    array_push($a, $voting_areas[$t]);
            }
            $area_representatives = dadem_get_representatives($a);
            foreach (array_values($area_representatives) as $rr) {
                if (count($rr) > 1) {
                    $id = null;
                    break;
                } else if (count($rr) == 1) {
                    if ($id) {
                        $id = null;
                        break;
                    } else
                        $id = $rr[0];
                }
            }

            if ($id)
                /* Single representative */
                header('Location: ' . url_new('write', true, 'a', null, 'who', $id, 'fyr_extref', fyr_external_referrer(), 'cocode', get_http_var('cocode')));
            else
                /* Several */
                header('Location: ' . url_new('who', true, 'a', implode(',', array_keys($area_types)), 'fyr_extref', fyr_external_referrer(), 'cocode', get_http_var('cocode')));
        } else {
            header('Location: ' . url_new('who', true, 'a', null, 'fyr_extref', fyr_external_referrer(), 'cocode', get_http_var('cocode')));
        }
        exit;
    }

    if ($voting_areas->code == MAPIT_BAD_POSTCODE) {
        $error_message = cobrand_bad_postcode_message($cobrand, $cocode);
        if (!$error_message) {
            $error_message = "Sorry, we need your complete UK postcode to identify your elected representatives.";
        }
        $template = "index-advice";
    }
    else if ($voting_areas->code == MAPIT_POSTCODE_NOT_FOUND) {
        $error_message = cobrand_postcode_not_found_message($cobrand, $cocode);
        if (!$error_message) {
            $error_message = "We’re not quite sure why, but we can’t seem to recognise your postcode.";
        }
        $template = "index-advice";
    }
    else {
        template_show_error($voting_areas->text);
    }
}

// Work out what representative types to show

$title = '';
if ($cobrand){
    $title = cobrand_step_title($cobrand, 1);
}
if ($title == ''){
    $title = "Email your Councillor, MP, MEP, MSP or Welsh, NI, or London Assembly Member for free";
}

$blurb_top = '<h2 id="title">Write to your politicians, national or local, for free.</h2><p>Over 200,000 messages sent last year.</p>';

$fyr_all_url = null;
$area_types = null;
if ($a_forward) {
    # Subset of representatives
    $area_types = fyr_parse_area_type_list($a_forward);
    if ($area_types) {
        $area_type_desc = fyr_describe_area_type_list($area_types);
        if (!$forced_rep_type) {
            $fyr_all_url = htmlspecialchars(url_new('', false,
                        'pc', get_http_var('pc'),
                        'fyr_extref', fyr_external_referrer(),
                        'cocode', get_http_var('cocode')));
        }
        $blurb_top = "<h2>Write to your $area_type_desc</h2><p>Over 200,000 messages sent last year.</p>";
    }
}

if ($template != 'index-advice') {
    header('Cache-Control: max-age=3600');
}

$options = cobrand_postcode_form_options($cobrand);
$form = postcode_form($pc, $cobrand, $cocode, $a_forward, $error_message, $options);

$num_messages = null;
if ($cobrand && file_exists("../../data/cobrand.csv")) {
    if (($handle = fopen("../../data/cobrand.csv", "r")) !== false) {
        while (($row = fgetcsv($handle)) !== false) {
            if ($row[0] === $cobrand) {
                $num_messages = $row[1];
                break;
            }
        }
        fclose($handle);
    }
}

// Display page
template_draw($template, array(
        'body_id' => 'home',
        "title" => $title,
        "blurb-top" => $blurb_top,
        "form" => $form,
        "error" => $error_message,
        "all_url" => $fyr_all_url,
        "cobrand" => $cobrand,
        "num_messages" => $num_messages,
        "template" => $template,
        "host" => fyr_get_host()
    ));

