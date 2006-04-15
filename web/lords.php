<?
/*
 * index.php:
 * Main page of FaxYourRepresentative, where you enter your postcode
 * 
 * Copyright (c) 2004 UK Citizens Online Democracy. All rights reserved.
 * Email: francis@mysociety.org. WWW: http://www.mysociety.org
 *
 * $Id: lords.php,v 1.4 2006-04-15 12:58:10 matthew Exp $
 * 
 */
require_once "../phplib/fyr.php";
require_once "../../phplib/utility.php";
require_once "../../phplib/mapit.php";
require_once '../../phplib/dadem.php';
require_once "../../phplib/votingarea.php";

// Random Lord
if (get_http_var("random_lord")) {
    $all_lords = dadem_get_representatives($HOC_AREA_ID);
    dadem_check_error($all_lords);
    $random_lord = $all_lords[rand(0, count($all_lords) - 1)];
    header('Location: ' . new_url('write', false, 'fyr_extref', fyr_external_referrer(), 'cocode', get_http_var('cocode'), 'who', $random_lord));
    exit;
}

if ($pid = get_http_var('pid')) {
    $ids = dadem_get_same_person('uk.org.publicwhip/person/' . $pid);
    dadem_check_error($ids);
    $id = $ids[count($ids)-1];
    header('Location: ' . new_url('write', false, 'fyr_extref', fyr_external_referrer(), 'cocode', get_http_var('cocode'), 'who', $id));
    exit;
}

// Front page form
$ref = fyr_external_referrer();
$form_extra = '';
if (isset($ref))
    $form_extra .= '<input type="hidden" name="fyr_extref" value="'.htmlentities($ref).'">';
$cocode = get_http_var('cocode');
if ($cocode)
    $form_extra .= '<input type="hidden" name="cocode" value="'.htmlentities($cocode).'">';

$values = array(
        "title" => "Email or fax a member of the House of Lords in the UK Parliament",
);
template_draw('header', $values);

// print fyr_breadcrumbs(1, 'lords')

