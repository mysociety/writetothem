<?php

$color_cyan = '#4695b3';
$color_cyan_dark = '#0c5875';
$color_green = '#46b350';
$color_red = '#cc5350';
$color_cream = '#f4f3ef';
$color_cream_light = '#faf9f5';
$color_brown = '#696966';
$color_grey = '#999999';
$color_grey_light = '#d9d9d9';
$color_white = '#ffffff';
$color_black = '#000000';

$link_text_color = $color_cyan;
$link_hover_text_color = $color_cyan_dark;

$body_background_color = $color_cream;
$body_font_family = "Georgia, Times, serif";
$body_text_color = $color_grey;

$header_background_color = $color_cyan;
$header_text_color = $color_white;
$header_padding = "20px 30px"; # a full CSS padding property (eg: top/right/bottom/left)

$logo_width = "236"; # pixel measurement, but without 'px' suffix
$logo_height = "44"; # pixel measurement, but without 'px' suffix
$logo_font_size = "32px";

$primary_column_background_color = $color_white;
$primary_column_text_color = $color_black;
$primary_column_border_color = $color_grey_light;

$secondary_column_background_color = $color_cream_light;
$secondary_column_text_color = $color_brown;
$secondary_column_border_color = $color_grey_light;

$column_padding = "30"; # a single CSS pixel measurement without the "px" suffix
$column_padding_px = $column_padding . 'px';

$button_border_radius = "4px"; # a full CSS border-radius property
$button_background_color = $color_green;
$button_background_color_positive = $color_green;
$button_background_color_negative = $color_red;
$button_text_color = $color_white;
$button_text_color_positive = $button_text_color;
$button_text_color_negative = $button_text_color;
$button_font_weight = "bold";
$button_font_size = "20px";
$button_line_height = "24px";

# Variables used inside the email templates.

$table_reset = 'cellspacing="0" cellpadding="0" border="0" width="100%"';
$wrapper_table_reset = 'cellspacing="0" cellpadding="5" border="0" width="100%"';

$link_style = "color: $link_text_color;";
$link_hover_style = "text-decoration: none; color: $link_hover_text_color;";

$td_style = "font-family: $body_font_family; font-size: 18px; line-height: 26px; font-weight: normal; text-align: left;";

$body_style = "margin: 0; padding: 0; background: $body_background_color;";
$wrapper_style = "$td_style background: $body_background_color;";

$hint_style = "padding: $column_padding_px; font-family: Helvetica, Arial, sans-serif; color: $body_text_color; font-size: 14px; line-height: 20px;";
$header_style = "padding: $header_padding; background: $header_background_color; color: $header_text_color;";

$primary_column_style = "padding: $column_padding_px; background-color: $primary_column_background_color; color: $primary_column_text_color; border: 1px solid $primary_column_border_color; border-top: none;";

$secondary_column_style = "padding: $column_padding_px; background-color: $secondary_column_background_color; color: $secondary_column_text_color; border: 1px solid $secondary_column_border_color;";

$column_separator_style = "padding: 0 $column_padding_px 0 $column_padding_px; height: $column_padding_px;";

$logo_style = "font-size: $logo_font_size; line-height: " . $logo_height . "px; vertical-align: middle;";
$h1_style = "margin: 0 0 30px 0; font-size: 32px; line-height: 36px;";
$h2_style = "margin: 0 0 30px 0; font-size: 24px; line-height: 28px;";
$p_style = "margin: 0 0 0.8em 0;";

function make_button($background_color, $text_color){
  # https://litmus.com/blog/a-guide-to-bulletproof-buttons-in-email-design
  global $button_border_radius;
  global $button_font_weight;
  global $button_font_size;
  global $button_line_height;
  return "display: inline-block; border: 10px solid $background_color; border-width: 10px 20px; border-radius: $button_border_radius; background-color: $background_color; color: $text_color; font-size: $button_font_size; line-height: $button_line_height; font-weight: $button_font_weight; text-decoration: underline;";
}
$button_style = make_button($button_background_color, $button_text_color);
$positive_button_style = make_button($button_background_color_positive, $button_text_color_positive);
$negative_button_style = make_button($button_background_color_negative, $button_text_color_negative);
