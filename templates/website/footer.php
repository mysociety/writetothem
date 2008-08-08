</div>
<div id="footer">
The new FaxYourMP.  
<a href="/about-us">Built by 
<!--<img src="/images/mysociety_sm.gif" border="0" alt="mySociety" />-->
mySociety</a>.  
<a href="/about-copyright">Data by GovEval</a>.
<a href="http://www.easynet.net/publicsector/">Powered by Easynet</a>
<br>
<? $links = array(
    '/about-qa'=>'Help',
    '/about-contact' => 'Contact WriteToThem.com',
    /* '/about-guidelines' => 'Guidelines for Campaigners', */  /* we don't get much campaign abuse any more, so prominent link not so needed? */
    '/lords' => 'Lords',
    '/stats' => 'Statistics',
    '/about-linktous' => 'Link to us',
    '/about-copyright' => 'Copyright', /* for GNU Affero GPL */
);
foreach ($links as $uri => $text) {
    $f = '';
    if ($_SERVER['REQUEST_URI'] != $uri)
        $f .= '<a href="' . $uri . '">';
    $f .= $text;
    if ($_SERVER['REQUEST_URI'] != $uri)
        $f .= '</a>';
    $footer[] = $f;
}
print join(' | ', $footer);
echo '</div>';

global $track;
if (isset($track) && $track) {
    track_event($track);
}

if (OPTION_WEB_DOMAIN == 'writetothem.com') {
?>
<!-- Piwik -->
<a href="http://piwik.org" title="Web analytics" onclick="window.open(this.href);return(false);">
<script type="text/javascript">
var pkBaseURL = (("https:" == document.location.protocol) ? "https://piwik.mysociety.org/" : "http://piwik.mysociety.org/");
document.write(unescape("%3Cscript src='" + pkBaseURL + "piwik.js' type='text/javascript'%3E%3C/script%3E"));
</script>
<script type="text/javascript">
<!--
piwik_action_name = '';
piwik_idsite = 2;
piwik_url = pkBaseURL + "piwik.php";
piwik_log(piwik_action_name, piwik_idsite, piwik_url);
//-->
</script><object>
<noscript><p>Web analytics <img src="http://piwik.mysociety.org/piwik.php" style="border:0" alt="piwik"/></p>
</noscript></object></a>
<!-- /Piwik --> 
<?
}

echo '</body></html>';
