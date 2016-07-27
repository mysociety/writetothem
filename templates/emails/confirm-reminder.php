<?php

include '_functions.php';
include '_settings.php';

$recipient_name = 'David Cameron';
$time_ago = 'Yesterday';
$contact_email = 'support@writetothem.com';

include '_top.php';

?><th style="<?= $td_style ?> <?= $primary_column_style ?>">

  <h1 style="<?= $h1_style ?>">Last chance to confirm your letter to <?= $recipient_name ?>.</h1>
  <p style="<?= $p_style ?>"><?= $time_ago ?>, someone used <a href="https://www.writetothem.com">WriteToThem.com</a> to write a letter to <?= $recipient_name ?>, from your email account.</p>
  <p style="<?= $p_style ?>">If this was you, please click the link below, and we’ll send the letter right away!</p>
  <p style="margin: 30px auto; text-align: center">
    <a style="<?= $button_style ?>" href="#">Yes, send my letter!</a>
  </p>
  <p style="<?= $p_style ?>">If you didn’t write this letter, please let us know by sending an email to <a href="mailto:<?= $contact_email ?>"><?= $contact_email ?></a>.</p>

</th>

</tr><tr>
  <th style="<?= $td_style ?> <?= $column_separator_style; ?>"></th>
</tr><tr>

<th style="<?= $td_style ?> <?= $secondary_column_style ?>">
  <h2 style="<?= $h2_style ?>">Your letter to <?= $recipient_name ?></h2>
  <p style="<?= $p_style ?>">Dear MP,</p>
  <p style="<?= $p_style ?>">I think you are the best.</p>
  <p style="<?= $p_style ?>">Yours,</p>
  <p style="<?= $p_style ?>">Janet Bloggs</p>
</th><?php

include '_bottom.php';
