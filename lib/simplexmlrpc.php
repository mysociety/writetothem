<?php
/*
 * simplexmlrpc.php:
 * Simple wrapper around XMLRPC calls.
 * 
 * Copyright (c) 2004 UK Citizens Online Democracy. All rights reserved.
 * Email: chris@mysociety.org; WWW: http://www.mysociety.org/
 *
 * $Id: simplexmlrpc.php,v 1.6 2004-10-14 14:14:24 chris Exp $
 * 
 */

require_once('XML/RPC.php');
include_once('utility.php');

$sxr_clients = array( );

/* sxr_call HOST PORT PATH METHOD PARAMS
 * Call, via the HTTP "proxy", HOST, PORT and PATH, the named METHOD, passing
 * it the given PARAMS, which should be a single value or an array of values.
 * Return whatever the method returns on success, or FALSE on failure. */
function sxr_call($host, $port, $path, $func, $params) {
    debug("SXR calling $func via http://$host:$port/$path");
    global $sxr_clients;
    $key = "$host:$port/$path";
    if (!array_key_exists($key, $sxr_clients))
        $sxr_clients[$key] = new XML_RPC_Client($path, $host, $port);

    $p = array();
    if (is_array($params)) {
        foreach ($params as $i) {
            array_push($p, XML_RPC_encode($i));
        }
    } else
        $p[0] = $params;

    $req = new XML_RPC_Message($func, $p);
    $resp = $sxr_clients[$key]->send($req);

    if ($resp->faultCode())
        return FALSE;
    else
        return XML_RPC_decode($resp->value());
}

/* sxr_call_idem HOST PORT PATH METHOD PARAMS
 * As for sxr_call; but assumes that the result of any given call with specific
 * parameters is unchanging, and so caches results from old queries. */
function sxr_call_idem($host, $port, $path, $func, $params) {
}

?>
