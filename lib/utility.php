<?php

/*
General utility functions v1.1 (well, it was).

*/

function debug ($header, $text="", $complex_variable=null) {
	// Pass it a brief header word and some debug text and it'll be output.

	// We set ?debug=n in the URL.
	// n is a number from (currently) 1 to 4.
	// This sets what amount of debug information is shown.
	// For level '1' we show anything that is passed to this function
	// with a $header in $levels[1].
	// For level '2', anything with a $header in $levels[1] AND $levels[2].
	// Level '4' shows everything.
    // $complex_variable is dumped in full, so you can put arrays/hashes here
	
	$debug_level = get_http_var("debug");
	
	if ($debug_level != '') {
	
		// Set which level shows which types of debug info.
		$levels = array (
			1 => array ('FRONTEND', 'WARNING', 'MAPIT', 'DADEM'),
			2 => array ('SQL', 'MAPITRESULT', 'DADEMRESULT'), // SQL not used yet
			3 => array ('SQLRESULT') // SQLRESULT not used yet
			// Higher than this: 'DATA', etc.
		);
	
		// Store which headers we are allowed to show.
		$allowed_headers = array();
		
		if ($debug_level > count($levels)) {
			$max_level_to_show = count($levels);
		} else {
			$max_level_to_show = $debug_level;
		}
		
		for ($n = 1; $n <= $max_level_to_show; $n++) {
			$allowed_headers = array_merge ($allowed_headers, $levels[$n] );
		}
		
		// If we can show this header, then, er, show it.
		if ( in_array($header, $allowed_headers) || $debug_level >= 4) {
            	
			print "<p><span style=\"color:#039;\"><strong>$header</strong></span> $text";
            if (isset($complex_variable)) {
                print "</p><p>";
                vardump($complex_variable);
            }
            print "</p>\n";	
		}
	}
}


function error_handler ($errno, $errmsg, $filename, $linenum, $vars) {
	// Custom error-handling function.
	// Sends an email to BUGSLIST.
	global $PAGE;

   // define an assoc array of error string
   // in reality the only entries we should
   // consider are E_WARNING, E_NOTICE, E_USER_ERROR,
   // E_USER_WARNING and E_USER_NOTICE
   $errortype = array (
		E_ERROR				=> "Error",
		E_WARNING			=> "Warning",
		E_PARSE				=> "Parsing Error",
		E_NOTICE			=> "Notice",
		E_CORE_ERROR		=> "Core Error",
		E_CORE_WARNING		=> "Core Warning",
		E_COMPILE_ERROR		=> "Compile Error",
		E_COMPILE_WARNING	=> "Compile Warning",
		E_USER_ERROR		=> "User Error",
		E_USER_WARNING		=> "User Warning",
		E_USER_NOTICE		=> "User Notice",
		// PHP 5 only
		//E_STRICT			=> "Runtime Notice"
	);

	$err = "URL:\t\thttp://" . DOMAIN . $_SERVER['REQUEST_URI'] . "\n";
	if (isset($_SERVER['HTTP_REFERER'])) {
		$err .= "Referer:\t" . $_SERVER['HTTP_REFERER'] . "\n";
	} else {
		$err .= "Referer:\tNone\n";
	}
	$err .= "User-Agent:\t" . $_SERVER['HTTP_USER_AGENT'] . "\n";
	$err .= "Number:\t\t$errno\n";
	$err .= "Type:\t\t" . $errortype[$errno] . "\n";
	$err .= "Message:\t$errmsg\n";
	$err .= "File:\t\t$filename\n";
	$err .= "Line:\t\t$linenum\n";


// I'm not sure this bit is actually any use!

	// set of errors for which a var trace will be saved.
//	$user_errors = array(E_USER_ERROR, E_USER_WARNING, E_USER_NOTICE);
//	if (in_array($errno, $user_errors)) {
//		$err .= "Variables:\t" . serialize($vars) . "\n";
//	}
	
	
	// Add the problematic line if possible.
	if (is_readable($filename)) {
		$source = file($filename);
		$err .= "\nSource:\n\n";
		// Show the line, plus prev and next, with line numbers.
		$err .= $linenum-2 . " " . $source[$linenum-3];
		$err .= $linenum-1 . " " . $source[$linenum-2];
		$err .= $linenum . " " . $source[$linenum-1];
		$err .= $linenum+1 . " " . $source[$linenum];
		$err .= $linenum+2 . " " . $source[$linenum+1];
	}
	
	
	// Will we need to exit after this error?
	$fatal_errors = array(E_ERROR, E_USER_ERROR);
	if (in_array($errno, $fatal_errors)) {
		$fatal = true;
	} else {
		$fatal = false;
	}


	// Finally, display errors and stuff...

	if (DEVSITE) {
		// On a devsite we just display the problem.
		$message = array(
			'title' => "Error",
			'text' => "<pre>$err</pre>\n"
		);
		if (is_object($PAGE)) {
			$PAGE->error_message($message, $fatal);
		} else {
			var_dump($message);
		}
		
	} else {
		// On live sites we display a nice message and email the problem.
		
		$message = array(
			'title' => "Sorry, an error has occurred",
			'text' => "We've been notified by email and will try to fix the problem soon!"
		);

		if (is_object($PAGE)) {
			$PAGE->error_message($message, $fatal);
		} else {
			print "<p>Oops, sorry, an error has occurred!</p>\n";
		}
		mail(BUGSLIST, "TheyWorkForYou.com error: $errmsg", $err,
			"From: Bug <gyford@gmail.com>\n".
			"X-Mailer: PHP/" . phpversion()
		);
	}	
	

	// Do we need to exit?
	
	if ($fatal) {
		exit(1);
	}

}




