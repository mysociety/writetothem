<?php
/*
 * Example cobrand for the functions in cobrand.php
 * $Id: utils.php,v 1.6 2009-10-22 09:38:24 louise Exp $
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

    function url($url) {
         return 'rewritten_url';
    }
}

?>
