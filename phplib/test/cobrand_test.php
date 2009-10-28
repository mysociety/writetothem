<?php
/*
 * SimpleTest tests for the functions in cobrand.php
 * $Id: cobrand_test.php,v 1.10 2009-10-28 17:51:28 louise Exp $
 */
error_reporting (E_ALL ^ E_NOTICE);
ini_set("display_errors", 1);
include_once dirname(__FILE__) . '/../../conf/general'; 
include_once dirname(__FILE__) . '/../cobrand.php';
include_once 'simpletest/unit_tester.php';
include_once 'simpletest/reporter.php';

class CobrandTest extends UnitTestCase{
  
    function test_cobrand_headers(){
        $headers = cobrand_headers('nosite', 'common_header');
        $this->assertEqual('', $headers, 'Should return an empty string for common_header with no cobrand');

        $headers = cobrand_headers('mysite', 'common_header');
        $this->assertEqual('My-Header: hello', $headers, 'Should return the value of the cobrand header function if one exists');
    }

    function test_display_councillor_correction_link() {
        $display_link = cobrand_display_councillor_correction_link('');
        $this->assertEqual(true, $display_link, 'Should return true if no cobrand is set');

        $display_link = cobrand_display_councillor_correction_link('mysite');
        $this->assertEqual(false, $display_link, 'Should return the value of the cobrand display_councillor_correction_link function if one exists');

        $display_link = cobrand_display_councillor_correction_link('nosite');
        $this->assertEqual(true, $display_link, 'Should return true if the cobrand does not define a display_councillor_correction_link function');

    }

    function test_display_spellchecker() {

        $display_spell = cobrand_display_spellchecker('');
        $this->assertEqual(true, $display_spell, 'Should return true if no cobrand is set');

        $display_spell = cobrand_display_spellchecker('mysite');
        $this->assertEqual(false, $display_spell, 'Should return the value of the cobrand display_spellchecker function if one exists');

        $display_spell = cobrand_display_spellchecker('nosite');
        $this->assertEqual(true, $display_spell, 'Should return true if the cobrand does not define a display_spellcheck function');

    }

    function test_display_survey() {

        $display_survey = cobrand_display_survey('');
        $this->assertEqual(1, $display_survey, 'Should return true if no cobrand is set');

        $display_survey = cobrand_display_survey('mysite');
        $this->assertEqual(false, $display_survey, 'Should return the value of the cobrand display_survey function if one exists');

        $display_survey = cobrand_display_survey('nosite');
        $this->assertEqual(true, $display_survey, 'Should return true if the cobrand does not define a display_survey function');
   
    }

    function test_cobrand_page() {

        $text = cobrand_page('mysite', 'existing_page');
        $this->assertEqual('page content', $text, 'Should return the content of the cobrand page for pages that exist');

        $text = cobrand_page('mysite', 'non_existing_page');
        $this->assertEqual('', $text, 'Should return an empty string for pages that do not exist');

        $text = cobrand_page('nosite', 'existing_page');
        $this->assertEqual('', $text, 'Should return an empty string for cobrands that do not define a page function');

    }

    function test_url() {

        $url = cobrand_url('mysite', 'url');
        $this->assertEqual('rewritten_url', $url, 'Should return the url as rewritten by the cobrand if the cobrand defines a url function');

        $url = cobrand_url('nosite', 'url');
        $this->assertEqual('url', $url, 'Should return the url passed if there is no cobrand url function');

    }

    function test_enter_postcode_message() {

        $message = cobrand_enter_postcode_message('mysite', 'cocode');
        $this->assertEqual('My message', $message, 'Should return the message returned by the cobrand if the cobrand defines an enter_postcode_message function');
        
        $message = cobrand_enter_postcode_message('nosite', 'cocode');
        $this->assertEqual(false, $message, 'Should return false if there is no cobrand enter_postcode_message function');

    }


    function test_cocode_allowed() {

        $allowed = cobrand_cocode_allowed('mysite', 'cocode');
        $this->assertEqual(false, $allowed, 'Should return the value returned by the cobrand if the cobrand defines a cocode_allowed function');

        $allowed = cobrand_cocode_allowed('nosite', 'cocode');
        $this->assertEqual(true, $allowed, 'Should return true if there is no cobrand cocode_allowed function');

    }


} 

$test = new CobrandTest;
$reporter = new TextReporter;
exit ($test->run($reporter) ? 0 : 1);
?>
