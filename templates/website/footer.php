</div>
<div id="footer">
The new FaxYourMP.  Built by <a href="http://www.mysociety.org/">mySociety</a>.  Data kindly provided by <a href="http://www.goveval.com/">GovEval</a>.
<br>
<? $links = array(
    '/about-qa'=>'Help',
    '/about-contact' => 'Contact Us',
    '/about-guidelines' => 'Guidelines for Campaigners',
    '/about-copyright' => 'Copyright'
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

