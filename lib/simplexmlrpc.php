<?php
/*
 * simplexmlrpc.php:
 * Simple wrapper around XMLRPC calls.
 * 
 * Copyright (c) 2004 UK Citizens Online Democracy. All rights reserved.
 * Email: chris@mysociety.org; WWW: http://www.mysociety.org/
 *
 * $Id: simplexmlrpc.php,v 1.3 2004-10-06 17:07:37 chris Exp $
 * 
 */

require_once('XML/RPC.php');

$sxr_clients = array( );

/* array_is_list ARRAY
 * Try to guess whether ARRAY is being used as a list or an associative array.
 * It's a mess, basically. */
function array_is_list($a) {
    $c = count($a);
    for ($i = 0; $i < $c; ++$i) {
        if (!array_key_exists($i, $a))
            return FALSE;
    }
    return TRUE;
}

/* sxr_marshall VAL
 * Take a PHP value VAL, and return an XML-RPC representation of it (built out
 * of XML_RPC objects. */
function sxr_marshall($val) {
    if (is_int($val))
        return new xmlrpcval($val, 'int');
    else if (is_bool($val))
        return new xmlrpcval($val, 'bool');
    else if (is_float($val))
        return new xmlrpcval($val, 'double');
    else if (is_string($val))
        return new xmlrpcval($val, 'string');
    else if (is_array($val)) {
        /* Now we're screwed. XMLRPC distinguishes between "structs", which
         * are associative arrays, and "arrays", which are lists. PHP doesn't.
         * So we have to guess. */
        if (array_is_list($val)) {
            $a = array();
            foreach ($val as $i) {
                array_push($a, sxr_marshall($i));
            }
            return new xmlrpcval($a, 'array');
        } else {
            $a = array();
            foreach ($val as $k => $v) {
                $a[$k] = sxr_marshall($v);
            }
            return new xmlrpcval($a, 'struct');
        }
    }
}

/* sxr_unmarshall VAL
 * Take an XML-RPC expression of a value VAL, and return its representation in
 * native PHP types. */
function sxr_unmarshall($val) {
    if ($val->kindOf() == 'scalar')
        return $val->scalarval();
    else if ($val->kindOf() == 'array') {
        $a = array();
        for ($i = 0; $i < $val->arraysize(); ++$i) {
            array_push($a, sxr_unmarshall($val->arraymem($i)));
        }
        return $a;
    } else if ($val->kindOf() == 'struct') {
        $a = array();
        while (list($k, $v) = $val->structeach()) {
            $a[$k] = sxr_unmarshall($v);
        }
        return $a;
    } else {
        exit('Bad kindOf "' . $val->kindOf() . '" in sxr_unmarshall'); /* why oh why isn't there a proper abort() or die()? */
    }
}

/* sxr_call HOST PORT PATH METHOD PARAMS
 * Call, via the HTTP "proxy", HOST, PORT and PATH, the named METHOD, passing
 * it the given PARAMS, which should be a single value or an array of values.
 * Return whatever the method returns on success, or FALSE on failure. */
function sxr_call($host, $port, $path, $func, $params) {
    global $sxr_clients;
    $key = "$host:$port/$path";
    if (!array_key_exists($key, $sxr_clients))
        $sxr_clients[$key] = new XML_RPC_Client($path, $host, $port);

    $p = array();
    foreach ($params as $i) {
        array_push($p, new XML_RPC_Value($i, 'int')); /* XXX fix! */
    }

    $req = new XML_RPC_Message($func, $p);

    $resp = $sxr_clients[$key]->send($req);

    if ($resp->faultCode())
        return FALSE;
    else
        return sxr_unmarshall($resp->value());
}

?>
