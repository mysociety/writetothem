<?php
/*
 * Functions for FOI Order 2009 cobrand
 * For function notes see cobrand.php
 * 
 * Copyright (c) 2009 UK Citizens Online Democracy. All rights reserved.
 * Email: louise@mysociety.org; WWW: http://www.mysociety.org
 *
 * $Id: utils.php,v 1.2 2009-10-05 15:33:34 louise Exp $
 * 
 */

Class Foiorder2009 {

  function factory() {
    return new Foiorder2009();
  }

  function get_letter_help($fyr_values) {
    $letter_help = file_get_contents("http://www.theyworkforyou.com/foiorder2009/wtt.html");
    return $letter_help;
  }

  function force_default_cocode($cocode) {
      if (!$cocode) {
        $cocode = "email1";
      }
      return $cocode;
  }

  function force_representative_type($cocode, $type) {
      if ($cocode && $cocode == 'email1') {
          $type = 'westminstermp';
      }
      return $type;
  }
}
?>
