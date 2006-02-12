<?
/*
 * index.php:
 * Statistics!
 * 
 * Copyright (c) 2006 UK Citizens Online Democracy. All rights reserved.
 * Email: matthew@mysociety.org. WWW: http://www.mysociety.org
 *
 * $Id: stats.php,v 1.1 2006-02-12 20:46:14 matthew Exp $
 * 
 */
require_once '../phplib/fyr.php';

# Read in data
require_once '../phplib/questionnaire_report_2005_WMC.php';
$data = array();
foreach ($questionnaire_report_2005_WMC as $key => $row) {
	if (is_array($row)) {
		$data['data'][] = array(
			'id' => $row['recipient_id'],
			'name' => $row['name'],
			'party' => $row['party'],
			'area' => $row['area'],
			'sent' => $row['dispatched_success'],
			'category' => $row['category'],
			'notes' => category_lookup($row['category']),
			'response' => round($row['responded_mean'] * 100, 1),
			'low' => round($row['responded_95_low'] * 100, 1),
			'high' => round($row['responded_95_high'] * 100, 1)
		);
	} else {
		$data['info'][$key] = $row;
	}
}

# Sort data
$sort = get_http_var('o');
if ($sort == 'n') {
	function by_name($a, $b) {
		return strcmp($a['name'], $b['name']);
	}
	usort($data['data'], 'by_name');
} elseif ($sort == 'c') {
	function by_area($a, $b) {
		return strcmp($a['area'], $b['area']);
	}
	usort($data['data'], 'by_area');
} elseif ($sort == 's') {
	function by_sent($a, $b) {
		if ($a['sent']<$b['sent']) return 1;
		elseif ($a['sent']>$b['sent']) return -1;
		return 0;
	}
	usort($data['data'], 'by_sent');
} else {
	function by_response($a, $b) {
		if ($a['response']<$b['response']) return 1;
		elseif ($a['response']>$b['response']) return -1;
		if ($a['low']<$b['low']) return 1;
		elseif ($a['low']>$b['low']) return -1;
		return 0;
	}
	usort($data['data'], 'by_response');
}
$data['info']['sort'] = $sort;

# Output data
template_draw('stats-index', array(
        "title" => "Statistics",
	'data' => $data
    ));

function category_lookup($cat) {
    if (strstr($cat, 'good')) return '';
    elseif ($cat == 'shame') return "MP doesn't accept messages from WriteToThem";
    elseif ($cat == 'toofew') return 'Too few messages sent to MP';
    elseif ($cat == 'unknown') return '*** Unknown ***';
    return $cat;
}
?>

