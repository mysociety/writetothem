#!/usr/bin/perl
#
# FYR/Fax.pm:
# Rendering and sending of faxes.
#
# Copyright (c) 2004 UK Citizens Online Democracy. All rights reserved.
# Email: chris@mysociety.org; WWW: http://www.mysociety.org/
#
# $Id: Fax.pm,v 1.1 2004-11-18 13:27:57 chris Exp $
#

package FYR::Fax;

use strict;

use GD;
use HTML::Entities;
use utf8;

use FYR::EmailTemplate;
use mySociety::Config;

# Faxes are generated at a resolution of 204x196 dpi (this is the "fine"
# setting on a fax machine). 196 lines per inch is apparently a historic
# standard from ancient printer paper-feed stepper motors, and hence the
# excuse for using different horizontal and vertical resolutions.
use constant H_RESOLUTION => 204;
use constant V_RESOLUTION => 196;

# The maximum page width (and the width which sendfax(8) expects) is 1728
# pixels (just under 8.5").
use constant FAX_PAGE_CX => 1728;

# A4 paper is (approximately) 8.25" x 11.75".
use constant A4_WIDTH => 8.25;
use constant A4_HEIGHT => 11.75;

# We must choose a margin which ensures that all text lands inside the fax
# raster, and inside the printable area of an A4 page (many fax machines now
# use plain paper).
use constant A4_MARGIN => 1.0;  # inches

# This allows us to compute the image height, in pixels;
use constant FAX_PAGE_CY => int(V_RESOLUTION * (A4_HEIGHT - 0.1 * A4_MARGIN));

# the width of the text;
use constant TEXT_CX => int(H_RESOLUTION * (A4_WIDTH - 2 * A4_MARGIN));

# the height of the text;
use constant TEXT_CY => int(V_RESOLUTION * (A4_HEIGHT - 2 * A4_MARGIN));

# the margin at the top; and
use constant TMARGIN_CY => int(A4_MARGIN * V_RESOLUTION);

# the offset of the text from the LHS. We assume that the receiving fax centers
# the raster across the page;
use constant LMARGIN_CX => int((FAX_PAGE_CX - A4_WIDTH * H_RESOLUTION) / 2 + A4_MARGIN * H_RESOLUTION);

# Text size for the body text, in points.
use constant FONT_SIZE_BODY => 13;

# Smallprint font size, in points.
use constant FONT_SIZE_SMALL => 9;

# Space we skip for each line. NB we do this ourselves, rather than relying on
# the GD linespacing option.
use constant LINE_ADVANCE => 1.25;

# How large a space we leave above the footer on each page.
use constant FOOTER_MARGIN => 0.25;
use constant FMARGIN_CY => int(FOOTER_MARGIN * V_RESOLUTION);

# Options we pass to GD for writing text.
use constant GD_TEXT_OPTIONS => {
        charmap => 'Unicode',
        resolution => sprintf('%d,%d', H_RESOLUTION, V_RESOLUTION),
        kerning => 0
    };

=head1 NAME

FYR::Fax

=head1 DESCRIPTION

Create fax bitmaps; send same.

=head1 FUNCTIONS

=over 4

=cut

# fax_template NAME
# Find the fax template with the given NAME. We look for the templates
# directory in ../ and ../../. Nasty.
sub fax_template ($) {
    my ($name) = @_;
    $name = "templates/faxes/$name";
    foreach (qw(.. ../..)) {
        return "$_/$name" if (-e "$_/$name");
    }
    die "unable to locate email template for '$name'";
}


# text_dimensions TEXT FONTSIZE
# Return the width and height of TEXT, rendered on the GD page.
sub text_dimensions ($$) {
    my ($text, $size) = @_;
    encode_entities($text, '&'); # GD madness
    my @bounds = GD::Image->stringFT(0,
                        mySociety::Config::get('FAX_FONT'),
                        $size, 0, 0, 0,
                        $text,
                        GD_TEXT_OPTIONS
                    );
    return ($bounds[2] - $bounds[0], int(abs($bounds[1] - $bounds[4]) * LINE_ADVANCE));
}

# format_text IMAGE TEXT X Y WIDTH HEIGHT [NODRAW] [SIZE]
# Format TEXT into IMAGE in the WIDTH by HEIGHT rectangle whose top-left corner
# is at (X, Y). Returns in list context the number of rows consumed writing
# text, and any text which has not been output. Interprets \n as a line break.
# If NODRAW is true, do the laying out but don't actually draw the text. SIZE,
# if specified, is the point size to use; if not specified, the default is
# used.
sub format_text ($$$$$$;$$) {
    my ($img, $text, $x0, $y0, $width, $height, $nodraw, $size) = @_;
    $nodraw ||= 0;
    $size ||= FONT_SIZE_BODY;
    my @stuff = split(/(\n|[ \t]+)/, $text);
    # Coordinates relative to top left of rectangle.
    my ($x, $y) = (0, 0);
    
    # Advance one line so that first line is within rectangle.
    $y += (text_dimensions("M", $size))[1];

    while (@stuff > 0 && $y < $height) {
        my $word = shift(@stuff);
        if ($word eq "\n") {
            # Line break. Go to beginning of next line.
            $x = 0;
            $y += (text_dimensions("M", $size))[1];
        } elsif ($word =~ /^[ \t]+$/) {
            # Whitespace. Advance, possibly to the next line.
            $x += (text_dimensions($word, $size))[0];
            if ($x >= $width) {
                $x = 0;
                $y += (text_dimensions("M", $size))[1];
            }
        } else {
            my ($w, $h) = text_dimensions($word, $size);
            if ($w <= $width - $x) {
                # Just render it here.
                $img->stringFT(-1,  # no antialiasing
                        mySociety::Config::get('FAX_FONT'),
                        $size,
                        0,
                        $x0 + $x,
                        $y0 + $y,
                        $word,
                        GD_TEXT_OPTIONS
                    ) unless ($nodraw);
                $x += $w;
            } elsif ($x == 0 && $w > $width) {
                # Special case. We have to break a long word into line-length
                # chunks.
                my ($ll, $lh) = (0, length($word));
                while ($lh > $ll + 1) {
                    my $l2 = ($ll + $lh) / 2;
                    my $w2 = (text_dimensions(substr($word, 0, $l2), $size))[0];
                    if ($w2 < $width) {
                        $ll = $l2;
                    } else {
                        $lh = $l2;
                    }
                }
                unshift(@stuff, substr($word, 0, $ll), substr($word, $ll));
            } else {
                # Just stick the word back on the stack to process, with a leading line-break.
                unshift(@stuff, "\n", $word);
            }
        }
    }

    return ($y, join('', @stuff));
}

