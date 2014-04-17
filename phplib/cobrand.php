<?php
/*
 * Cobranding.
 * 
 * Copyright (c) 2008 UK Citizens Online Democracy. All rights reserved.
 * Email: francis@mysociety.org; WWW: http://www.mysociety.org
 *
 * $Id: cobrand.php,v 1.28 2009-11-05 11:19:22 louise Exp $
 * 
 */

$handles = array();

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

// Return the message that asks the user to enter their postcode
function cobrand_enter_postcode_message($cobrand, $cocode) {
    if (!$cobrand) {
        return false;
    } 
    $cobrand_handle = cobrand_handle($cobrand);
    if ($cobrand_handle && method_exists($cobrand_handle, 'enter_postcode_message')){
        return $cobrand_handle->enter_postcode_message($cocode);
    }
    return false;

} 

//Return the message for when an empty postcode is entered
function cobrand_empty_postcode_message($cobrand, $cocode) {
    if ($cobrand) {
        $cobrand_handle = cobrand_handle($cobrand);
        if ($cobrand_handle && method_exists($cobrand_handle, 'empty_postcode_message')){
            return $cobrand_handle->empty_postcode_message($cocode);
        }
    }
    return false;
}

// Return the message for when a bad postcode is entered
function cobrand_bad_postcode_message($cobrand, $cocode) {
    if ($cobrand) {
        $cobrand_handle = cobrand_handle($cobrand);
        if ($cobrand_handle && method_exists($cobrand_handle, 'bad_postcode_message')){
            return $cobrand_handle->bad_postcode_message($cocode);
        }
    }
    return false;
}

// Return the message for when a postcode can't be found
function cobrand_postcode_not_found_message($cobrand, $cocode) {
    if ($cobrand) {
        $cobrand_handle = cobrand_handle($cobrand);
        if ($cobrand_handle && method_exists($cobrand_handle, 'postcode_not_found_message')){
            return $cobrand_handle->postcode_not_found_message($cocode);
        }
    }
    return false;
}

// Return the message to be used for bad contact information
function cobrand_bad_contact_error_msg($cobrand, $eb_area_info) {
    if ($cobrand) {
        $cobrand_handle = cobrand_handle($cobrand);
        if ($cobrand_handle && method_exists($cobrand_handle, 'bad_contact_error_msg')){
            return $cobrand_handle->bad_contact_error_msg($eb_area_info);
        }
    }
    return false;
} 

// Return the message to be used for people who don't want to be contacted
// through the service
function cobrand_shame_error_msg($cobrand, $fyr_voting_area, $fyr_representative) {
    if ($cobrand) {
        $cobrand_handle = cobrand_handle($cobrand);
        if ($cobrand_handle && method_exists($cobrand_handle, 'shame_error_msg')){
            return $cobrand_handle->shame_error_msg($fyr_voting_area, $fyr_representative);
        }
    }
    return false;
}

// Return the message to be used for an unexpected error
function cobrand_generic_error_message($cobrand, $cocode, $page_name) {
    if ($cobrand) {
        $cobrand_handle = cobrand_handle($cobrand);
        if ($cobrand_handle && method_exists($cobrand_handle, 'generic_error_message')){
            return $cobrand_handle->generic_error_message($cocode, $page_name);
        }
    }
    return false;
}

// Return the short error strings to be used when errors are returned writing to multiple reps
function cobrand_message_sending_errors($cobrand) {
    if ($cobrand) {
        $cobrand_handle = cobrand_handle($cobrand);
        if ($cobrand_handle && method_exists($cobrand_handle, 'message_sending_errors')){
            return $cobrand_handle->message_sending_errors();
        }
    }
    return false;
}

// Return the link to write to all representatives of a given type
function cobrand_write_all_link($cobrand, $url, $rep_desc_plural, $cocode) {
    if ($cobrand) {
        $cobrand_handle = cobrand_handle($cobrand);
        if ($cobrand_handle && method_exists($cobrand_handle, 'write_all_link')){
            return $cobrand_handle->write_all_link($url, $rep_desc_plural, $cocode);
        }
    }
    return false;
}

