<?
/*
 * index.php:
 * Main page of FaxYourRepresentative, where you enter your postcode
 * 
 * Copyright (c) 2004 UK Citizens Online Democracy. All rights reserved.
 * Email: francis@mysociety.org. WWW: http://www.mysociety.org
 *
 * $Id: lords.php,v 1.14 2006-08-01 16:38:44 francis Exp $
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
    header('Location: ' . url_new('write', false, 'fyr_extref', fyr_external_referrer(), 'cocode', get_http_var('cocode'), 'who', $random_lord));
    exit;
}

if ($pid = get_http_var('pid')) {
    $ids = dadem_get_same_person('uk.org.publicwhip/person/' . $pid);
    dadem_check_error($ids);
    $id = $ids[count($ids)-1];
    header('Location: ' . url_new('write', false, 'fyr_extref', fyr_external_referrer(), 'cocode', get_http_var('cocode'), 'who', $id));
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

print fyr_breadcrumbs(1, 'lords');

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
    				if (dadem_get_error($ids)) continue;
				$id = $ids[count($ids)-1];
				$matches[] = $id;
			}
		}
		if (!count($matches)) {
			$error = 'No Lord shares that date, I\'m afraid. Pick another date!';
			lords_form(array('date'=>$error));
		} elseif (count($matches)==1) {
    			header('Location: ' . url_new('write', false, 'fyr_extref', fyr_external_referrer(), 'cocode', get_http_var('cocode'), 'who', $matches[0]));
			exit;
		} else {
			print '<p>There is more than one Lord who shares your birthday. Please pick from the list below:</p> <ul>';
			$reps_info = dadem_get_representatives_info($matches);
			dadem_check_error($reps_info);
			foreach ($matches as $id) {
    				$url = url_new('write', false, 'fyr_extref', fyr_external_referrer(), 'cocode', get_http_var('cocode'), 'who', $id);
				print '<li><a href="' . $url . '">' . $reps_info[$id]['name'] . '</a></li>'."\n";
			}
			print '</ul>';
		}
	} else {
		lords_form(array('date'=>'We didn\'t recognise that date, sorry.'));
	}
} elseif ($q_college = get_http_var('c')) {
	$f = file('../phplib/DoBsP.bsv');
	$matches = array();
	$q_college = preg_quote($q_college, '#');
	$uni = preg_quote(get_http_var('uni'), '#');
	foreach ($f as $r) {
		list($id, $dob, $education) = explode('|', $r);
		$education = trim(str_replace('; ', "\n", $education));
		if (!$education) continue;
		if (preg_match("#.*$q_college.*?$uni.*#i", $education, $m)) {
			$ids = dadem_get_same_person('uk.org.publicwhip/person/'.$id);
    			if (dadem_get_error($ids)) continue;
			$id = $ids[count($ids)-1];
			$matches[] = $id;
			$colleges[] = $m[0];
		}
	}
	if (!count($matches)) {
		$error = 'No Lords went to that college, I\'m afraid. Pick another college!';
		lords_form(array('college'=>$error));
	} elseif (count($matches)==1) {
    		header('Location: ' . url_new('write', false, 'fyr_extref', fyr_external_referrer(), 'cocode', get_http_var('cocode'), 'who', $matches[0]));
		exit;
	} else {
		print '<p>There is more than one Lord who went to that college. Please pick from the list below:</p> <ul>';
		$reps_info = dadem_get_representatives_info($matches);
		dadem_check_error($reps_info);
		foreach ($matches as $i => $id) {
			$url = url_new('write', false, 'fyr_extref', fyr_external_referrer(), 'cocode', get_http_var('cocode'), 'who', $id);
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
	$place = preg_quote($place, '#');
	$f = file('../phplib/places.bsv');
	foreach ($f as $r) {
		list($pid, $lordofname, $lordofname_full, $county) = explode('|', $r);
		$county = trim($county);
		if (preg_match("#$place#i", $lordofname)) {
			$ids = dadem_get_same_person('uk.org.publicwhip/person/'.$pid);
    			if (dadem_get_error($ids)) continue;
			$id = $ids[count($ids)-1];
			$matches[] = $id;
			$reason[] = '';
		} elseif (preg_match("#$place#i", $county)) {
			$ids = dadem_get_same_person('uk.org.publicwhip/person/'.$pid);
    			if (dadem_get_error($ids)) continue;
			$id = $ids[count($ids)-1];
			$matches[] = $id;
			$r = '(';
			if (!$lordofname || ($lordofname!=$lordofname_full && $lordofname_full))
				$r .= 'of ' . $lordofname_full . ' ';
			$r .= 'in '. $county . ')';
			$reason[] = $r;
		} elseif (preg_match("#$place#i", $lordofname_full)) {
			$ids = dadem_get_same_person('uk.org.publicwhip/person/'.$pid);
    			if (dadem_get_error($ids)) continue;
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
    			if (dadem_get_error($ids)) continue;
			$id = $ids[count($ids)-1];
			$matches[] = $id;
			$reason[] = '('.$m[0].')';
		}
	}
	if (!count($matches)) {
		$error = 'No Lords associated with that place, I\'m afraid. Pick another place.';
		lords_form(array('place'=>$error));
#	} elseif (count($matches)==1) {
#    		header('Location: ' . url_new('write', false, 'fyr_extref', fyr_external_referrer(), 'cocode', get_http_var('cocode'), 'who', $matches[0]));
#		exit;
	} else {
		$reps_info = dadem_get_representatives_info($matches);
		dadem_check_error($reps_info);
		print '<p>';
		if (count($matches)==1) {
			print 'Only one Lord matched your search criteria:';
		} else {
			print 'There is more than one Lord associated with that place. Please pick from the list below:';
		}
		print '</p> <ul>';
		foreach ($matches as $i => $id) {
			$url = url_new('write', false, 'fyr_extref', fyr_external_referrer(), 'cocode', get_http_var('cocode'), 'who', $id);
			print '<li><a href="' . $url . '">' . $reps_info[$id]['name'] . '</a> <small>' . $reason[$i] . '</small></li>'."\n";
		}
		print '</ul>';
	}
} else {
	lords_form();
}
template_draw('footer', $values);

# ---

function lords_form($error = array()) {
	global $form_extra;
?>
<style type="text/css">
li {
	padding-top: 2em;
}
</style>

<h2>Which Lord would you like to write to?</h2>

<p>WriteToThem <em>Lords edition</em> is an experiment. Lords do not have a duty to
reply to the public, and they are (obviously) not elected.
Nevertheless, they get to vote on things that affect all of us, so we
reckon they should at least be easy to contact.  It is best only to contact a
Lord about an issue they can help with or influence, which includes national
legislation and other issues Parliament deals with.
For more information, read our <a href="about-lords"><strong>Frequently Asked Questions</strong></a>.
</p>

<p>Lords do not have constituencies like MPs, so we need
different ways for you to find a Lord to contact. We hope you
like the methods we have come up with. </p>


<ul>

<li>
<form action="http://www.theyworkforyou.com/search/" method="get" name="topicLordForm" id="topicLordForm">
Find a Lord interested in my <strong>topic</strong>:

<input type="text" name="s" id="s" value="<?=htmlentities(get_http_var('s')) ?>" size="20">
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
<? if (isset($error['place'])) { ?>
<div id="error"><?=$error['place'] ?></div>
<?  } ?>
<form action="/lords" method="get" name="placeLordForm" id="placeLordForm">

Find a Lord with some association with this <strong>place</strong>:

<input type="text" name="p" id="p" value="<?=htmlentities(get_http_var('p')) ?>" size="20">
<input type="submit" value="Go">
<?=$form_extra ?>
<br><small><em>e.g. they're Lord of there, or somewhere in that county, or they went to university there.</em></small>
</form>

<li>
<? if (isset($error['date'])) { ?>
<div id="error"><?=$error['date'] ?></div>
<?  } ?>
<form action="/lords" method="get" name="dateLordForm" id="dateLordForm">
Find a Lord who shares my <strong>birthday</strong>:

<input type="text" name="d" id="d" value="<?=htmlentities(get_http_var('d')) ?>" size="20">
<input type="submit" value="Go">
<?=$form_extra ?>
<br><small><em>e.g. 19th September</em></small>
</form>


<? /*
<li>
<? if (isset($error['college'])) { ?>
<div id="error"><?=$error['college'] ?></div>
<?  } ?>
<form action="/lords" method="get" name="collegeLordForm" id="collegeLordForm">
Find a Lord who went to this Oxbridge <strong>college</strong>:
<input type="text" name="c" id="c" value="<?=htmlentities(get_http_var('c')) ?>" size="10">
<select name="uni">
<option>Oxford
<option>Cambridge
</select>
<input type="submit" value="Go">
<?=$form_extra ?>
<br><small><em>e.g. Trinity College, Oxford</em></small>
</form>
*/ ?>

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

<?
}
?>
