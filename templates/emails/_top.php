<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Strict//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-strict.dtd">
<html xmlns="http://www.w3.org/1999/xhtml">
<head>
  <title></title>
  <meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
  <style type="text/css">
  <?php # Styles here will be applied by everything except Gmail.com ?>
  a { <?= $link_style ?> }
  a:hover { <?= $link_hover_style ?> }

  @media only screen and (max-width: 619px) {
    <?php # remove wrapper's cellpadding because we want email to appear "full width" on narrow screens ?>
    .top-level-cell {
      padding: 0 !important;
    }
  }

  @media only screen and (max-width: 519px) {
    #main {
      min-width: 0 !important;
    }

    #main table, #main tbody, #main thead, #main tr, #main th {
      display: block !important;
    }
  }
  </style>
</head>
<body style="<?= $body_style ?>">
  <table <?= $wrapper_table_reset ?> style="<?= $wrapper_style ?>">
    <tr>
      <th class="top-level-cell"></th>
      <th width="620" style="<?= $td_style ?> min-width: 520px; padding-top: <?= $column_padding_px ?>;" id="main" class="top-level-cell">
        <table <?= $table_reset ?>>
          <tr>
            <th style="<?= $td_style ?><?= $header_style ?>">
                <img src="<?= inline_image('logo.gif') ?>" width="<?= $logo_width ?>" height="<?= $logo_height ?>" alt="WriteToThem" style="<?= $logo_style ?>"/>
            </th>
          </tr>
          <tr>
