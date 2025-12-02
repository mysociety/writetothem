<div class="mysoc-footer row" role="contentinfo">
    <div class="large-10 large-centered columns" role="navigation">
        <div class="row">

            <div class="large-5 columns">
                <h2 class="mysoc-footer__site-name"><?= _('WriteToThem') ?></h2>
                <div class="mysoc-footer__site-description">
                <p><?= _('Making it easy to write to the politicians who represent you &ndash; even if you don&rsquo;t know who they are.') ?></p>
                </div>
            </div>

            <div class="large-4 columns">
                <nav class="mysoc-footer__links">
                    <ul>
                        <li role="presentation"><a href="/about-qa"><?= _('Help') ?></a></li>
                        <li role="presentation"><a href="/about-contact"><?= _('Contact Us') ?></a></li>
                        <li role="presentation"><a href="/about-linktous"><?= _('Link To Us') ?></a></li>
                    </ul>
                    <ul>
                        <li role="presentation"><a href="/about-copyright"><?= _('Copyright') ?></a></li>
                        <li role="presentation"><a href="/about-privacy"><?= _('Privacy') ?></a></li>
                    </ul>
                </nav>
            </div>

            <?php if (!array_key_exists('donate_shown', $values)) { ?>
            <div class="large-3 columns">
                <div class="mysoc-footer__donate">
                    <p><?= _('Your donations keep this site and others like it running') ?></p>
                    <a href="https://www.mysociety.org/donate?utm_source=writetothem.com&amp;utm_content=footer+donate+now&amp;utm_medium=link&amp;utm_campaign=mysoc_footer" class="mysoc-footer__donate__button"><?= _('Donate now') ?></a>
                </div>
            </div>
            <?php } ?>

        </div>
        <hr class="mysoc-footer__divider" role="presentation">
        <div class="row">

            <div class="large-5 columns">
                <div class="mysoc-footer__orgs">
                    <p class="mysoc-footer__org">
                        <?= _('Built by
                        <a href="https://www.mysociety.org?utm_source=writetothem.com&amp;utm_content=footer+logo&amp;utm_medium=link&amp;utm_campaign=mysoc_footer" class="mysoc-footer__org__logo mysoc-footer__org__logo--mysociety">mySociety</a>') ?>
                    </p>
                </div>
            </div>

            <div class="large-4 columns">
                <div class="mysoc-footer__legal">
                    <p>
                        <a href="/about-copyright"><?= _('Data by GovEval') ?></a>.
                        <a href="https://www.mythic-beasts.com/"><?= _('Hosted by Mythic Beasts') ?></a>.
                        <?php if(isset($values['credit'])) echo $values['credit'] . '.'; ?>
                    </p>
                    <p>
                        <?= _('<a href="https://www.mysociety.org?utm_source=writetothem.com&amp;utm_content=footer+full+legal+details&amp;utm_medium=link&amp;utm_campaign=mysoc_footer">mySociety</a>
                        is a registered charity in England and Wales (1076346)
                        and a limited company (03277032). We provide commercial
                        services through our wholly owned subsidiary
                        <a href="https://www.societyworks.org?utm_source=writetothem.com&amp;utm_content=footer+full+legal+details&amp;utm_medium=link&amp;utm_campaign=mysoc_footer">SocietyWorks Ltd</a>
                        (05798215).') ?>
                    </p>
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
<script src="/static/js/jquery.fixedthead.js"></script>
<script src="/static/js/main.js"></script>

</body>
</html>
