<?
/*
 * about.php:
 * Wrapper for displaying UCL thanks page.
 *
 */

require_once "../phplib/fyr.php";

// UCL A/B Testing
require_once "ucl_ab_test.php";
$UCLTest = new UCLTest;
$UCLTest->record_survey_submitted();

$values = array();

template_draw('ucl-thanks', $values);