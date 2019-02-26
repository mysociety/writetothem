<?php

# To do WTT specific things when calling MapIt.

require_once "../commonlib/phplib/mapit.php";

function mapit_postcode($pc, $opts = array(), $errors = array()) {
    if (OPTION_MAPIT_GENERATION) {
        $opts['generation'] = OPTION_MAPIT_GENERATION;
    }
    return mapit_call('postcode', $pc, $opts, $errors);
}

function mapit_areas($areas, $opts = array()) {
    if (OPTION_MAPIT_GENERATION) {
        $opts['generation'] = OPTION_MAPIT_GENERATION;
    }
    return mapit_call('areas', $areas, $opts);
}
