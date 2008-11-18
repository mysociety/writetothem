<?php
/*
 * Cobranding.
 * 
 * Copyright (c) 2008 UK Citizens Online Democracy. All rights reserved.
 * Email: francis@mysociety.org; WWW: http://www.mysociety.org
 *
 * $Id: cobrand.php,v 1.3 2008-11-18 17:40:51 francis Exp $
 * 
 */

// List of subdomains of WriteToThem which are cobrands.
function cobrand_allowed() {
    return array('cheltenham'=>1, 'animalaid'=>1, 'freeourbills'=>1);
}

// To change look and feel, make new files in templates/. Look at cheltenham
// for examples. Also need to make file like web/cheltenham.css.

// Bullet points / tips to put at the top of a letter.
function cobrand_get_letter_help($cobrand, $fyr_values) {
    $cobrand_letter_help = false;
    if ($cobrand == 'animalaid' && $fyr_values['cocode']) {
        $cobrand_letter_help = file_get_contents("http://www.animalaiduk.com/functions/custom/action_snippet.php?id=" . $fyr_values['cocode']);
        $cobrand_letter_help = str_replace('<h1>', '<h2>', $cobrand_letter_help);
        $cobrand_letter_help = str_replace('</h1>', '</h2>', $cobrand_letter_help);
    } elseif ($cobrand == 'freeourbills') {
        # First one was really: $fyr_values['cocode'] == 'email3'
        # But we make it the default also for now, so nothing to check.
        $cobrand_letter_help = file_get_contents("http://www.theyworkforyou.com/freeourbills/edm?wtt=1&pc=" . urlencode($fyr_values['pc']));
    }
    return $cobrand_letter_help;
}

// Action to perform after the letter has been sent.
// Return true if you've rendered a template so another doesn't need rendering. 
// Exit if you've done a redirect with header().
// Return false for default behaviour.
function cobrand_post_letter_send($values) {
    if ($values['cobrand'] == 'animalaid') {
        header("Location: http://www.animalaiduk.com/h/f/ACTIVE/blog//1//?id=".$values['cocode']);
        exit;
    }
    if ($values['cobrand'] == 'freeourbills') {
        header("Location: http://www.theyworkforyou.com/freeourbills/doshare.php?letterthanks=1");
        exit;
    }
    return false;
}

// On front page, force a particular campaign code as default for site (either
// forced value, or if another code isn't set).
function cobrand_force_default_cocode($cobrand, $cocode) {
    if ($cobrand == 'freeourbills' && !$cocode) {
        $cocode = "email3";
    }
    return $cocode;
}

// Force setting the representative type or types to a certain type, mainly for
// when they land on the front page. Can set per cobrand or cocode.
// $type, and your return value, are the options as described for the 'a'
// parameter in http://www.writetothem.com/about-linktous
// $type input is the value set with the 'a' URL parameter, if there is one.
function cobrand_force_representative_type($cobrand, $cocode, $type) {
    if ($cobrand == 'freeourbills') {
        if ($cocode && $cocode == 'email3') {
            $type = 'westminstermp';
        }
    }
    return $type;
}



