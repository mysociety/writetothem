#!/usr/bin/perl
#
# FYR/Fax.pm:
# Rendering and sending of faxes.
#
# Copyright (c) 2004 UK Citizens Online Democracy. All rights reserved.
# Email: chris@mysociety.org; WWW: http://www.mysociety.org/
#
# $Id: Fax.pm,v 1.33 2007-01-31 17:36:35 louise Exp $
#

# In this context soft errors are those which occur locally (out of disk space,
# unable to lock serial port), and hard errors are those which occur remotely
# (number engaged, no carrier).
package FYR::Fax::HardError;
use Error;
@FYR::Fax::HardError::ISA = qw(Error::Simple);

package FYR::Fax::SoftError;
use Error;
@FYR::Fax::SoftError::ISA = qw(Error::Simple);

package FYR::Fax;

use strict;

use Errno;
use Error qw(:try);
use Fcntl;
use File::stat;
use FindBin;
use GD;
use HTML::Entities;
use IO::Pipe;
use POSIX qw(strftime);
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

# Text size for the page footers, in points.
use constant FONT_SIZE_FOOTER => 9;

# Text size for the (optional) cover page, in points.
use constant FONT_SIZE_COVER => 16;

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
    my $fn = "$FindBin::Bin/../templates/faxes/$name";
    die "unable to locate fax template for '$name'" if (!-e $fn);
    return $fn;
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
    if (@bounds == 0) {
        throw FYR::Fax::SoftError("unable to compute text bounds (font missing?); GD error $@");
    } elsif (@bounds != 8) {
        throw FYR::Fax::SoftError(
                "GD::Image->stringFT returned bad bounding rectangle (got "
                    . scalar(@bounds) . " elements, wanted 8)");
    }

    # The order of the return values from stringFT changes between different
    # versions of libgd and hence GD.pm. Assume that the list is always ordered
    # as XYXYXYXY, and find the max and min.
    my ($minx, $maxx, $miny, $maxy);
    for (my $i = 0; $i < 4; ++$i) {
        my ($x, $y) = @bounds[2 * $i .. 2 * $i + 2];
        $minx = $x if (!defined($minx) || $x < $minx);
        $maxx = $x if (!defined($maxx) || $x > $maxx);
        $miny = $y if (!defined($miny) || $y < $miny);
        $maxy = $y if (!defined($maxy) || $y > $maxy);
    }
        
    return ($maxx - $minx, ($maxy - $miny) * LINE_ADVANCE);
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

    while (@stuff > 0 && $y <= $height) {
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

# format_postal_address IMAGE TEXT Y_OFFSET HEIGHT
# Format TEXT as a postal address, right-aligned in the appropriate place on
# IMAGE. Returns a boolean flag indicating whether there was enough room to
# format the address within the rectangle whose height is HEIGHT, and the 
# number of rows of the image used for the address (zero on failure).
sub format_postal_address ($$$$;$) {
    my ($img, $text, $y, $height, $force) = @_;
    my $x;
    $force ||= 0;
    my @lines = split(/\n/, $text);
    my $width = TEXT_CX / 2;
    my $required_height = 0;
    # Find largest line width.
    my $max = 0;
    foreach (@lines) {
        my ($w, $h) = text_dimensions($_, FONT_SIZE_BODY);
        $max = $w if ($w > $max);
        $required_height += $h;
    }

    $max *= 1.05; # XXX
    $max = TEXT_CX / 2 if ($max > TEXT_CX / 2);
    $max = int($max);
    
    ($x, $y) = (LMARGIN_CX + (TEXT_CX) - $max, $y);
   
    if ($force || ($required_height < $height)){
        # actually format the address to the image
        my ($y_offset, $remainder) = format_text($img, $text, $x, $y, $max, $height);
        return (1, $y_offset + $y);
    }else{
        return (0, 0);    
     }
}

# group_text GROUP_ID MESSAGE_ID
# Return text for the fax listing the other recipients of the same message
sub group_text($$){
    my ($group_id, $id) = @_;
    my $text = FYR::EmailTemplate::format(fax_template('group'),{other_recipient_list => FYR::Queue::other_recipient_list($group_id, $id)}, 1);
    return $text;
}

# footer_text PAGE TOTAL URL FAX
# Return footer text for the fax; PAGE is the current page number (from 1);
# TOTAL is the total number of pages; URL is the URL a representative may visit
# to forward the message to others; and FAX is the fax number to which the fax
# is being delivered.
sub footer_text ($$$$) {
    my ($page, $total, $url, $number) = @_;
    my $text = FYR::EmailTemplate::format(fax_template($page == 1 ? 'footer-first' : 'footer'), {
                        this_page => $page,
                        total_pages => $total,
                        representative_url => $url,
                        representative_fax => $number
                    }, 1);
    $text =~ s#\n+#\n#sg;
    $text =~ s#\n$##s;
    return $text;
}

# cover_text MESSAGE
# Format the cover-page text for a "via" MESSAGE.
sub cover_text ($) {
    my ($msg) = @_;
    my $coversheet = 'via-coversheet';
    return FYR::EmailTemplate::format(fax_template($coversheet), FYR::Queue::email_template_params($msg), 1);
}

# make_pbm_file IMAGE
# Create a PBM file on-disk containing the contents of IMAGE, and return its
# name.
sub make_pbm_file ($) {
    my ($im) = @_;
    
    # Nasty. We need to create a PBM file on disk, but getting bitmapped data
    # out of GD is not trivial. We use the "WBMP" format (part of the WAP
    # specification, apparently). This is designed for mobile phones with 2x3
    # pixel screens or whatever, and so is not quite the right thing for
    # thousands-of-pixels-square fax images. But it seems to work.
    my ($h, $name) = mySociety::Util::named_tempfile('.pbm');
    my ($p, $pid) = mySociety::Util::pipe_via('wbmptopbm', $h);
    $h->close() or die "close: $name: $!";;
    $p->print($im->wbmp(1)) or die "write: $name: $!";
    $p->close() or die "close: $!";

    waitpid($pid, 0);

    if ($?) {
        # Something went wrong.
        if ($? & 127) {
            die "wbmptopbm died with signal " . ($? & 127);
        } else {
            die "wbmptopbm exited with status " . ($? >> 8);
        }
    }

    # Now we might be in serious trouble. The fucking idiot who wrote wbmptopbm
    # bothered to *test* for write errors (as might occur on a full filesystem)
    # but didn't actually return an exit code about them:
    #
    #     # df -h .
    #     Filesystem            Size  Used Avail Use% Mounted on
    #     /tmp/fullfs          1003k 1003k     0 100% /mnt/fullfs
    #     # cat fish.wbmp | wbmptopbm > fish.pbm && echo success
    #     wbmptopbm: a file read or write error occurred at some point
    #     success
    #
    # So to check that this succeeded, we ought to parse the file to make sure
    # it's (at least) the correct size etc. But just looking at the file size
    # will catch the common cases.
    my $st = stat($name);
    if (!defined($st)) {
        my $err = $!;
        unlink($name);
        die "stat of temp file failed with: $err";
    } elsif ($st->size() < (($im->width() * $im->height()) / 8.)) {
        unlink($name);
        die "temporary file is too small (" . $st->size() . " bytes)";
    }

    return $name;
}

=item new_fax_page

Create a new page image for sending by fax. Returns 
the image with starting coordinates at the top left
corner of the page.

=cut
sub new_fax_page(){
    my $im = new GD::Image(FAX_PAGE_CX, FAX_PAGE_CY) or die "unable to create GD image: $!";
    $im->colorAllocate(255, 255, 255);
    $im->colorAllocate(0, 0, 0);
    return ($im, 0, 0);
}

=item make_representative_fax MESSAGE

Generates page images suitable for sending MESSAGE to its recipient by fax.
Returns in list context a list of temporary filenames containing PBM image
data for each page of the fax. These should be deleted by the caller.

=cut
sub make_representative_fax ($) {
    my ($msg) = @_;

    my @imgfiles = ( );

    my $url = '<not yet available -- sorry>';

    try {
        my @pages = ( );
        my ($im, $x, $y) = new_fax_page();
        push(@pages, $im);
        
        # First thing to do is to format the fax footers. The purpose of this
        # is just to figure out how much space it takes up, so that we can
        # subtract that from the space available for the text.
        my $text = footer_text(1, 99, $url, $msg->{recipient_fax});
        my $firstfooterheight = (format_text($im, $text, LMARGIN_CX, TMARGIN_CY, TEXT_CX, TEXT_CY, 1, FONT_SIZE_FOOTER))[0];
        $text = footer_text(2, 99, $url, $msg->{recipient_fax});
        my $footerheight = (format_text($im, $text, LMARGIN_CX, TMARGIN_CY, TEXT_CX, TEXT_CY, 1, FONT_SIZE_FOOTER))[0];

        my $pagenum = 1;
        my $remainder = '';
        my $f = $firstfooterheight;
        
        # For messages that are being sent as part of a group
        # add text letting the recipient know who else this
        # message has been sent to.
        if ($msg->{group_id}){
            $text = group_text($msg->{group_id}, $msg->{id}) . "\n";
            while(length($text) > 0){
                ($y, $remainder) = format_text($im, $text, $x + LMARGIN_CX, TMARGIN_CY, TEXT_CX, (TEXT_CY - $f - FMARGIN_CY));   
                if (length($remainder) > 0){
                    # now just use the regular footer
                    $f = $footerheight;
                    ($im, $x, $y) = new_fax_page();
                    ++$pagenum;
                    push(@pages, $im);
                }
                $text = $remainder;
            }
        }
        
        # add the sender's address
        my $addr = $msg->{sender_name} . "\n" . $msg->{sender_addr};
        $addr .= "\n\n" . "Phone: $msg->{sender_phone}" if (defined($msg->{sender_phone}));
        $addr .= "\n\n" . "Email: $msg->{sender_email}";

        # Also stick the date under the address, so that it's properly
        # right-justified.
        $addr .= "\n\n" . strftime("%A %d %B %Y", localtime($msg->{created}));

        # Try and format the address to current page
        my $success;
        ($success, $y) =  format_postal_address($im, $addr, $y + TMARGIN_CY, (TEXT_CY - $y - $f - FMARGIN_CY));
        if (!$success){
            # Need a new page
            $f = $footerheight;
            ($im, $x, $y) = new_fax_page();
            ++$pagenum;
            push(@pages, $im);

            #XXX Assumes that address on its own won't overflow page
            ($success, $y) = format_postal_address($im, $addr, TMARGIN_CY, (TEXT_CY - $y - $f - FMARGIN_CY), 1);
        }
        $text = "\n";

        # For members of the House of Lords whose messages reach them via the
        # Lords Lobby fax machine (all / almost all?), then format their
        # address at the top of the fax.
        if ($msg->{recipient_via} && $msg->{recipient_type} eq 'HOC') {
            $text = "\n" . FYR::Queue::make_house_of_lords_address($msg->{recipient_name});
        }
            # XXX might consider doing the same for MPs (just for form's sake)
            # except that we probably don't want to stick "House of Commons,
            # London" on a fax going to a constituency office -- that might
            # confuse people.

        $text .= "\n\n" . $msg->{message};
        while (length($text) > 0) {
            ($y, $remainder) = format_text($im, $text, $x + LMARGIN_CX, $y + TMARGIN_CY, TEXT_CX, (TEXT_CY - $y - $f - FMARGIN_CY));

            if (length($remainder) > 0){
                $f = $footerheight;
                ($im, $x, $y) = new_fax_page();
                ++$pagenum;
                push(@pages, $im);
            }
            $text = $remainder;
        }
        
        # At this point, generate a cover sheet if we need one.
        # NB that though Lords are 'via' contacts, they don't get a coversheet
        # -- instead we stick their address into the letter as would be done on
        # a normal letter, above.
        if ($msg->{recipient_via} && $msg->{recipient_type} ne 'HOC') {
            ($im, $x, $y) = new_fax_page();
            my $cover = cover_text($msg);
            my $coverheight = (format_text($im, $cover, LMARGIN_CX, TMARGIN_CY, TEXT_CX, TEXT_CY, 1, FONT_SIZE_COVER))[0];
            format_text($im, $cover, LMARGIN_CX, TMARGIN_CY + int((TEXT_CY - $coverheight) / 2), TEXT_CX, $coverheight + 100, 0, FONT_SIZE_COVER);
            push(@imgfiles, make_pbm_file($im));
        }

        # Now go back over each page and write the appropriate footer, and save
        # the pages to temporary PBM files whose names we return.
        for (my $i = 0; $i < @pages; ++$i) {
            $text = footer_text($i + 1, scalar(@pages), $url, $msg->{recipient_fax});
            $f = ($i > 0 ? $footerheight : $firstfooterheight);
            format_text($pages[$i], $text, $x + LMARGIN_CX, TMARGIN_CY + TEXT_CY - $f, TEXT_CX, $f, 0, FONT_SIZE_FOOTER);
            $pages[$i]->setThickness(2);
            $pages[$i]->line(LMARGIN_CX, TMARGIN_CY + TEXT_CY - $f - 10, LMARGIN_CX + TEXT_CX, TMARGIN_CY + TEXT_CY - $f - 10, 1);
            push(@imgfiles, make_pbm_file($pages[$i]));
        }
    } otherwise {
        my $E = shift;
        # Something went wrong; clean up the temporary files.
        foreach (@imgfiles) {
            unlink($_);
        }
        $E->throw();
    };
    
    die "no fax images created" if (@imgfiles == 0);
    
    return @imgfiles;
}

use constant FAX_SUCCESS => 0;
use constant FAX_SOFT_ERROR => 1;
use constant FAX_HARD_ERROR => 2;

# deliver MESSAGE
# Send the MESSAGE by fax. Returns one of the FAX_ constants to indicate
# whether the transaction was successful, failed with a local (soft) error, or
# failed with a remote (hard) error.
sub deliver ($) {
    my ($msg) = @_;
    my $ret = FAX_SOFT_ERROR;
    my $id = $msg->{id} or die "no ID in message";

    die "attempted fax delivery for message $msg->{id} without fax recipient"
        unless (exists($msg->{recipient_fax}) and defined($msg->{recipient_fax}));

    FYR::Queue::logmsg($id, 1, "attempting delivery by fax to $msg->{recipient_fax}");
again:
    # First, try to lock the fax device. If that doesn't work, then soft-fail.
    #
    # We do this here (rather than having efax do it) so that we can reliably
    # detect "in use" errors.
    my $lockfilename = mySociety::Config::get('FAX_LOCKDIR') . '/LCK..' . mySociety::Config::get('FAX_DEVICE');
    my $f = new IO::File($lockfilename, O_WRONLY | O_CREAT | O_EXCL, 0644);
    if (!$f) {
        if ($!{EEXIST}) {
            # A lockfile is present.
            my $pid;
            if (!defined($f = new IO::File($lockfilename, O_RDONLY))) {
                FYR::Queue::logmsg($id, 1, "not faxing: sending device is locked and cannot open lockfile: $!");
                return FAX_SOFT_ERROR;
            } else {
                $pid = $f->getline();
                if (!defined($pid)) {
                    FYR::Queue::logmsg($id, 1, "not faxing: sending device is locked and cannot read lockfile: $!");
                    return FAX_SOFT_ERROR;
                }
                $f->close();
                chomp($pid);

                # Some programs (efax!) stick whitespace in PID files.
                $pid =~ s/^\s+//;
                $pid =~ s/\s+$//;

                # (The "empty" or "not a PID" tests might wind up with us
                # colliding with some other process, but that's pretty unlikely
                # and in any case just likely to result in both failing, so
                # we'd back off and retry.)
                my $again = 0;
                if ($pid eq '') {
                    FYR::Queue::logmsg($id, 1, "lock file was empty; assuming stale");
                    $again = 1;
                } elsif ($pid =~ m#[^\d]#) {
                    FYR::Queue::logmsg($id, 1, "lock file contains \"$pid\", not a PID; assuming stale");
                    $again = 1;
                } elsif (!kill(0, $pid) && !$!{EPERM}) {
                    FYR::Queue::logmsg($id, 1, "stale lock file (refers to \"$pid\", which is not running)");
                    $again = 1;
                }

                if ($again) {
                    if (!unlink($lockfilename)) {
                        FYR::Queue::logmsg($id, 1, "cannot remove lock file: $!");
                        return FAX_SOFT_ERROR;
                    } else {
                        goto again;
                    }
                }
            }
            # Device was locked.
            FYR::Queue::logmsg($id, 1, "not faxing: sending device is locked by PID $pid");
            return FAX_SOFT_ERROR;
        } else {
            FYR::Queue::logmsg($id, 1, "unable to lock fax sending device " . mySociety::Config::get('FAX_DEVICE') . ": $lockfilename: $!");
            # This probably indicates that something on the system is broken
            # (on fire, eh?) but does not indicate that the message is
            # undeliverable.
            return FAX_SOFT_ERROR;
        }
    }

    # We win; save our PID in the lock file.
    $f->print("$$\n");
    $f->close();    # Should lock it, though other programs won't obey that.

    my @imgfiles;
    my %unsent = ( xxx => 1 );  # ugh
    try {
        @imgfiles = make_representative_fax($msg);
        throw FYR::Fax::SoftError("make_representative_fax didn't return any images")
            if (!@imgfiles);
        %unsent = map { $_ => 1 } @imgfiles;

        FYR::Queue::logmsg($id, 0, "messages has " . scalar(@imgfiles) . " page/s; files are: " . join(", ", @imgfiles));

        my $number = $msg->{recipient_fax};

        # Now we have to process this to generate a phone number.
        $number =~ s/ //g;
        if ($number =~ m#^\+#) {
            if ($number =~ m#^\+44#) {
                # National number, within this country.
                $number =~ s#^\+44#0#;
            } else {
                # International direct dialing.
                $number =~ s#\^+#00#;
            }
        }

        if ($number =~ m#([^\d])#) {
            throw FYR::Fax::HardError("recipient number contains bad character '$1'; unable to send by fax");
        } else {
            FYR::Queue::logmsg($id, 0, "recipient's dialing number is $number");
            throw FYR::Fax::HardError("recipient's dialing number does not begin '0'; won't send")
                if ($number !~ /^0/);
        }

        # We call efax(1) directly rather than via the fax(1) wrapper, because
        # we need to interpret the exit status of efax(1); fax(1) does not
        # return this, but instead produces "human readable" output. We also
        # want to log errors and warnings (but nothing else) from efax.
        my @efaxcmd = (
                mySociety::Config::get('FAX_COMMAND', 'efax'),
                split(/ +/, mySociety::Config::get('FAX_OPTIONS')),
                '-d', '/dev/' . mySociety::Config::get('FAX_DEVICE'),
                '-l', mySociety::Config::get('FAX_STATIONID'),
                '-h', mySociety::Config::get('FAX_HEADER'),
                # Don't produce anything on standard error, but log errors,
                # warnings and progress information on standard output. This
                # makes life slightly easier for us, since we can then use
                # pipe_via to process the error stream.
                '-v', '',
                '-v', 'ewi',
                '-t', $number,
                @imgfiles
            );

        FYR::Queue::logmsg($id, 0, "efax command is: " . join(' ', map { /(\s|^$)/ ? "'$_'" : $_ } @efaxcmd));

        my ($rd, $wr);
        $rd = new IO::Handle();
        $wr = new IO::Handle();
        my $p = new IO::Pipe($rd, $wr) or throw FYR::Fax::SoftError("pipe: $!");

        my ($p2, $pid) = mySociety::Util::pipe_via(@efaxcmd, $wr);
        $wr->close();
        $p2->close();   # efax needs nothing on standard input.
        
        my $alarmfired = 0;
        local $SIG{ALRM} = sub { kill(TERM => $pid); $alarmfired = 1; };

        # Determine a timeout. From existing logfiles the average time to
        # connect is 26s, and the time per page transmitted an additional 41s.
        # Allow this amount plus five minutes.
        my $timeout = 26 + scalar(@imgfiles) * 41 + 300;
        alarm($timeout);

        # Read output from efax, and log it.
        my $dial_command_failed = 0; # note "dial command failed" errors
        while (defined(my $line = $rd->getline())) {
            chomp($line);
            $dial_command_failed = 1 if ($line =~ /Error: dial command failed/);
            FYR::Queue::logmsg($id, 0, "efax output: $line");
            # Record the pages which efax believes it's sent.
            if ($line =~ m#efax: \d+:\d+ sent -> (.+)#) {
                delete($unsent{$1});
            }
        }
        if ($rd->error()) {
            throw FYR::Fax::SoftError("read from efax: $!");
        }
        $rd->close();

        if ($alarmfired) {
            FYR::Queue::logmsg($id, 0, "timed out efax");
        }

        alarm(0);

        if (!defined(waitpid($pid, 0))) {
            throw FYR::Fax::SoftError("wait for efax termination: $!");
        }

        if ($?) {
            if ($? & 127) {
                throw FYR::Fax::SoftError("efax was killed by signal " . ($? & 127));
            } else {
                my $st = $? >> 8;
                #
                # efax exit codes:
                #
                if ($st == 1) {
                    # number busy or device in use
                    throw FYR::Fax::HardError("fax number was engaged");
                } elsif ($st == 2) {
                    if ($dial_command_failed) {
                        # Probably some kind of engaged or similar tone that
                        # the modem didn't recognise; treat it as a remote
                        # error.
                        throw FYR::Fax::HardError("failed to connect to number");
                    } else {
                        # Some kind of fatal error in efax; assume that this is NOT
                        # a fatal error per sending.
                        throw FYR::Fax::SoftError("fatal error in efax");
                    }
                } elsif ($st == 3) {
                    # "Modem protocol error"
                    throw FYR::Fax::HardError("modem protocol error (exit status 3) in efax");
                } elsif ($st == 4) {
                    # Modem is not responding.
                    throw FYR::Fax::SoftError("modem is not responding");
                } elsif ($st == 5) {
                    # Program terminated by signal
                    throw FYR::Fax::SoftError("efax terminated by signal");
                }
            }
        } else {
            # Yay!
            $ret = FAX_SUCCESS;
        }
    } catch FYR::Fax::HardError with {
        my $E = shift;
        $ret = FAX_HARD_ERROR;
        FYR::Queue::logmsg($id, 1, $E->text());
        if (0 == keys(%unsent)) {
            FYR::Queue::logmsg($id, 1, "however all pages were apparently sent; assuming success");
            $ret = FAX_SUCCESS;
        } 
    } catch FYR::Fax::SoftError with {
        my $E = shift;
        $ret = FAX_SOFT_ERROR;
        FYR::Queue::logmsg($id, 1, $E->text());
        if (0 == keys(%unsent)) {
            FYR::Queue::logmsg($id, 1, "however all pages were apparently sent; assuming success");
            $ret = FAX_SUCCESS;
        } 
    } finally {
        # whatever happens, nuke the lockfile and all the image files.
        foreach ($lockfilename, @imgfiles) {
            unlink($_);
        }
    };

    return $ret;
}

1;