# format_postal_address IMAGE TEXT
# Format TEXT as a postal address, right-aligned in the appropriate place on
# IMAGE. Returns the number of rows of the image used for the address.
sub format_postal_address ($$) {
    my ($img, $text) = @_;
    my @lines = split(/\n/, $text);
    my $width = TEXT_CX / 2;

    # Find largest line width.
    my $max = 0;
    foreach (@lines) {
        my ($w, $h) = text_dimensions($_, FONT_SIZE_BODY);
        $max = $w if ($w > $max);
    }

    $max *= 1.05;
    $max = TEXT_CX / 2 if ($max > TEXT_CX / 2);
    $max = int($max);
    
    my ($x, $y) = (LMARGIN_CX + (TEXT_CX) - $max, TMARGIN_CY);

    ($y, my $remainder) = format_text($img, $text, $x, $y, $max, TEXT_CY);
    # XXX Assume that remainder is '', i.e. address fits on one page.
    return $y;
}

=item make_representative_fax MESSAGE



=cut
sub make_representative_fax ($) {
    my ($msg) = @_;

    my @pages = ( );

    my $im = new GD::Image(FAX_PAGE_CX, FAX_PAGE_CY);
    $im->colorAllocate(255, 255, 255);
    $im->colorAllocate(0, 0, 0);

    # First thing to do is to format the fax footer. The purpose of this is
    # just to figure out how much space it takes up, so that we can subtract
    # that from the space available for the text.
    my $text = FYR::EmailTemplate::format(fax_template('footer'), {
                    this_page => '10',
                    total_pages => '99',
                    representative_url => 'https://secure.writetothem.com/Roign243ohn4h8n8n9n32h4nh'
                });
    # Remove blank lines and trailing carriage returns.
    $text =~ s#\n+#\n#sg;
    $text =~ s#\n$##s;
    my ($footerheight, $remainder) = format_text($im, $text, LMARGIN_CX, TMARGIN_CY, TEXT_CX, TEXT_CY, 1, FONT_SIZE_SMALL);

    my $addr = $msg->{sender_addr};
    $addr .= "\n\n" . "Phone: $msg->{sender_phone}" if (exists($msg->{sender_phone}));
    $addr .= "\n\n" . "Email: $msg->{sender_email}";

    # Coordinates relative to text area.
    my ($x, $y) = (0, format_postal_address($im, $addr));
    my $pagenum = 0;

    $text = "\n\n" . $msg->{message};
    while (length($text) > 0) {
        ++$pagenum;
        my ($dy, $text2) = format_text($im, $text, $x + LMARGIN_CX, $y + TMARGIN_CY, TEXT_CX, (TEXT_CY - $y - $footerheight - FMARGIN_CY));

        push(@pages, $im);

        ($x, $y) = (0, 0);
        $im = new GD::Image(FAX_PAGE_CX, FAX_PAGE_CY);
        $im->colorAllocate(255, 255, 255);
        $im->colorAllocate(0, 0, 0);
        $text = $text2;
    }

    # Now go back over each page and write the appropriate footer.
    for (my $i = 0; $i < @pages; ++$i) {
        $text = FYR::EmailTemplate::format(fax_template('footer'), {
                    this_page => $i + 1,
                    total_pages => scalar(@pages),
                    representative_url => 'https://secure.writetothem.com/Roign243ohn4h8n8n9n32h4nh'
                }, 1);
        # Remove blank lines and trailing carriage returns.
        $text =~ s#\n+#\n#sg;
        $text =~ s#\n$##s;
        format_text($pages[$i], $text, $x + LMARGIN_CX, TMARGIN_CY + TEXT_CY - $footerheight, TEXT_CX, $footerheight, 0, FONT_SIZE_SMALL);
        $pages[$i]->setThickness(2);
        $pages[$i]->line(LMARGIN_CX, TMARGIN_CY + TEXT_CY - $footerheight - 10, LMARGIN_CX + TEXT_CX, TMARGIN_CY + TEXT_CY - $footerheight - 10, 1);

        open(PNG, ">/tmp/page-" . ($i + 1) . ".png");
        print PNG $pages[$i]->png();
        close(PNG);
        undef($pages[$i]);
    }
}

1;