// Date of birth
if ($date = get_http_var('d')) {
	$date = parse_date($date);
	if (isset($date['epoch'])) {
		$f = file('../phplib/DoBsP.bsv');
		$matches = array();
		foreach ($f as $r) {
			list($id, $dob) = explode('|', $r);
			$dob = preg_replace('# \d+$#', '', $dob);
			if (!$dob) continue;
			$d = strtotime($dob);
			if (date('d/m', $date['epoch']) == date('d/m', strtotime($dob))) {
				$ids = dadem_get_same_person('uk.org.publicwhip/person/'.$id);
				dadem_check_error($ids);
				$id = $ids[count($ids)-1];
				$matches[] = $id;
			}
		}
		if (!count($matches)) {
			print '<p><em>No Lord shares that date, I\'m afraid. Pick another date!</em></p>';
			lords_form();
		} elseif (count($matches)==1) {
    			header('Location: ' . new_url('write', false, 'fyr_extref', fyr_external_referrer(), 'cocode', get_http_var('cocode'), 'who', $matches[0]));
			exit;
		} else {
			print '<p>There is more than one Lord who shares your birthday. Please pick from the list below:</p> <ul>';
			$reps_info = dadem_get_representatives_info($matches);
			dadem_check_error($reps_info);
			foreach ($matches as $id) {
    				$url = new_url('write', false, 'fyr_extref', fyr_external_referrer(), 'cocode', get_http_var('cocode'), 'who', $id);
				print '<li><a href="' . $url . '">' . $reps_info[$id]['name'] . '</a></li>'."\n";
			}
			print '</ul>';
		}
	}
} elseif ($q_college = get_http_var('c')) {
	$f = file('../phplib/DoBsP.bsv');
	$matches = array();
	foreach ($f as $r) {
		list($id, $dob, $education) = explode('|', $r);
		$education = trim(str_replace('; ', "\n", $education));
		if (!$education) continue;
		if (preg_match("#.*$q_college.*?".get_http_var('uni').'.*#i', $education, $m)) {
			$ids = dadem_get_same_person('uk.org.publicwhip/person/'.$id);
			dadem_check_error($ids);
			$id = $ids[count($ids)-1];
			$matches[] = $id;
			$colleges[] = $m[0];
		}
	}
	if (!count($matches)) {
		print '<p><em>No Lords went to that college, I\'m afraid. Pick another college!</em></p>';
		lords_form();
	} elseif (count($matches)==1) {
    		header('Location: ' . new_url('write', false, 'fyr_extref', fyr_external_referrer(), 'cocode', get_http_var('cocode'), 'who', $matches[0]));
		exit;
	} else {
		print '<p>There is more than one Lord who went to that college. Please pick from the list below:</p> <ul>';
		$reps_info = dadem_get_representatives_info($matches);
		dadem_check_error($reps_info);
		foreach ($matches as $i => $id) {
			$url = new_url('write', false, 'fyr_extref', fyr_external_referrer(), 'cocode', get_http_var('cocode'), 'who', $id);
			print '<li><a href="' . $url . '">' . $reps_info[$id]['name'] . '</a> ('.$colleges[$i].')</li>'."\n";
		}
		print '</ul>';
	}
} elseif ($place = get_http_var('p')) {
	$matches = array();

#	XXX: *Much* quicker to use BSV file than fetch all Lords from DaDem
#	$all_lords = dadem_get_representatives($HOC_AREA_ID);
#	dadem_check_error($all_lords);
#	$reps_info = dadem_get_representatives_info($all_lords);
#	dadem_check_error($reps_info);
#	foreach ($reps_info as $id => $rep) {
#		if (preg_match('# of (.*)$#', $rep['name'], $m)) {
#			$of_name = $m[1];
#			if (stristr($of_name, $place)) {
#				$matches[] = $id;
#				$reason[] = '(name)';
#			}
#		}
#	}
	$place = preg_quote($place);
	$f = file('../phplib/places.bsv');
	foreach ($f as $r) {
		list($pid, $lordofname, $lordofname_full, $county) = explode('|', $r);
		$county = trim($county);
		if (preg_match("#$place#i", $lordofname)) {
			$ids = dadem_get_same_person('uk.org.publicwhip/person/'.$pid);
			dadem_check_error($ids);
			$id = $ids[count($ids)-1];
			$matches[] = $id;
			$reason[] = '';
		} elseif (preg_match("#$place#i", $county)) {
			$ids = dadem_get_same_person('uk.org.publicwhip/person/'.$pid);
			dadem_check_error($ids);
			$id = $ids[count($ids)-1];
			$matches[] = $id;
			$r = '(';
			if (!$lordofname || ($lordofname!=$lordofname_full && $lordofname_full))
				$r .= 'of ' . $lordofname_full . ' ';
			$r .= 'in '. $county . ')';
			$reason[] = $r;
		} elseif (preg_match("#$place#i", $lordofname_full)) {
			$ids = dadem_get_same_person('uk.org.publicwhip/person/'.$pid);
			dadem_check_error($ids);
			$id = $ids[count($ids)-1];
			$matches[] = $id;
			$reason[] = '(of ' . $lordofname_full . ')';
		}
	}
	$f = file('../phplib/DoBsP.bsv');
	foreach ($f as $r) {
		list($id, $dob, $education) = explode('|', $r);
		$education = trim(str_replace('; ', "\n", $education));
		if (!$education) continue;
		if (preg_match("#.*$place.*#i", $education, $m)) {
			$ids = dadem_get_same_person('uk.org.publicwhip/person/'.$id);
			dadem_check_error($ids);
			$id = $ids[count($ids)-1];
			$matches[] = $id;
			$reason[] = '('.$m[0].')';
		}
	}
	if (!count($matches)) {
		print '<p><em>No Lords associated with that place, I\'m afraid. Pick another place.</em></p>';
		lords_form();
	} elseif (count($matches)==1) {
    		header('Location: ' . new_url('write', false, 'fyr_extref', fyr_external_referrer(), 'cocode', get_http_var('cocode'), 'who', $matches[0]));
		exit;
	} else {
		$reps_info = dadem_get_representatives_info($matches);
		dadem_check_error($reps_info);
		print '<p>There is more than one Lord associated with that place. Please pick from the list below:</p> <ul>';
		foreach ($matches as $i => $id) {
			$url = new_url('write', false, 'fyr_extref', fyr_external_referrer(), 'cocode', get_http_var('cocode'), 'who', $id);
			print '<li><a href="' . $url . '">' . $reps_info[$id]['name'] . '</a> <small>' . $reason[$i] . '</small></li>'."\n";
		}
		print '</ul>';
	}
} else {
	lords_form();
}
template_draw('footer', $values);