// Replacement for var_dump()
function vardump($blah) {
	print "<pre>\n";
	var_dump($blah);
	print "</pre>\n";
}



// Far from foolproof, but better than nothing.
function validate_email ($string) {
	if (!ereg('^[-!#$%&\'*+\\./0-9=?A-Z^_`a-z{|}~]+'.
		'@'.
		'[-!#$%&\'*+\\/0-9=?A-Z^_`a-z{|}~]+\.'.
		'[-!#$%&\'*+\\./0-9=?A-Z^_`a-z{|}~]+$', $string)) {
		return false;
	} else {
		return true;
	}
}


function validate_postcode ($postcode) {
	// See http://www.govtalk.gov.uk/gdsc/html/noframes/PostCode-2-1-Release.htm
	
	$in  = 'ABDEFGHJLNPQRSTUWXYZ';
	$fst = 'ABCDEFGHIJKLMNOPRSTUWYZ';
	$sec = 'ABCDEFGHJKLMNOPQRSTUVWXY';
	$thd = 'ABCDEFGHJKSTUW';
	$fth = 'ABEHMNPRVWXY';
	$num = '0123456789';
	$nom = '0123456789';
	$gap = '\s\.';	

	if (	preg_match("/^[$fst][$num][$gap]*[$nom][$in][$in]$/i", $postcode) ||
			preg_match("/^[$fst][$num][$num][$gap]*[$nom][$in][$in]$/i", $postcode) ||
			preg_match("/^[$fst][$sec][$num][$gap]*[$nom][$in][$in]$/i", $postcode) ||
			preg_match("/^[$fst][$sec][$num][$num][$gap]*[$nom][$in][$in]$/i", $postcode) ||
			preg_match("/^[$fst][$num][$thd][$gap]*[$nom][$in][$in]$/i", $postcode) ||
			preg_match("/^[$fst][$sec][$num][$fth][$gap]*[$nom][$in][$in]$/i", $postcode)
		) {
		return true;
	} else {
		return false;
	}
}


// Returns the unixtime in microseconds.
function getmicrotime() {
	$mtime = microtime();
	$mtime = explode(" ",$mtime);
	$mtime = $mtime[1] + $mtime[0];

	return $mtime;
}



function format_timestamp ($timestamp, $format) {
	// Pass it a MYSQL TIMESTAMP (YYYYMMDDHHMMSS) and a
	// PHP date format string (eg, "Y-m-d H:i:s")
	// and it returns a nicely formatted string according to requirements.
	
	// Because strtotime can't handle TIMESTAMPS.
	
	if (preg_match("/^(\d\d\d\d)(\d\d)(\d\d)(\d\d)(\d\d)(\d\d)$/", $timestamp, $matches)) {
		list($string, $year, $month, $day, $hour, $min, $sec) = $matches;
	
		return gmdate ($format, gmmktime($hour, $min, $sec, $month, $day, $year));
	} else {
		return "";
	}

}


