#!/usr/bin/perl
#
# FYR/AbuseChecks.pm:
# Some automated abuse checks.
#
# Copyright (c) 2004 UK Citizens Online Democracy. All rights reserved.
# Email: chris@mysociety.org; WWW: http://www.mysociety.org/
#
# $Id: AbuseChecks.pm,v 1.1 2004-12-15 20:25:16 chris Exp $
#

package FYR::AbuseChecks;

use strict;

use Net::Google::Search;

use mySociety::Config;

# google_for_postcode POSTCODE
# Return true if the POSTCODE occurs with the terms "faxyourmp" or
# "writetothem" on a web page indexed by Google.
sub google_for_postcode ($) {
    my ($pc) = @_;
    our ($G, $nogoogle);
    if (!$G) {
        my $key = mySociety::Config::get('GOOGLE_API_KEY', undef);
        if (defined($key)) {
            $G = new Net::Google::Search(key => $key);
        }
        $nogoogle = 1 unless ($G);
    }
    return 0 if ($nogoogle);

    $pc =~ s# ##g;
    $pc = uc($pc);

    # We need to put the space back in in the right place now.
    # http://www.govtalk.gov.uk/gdsc/html/noframes/PostCode-2-1-Release.htm
    my $pc2 = $pc;
    $pc2 =~ s#(\d[A-Z]{2})# $2#;
    
    # ("-site:mysociety.org" is a hack to stop it finding (e.g.) my postcode in
    # checked-in code in CVSTrac.... --chris 20041215)
    $G->query('', sprintf('%s OR %s writetothem OR faxyourmp -site:mysociety.org', $pc, $pc2));

    return (scalar($G->results()) > 0);
}

=item test MESSAGE

Perform abuse checks on the MESSAGE (hash of database fields). This returns in
list context one of: 'ok' to indicate that delivery should occur as normal,
'hold' to indicate that the message should be held for inspection by an
administrator, or 'drop' to indicate that the message should be discarded; and
the reason for the result.

=cut
sub test ($) {
    my ($msg) = @_;

    return ('hold', 'Postcode appears in Google with term "faxyourmp" or "writetothem"') if (google_for_postcode($msg->{sender_postcode}));

    # XXX flesh out with dynamic anti-spam rules

    return ('ok', undef);
}

1;
