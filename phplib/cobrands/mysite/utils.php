<?php
/*
 * Example cobrand for the functions in cobrand.php
 * $Id: utils.php,v 1.7 2009-10-28 17:51:28 louise Exp $
 */

class Mysite {
  
    function factory() {
        return new Mysite();
    }

    function headers($template) {
         return 'My-Header: hello';
    }
 
    function enter_postcode_message() {
         return 'My message';
    }

    function cocode_allowed() {
         return false;
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
