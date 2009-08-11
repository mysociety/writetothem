<?php
/*
 * Functions for AnimalAid cobrand
 * For function notes see cobrand.php
 * 
 * Copyright (c) 2009 UK Citizens Online Democracy. All rights reserved.
 * Email: louise@mysociety.org; WWW: http://www.mysociety.org
 *
 * $Id: utils.php,v 1.1 2009-08-11 13:39:43 louise Exp $
 * 
 */
function get_letter_help($fyr_values) {
  $letter_help = false;
  if ($fyr_values['cocode']) {
      $letter_help = file_get_contents("http://www.animalaiduk.com/functions/custom/action_snippet.php?id=" . $fyr_values['cocode']);
      $letter_help = str_replace('<h1>', '<h2>', $letter_help);
      $letter_help = str_replace('</h1>', '</h2>', $letter_help);
  }
  return $letter_help;
}

function post_letter_send($values) {
  header("Location: http://www.animalaiduk.com/h/f/ACTIVE/blog//1//?id=".$values['cocode']);
  exit;
}

?>