// Return the text for the preview button
function cobrand_preview_button_text($cobrand) {
    if ($cobrand) {
        $cobrand_handle = cobrand_handle($cobrand);
        if ($cobrand_handle && method_exists($cobrand_handle, 'preview_button_text')){
            return $cobrand_handle->preview_button_text();
        }
    }
    return false;
}

// Return the text to display when a user accesses a token-based URL without a token
function cobrand_missing_token_message($cobrand) {
    if ($cobrand) {   
        $cobrand_handle = cobrand_handle($cobrand);
        if ($cobrand_handle && method_exists($cobrand_handle, 'missing_token_message')){
            return $cobrand_handle->missing_token_message();
        }
    }
    return false;
}

// Return the text to display when a user accesses an answer URL without an answer
function cobrand_missing_answer_message($cobrand) {
    if ($cobrand) {
        $cobrand_handle = cobrand_handle($cobrand);
        if ($cobrand_handle && method_exists($cobrand_handle, 'missing_answer_message')){
            return $cobrand_handle->missing_answer_message();
        }
    }
    return false;

}

// Return the text to display when a message can't be found for a token
function cobrand_unfound_token_message($cobrand) {
    if ($cobrand) {
        $cobrand_handle = cobrand_handle($cobrand);
        if ($cobrand_handle && method_exists($cobrand_handle, 'unfound_token_message')){
            return $cobrand_handle->unfound_token_message();
        }
    }
    return false;

}

// Return the text for previewing your message
function cobrand_preview_text($cobrand) {
    if ($cobrand) {
        $cobrand_handle = cobrand_handle($cobrand);
        if ($cobrand_handle && method_exists($cobrand_handle, 'preview_text')){
            return $cobrand_handle->preview_text();
        }
    }
    return false;
}

// Return a boolean indicating whether the cocode is allowed
function cobrand_cocode_allowed($cobrand, $cocode) {
    if ($cobrand) {
         $cobrand_handle = cobrand_handle($cobrand);
         if ($cobrand_handle && method_exists($cobrand_handle, 'cocode_allowed')){
             return $cobrand_handle->cocode_allowed($cocode);
         } 
    }
    return true;
}

// Return the column blurb for a set of representatives
function cobrand_col_blurb($cobrand, $va_type, $va_info, $eb_info, $rep_count, $rep_counts, $representatives, $va_salaried){
    if ($cobrand) {
         $cobrand_handle = cobrand_handle($cobrand);
         if ($cobrand_handle && method_exists($cobrand_handle, 'col_blurb')){
             return $cobrand_handle->col_blurb($va_type, $va_info, $eb_info, $rep_count, $rep_counts, $representatives, $va_salaried);
         }
    }
    return false;

}

// Message to show when there's an election
function cobrand_election_error_message($cobrand) {
    if ($cobrand) {
         $cobrand_handle = cobrand_handle($cobrand);
         if ($cobrand_handle && method_exists($cobrand_handle, 'election_error_message')){
             return $cobrand_handle->election_error_message();
         }
    }
    return false;
}

// Message to show when the user enters an invalid email address
function cobrand_invalid_email_message($cobrand) {
    if ($cobrand) {
         $cobrand_handle = cobrand_handle($cobrand);
         if ($cobrand_handle && method_exists($cobrand_handle, 'invalid_email_message')){
             return $cobrand_handle->invalid_email_message();
         }
    }
    return false;
}

