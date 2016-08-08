<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Strict//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-strict.dtd">
<html xmlns="http://www.w3.org/1999/xhtml">
<head>
  <title></title>
  <meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
  <style type="text/css">
  a { <?= $values['link_style'] ?> }
  a:hover { <?= $values['link_hover_style'] ?> }

  @media only screen and (max-width: 619px) {
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
<body style="<?= $values['body_style'] ?>">
  <table <?= $values['wrapper_table_reset'] ?> style="<?= $values['wrapper_style'] ?>">
    <tr>
      <th class="top-level-cell"></th>
      <th width="620" style="<?= $values['td_style'] ?> min-width: 520px; padding-top: <?= $values['column_padding_px'] ?>;" id="main" class="top-level-cell">
        <table <?= $values['table_reset'] ?>>
          <tr>
            <th style="<?= $values['td_style'] ?><?= $values['header_style'] ?>">
                <img src="cid:logo.gif" width="<?= $values['logo_width'] ?>" height="<?= $values['logo_height'] ?>" alt="WriteToThem" style="<?= $values['logo_style'] ?>"/>
            </th>
          </tr>
          <tr>
