#!/usr/bin/perl
#
# FYR/SubstringHash.pm:
# Substring hashing for similarity detection.
#
# Copyright (c) 2004 UK Citizens Online Democracy. All rights reserved.
# Email: chris@mysociety.org; WWW: http://www.mysociety.org/
#
# $Id: SubstringHash.pm,v 1.3 2005-01-17 10:11:04 chris Exp $
#

package FYR::SubstringHash;

use strict;

use String::CRC32;

=item hash TEXT LENGTH NUM

Compute substring hashes of TEXT. TEXT is first normalised (converted to lower
case and stripped of punctuation and whitespace). Then compute hashes of each
overlapping LENGTH-byte substring in the text; record hashes of nonoverlapping
substrings all of whose NUM least-significant bits are zero and return a
reference to a hash with keys for each accepted value.

=cut
sub hash ($$$) {
    my ($text, $len, $num) = @_;

    # XXX This is basically Manber hashing -- see
    # http://citeseer.ist.psu.edu/manber94finding.html
    # but I'm lazy and don't use the sliding polynomial approach, just
    # recompute the whole CRC each time.

    # Some canonicalisation.
    $text = lc($text);
    $text =~ s#[[:punct:]]##g;
    $text =~ s#\p{IsSpace}##g;

    # Need to treat $text as bytes.
    utf8::encode($text);
    $len = length($text) if ($len > length($text));     # XXX

    my $mask = (1 << $num) - 1;
    my %h = ();
    my $i = 0;
    while ($i < length($text) - $len + 1) {
        my $c = crc32(substr($text, $i, $len));
        if (($c & $mask) == 0 && !exists($h{$c})) {
            # Accept hash.
            $h{$c} = 1;
            $i += $len;
        } else {
            ++$i;
        }
    }

    return \%h;
}

=item similarity H1 H2

Given two hashes returned by hash, compute a measure of similarity between the
two hashed documents. Dissimilar documents have similarities near zero; similar
documents have similarities near one.

=cut
sub similarity ($$) {
    my ($a, $b) = @_;
    # We can't compute similarities when one or the other hash has no entries.
    # This will occur for the case of very short messages.
    return 0. if (scalar(keys(%$a)) == 0 || scalar(keys(%$b)) == 0);
    ($a, $b) = ($b, $a) if (scalar(keys(%$a)) < scalar(keys(%$b)));
    my $n = 0;
    foreach (keys %$a) {
        $n += 2 if (exists($b->{$_}));
    }
    return $n / (scalar(keys(%$a)) + scalar(keys(%$b)));
}

1;
