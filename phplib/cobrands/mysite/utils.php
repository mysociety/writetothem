<?php
/*
 * Example cobrand for the functions in cobrand.php
 * $Id: utils.php,v 1.2 2009-10-05 15:33:35 louise Exp $
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

}

?>
