<?php
/*
 * Example cobrand for the functions in cobrand.php
 * $Id: utils.php,v 1.5 2009-10-07 13:28:27 louise Exp $
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

  function page($page) {
    if ($page == 'existing_page') {
      return 'page content';
    }
  }
}

?>
