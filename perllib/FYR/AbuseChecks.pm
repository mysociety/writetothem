#!/usr/bin/perl
#
# FYR/AbuseChecks.pm:
# Some automated abuse checks.
#
# Copyright (c) 2004 UK Citizens Online Democracy. All rights reserved.
# Email: chris@mysociety.org; WWW: http://www.mysociety.org/
#
# $Id: AbuseChecks.pm,v 1.8 2004-12-21 01:28:53 chris Exp $
#

package FYR::AbuseChecks;

use strict;

use Geo::IP;
use Net::Google::Search;
use Data::Dumper;

use mySociety::Config;

# google_for_postcode POSTCODE
# Return true if the POSTCODE occurs with the terms "faxyourmp" or
# "writetothem" on a web page indexed by Google.
sub google_for_postcode ($) {
    my ($pc) = @_;
    our ($G, $nogoogle);
    if (!$G) {
        my $key = mySociety::Config::get('GOOGLE_API_KEY', "");
        if ($key ne "") {
            $G = new Net::Google::Search({key => $key});
        }
        $nogoogle = 1 unless ($G);
    }
    return 0 if ($nogoogle);

    $pc =~ s#\s##g;
    $pc = uc($pc);

    # We need to put the space back in in the right place now.
    # http://www.govtalk.gov.uk/gdsc/html/noframes/PostCode-2-1-Release.htm
    my $pc2 = $pc;
    $pc2 =~ s#(\d[A-Z]{2})# $1#;
    
    # ("-site:mysociety.org" is a hack to stop it finding (e.g.) my postcode in
    # checked-in code in CVSTrac.... --chris 20041215)
    my $googlesearch = sprintf('%s OR "%s" writetothem OR faxyourmp -site:mysociety.org', $pc, $pc2);
    $G->query('', $googlesearch);
    my $results = $G->results();

    return (scalar(@$results) > 0);
}

# check_ip_country ADDRESS
# Return true if the IP ADDRESS is outside the UK.
sub check_ip_country ($) {
    my ($addr) = @_;
    our $geoip;
    $geoip ||= new Geo::IP(GEOIP_STANDARD);
    my $cc = $geoip->country_code_by_addr($addr);
    return !(defined($cc) and $cc =~ m#^(GB|UK)$#);
}

# @tests
# List of tests to apply to messages. Each entry in the array should be a
# reference to a list of: action ('hold' or 'drop') and a code reference, which
# should return an explanatory message if the test succeeds for this message.
# Tests are applied in the order given and the first which succeeds for a
# message determines its fate.
my @tests = (
        # Debugging test cases
        [
            'hold',
            sub ($) {
                warn "in first test";
                warn $_[0]->{message};
                return 'ABUSETESTHOLD appears in message body'
                    if ($_[0]->{message} =~ m#ABUSETESTHOLD#);
            }
        ],

        [
            'reject',
             sub ($) {
                return "ABUSETESTREJECT appears in message body"
                    if ($_[0]->{message} =~ m#ABUSETESTREJECT#);
            },
        ],

        # IP address outside UK
        [
            'hold',
            sub ($) {
                return qq#IP address $_[0]->{sender_ipaddr} is not in the UK#
                    if (!check_ip_country($_[0]->{sender_ipaddr}));
            }
        ],

        # Extremely short messages
        [
            'hold',
            sub ($) {
                return "Message is extremely short"
                    if (length($_[0]->{message}) - length($_[0]->{recipient_name}) < 50);
            }
        ],

        # Check for postcodes advertised on Google
        [
            'hold',
            sub ($) {
                return qq#Postcode "$_[0]->{sender_postcode}" appears in Google with term "faxyourmp" or "writetothem"#
                    if (google_for_postcode($_[0]->{sender_postcode}));
            }
        ]
    );

=item test MESSAGE

Perform abuse checks on the MESSAGE (hash of database fields). This returns in
list context one of: 'ok' to indicate that delivery should occur as normal,
'hold' to indicate that the message should be held for inspection by an
administrator, or 'reject' to indicate that the message should be discarded; 
the reason for the admin for the result.

=cut
sub test ($) {
    my ($msg) = @_;

    foreach (@tests) {
        my ($what, $f) = @$_;
        my $why = &$f($msg);
        if ($why) {
            return ($what, $why);
        }
    }

    return ('ok', undef);
}

1;
