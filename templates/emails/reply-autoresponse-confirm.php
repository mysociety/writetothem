<?php

include '_functions.php';
include '_settings.php';

$contact_email = 'support@writetothem.com';

include '_top.php';

?><th style="<?= $td_style ?> <?= $primary_column_style ?>">

  <h1 style="<?= $h1_style ?>">Sorry, we don’t accept email at this address.</h1>
  <p style="<?= $p_style ?> font-weight: bold;">This is an automatic response, we have NOT read your email.</p>
  <p style="<?= $p_style ?>">If you are trying to confirm your letter so that we can send it, please click the link below, and we’ll send the letter right away!</p>
  <p style="margin: 30px auto; text-align: center">
    <a style="<?= $button_style ?>" href="#">Yes, send my letter!</a>
  </p>
  <p style="<?= $p_style ?>">If you have a question or comment about the site, please send an email to <a href="mailto:<?= $contact_email ?>"><?= $contact_email ?></a>.</p>

</th><?php

include '_bottom.php';
