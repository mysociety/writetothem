</div>
<div id="footer">
The new FaxYourMP.  
<a href="/about-us">Built by 
<!--<img src="/images/mysociety_sm.gif" border="0" alt="mySociety" />-->
mySociety</a>.  
<a href="/about-copyright">Data by GovEval</a>.
<a href="http://www.m247.com/">Powered by M247</a>
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

if (OPTION_WEB_DOMAIN == 'writetothem.com') {
?>
<!-- Piwik -->
<script type="text/javascript">
var pkBaseURL = (("https:" == document.location.protocol) ? "https://piwik.mysociety.org/" : "http://piwik.mysociety.org/");
document.write(unescape("%3Cscript src='" + pkBaseURL + "piwik.js' type='text/javascript'%3E%3C/script%3E"));
</script><script type="text/javascript">
try {
var piwikTracker = Piwik.getTracker(pkBaseURL + "piwik.php", 2);
<? 
if ($values['title'] && preg_match('/Now write your message to/', $values['title'])){
  $values['tracking_title'] = 'Now write your message';
}else{
  $values['tracking_title'] = $values['title'];
}
?>
piwikTracker.setDocumentTitle("<?php echo $values['tracking_title'] ?>");
piwikTracker.trackPageView();
piwikTracker.enableLinkTracking();
} catch( err ) {}
</script><noscript><p><img src="http://piwik.mysociety.org/piwik.php?idsite=2" style="border:0" alt=""/></p></noscript>
<!-- End Piwik Tag -->
<?
}

echo '</body></html>';
