#!/usr/bin/perl
#
# FYR/SubstringHash.pm:
# Substring hashing for similarity detection.
#
# Copyright (c) 2004 UK Citizens Online Democracy. All rights reserved.
# Email: chris@mysociety.org; WWW: http://www.mysociety.org/
#
# $Id: SubstringHash.pm,v 1.1 2004-12-17 22:02:37 chris Exp $
#

package FYR::SubstringHash;

use strict;

use Digest::SHA1;

# Number of bytes of hash we treat as significant.
use constant HASHLEN => 8;

=item hash TEXT LENGTH NUM

Compute substring hashes of TEXT. TEXT is first normalised (converted to lower
case and stripped of punctuation and repeated whitespace). Then compute hashes
of each overlapping LENGTH-byte substring in the text, and return a reference
to a list of the NUM largest distinct ones.

=cut
sub hash ($$$) {
    my ($text, $len, $num) = @_;

    # Some canonicalisation.
    $text = lc($text);
    $text =~ s#[[:punct:]]# #g;
    $text =~ s#\p{IsSpace}+# #g;

    # Need to treat $text as bytes.
    utf8::encode($text);

    for (my $i = 0; $i < length($text) - $len + 1; ++$i) {
        
    }
}

1;
