<div class="mysoc-footer row" role="contentinfo">
    <div class="large-10 large-centered columns" role="navigation">
        <div class="row">

            <div class="large-5 columns">
                <h2 class="mysoc-footer__site-name">WriteToThem</h2>
                <div class="mysoc-footer__site-description">
                    <p>Making it easy to write to the politicians who represent you &ndash; even if you don&rsquo;t know who they are.</p>
                </div>
            </div>

            <div class="large-4 columns">
                <nav class="mysoc-footer__links">
                    <ul>
                        <li role="presentation"><a href="/about-qa">Help</a></li>
                        <li role="presentation"><a href="/about-contact">Contact Us</a></li>
                        <li role="presentation"><a href="/about-linktous">Link To Us</a></li>
                    </ul>
                    <ul>
                        <li role="presentation"><a href="/about-copyright">Copyright</a></li>
                        <li role="presentation"><a href="/about-privacy">Privacy &amp; Cookies</a></li>
                    </ul>
                </nav>
            </div>

            <?php if (!array_key_exists('donate_shown', $values)) { ?>
            <div class="large-3 columns">
                <div class="mysoc-footer__donate">
                    <p>Your donations keep this site and others like it running</p>
                    <a href="https://www.mysociety.org/donate?utm_source=writetothem.com&utm_content=footer+donate+now&utm_medium=link&utm_campaign=mysoc_footer" class="mysoc-footer__donate__button">Donate now</a>
                </div>
            </div>
            <?php } ?>

        </div>
        <hr class="mysoc-footer__divider" role="presentation">
        <div class="row">

            <div class="large-5 columns">
                <div class="mysoc-footer__orgs">
                    <p class="mysoc-footer__org">
                        Built by
                        <a href="https://www.mysociety.org?utm_source=writetothem.com&utm_content=footer+logo&utm_medium=link&utm_campaign=mysoc_footer" class="mysoc-footer__org__logo mysoc-footer__org__logo--mysociety">mySociety</a>
                    </p>
                </div>
            </div>

            <div class="large-4 columns">
                <div class="mysoc-footer__legal">
                    <p>
                        <a href="/about-copyright">Data by GovEval</a>.
                        <a href="http://www.bytemark.co.uk/">Hosted by Bytemark</a>.
                        <?php if(isset($values['credit'])) echo $values['credit']; ?>.
                    </p>
                    <p>mySociety Limited is a project of UK Citizens Online Democracy, a registered charity in England and Wales. For full details visit <a href="https://www.mysociety.org?utm_source=writetothem.com&utm_content=footer+full+legal+details&utm_medium=link&utm_campaign=mysoc_footer">mysociety.org</a>.</p>
                </div>
            </div>

            <div class="large-3 columns">
                <ul class="mysoc-footer__badges">
                    <li role="presentation"><a href="https://github.com/mysociety/writetothem/" class="mysoc-footer__badge mysoc-footer__badge--github">Github</a></li>
                    <li role="presentation"><a href="https://twitter.com/mysociety" class="mysoc-footer__badge mysoc-footer__badge--twitter">Twitter</a></li>
                    <li role="presentation"><a href="https://www.facebook.com/mysociety" class="mysoc-footer__badge mysoc-footer__badge--facebook">Facebook</a></li>
                </ul>
            </div>

        </div>
    </div>
</div>

</div>
</div>
</div>

<script src="/static/js/vendor/jquery-1.8.3.min.js"></script>
<script src="/static/js/fancybox/jquery.fancybox-1.3.4.pack.js"></script>

<script>
    $(function() {
        $('.fancybox').each(function(){
            $(this).fancybox({
                href: $(this).prop('href').replace('#', '-')
            });
        });

        $('.facebook-share-button').on('click', function(e){
            e.preventDefault();
            FB.ui({
                method: 'share',
                href: $(this).attr('data-url'),
                quote: $(this).attr('data-text')
            }, function(response){});
        });
    });
</script>

</body>
</html>
