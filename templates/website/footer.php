</div>
<div id="footer">
The new FaxYourMP.  
<a href="/about-us">Built by 
<!--<img src="/images/mysociety_sm.gif" border="0" alt="mySociety" />-->
mySociety</a>.  
<a href="/about-copyright">Data by GovEval</a>.
<br>
<? $links = array(
    '/about-qa'=>'Help',
    '/about-contact' => 'Contact Us',
    '/about-guidelines' => 'Guidelines for Campaigners',
    '/about-linktous' => 'Link to us',
    '/stats' => 'Statistics',
    '/about-copyright' => 'Copyright', /* for Affero GPL */
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
?>
</div>
<?
    /* User-tracking. */
    global $track;
    track_event($track);
?>
</body>
</html>

