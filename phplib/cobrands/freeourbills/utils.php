<?php
/*
 * Functions for Free Our Bills cobrand
 * For function notes see cobrand.php
 * 
 * Copyright (c) 2009 UK Citizens Online Democracy. All rights reserved.
 * Email: louise@mysociety.org; WWW: http://www.mysociety.org
 *
 * $Id: utils.php,v 1.2 2009-10-05 15:33:34 louise Exp $
 * 
 */

Class Freeourbills {

  function factory() {
    return new Freeourbills();
  }


  function get_letter_help($fyr_values) {
    # First one was really: $fyr_values['cocode'] == 'email3'
    # But we make it the default also for now, so nothing to check.
    $letter_help = file_get_contents("http://www.theyworkforyou.com/freeourbills/edm?wtt=1&pc=" . urlencode($fyr_values['pc']));
    return $letter_help;
  }

  function post_letter_send($values) {
    header("Location: http://www.theyworkforyou.com/freeourbills/doshare.php?letterthanks=1");
    exit;
  }

  function force_default_cocode($cocode) {
      if (!$cocode) {
        $cocode = "email3";
      }
    return $cocode;
  }

  function force_representative_type($cocode, $type) {
      if ($cocode && $cocode == 'email3') {
          $type = 'westminstermp';
      }
      return $type;
  }
}
?>
