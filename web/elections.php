<?
/*
 * elections.php:
 * Show current state after an election
 * 
 * Copyright (c) 2006 UK Citizens Online Democracy. All rights reserved.
 * Email: matthew@mysociety.org. WWW: http://www.mysociety.org
 *
 * $Id: elections.php,v 1.3 2007-07-11 12:01:01 matthew Exp $
 * 
 */
require_once '../phplib/fyr.php';
require_once '../../phplib/mapit.php';
require_once '../../phplib/dadem.php';

$dates = array(
	# First lot is always councils with new data
	'2007-05-19' => array(
		2465,2455,2516,2556,2448,2280,2596,2256,2449,2628,2562,2524,2313,2591,2457,2343,2518,2404,2473,2451,2589,2606,2346,2408,2407,2443,2456,2623,2431,2546,2342,2452,2403,2282,
		# Councils with boundary changes: Dacorum, N Wiltshire, S Gloucestershire
		2470,2608,2341, 2345, # Last one?
        	# Welsh areas *without* boundary changes
		11913,11892,11881,11899,11895,11889,11885,11901,11905,11904,11896,11911,11914,11902,11891,11935
	),
	'2007-05-20' => array(
		# Welsh areas *with* boundary changes
		11917,11938,11883,11884,11882,11931,11916,11890,11886,11888,11887,11893,11898,11894,11933,11897,11907,11900,11903,11906,11910,11908,11909,11912
	),
	'2007-05-24' => array(
		2632,2440,2533,2466,2379,2468,2262,2441,2513,2377,2251,2378,2539,2344,2255,2270,2405,2386,2382,2635,2327,2528,2318,2627,2390,2252,2583,2446,2388,2629,2297,2372,2631,2444,2600,2261,2626,2572,2380,2634,2442,2581,2338,2565,2397,2384, # 2633???
		# Lincoln boundary change
		2385
	),
	'2007-05-28' => array(
		2548,2527,2523,2295,2320,2271,2406,2265,2310,2317,2319,2521,2530,2347,2268,2541,2273,2607,2401,2537,2267,2272,2276,2409,2618,2368,
		# Kettering and N. kesteven boundary changes
		2383, 2396
		# 2269, 2433 byelections this Thursday
	),
	'2007-05-31' => array(
		2642,2551,2285,2617,2412,2306,2420,2281,2304,2283,2278,2305,2422,2658,2532,2614,2253,2289,2298,2354,2538,2307,2410,2290,2460,2259,2287,2284,2514,2419,2630,2263,2302,2258,2535,2286,
		# W Wiltshire, S Northamptonshire, S Holland, Newark&Sherwood, Mendip, E Northamptonshire boundary changes
		2393,2381,2414,2428,2472,2392, 2429 # Last one later too?

	),
	'2007-06-05' => array(
		2613,2311,2365,2450,2387,2622,2217,2483,2303,2563,2339,2292,2260,2452,2254,2296,2411,2362,2301,2594,2456,2293,2300,
		# Castle Morpeth and Wansbeck boundary changes
		2399, 2402
	),
	'2007-06-06' => array(
		2274,2315,2333,2337,2339,2350,2351,2353,2356,2357,2358,2370,2515,2526,2584,2612,
		# N. Hertfordshire and Taunton Deane boundary changesa
		2345,2429),
		# 2349 Shepway only had deletions
	'2007-06-07' => array(
		2625,2424,2367,2437,2375,2543,2434,2374,2394,2540,2435,2371,2479,2366,2323,2553,2352,2427,2321,2520,2360,2463
	),
	'2007-06-09' => array(
		2288,2561,2391,2361,2478,2330,2534,2552,2426,2564,2477,2325,2611,2415,2555,2417,2459,2588,2336,
		# Corby
		2398
	),
	'2007-06-17' => array(
		2395, 2314, 2430, 2458, 2536, 2359, 2340, 2322, 2454, 2547, 2461, 2418, 2432, 2544, 2316, 2439, 2522, 2571, 2436, 2416, 2542, 2364, 2312, 2517, 2329
	),
	'2007-06-19' => array(
		2453,2425,2438,2568,2331,2376,2467,2529
		# 2251 COI, 2633???
	),
	'2007-06-20' => array(
		2567,2474,2657,2355,2597,2369,2476,2363
	),
	'2007-06-22' => array(
		2471, 2531, 2619, 2324,2525, 2277, 2291, 2577,2373, 2545, 2580,2275, 2299, 2423,2348, 2294, 2566, 2332,
		# Scotland - boundary changes!
		2643,2649,2590,2579,2609,2648,2651,2656,2601,2593,2647,2655,
		2598,2621,2650,2550,2654,2574,2573,2646,2578,2620,2644,2610,
		2653,2575,2587,2582,2652,2645,2569,2576
	),
	'2007-06-23' => array(2413,2309,2469,2279,2389,2519,
		2433, # S. Staffs byelection result
		2269, # Vale Royal byelection result
	),
	'2007-06-27' => array(2328),
	'2007-06-28' => array(2266,2586,2445,2400,2349,2615,2264,2475,2257,2447,
	2633),

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

template_draw('header', array('title'=>'2007 elections'));
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
<p>Here's a list of areas with new data, but there have been boundary changes:</p>
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
<p>Here is a list of the <?=count($out['recent_election']) ?> areas for which we are still awaiting election results:</p>
<ul><li><?
	print join("\n<li>", $out['recent_election']);
	print '</ul></div>';
}
template_draw('footer', array());

?>

