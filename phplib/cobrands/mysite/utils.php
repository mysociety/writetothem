<?php
/*
 * Example cobrand for the functions in cobrand.php
 * $Id: utils.php,v 1.4 2009-10-05 16:57:48 louise Exp $
 */

class Mysite {
  
  function factory() {
    return new Mysite();
  }

  function headers($template) {
    return 'My-Header: hello';
  }

  function display_councillor_correction_link() {
    return false;  
  }

  function display_spellchecker() {
    return false;
  }

  function display_survey() { 
    return false;
  }   
}

?>
