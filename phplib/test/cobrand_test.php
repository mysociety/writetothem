<?php
/*
 * SimpleTest tests for the functions in cobrand.php
 * $Id: cobrand_test.php,v 1.3 2009-09-29 15:04:46 louise Exp $
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
 
} 

$test = new CobrandTest;
$reporter = new TextReporter;
exit ($test->run($reporter) ? 0 : 1);
?>
