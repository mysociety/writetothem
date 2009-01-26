#!/usr/bin/perl
#
# FYR/EmailTemplate.pm:
# Plain-text email templates.
#
# Copyright (c) 2004 UK Citizens Online Democracy. All rights reserved.
# Email: chris@mysociety.org; WWW: http://www.mysociety.org/
#
# $Id: EmailTemplate.pm,v 1.3 2009-01-26 12:54:08 matthew Exp $
#

package FYR::EmailTemplate;

use strict;

use IO::File;
use Text::Wrap ();
use utf8;

=head1 NAME

FYR::EmailTemplate

=head1 DESCRIPTION

Formatting of emails from plain-text templates.

=head1 FUNCTIONS

=over 4

=item format TEMPLATE VALUES [NOWRAP]

TEMPLATE is the filename of an email template; VALUES is a reference to a hash
of the values which will be filled in in it. If NOWRAP is true, no wrapping of
the output text will be performed.

=cut
sub format ($$;$) {
    my ($template, $values, $nowrap) = @_;
    my $f = new IO::File($template, O_RDONLY) or die "$template: $!";

    my $text = '';
    my $parstart = 1;
    while (defined(my $line = $f->getline())) {
        chomp($line);
        if ($line =~ m#^\s*$#) {
            $text .= "\n\n";
            $parstart = 1;
        } else {
            my (@vals) = ($line =~ m#<\?=\s*\$values\['([^']+)'\]\s*\?>#g);
            foreach (@vals) {
                die "no value '$_' in passed hashref" unless (exists($values->{$_}));
            }
            $line =~ s#<\?=\s*\$values\['([^']+)'\]\s*\?>#$values->{$1}#ge;

            $text .= ' ' unless ($parstart);
            $text .= $line;
            $parstart = 0;
        }
    }

    if ($f->error()) {
        $f->close();
        die "$template: $!";
    }
    $f->close();
    
    $text .= "\n\n";

    return $text if ($nowrap);

    local($Text::Wrap::columns) = 72;
    local($Text::Wrap::huge) = 'overflow';

    return Text::Wrap::wrap('', '', $text);
}

1;