function format_date ($date, $format) {
	// Pass it a date (YYYY-MM-DD) and a
	// PHP date format string (eg, "Y-m-d H:i:s")
	// and it returns a nicely formatted string according to requirements.

	if (preg_match("/^(\d\d\d\d)-(\d\d)-(\d\d)$/", $date, $matches)) {
		list($string, $year, $month, $day) = $matches;
	
		return gmdate ($format, gmmktime(0, 0, 0, $month, $day, $year));
	} else {
		return "";
	}

}


function format_time ($time, $format) {
	// Pass it a time (HH:MM:SS) and a
	// PHP date format string (eg, "H:i")
	// and it returns a nicely formatted string according to requirements.

	if (preg_match("/^(\d\d):(\d\d):(\d\d)$/", $time, $matches)) {
		list($string, $hour, $min, $sec) = $matches;

		return gmdate ($format, gmmktime($hour, $min, $sec));
	} else {
		return "";
	}
}



function relative_time ($datetime) {
	// Pass it a 'YYYY-MM-DD HH:MM:SS' and it will return something
	// like "Two hours ago", "Last week", etc.
	
	// http://maniacalrage.net/projects/relative/
	
	if (!preg_match("/\d\d\d\d-\d\d-\d\d \d\d\:\d\d\:\d\d/", $datetime)) {
		return '';
	}

	$in_seconds = strtotime($datetime);
	$now = mktime();

	$diff 	=  $now - $in_seconds;
	$months	=  floor($diff/2419200);
	$diff 	-= $months * 2419200;
	$weeks 	=  floor($diff/604800);
	$diff	-= $weeks*604800;
	$days 	=  floor($diff/86400);
	$diff 	-= $days * 86400;
	$hours 	=  floor($diff/3600);
	$diff 	-= $hours * 3600;
	$minutes = floor($diff/60);
	$diff 	-= $minutes * 60;
	$seconds = $diff;
    
	
	if ($months > 0) {
		// Over a month old, just show the actual date.
		$date = substr($datetime, 0, 9);
		return format_date($date, LONGDATEFORMAT);

	} else {
		$relative_date = '';
		if ($weeks > 0) {
			// Weeks and days
			$relative_date .= ($relative_date?', ':'').$weeks.' week'.($weeks>1?'s':'');
			$relative_date .= $days>0?($relative_date?', ':'').$days.' day'.($days>1?'s':''):'';
		} elseif ($days > 0) {
			// days and hours
			$relative_date .= ($relative_date?', ':'').$days.' day'.($days>1?'s':'');
			$relative_date .= $hours>0?($relative_date?', ':'').$hours.' hour'.($hours>1?'s':''):'';
		} elseif ($hours > 0) {
			// hours and minutes
			$relative_date .= ($relative_date?', ':'').$hours.' hour'.($hours>1?'s':'');
			$relative_date .= $minutes>0?($relative_date?', ':'').$minutes.' minute'.($minutes>1?'s':''):'';
		} elseif ($minutes > 0) {
			// minutes only
			$relative_date .= ($relative_date?', ':'').$minutes.' minute'.($minutes>1?'s':'');
		} else {
			// seconds only
			$relative_date .= ($relative_date?', ':'').$seconds.' second'.($seconds>1?'s':'');
		}
	}
	
	// Return relative date and add proper verbiage
	return $relative_date.' ago';
	
}



// Alternative to strip_tags which replaces some tags with spaces,
// so words don't end up stuck together. For example, if they were only
// separated by a <p>.
function strip_tags_tospaces($text) {
    $text = preg_replace("#\<(p|br|div|td|tr|th|table)[^>]*\>#i", " ", $text);
    return strip_tags(trim($text));
}