// Message for mismatched email addresses
function cobrand_mismatched_emails_message($cobrand) {
    if ($cobrand) {
         $cobrand_handle = cobrand_handle($cobrand);
         if ($cobrand_handle && method_exists($cobrand_handle, 'mismatched_emails_message')){
             return $cobrand_handle->mismatched_emails_message();
         }
    }
    return false;

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

// Action to perform if someone enters a special (mostly fictional) postcode
// Return true if you've rendered a template so another template doesn't need rendering
// Exit if you've done a redirect with header()
// Return false for default behaviour
function cobrand_special_postcodes($cobrand, $cocode, $pc, $a) {
    if ($cobrand) {
        $cobrand_handle = cobrand_handle($cobrand);
        if ($cobrand_handle && method_exists($cobrand_handle, 'special_postcodes')){
             return $cobrand_handle->special_postcodes($cocode, $pc, $a);
        }

    }
    return false; 
}

// Action to perform if someone enters one of the outside UK special postcodes
// Return true if you've rendered a template so another template doesn't need rendering
// Exit if you've done a redirect with header()
// Return false for default behaviour
function cobrand_other_postcodes($cobrand, $cocode, $pc, $a) {
    if ($cobrand) {
        $cobrand_handle = cobrand_handle($cobrand);
        if ($cobrand_handle && method_exists($cobrand_handle, 'other_postcodes')){
             return $cobrand_handle->other_postcodes($cocode, $pc, $a);
        }

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
// parameter in https://www.writetothem.com/about-linktous
// $type input is the value set with the 'a' URL parameter, if there is one.
function cobrand_force_representative_type($cobrand, $cocode, $type) {
    $cobrand_handle = cobrand_handle($cobrand);
    if ($cobrand_handle && method_exists($cobrand_handle, 'force_representative_type')){
        return $cobrand_handle->force_representative_type($cocode, $type);
    }
    return $type;
}

// Perform any additional checks on the voting areas returned
// for a postcode.
// Return true if you've rendered a template so another doesn't need rendering.
// Exit if you've done a redirect with header().
function cobrand_check_areas($cobrand, $cocode, $voting_areas, $pc, $a) {
    if (! $cobrand) {
         return false;
    }
    $cobrand_handle = cobrand_handle($cobrand);
    if ($cobrand_handle && method_exists($cobrand_handle, 'check_areas')){
        return $cobrand_handle->check_areas($cocode, $voting_areas, $pc, $a);
    }
    return false;
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

// Generate a url for writing to all reps in the main app
function cobrand_main_write_url($cobrand, $fyr_postcode, $cocode, $fyr_extref) {
  if ($cobrand) {
    $cobrand_handle = cobrand_handle($cobrand);
    if ($cobrand_handle && method_exists($cobrand_handle, 'main_write_url')) {
      return $cobrand_handle->main_write_url($fyr_postcode, $cocode, $fyr_extref);
    }
  }
  return '';
}

// Give cobrands a hook to rewrite URLs
function cobrand_url($cobrand, $url, $cocode) {
  if ($cobrand) {
    $cobrand_handle = cobrand_handle($cobrand);
    if ($cobrand_handle && method_exists($cobrand_handle, 'url')) {
      return $cobrand_handle->url($url, $cocode);
    }
  }
  return $url;
}

// Return the rendering options for the postcode entry form
function cobrand_postcode_form_options($cobrand) {
  if ($cobrand){
    $cobrand_handle = cobrand_handle($cobrand);
    if ($cobrand_handle && method_exists($cobrand_handle, 'postcode_form_options')){
        return $cobrand_handle->postcode_form_options();
    }
  }
  return array(
               'inner_div'   => true, 
               'extra_space' => true, 
               'bold_labels' => true, 
               'show_errors' => false);
}

// Return the rendering options for lists of representatives
function cobrand_rep_list_options($cobrand) { 
  if ($cobrand){
    $cobrand_handle = cobrand_handle($cobrand);
    if ($cobrand_handle && method_exists($cobrand_handle, 'rep_list_options')){
        return $cobrand_handle->rep_list_options();
    }
  }
  return array('include_write_all' => false);
}

// Return the rendering options for message-writing form
function cobrand_write_form_options($cobrand) {
  if ($cobrand){
    $cobrand_handle = cobrand_handle($cobrand);
    if ($cobrand_handle && method_exists($cobrand_handle, 'write_form_options')){
        return $cobrand_handle->write_form_options();
    }
  }
  return array('table_layout'         => true, 
               'include_write_header' => true,
               'include_fao'          => false,  
               'renderer'             => new HTML_QuickForm_Renderer_mySociety());
}

// Return the rendering options for the message preview form
function cobrand_preview_form_options($cobrand) {
  if ($cobrand){
    $cobrand_handle = cobrand_handle($cobrand);
    if ($cobrand_handle && method_exists($cobrand_handle, 'preview_form_options')){
        return $cobrand_handle->preview_form_options();
    }
  }
  return array('inner_div' => true);

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
