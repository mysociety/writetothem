<?
/*
 * elections.php:
 * Show current state after an election
 * 
 * Copyright (c) 2006 UK Citizens Online Democracy. All rights reserved.
 * Email: matthew@mysociety.org. WWW: http://www.mysociety.org
 *
 * $Id: elections.php,v 1.2 2007-05-21 13:50:16 matthew Exp $
 * 
 */
require_once '../phplib/fyr.php';
require_once '../../phplib/mapit.php';
require_once '../../phplib/dadem.php';

$dates = array(
	'2007-05-19' => array(2465,2455,2516,2556,2448,2280,2596,2256,2449,2628,2562,2524,2313,2591,2457,2343,2518,2404,2473,2451,2589,2606,2346,2408,2407,2443,2456,2623,2431,2546,2342,2452,2403,2282, 2345,2470,2608,2341),
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

