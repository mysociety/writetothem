<?php
/*
 * Example cobrand for the functions in cobrand.php
 * $Id: utils.php,v 1.3 2009-10-05 16:18:01 louise Exp $
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
   
}

?>
