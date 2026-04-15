<?php
/*
 * message-type.php:
 * Page to ask what type of message they want to send, shown after
 * selecting a representative but before writing the message.
 *
 * Copyright (c) 2026 UK Citizens Online Democracy. All rights reserved.
 * WWW: http://www.mysociety.org
 *
 */

require_once "../phplib/fyr.php";
require_once "../commonlib/phplib/utility.php";

$who = get_http_var('who');
$pc = get_http_var('pc');
$type = get_http_var('type');
$fyr_extref = get_http_var('fyr_extref');
$cocode = get_http_var('cocode');

if (!$who) {
    header('Location: /');
    exit;
}

// Build the URL to the write page, preserving all parameters
$write_params = array('who' => $who, 'pc' => $pc);
if ($type) $write_params['type'] = $type;
if ($fyr_extref) $write_params['fyr_extref'] = $fyr_extref;
if ($cocode) $write_params['cocode'] = $cocode;

$write_url = cobrand_url($cobrand, '/write', $cocode);

template_draw('message-type', array(
    'write_url' => $write_url,
    'write_params' => $write_params,
    'who' => $who,
    'pc' => $pc,
    'type' => $type,
    'fyr_extref' => $fyr_extref,
    'cocode' => $cocode,
    'cobrand' => $cobrand,
    'host' => fyr_get_host(),
));
