<?php
/*
 * SimpleTest tests for the functions in cobrand.php
 * $Id: cobrand_test.php,v 1.1 2009-09-29 14:14:53 louise Exp $
 */
error_reporting (E_ALL);
ini_set("display_errors", 1);
include_once dirname(__FILE__) . '/../../conf/general'; 
include_once dirname(__FILE__) . '/../fyr.php';
include_once 'simpletest/unit_tester.php';
include_once 'simpletest/reporter.php';

class CobrandTest extends UnitTestCase{
  
  function test_cobrand_headers(){
    $headers = cobrand_headers('nosite', 'common_header');
    $this->assertEqual('', $headers, 'Should return an empty string for common_header with no cobrand');
  }
 
} 

$test = new CobrandTest();
$test->run(new TextReporter);
?>
