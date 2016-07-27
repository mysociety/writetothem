<?php

include '_functions.php';
include '_settings.php';

$recipient_name = 'David Cameron';
$recipient_position = 'MP';
$contact_email = 'support@writetothem.com';

include '_top.php';

?><th style="<?= $td_style ?> <?= $primary_column_style ?>">

  <h1 style="<?= $h1_style ?>">Your letter could not be sent.</h1>
  <p style="<?= $p_style ?>">We’re very sorry, but it wasn’t possible to send your letter (below) to
<?= $recipient_name ?>, your <?= $recipient_position ?>.</p>
  <p style="<?= $p_style ?>">We tried for a week, but it appears that their mailbox was full and so your
message to them bounced back to us.</p>
  <p style="<?= $p_style ?>">Meanwhile, we’ve attached a copy of your letter to the bottom of this email, so that you can print it out and send it by other means. Or, try again via our site in a week or so – hopefully they’ll have cleared out some of their backlog by then.</p>

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