function trim_characters ($text, $start, $length) {
	// Pass it a string, a numeric start position and a numeric length.
	// If the start position is > 0, the string will be trimmed to start at the
	// nearest word boundary after (or at) that position.
	// If the string is then longer than $length, it will be trimmed to the nearest
	// word boundary below (or at) that length.
	// If either end is trimmed, ellipses will be added.
	// The modified string is then returned - its *maximum* length is $length.
	// HTML is always stripped (must be for trimming to prevent broken tags).

	$text = strip_tags_tospaces($text);
	
	// Split long strings up so they don't go too long.
	// Mainly for URLs which are displayed, but aren't links when trimmed.
	$text = preg_replace("/(\S{60})/", "\$1 ", $text);

	// Otherwise the word boundary matching goes odd...
	$text = preg_replace("/[\n\r]/", " ", $text);
	
	// Trim start.
	if ($start > 0) {
		$text = substr($text, $start);
		
		// Word boundary.         
		if (preg_match ("/.+?\b(.*)/", $text, $matches)) {
			$text = $matches[1];
			// Strip spare space at the start.
			$text = preg_replace ("/^\s/", '', $text);
		}
		$text = '...' . $text;
	}
	
	// Trim end.
	if (strlen($text) > $length) {

		// Allow space for ellipsis.
		$text = substr($text, 0, $length - 3); 

		// Word boundary.         
		if (preg_match ("/(.*)\b.+/", $text, $matches)) {
			$text = $matches[1];
			// Strip spare space at the end.
			$text = preg_replace ("/\s$/", '', $text);
		}
		// We don't want to use the HTML entity for an ellipsis (&#8230;), because then 
		// it screws up when we subsequently use htmlentities() to print the returned
		// string!
		$text .= '...'; 
	}

	return $text;
}


function filter_user_input ($text, $filter_type) {
	// We use this to filter any major user input, especially comments.
	// Gets rid of bad HTML, basically.
	// Uses iamcal.com's lib_filter class.
	
	// $filter_type is the level of filtering we want:
	// 	'comment' allows <b> and <i> tags.
	//	'strict' strips all tags.
	
	global $filter;
	
	$text = trim($text);
	
	// Replace 3 or more newlines with just two newlines.
	//$text = preg_replace("/(\n){3,}/", "\n\n", $text);
	
	if ($filter_type == 'strict') {
		// No tags allowed at all!
		$filter->allowed = array ();
		
	} else {
		// Comment.
		// Only allowing <b> and <i> tags.
		$filter->allowed = array (
			'b' => array(),
			'i' => array(),
		);
	}
	
	$text = $filter->go($text);


	return $text;

}



function prepare_comment_for_display ($text) {
	// Makes any URLs into HTML links.
	// Turns \n's into <br />

	preg_match_all(
		"/((http(s?):\/\/)|(www\.))([a-zA-Z0-9\_\.\,\?\%\~\-\/\#\='\*\$\!\(\)\&]+)/",
		$text,
		$matches);

	// Encode HTML entities.
	// Can't do htmlentities() because it'll turn the few tags we allow into &lt;
	// Must go before the URL stuff.
	$text = htmlentities_notags($text);
	
	// ...and finally replace all the urls with truncated links to the original full url.
	foreach ($matches[0] as $match){
		$link_length = 60;
		$short_match = substr($match, 0, $link_length);
		// Did we actually truncateanything?
		if (strlen($match) > $link_length) {
			$short_match .= "...";
		}
		// because we cleaned up the body text above, all our links are htmlentitied now.
		// So we'll need to translate the ones we ripped out before we can match them.
		$old_match = htmlentities($match);
		$text = str_replace($old_match, "<a href=\"$match\">$short_match</a>", $text);
	}
		
	$text = preg_replace("/([\w\.]+)(@)([\w\.\-]+)/i", "<a href=\"mailto:$0\">$0</a>", $text); 


	$text = nl2br($text);
	
	return $text;	
}


