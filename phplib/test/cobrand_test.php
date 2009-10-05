<?php
/*
 * SimpleTest tests for the functions in cobrand.php
 * $Id: cobrand_test.php,v 1.4 2009-10-05 15:33:35 louise Exp $
 */
error_reporting (E_ALL ^ E_NOTICE);
ini_set("display_errors", 1);
include_once dirname(__FILE__) . '/../../conf/general'; 
include_once dirname(__FILE__) . '/../cobrand.new.php';
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
    print "display_link is $display_link";
    $this->assertEqual(true, $display_link, 'Should return true if the cobrand does not define a display_councillor_correction_link function');
  
  }
 
} 

$test = new CobrandTest;
$reporter = new TextReporter;
exit ($test->run($reporter) ? 0 : 1);
?>
