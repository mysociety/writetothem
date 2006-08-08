</div>
<div id="footer">
The new FaxYourMP.  
<a href="/about-us">Built by 
<!--<img src="/images/mysociety_sm.gif" border="0" alt="mySociety" />-->
mySociety</a>.  
<a href="/about-copyright">Data by GovEval</a>.
<a href="http://www.easynet.net/publicsector/">Powered by EasyNet</a>
<br>
<? $links = array(
    '/about-qa'=>'Help',
    '/about-contact' => 'Contact WriteToThem.com',
    /* '/about-guidelines' => 'Guidelines for Campaigners', */  /* we don't get much campaign abuse any more, so prominent link not so needed? */
    '/lords' => 'Lords',
    '/stats' => 'Statistics',
    '/about-linktous' => 'Link to us',
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
</body>
</html>