# ---

function lords_form() {
	global $form_extra;
?>
<style type="text/css">
li {
	padding-top: 2em;
}
</style>

<h2>Which Lord would you like to write to?</h2>

<p>WriteToThem <em>Lords edition</em> is an experiment. Lords don't have a duty to
reply to the public, and they're (obviously) not elected.
Nevertheless, they get to vote on things that affect all of us, so we
reckon they should at least be easy to contact.

However, Lords do not have constituencies like MPs, so we need
different ways for you to find a Lord to contact. We hope you
like the methods we've come up with.</p>

<p>It's best only to contact a Lord about an issue they
can help with or influence, which includes national legislation
and other issues Parliament deals with.

<a href="about-lords"><strong>Frequently Asked Questions</strong></a></p>
<ul>

<li>
<form action="http://www.theyworkforyou.com/search/" method="get" name="topicLordForm" id="topicLordForm">
Find a Lord interested in my <strong>topic</strong>:

<input type="input" name="s" id="s" value="<?=htmlentities(get_http_var('s')) ?>" size="20">
<input type="hidden" name="o" value="p">
<input type="hidden" name="house" value="2">
<input type="hidden" name="wtt" value="1">
<input type="submit" value="Go">
<?=$form_extra ?>
<br><small><em>(uses <a href="http://www.theyworkforyou.com/">TheyWorkForYou</a>;
by words spoken in Parliament, nothing more)</em></small>
</form>
</li>

<li>
<form action="/lords" method="get" name="placeLordForm" id="placeLordForm">

Find a Lord with some association with this <strong>place</strong>:

<input type="input" name="p" id="p" value="<?=htmlentities(get_http_var('p')) ?>" size="20">
<input type="submit" value="Go">
<?=$form_extra ?>
<br><small><em>e.g. they're Lord of there, or somewhere in that county, or they went to university there.</em></small>
</form>

<li>
<form action="/lords" method="get" name="dateLordForm" id="dateLordForm">
Find a Lord who shares my <strong>birthday</strong>:

<input type="input" name="d" id="d" value="<?=htmlentities(get_http_var('d')) ?>" size="20">
<input type="submit" value="Go">
<?=$form_extra ?>
<br><small><em>e.g. 19th September</em></small>
</form>

<li>
<form action="/lords" method="get" name="collegeLordForm" id="collegeLordForm">
Find a Lord who went to this Oxbridge <strong>college</strong>:
<input type="input" name="c" id="c" value="<?=htmlentities(get_http_var('c')) ?>" size="10">
<select name="uni">
<option>Oxford
<option>Cambridge
</select>
<input type="submit" value="Go">
<?=$form_extra ?>
<br><small><em>e.g. Trinity College, Oxford</em></small>
</form>

<li>
<form action="/lords" method="get" name="randomLordForm" id="randomLordForm">
I've <strong>no idea!</strong>
Give me a
<input type="hidden" name="random_lord" value="1">
<input type="submit" value="Random Lord">
<?=$form_extra ?>
</form>
</li>

</ul>

<p>We're using the House of Lords fax machine for all correspondence, so
we're limiting each person to one message a day for the moment.</p>


<?
}
?>
