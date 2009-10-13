<?php
/*
 * Cobranding.
 * 
 * Copyright (c) 2008 UK Citizens Online Democracy. All rights reserved.
 * Email: francis@mysociety.org; WWW: http://www.mysociety.org
 *
 * $Id: cobrand.php,v 1.16 2009-10-13 13:23:04 louise Exp $
 * 
 */

$handles = array();

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
// for examples. Also need to make file like web/cobrands/cheltenham/cheltenham.css. To change the 
// behaviour of a cobranded site, add a function to phplib/cobrands/[cobrand name]/utils.php
// and add a hook function with default behaviour here. 

// Include any cobrand-specific code
function cobrand_handle($cobrand){
  global $handles;
  $dir = dirname(__FILE__);
  if (array_key_exists($cobrand, $handles)){
    return $handles[$cobrand];
  }
  if (file_exists("$dir/cobrands/$cobrand/utils.php")){
      include_once("$dir/cobrands/$cobrand/utils.php");
      $classname = ucwords($cobrand);
      $handle = call_user_func( array($classname, 'factory'));      
      $handles[$cobrand] = $handle;
  }else{
      $handles[$cobrand] = null;
  }
  return $handles[$cobrand];
}

// Bullet points / tips to put at the top of a letter.
function cobrand_get_letter_help($cobrand, $fyr_values) {
    $cobrand_handle = cobrand_handle($cobrand);
    if ($cobrand_handle && method_exists($cobrand_handle, 'get_letter_help')){
        return $cobrand_handle->get_letter_help($fyr_values);
    }
    return false;
}

// Title for the (1-based) step of the process
function cobrand_step_title($cobrand, $step) {
    $cobrand_handle = cobrand_handle($cobrand);
    if ($cobrand_handle && method_exists($cobrand_handle, 'step_title')){
        return $cobrand_handle->step_title($step);
    }
    return '';
}

// Action to perform after the letter has been sent.
// Return true if you've rendered a template so another doesn't need rendering. 
// Exit if you've done a redirect with header().
// Return false for default behaviour.
function cobrand_post_letter_send($values) {
    $cobrand = $values['cobrand']; 
    $cobrand_handle = cobrand_handle($cobrand);
    if ($cobrand_handle && method_exists($cobrand_handle, 'post_letter_send')){
        return $cobrand_handle->post_letter_send($values);
    }
    return false;
}

// On front page, force a particular campaign code as default for site (either
// forced value, or if another code isn't set).
function cobrand_force_default_cocode($cobrand, $cocode) {
    $cobrand_handle = cobrand_handle($cobrand);
    if ($cobrand_handle && method_exists($cobrand_handle, 'force_default_cocode')){
        return $cobrand_handle->force_default_cocode($cocode);
    }
    return $cocode;
}

// Force setting the representative type or types to a certain type, mainly for
// when they land on the front page. Can set per cobrand or cocode.
// $type, and your return value, are the options as described for the 'a'
// parameter in http://www.writetothem.com/about-linktous
// $type input is the value set with the 'a' URL parameter, if there is one.
function cobrand_force_representative_type($cobrand, $cocode, $type) {
    $cobrand_handle = cobrand_handle($cobrand);
    if ($cobrand_handle && method_exists($cobrand_handle, 'force_representative_type')){
        return $cobrand_handle->force_representative_type($cocode, $type);
    }
    return $type;
}

// Return any HTML headers to be used in the cobranded site
function cobrand_headers($cobrand, $template) {
  if ($cobrand) {
    $cobrand_handle = cobrand_handle($cobrand);
    if ($cobrand_handle && method_exists($cobrand_handle, 'headers')) {
      return $cobrand_handle->headers($template);
    }
    return '';
  }
}

// Return a boolean indicating whether the survey should be displayed
function cobrand_display_survey($cobrand) {
  if ($cobrand) {
    $cobrand_handle = cobrand_handle($cobrand);
    if ($cobrand_handle && method_exists($cobrand_handle, 'display_survey')) {
      return $cobrand_handle->display_survey();
    }
  }
  return true;
}

// Return a boolean indicating whether the spell checker should be displayed
function cobrand_display_spellchecker($cobrand) {
  if ($cobrand) {
    $cobrand_handle = cobrand_handle($cobrand);
    if ($cobrand_handle && method_exists($cobrand_handle, 'display_spellchecker')) {
      return $cobrand_handle->display_spellchecker();
    }
  }
  return true;
}

// Return a boolean indicating whether the link allowing users to submit
// councillor corrections should be displayed
function cobrand_display_councillor_correction_link($cobrand) {
  if ($cobrand) {
    $cobrand_handle = cobrand_handle($cobrand);
    if ($cobrand_handle && method_exists($cobrand_handle, 'display_councillor_correction_link')) {
      return $cobrand_handle->display_councillor_correction_link();
    }
  }
  return true;
}

// Generate a url for writing to all reps of a given type for a postcode
function cobrand_write_all_url($cobrand, $va_type, $fyr_postcode){
  if ($cobrand){
    $cobrand_handle = cobrand_handle($cobrand);
    if ($cobrand_handle && method_exists($cobrand_handle, 'write_all_url')){
        return $cobrand_handle->write_all_url($va_type, $fyr_postcode);
    }
  }
 return general_write_all_url($va_type, $fyr_postcode);
}

// Generate a url for writing to a specific rep 
function cobrand_write_rep_url($cobrand, $va_type, $rep_specificid, $fyr_postcode){
  if ($cobrand){
    $cobrand_handle = cobrand_handle($cobrand);
    if ($cobrand_handle && method_exists($cobrand_handle, 'write_rep_url')){
        return $cobrand_handle->write_rep_url($va_type, $rep_specificid, $fyr_postcode);
    }
  }
 return general_write_rep_url($va_type, $rep_specificid, $fyr_postcode);
}


// Return the HTML for a cobrand page
function cobrand_page($cobrand, $page) {
  if ($cobrand){
    $cobrand_handle = cobrand_handle($cobrand);
    if ($cobrand_handle && method_exists($cobrand_handle, 'page')){
        return $cobrand_handle->page($page);
    }
  }
 return '';

}
