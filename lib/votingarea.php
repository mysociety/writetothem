<?php
/*
 * votingarea.php:
 * Stuff about voting and administrative areas.
 * 
 * Copyright (c) 2004 Chris Lightfoot. All rights reserved.
 * Email: chris@ex-parrot.com; WWW: http://www.ex-parrot.com/~chris/
 *
 * $Id: votingarea.php,v 1.1 2004-10-04 17:06:05 chris Exp $
 * 
 */

/* Manifest constants for different types of areas. Larger numbers indicate
 * larger areas and "more important" bodies. */
define('VA_LBO', 101);  /* London Borough */
define('VA_LBW', 102);  /* ... ward */

define('VA_GLA', 201);  /* Greater London Assembly */
define('VA_LAC', 202);  /* London constituency */

define('VA_CTY', 301);  /* County */
define('VA_CED', 302);  /* ... electoral division */

define('VA_DIS', 401);  /* District */
define('VA_DIW', 402);  /* ... ward */

define('VA_UTA', 501);  /* Unitary authority */
define('VA_UTE', 502);  /* ... electoral division */
define('VA_UTW', 503);  /* ... ward */

define('VA_MTD', 601);  /* Metropolitan district */
define('VA_MTW', 602);  /* ... ward */

define('VA_SPA', 701);  /* Scottish Parliament */
define('VA_SPE', 702);  /* ... electoral region */
define('VA_SPC', 703);  /* ... constituency */

define('VA_WAS', 701);  /* Welsh Assembly */
define('VA_WAE', 702);  /* ... electoral region */
define('VA_WAC', 702);  /* ... constituency */

define('VA_WMP', 801);  /* Westminster Parliament */
define('VA_WMC', 802);  /* ... constituency */

define('VA_EUP', 901);  /* European Parliament */
define('VA_EUR', 902);  /* ... region */

/* va_inside
 * For any VA_ constant which refers to a voting area which is inside an
 * administrative area, there is an entry in this array saying which type of
 * area it's inside. */
$va_inside = array(
        VA_LBW => VA_LBO,

        VA_LAC => VA_GLA,

        VA_CED => VA_CTY,

        VA_DIW => VA_DIS,

        VA_UTE => VA_UTA,
        VA_UTW => VA_UTA,

        VA_MTW => VA_MTA,

        VA_SPE => VA_SPA,
        VA_SPC => VA_SPA,

        VA_WAE => VA_WAP,
        VA_WAC => VA_WAC,

        VA_WMC => VA_WMP,

        VA_EUR => VA_EUP
    );

/* va_name
 * Names of areas. For administrative areas, this is their full name, for
 * instance "County" or "London Borough"; for voting areas, it's a short name,
 * for instance "Ward" or "Electoral Division". */
$va_name = array(
        VA_LBO  =>  "London Borough",
        VA_LBW  =>  "Ward",

        VA_GLA  =>  "Greater London Assembly",
        VA_LAC  =>  "Constituency",

        VA_CTY  =>  "County",
        VA_CED  =>  "Electoral Division",

        VA_DIS  =>  "District",
        VA_DIW  =>  "Ward",

        VA_UTA  =>  "Unitary Authority",
        VA_UTE  =>  "Electoral Division",
        VA_UTW  =>  "Ward",

        VA_MTD  =>  "Metropolitan District",
        VA_MTW  =>  "Ward",

        VA_SPA  =>  "Scottish Parliament",
        VA_SPE  =>  "Electoral Region",
        VA_SPC  =>  "Constituency",

        VA_WAS  =>  "Welsh Assembly",
        VA_WAE  =>  "Electoral Region",
        VA_WAC  =>  "Constituency",

        VA_WMP  =>  "House of Commons",
        VA_WMC  =>  "Constituency",

        VA_EUP  =>  "European Parliament",
        VA_EUR  =>  "Region"
    );

?>
