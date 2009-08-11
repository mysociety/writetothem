<?php
/*
 * Cobranding.
 * 
 * Copyright (c) 2008 UK Citizens Online Democracy. All rights reserved.
 * Email: francis@mysociety.org; WWW: http://www.mysociety.org
 *
 * $Id: cobrand.php,v 1.6 2009-08-11 16:02:11 louise Exp $
 * 
 */

// List of subdomains of WriteToThem which are cobrands.
function cobrand_allowed() {
    $allowed_cobrands_string = OPTION_ALLOWED_COBRANDS;
    $allowed_cobrands_list = explode('|', $allowed_cobrands_string);
    $allowed_cobrands = array();
    foreach ($allowed_cobrands_list as $i => $cobrand) {
       $allowed_cobrands[$cobrand] = 1;
    }
    return $allowed_cobrands;
}

// To change look and feel, make new files in templates/. Look at cheltenham
// for examples. Also need to make file like web/cheltenham.css. To change the 
// behaviour of a cobranded site, add a function to phplib/cobrands/[cobrand name]/utils.php
// and add a hook function with default behaviour here. 

// Include any cobrand-specific code
function include_cobrand($cobrand){
  $dir = dirname(__FILE__);
  if (file_exists("$dir/cobrands/$cobrand/utils.php")){
      include_once("$dir/cobrands/$cobrand/utils.php");
  }
}

// Bullet points / tips to put at the top of a letter.
function cobrand_get_letter_help($cobrand, $fyr_values) {
    include_cobrand($cobrand);
    if (function_exists('get_letter_help')){
        return get_letter_help($fyr_values);
    }
    return false;
}

// Action to perform after the letter has been sent.
// Return true if you've rendered a template so another doesn't need rendering. 
// Exit if you've done a redirect with header().
// Return false for default behaviour.
function cobrand_post_letter_send($values) {
    $cobrand = $values['cobrand']; 
    include_cobrand($cobrand);
    if (function_exists('post_letter_send')){
        return post_letter_send($values);
    }
    return false;
}

// On front page, force a particular campaign code as default for site (either
// forced value, or if another code isn't set).
function cobrand_force_default_cocode($cobrand, $cocode) {
    include_cobrand($cobrand);
    if (function_exists('force_default_cocode')){
        return force_default_cocode($cocode);
    }
    return $cocode;
}

// Force setting the representative type or types to a certain type, mainly for
// when they land on the front page. Can set per cobrand or cocode.
// $type, and your return value, are the options as described for the 'a'
// parameter in http://www.writetothem.com/about-linktous
// $type input is the value set with the 'a' URL parameter, if there is one.
function cobrand_force_representative_type($cobrand, $cocode, $type) {
    include_cobrand($cobrand);
    if (function_exists('force_representative_type')){
        return force_representative_type($cocode, $type);
    }
    return $type;
}