function htmlentities_notags ($text) {
	// If you want to do htmlentities() on some text that has HTML tags
	// in it, then you need this function.
	
	$tbl = get_html_translation_table(HTML_ENTITIES);

	// You could encode extra stuff...
	//$tbl["“"] = "&quot;";
	//$tbl["”"] = "&quot;";
	//$tbl["…"] = "...";
	//$tbl["—"] = "-";
	//$tbl["»"] = "&raquo;";
	//$tbl["«"] = "&laquo;";
		 
	// Don't want to encode these things
	unset ($tbl["<"]);
	unset ($tbl[">"]);
	unset ($tbl["'"]);
	unset ($tbl['"']);
	
	// Need to do & separetly, or else things like £ end up as &amp;pound;
	// No idea why. But this seems to work.
	unset ($tbl['&']);
	$text = str_replace('&', '&amp;', $text);

	$text = str_replace(array_keys($tbl), array_values($tbl), $text);

	return $text;

}



function fix_gid_from_db ($gid, $keepmajor = false) {
	// The gids in the database are longer than we use in the site.
	// Feed this a gid from the db and it will be returned truncated.
	
	// $gid will be like 'uk.org.publicwhip/debate/2003-02-28.475.3'.
	
	// You will almost always want $keepmajor to be false.
	// This returns '2003-02-28.475.3' which is used for URLs.
	
	// However, trackbacks want a bit more info, so we can tell what
	// kind of thing they link to. So they need $keepmajor to be true.
	// This returns 'debate_2003-02-28.475.3'.
	
	if ($keepmajor) {
		$newgid = substr($gid, strpos($gid, '/')+1 );
		$newgid = str_replace('/', '_', $newgid);
	} else {
		$newgid = substr($gid, strrpos($gid, '/')+1 );
	}

	return $newgid;
	
}

function gid_to_anchor ($gid) {
	// For trimming gids to be used as #anchors in pages.
	// Extracted here so we keep it consistent.
	// The gid should already be truncated using fix_gid_from_db(), so it
	// will be like 2003-11-20.966.0
	// This function returns 966.0
	
	return substr( $gid, (strpos($gid, '.') + 1) );
}


function send_template_email ($data, $merge) {
	// We should have some email templates in INCLUDESPATH/easyparliament/templates/emails/.
	
	// $data is like:
	// array (
	//	'template' 	=> 'send_confirmation',
	//	'to'		=> 'phil@gyford.com',
	//	'subject'	=> 'Your confirmation email'
	// );
	
	// $merge is like:
	// array (
	//	'FIRSTNAME' => 'Phil',
	//	'LATNAME'	=> 'Gyford'
	// 	etc...
	// );
	
	// In $data, 'template' and 'to' are mandatory. 'template' is the 
	// name of the file (when it has '.txt' added to it).
	
	// We'll get the text of the template and replace all the $merge
	// keys with their tokens. eg, if '{FIRSTNAME}' in the template will 
	// be replaced with 'Phil'.
	
	// Additionally, the first line of a template may start with 
	// 'Subject:'. Any text immediately following that, on the same line
	// will be the subject of the email (it will also have its tokens merged).
	// But this subject can be overridden by sending including a 'subject'
	// pair in $data.
	
	global $PAGE;
	
	if (!isset($data['to']) || $data['to'] == '') {
		$PAGE->error_message ("We need an email address to send to.");
		return false;
	}

	$filename = INCLUDESPATH . "easyparliament/templates/emails/" . $data['template'] . ".txt";

	if (!file_exists($filename)) {
		$PAGE->error_message("Sorry, we could not find the email template '" . htmlentities($data['template']) . "'.");
		return false;
	}
	
	// Get the text from the template.
	$handle = fopen($filename, "r");
	$emailtext = fread($handle, filesize($filename));
	fclose($handle);

	// See if there's a default subject in the template.
	$firstline = substr($emailtext, 0, strpos($emailtext, "\n"));
	
	// Work out what the subject line is.
	if (preg_match("/Subject:/", $firstline)) {
		if (isset($data['subject'])) {
			$subject = trim($data['subject']);
		} else {
			$subject = trim( substr($firstline, 8) );
		}
		
		// Either way, remove this subject line from the template.
		$emailtext = substr($emailtext, strpos($emailtext, "\n"));
		
	} elseif (isset($data['subject'])) {
		$subject = $data['subject'];
	} else {
		$PAGE->error_message ("We don't have a subject line for the email, so it wasn't sent.");
		return false;
	}
	

	// Now merge all the tokens from $merge into $emailtext...
	$search = array();
	$replace = array();
	
	foreach ($merge as $key => $val) {
		$search[] = '/{'.$key.'}/';
		$replace[] = $val;
	}
	
	$emailtext = preg_replace($search, $replace, $emailtext);
	
	// Send it!
	$success = send_email ($data['to'], $subject, $emailtext);

	return $success;

}



