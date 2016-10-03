package FYR::EmailSettings;

my $color_cyan = '#4695b3';
my $color_cyan_dark = '#0c5875';
my $color_green = '#46b350';
my $color_red = '#cc5350';
my $color_orange = '#ffa500';
my $color_cream = '#f4f3ef';
my $color_cream_light = '#faf9f5';
my $color_brown = '#696966';
my $color_grey = '#999999';
my $color_grey_light = '#d9d9d9';
my $color_white = '#ffffff';
my $color_black = '#000000';

my $link_text_color = $color_cyan;
my $link_hover_text_color = $color_cyan_dark;

my $body_background_color = $color_cream;
my $body_font_family = "Georgia, Times, serif",
my $body_text_color = $color_grey;

my $header_background_color = $color_cyan;
my $header_text_color = $color_white;
my $header_padding = "20px 30px"; # a full CSS padding property (eg: top/right/bottom/left)

my $logo_width = "236"; # pixel measurement, but without 'px' suffix
my $logo_height = "44"; # pixel measurement, but without 'px' suffix
my $logo_font_size = "32px";

my $primary_column_background_color = $color_white;
my $primary_column_text_color = $color_black;
my $primary_column_border_color = $color_grey_light;

my $secondary_column_background_color = $color_cream_light;
my $secondary_column_text_color = $color_brown;
my $secondary_column_border_color = $color_grey_light;

my $column_padding = "30"; # a single CSS pixel measurement without the "px" suffix
my $column_padding_px = $column_padding . 'px';

my $button_border_radius = "4px"; # a full CSS border-radius property
my $button_background_color = $color_green;
my $button_background_color_positive = $color_green;
my $button_background_color_negative = $color_red;
my $button_text_color = $color_white;
my $button_text_color_positive = $button_text_color;
my $button_text_color_negative = $button_text_color;
my $button_font_weight = "bold";
my $button_font_size = "20px";
my $button_line_height = "24px";

my $td_style = "font-family: $body_font_family; font-size: 18px; line-height: 26px; font-weight: normal; text-align: left; border-collapse: collapse;";

sub make_button {
    my ($background_color, $text_color) = @_;
    # https://litmus.com/blog/a-guide-to-bulletproof-buttons-in-email-design
    return "display: inline-block; border: 10px solid $background_color; border-width: 10px 20px; border-radius: $button_border_radius; background-color: $background_color; color: $text_color; font-size: $button_font_size; line-height: $button_line_height; font-weight: $button_font_weight; text-decoration: underline;",
}

sub make_dot {
    my ($color) = @_;
    return "<h1 style='width: 12px; height: 12px; overflow: hidden; border-radius: 12px; background-color: $color; font-size: 12px; line-height: 12px; margin: 0;'></h1>",
}

sub get_settings {
    # Variables used inside the email templates.
    return {
        logo_width => $logo_width,
        logo_height => $logo_height,
        column_padding_px => $column_padding_px,
        table_reset => 'cellspacing="0" cellpadding="0" border="0" width="100%"',
        wrapper_table_reset => 'cellspacing="0" cellpadding="5" border="0" width="100%"',

        link_style => "color: $link_text_color;",
        link_hover_style => "text-decoration: none; color: $link_hover_text_color;",

        td_style => $td_style,

        body_style => "margin: 0; padding: 0; background: $body_background_color;",
        wrapper_style => "$td_style background: $body_background_color;",

        hint_style => "padding: $column_padding_px; font-family: Helvetica, Arial, sans-serif; color: $body_text_color; font-size: 14px; line-height: 20px;",
        header_style => "padding: $header_padding; background: $header_background_color; color: $header_text_color;",

        primary_column_style => "padding: $column_padding_px; background-color: $primary_column_background_color; color: $primary_column_text_color; border: 1px solid $primary_column_border_color; border-top: none;",

        secondary_column_style => "padding: $column_padding_px; background-color: $secondary_column_background_color; color: $secondary_column_text_color; border: 1px solid $secondary_column_border_color;",

        column_separator_style => "padding: 0 $column_padding_px 0 $column_padding_px; height: $column_padding_px;",

        logo_style => "font-size: $logo_font_size; line-height: " . $logo_height . "px; vertical-align: middle;",
        h1_style => "margin: 0 0 30px 0; font-size: 32px; line-height: 36px;",
        h2_style => "margin: 0 0 30px 0; font-size: 24px; line-height: 28px;",
        p_style => "margin: 0 0 0.8em 0;",
        preformatted_style => "white-space: pre-wrap;",
        button_style => make_button($button_background_color, $button_text_color),
        questionnaire_option_td_style => "$td_style padding: 0.5em; border-top: 1px solid #eee; border-bottom: 1px solid #eee;",
        questionnaire_dot_good => make_dot($color_green),
        questionnaire_dot_medium => make_dot($color_orange),
        questionnaire_dot_bad => make_dot($color_red),
    };
}

1;
