<?
/*
 * elections.php:
 * Show current state after an election
 * 
 * Copyright (c) 2006 UK Citizens Online Democracy. All rights reserved.
 * Email: matthew@mysociety.org. WWW: http://www.mysociety.org
 *
 * $Id: elections.php,v 1.1 2007-05-21 13:49:38 matthew Exp $
 * 
 */
require_once '../phplib/fyr.php';
require_once '../../phplib/mapit.php';
require_once '../../phplib/dadem.php';

$dates = array(
	'2006-05-15' => array(2502,2489,2492,2487,2493,2500,2494,2509,2486,2482,2497,2507,2480,2488,2490,2499),
	'2006-05-16' => array(2451,2318,2343,2449,2339,2485,2501,2508,2506,2481,2498),
	'2006-05-17' => array(2496,2505),
	'2006-05-18' => array(2516,2510,2345,2495,2504,2491,2612,2526),
	'2006-05-19' => array(2483,2484,2511),
	'2006-05-20' => array(2606,2607),
	'2006-05-23' => array(2527,2290,2541,2264,2529,2515),
	'2006-05-26' => array(2530,2542),
	'2006-05-30' => array(2548,2455,2311,2523,2391,2596,2536,2525,2540,2539,2519,2334,2581,2263,2544,2252,2589,2421,2407,2537,2532,2588,2517),
	'2006-05-31' => array(2453,2379,2339,2513,2319,2260,2518),
	'2006-06-01' => array(2338,2545),
	'2006-06-02' => array(2514,2520),
	'2006-06-06' => array(2615,2291),
	'2006-06-07' => array(2253,2262,2266,2267,2268,2315,2344,2405,2524,2533,2561,2566,2618,2633),
	'2006-06-09' => array(2333,2543,2657,2326,2272,2281),
	'2006-06-10' => array(2387,2435,2371,2528,2308,2364,2336),
	'2006-06-11' => array(2522),
	'2006-06-13' => array(2307,2562,2329),
	'2006-06-15' => array(2538,2440,2410,2468,2478,2310,2438,2547,2552,2479,2366,2419,2426,2323,2327,2273,2535,2331,2462,2362,2469,2337,2370,2464,2546,2368),
);
foreach ($dates as $date=>$ids) {
	foreach ($ids as $id) {
		$date_done[$id] = $date;
	}
}

$statuses = dadem_get_area_statuses();
foreach ($statuses as $status_arr) {
	$area_id = $status_arr[0];
	$status = $status_arr[1];
	$lookup[$area_id] = $status;
}
$areas_info = mapit_get_voting_areas_info(array_keys($lookup));
mapit_check_error($areas_info);

function by_name($a, $b) {
	global $areas_info, $date_done;
	if (isset($date_done[$a]) && isset($date_done[$b])) {
		if ($date_done[$a] < $date_done[$b]) return 1;
		if ($date_done[$a] > $date_done[$b]) return -1;
	} elseif (isset($date_done[$a])) {
		return -1;
	} elseif (isset($date_done[$b])) {
		return 1;
	}
	return strcmp($areas_info[$a]['name'], $areas_info[$b]['name']);
}
uksort($lookup, 'by_name');
foreach ($lookup as $area_id => $status) {
	$o = $areas_info[$area_id]['name'];
	if (isset($date_done[$area_id])) {
		$out[$status][$date_done[$area_id]][] = $o;
	} else {
		$out[$status][] = $o;
	}
}

template_draw('header', array('title'=>'2006 elections'));
if (isset($out['none'])) { ?>
<div style="float: left; width: 48%;">
<p>Here is a list of the areas for which we have received new data since the election:</p>
<?
	foreach ($out['none'] as $date => $data) {
	print '<strong>'.$date.'</strong> <ul><li>';
	if (is_array($data)) print join("\n<li>", $data);
	else print "<li>$data";
	print '</ul>';
	}
	print '</div>';
}
if (isset($out['recent_election'])) {
?>
<div style="float: left; width: 48%;">
<p>Here is a list of the <?=count($out['recent_election']) ?> areas for which we are still awaiting election results:</p>
<?
	foreach ($out['recent_election'] as $date => $data) {
		if (!preg_match('#\d\d\d\d-\d\d-\d\d#', $date))
			continue;
		print '<strong>'.$date.'</strong> <ul><li>';
		if (is_array($data)) print join("\n<li>", $data);
		else print "<li>$data";
		print '</ul>';
		unset($out['recent_election'][$date]);
	}
?>
<ul><li><?
	print join("\n<li>", $out['recent_election']);
	print '</ul></div>';
}
template_draw('footer', array());

?>

