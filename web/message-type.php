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
if (!$who) {
    header('Location: /');
    exit;
}

$write_url = cobrand_url($cobrand, '/write', get_http_var('cocode'));

/* whitelist of parameters to forward to the write page */
$forwarded_param_names = array(
    'who', 'type', 'pc', 'cocode', 'fyr_extref',
    'writer_name', 'writer_address1', 'writer_address2',
    'writer_town', 'writer_county', 'writer_email', 'writer_phone',
);

$write_params = array();
foreach ($forwarded_param_names as $name) {
    $value = get_http_var($name);
    if ($value !== '') {
        $write_params[$name] = $value;
    }
}

/* If the incoming link already says what the message is about, skip this
 * screen and go straight to writing, taking the whitelisted parameters
 * (plus message_type) along. */
$message_type = get_http_var('message_type');
if (in_array($message_type, array('casework', 'campaigning', 'other'), true)) {
    $redirect_params = array_merge($write_params, array('message_type' => $message_type));
    header('Location: ' . $write_url . '?' . http_build_query($redirect_params));
    exit;
}

template_draw('message-type', array(
    'write_url' => $write_url,
    'write_params' => $write_params,
    'cobrand' => $cobrand,
    'host' => fyr_get_host(),
));
