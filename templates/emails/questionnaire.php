<?php

include '_functions.php';
include '_settings.php';

$recipient_name = 'David Cameron';
$recipient_position = 'MP';
$recipient_position_plural = 'MPs';
$their_constituents = 'their constituents';
$weeks_ago = 'Two';
$contact_email = 'support@writetothem.com';

include '_top.php';

?><th style="<?= $td_style ?> <?= $primary_column_style ?>">

  <h1 style="<?= $h1_style ?>">Did <?= $recipient_name ?> reply to your letter?</h1>
  <p style="<?= $p_style ?>"><?= $weeks_ago ?> weeks ago we sent your letter (below) to <?= $recipient_name ?>, your
<?= $recipient_position ?>. Have you received a reply* yet?</p>
  <table <?= $table_reset ?> style="margin: 30px 0">
    <tr>
      <th style="<?= $td_style ?> padding: 5px 0; text-align: center;">
        <a style="<?= $positive_button_style ?>" href="#">I’ve had a reply</a>
      </th>
      <th style="<?= $td_style ?> padding: 5px 0; text-align: center;">
        <a style="<?= $negative_button_style ?>" href="#">No reply yet</a>
      </th>
    </tr>
  </table>
  <p style="<?= $p_style ?>; font-style: italic; font-size: ">* We don’t count acknowledgements as full replies. So if you’ve only had an acknowledgement, choose the “No reply yet” option.</p>
  <h2 style="<?= $h2_style ?> margin-top: 30px;">Why are we asking?</h2>
  <p style="<?= $p_style ?>">Your feedback will allow us to publish performance tables of the responsiveness
of all the politicians in the UK.</p>
  <p style="<?= $p_style ?>">The majority of <?= $recipient_position_plural ?> deserve credit and respect for their conscientiousness as they respond promptly and diligently to the needs and views of <?= $their_constituents ?>. Likewise, we’re keen to expose the minority of <?= $recipient_position_plural ?> who don’t.</p>
  <p style="<?= $p_style ?>">The letter you sent to your <?= $recipient_position ?> will be deleted from our database within the next two weeks. <a href="https://www.writetothem.com/about-qa#personal">Read our full privacy policy.</a></p>

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
