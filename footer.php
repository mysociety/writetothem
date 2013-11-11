<div class="row footer">
    <div class="large-10 large-centered columns">
        <div class="row">
            <div class="footer-content">

                <div class="large-6 columns">

                    <ul class="inline-list footer-links-main">
                        <li><a href="/about-qa">Help</a></li>
                        <li><a href="/about-contact">Contact Us</a></li>
                        <li><a href="/about-copyright">Copyright</a></li>
                        <li><a href="/about-privacy">Privacy &amp; Cookies</a></li>
                        <li><a href="/about-linktous">Link To Us</a></li>
                        <li><a href="https://www.facebook.com/writetothem">Facebook</a></li>
                    </ul>

                </div>

                <div class="large-6 columns">

                    <ul class="inline-list footer-links-credits">
                        <li><a href="/about-us">Built by mySociety</a></li>
                        <li><a href="/about-copyright">Data by GovEval</a></li>
                        <li><a href="http://www.bytemark.co.uk/">Hosted by Bytemark</a></li>
                        <?php if(isset($values['credit'])) echo '<li>' . $values['credit'] . '</li>'; ?>
                    </ul>

                </div>

            </div>
        </div>
    </div>
</div>

</div>
</div>
</div>

<script>
    document.write('<script src=' +
    ('__proto__' in {} ? 'static/js/vendor/zepto' : 'static/js/vendor/jquery') +
    '.js><\/script>')
</script>

<script src="static/js/foundation/foundation.js"></script>

<script>
    $(document).foundation();
</script>

</body>
</html>