function send_email ($to, $subject, $message) {
	// Use this rather than PHP's mail() direct, so we can make alterations
	// easily to all the emails we send out from the site.
	
	// eg, we might want to add a .sig to everything here...
	
	// Everything is BCC'd to REPORTLIST (unless it's already going to the list!).
	
	$headers = 
	 "From: TheyWorkForYou.com <" . CONTACTEMAIL . ">\r\n" .
     "Reply-To: TheyWorkForYou.com <" . CONTACTEMAIL . ">\r\n" .
     "X-Mailer: PHP/" . phpversion();
     
	if ($to != REPORTLIST) {
  		$headers .= "\r\nBcc: " . BCCADDRESS;
	}
     
	debug('EMAIL', "Sending email to $to with subject of '$subject'");

	$success = mail ($to, $subject, $message, $headers);

	return $success;
}



///////////////////////////////
// Cal's functions from
// http://www.iamcal.com/publish/article.php?id=13

// Call this with a key name to get a GET or POST variable.
function get_http_var ($name, $default=''){
	global $HTTP_GET_VARS, $HTTP_POST_VARS;
	if (arrayKeyExists($name, $HTTP_GET_VARS)) {
		return clean_var($HTTP_GET_VARS[$name]);
	}
	if (arrayKeyExists($name, $HTTP_POST_VARS)) {
		return clean_var($HTTP_POST_VARS[$name]);
	}
	return $default;
}

function clean_var ($a){
	return (ini_get("magic_quotes_gpc") == 1) ? recursive_strip($a) : $a;
}

function recursive_strip ($a){
	if (is_array($a)) {
		while (list($key, $val) = each($a)) {
			$a[$key] = recursive_strip($val);
		}
	} else {
		$a = StripSlashes($a);
	}
	return $a;
}


// Call this with a key name to get a COOKIE variable.
function get_cookie_var($name, $default=''){
	global $HTTP_COOKIE_VARS;
	if (arrayKeyExists($name, $HTTP_COOKIE_VARS)) {
		return clean_var($HTTP_COOKIE_VARS[$name]);
	}
	return $default;
}
///////////////////////////////



// Because array_key_exists() doesn't exist prior to PHP v4.1.0
function arrayKeyExists($key, $search) {
   if (in_array($key, array_keys($search))) {
       return true;
   } else {
       return false;
   }
}


// Pass it an array of key names that should not be generated as
// hidden form variables. It then outputs hidden form variables 
// based on the session_vars for this page.
function hidden_form_vars ($omit = array()) {
	global $DATA, $this_page;
	
	$session_vars = $DATA->page_metadata($this_page, "session_vars");

	foreach ($session_vars as $n => $key) {
		if (!in_array($key, $omit)) {
			print "<input type=\"hidden\" name=\"$key\" value=\"" . htmlentities(get_http_var($key)) . "\" />\n";
		}
	}
}



// Deprecated. Use hidden_form_vars, above, instead.
function hidden_vars ($omit = array()) {
	global $DATA;
	
	foreach ($args as $key => $val) {
		if (!in_array($key, $omit)) {
			print "<input type=\"hidden\" name=\"$key\" value=\"" . htmlspecialchars($val) . "\" />\n";
		}	
	}
}

function make_ranking($rank)
{
    # 11th, 12th, 13th use "th" not "st", "nd", "rd"
    if (floor(($rank % 100) / 10) == 1)
        return $rank . "th"; 
    # 1st
    if ($rank % 10 == 1)
        return $rank . "st";
    # 2nd
    if ($rank % 10 == 2)
        return $rank . "nd"; 
    # 3rd
    if ($rank % 10 == 3)
        return $rank . "rd"; 
    # Everything else use th
    return $rank . "th";
}

function make_plural($word, $number)
{
    if ($number == 1)
        return $word;
    return $word . "s";
}

?>
