#!/usr/bin/perl
#
# FYR/AbuseChecks.pm:
# Some automated abuse checks.
#
# Copyright (c) 2004 UK Citizens Online Democracy. All rights reserved.
# Email: chris@mysociety.org; WWW: http://www.mysociety.org/
#
# $Id: AbuseChecks.pm,v 1.18 2005-01-05 15:13:55 chris Exp $
#

package FYR::AbuseChecks;

use strict;

use DBD::Pg; # for BLOB (bytea) support
use Geo::IP;
use Net::Google::Search;
use Storable;

use mySociety::Config;

use FYR;
use FYR::SubstringHash;

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

# check_ip_in_uk ADDRESS
# Return true if the IP ADDRESS is in the UK.
sub check_ip_in_uk ($) {
    my ($addr) = @_;
    return 1 if $addr eq "127.0.0.1";
    our $geoip;
    $geoip ||= new Geo::IP(GEOIP_STANDARD);
    my $cc = $geoip->country_code_by_addr($addr);
    return defined($cc) and $cc =~ m#^(GB|UK)$#;
}

# Constants for similarity hashing.
# Length of substrings we consider.
use constant SUBSTRING_LENGTH => 32;
# Number of low bits which must be zero for a hash to be accepted.
use constant NUM_BITS => 4;

# check_similarity MESSAGE
# Test MESSAGE for similarity to other messages in the queue.
sub check_similarity ($) {
    my ($msg) = @_;
    # Compute and save hash of this message.
    my $h = SubstringHash::hash($msg->{message}, SUBSTRING_LENGTH, NUM_BITS);
    FYR::DB::dbh()->do(q#delete from message_extradata where message_id = ? and name = 'substringhash'#, {}, $msg->{id});
    # Horrid. To insert a value into a BYTEA column we need to do a little
    # parameter-binding dance:
    my $s = FYR::DB::dbh()->prepare(q#insert into message_extradata (message_id, name, data) values (?, 'substringhash', ?)#);
    $s->bind_param(1, $msg->{id});
    $s->bind_param(2, Storable::nfreeze($h), { pg_type => DBD::Pg::PG_BYTEA });
    $s->execute();
    # Retrieve hashes of other messages and compare them.
    my $stmt = FYR::DB::dbh()->prepare(q#select message_id, data from message_extradata where message_id <> ? and name = 'substringhash'#);
    $stmt->execute($msg->{id});
    my $thr = mySociety::Config::get('MAX_MESSAGE_SIMILARITY');
    my @similar = ( );
    while (my ($id2, $h2) = $stmt->fetchrow_array()) {
        $h2 = Storable::thaw($h2);
        my $similarity = FYR::SubstringHash::similarity($h, $h2);
        push(@similar, [$id2, $similarity]) if ($similarity > $thr);
    }
    return 0 unless (@similar);
    @similar = sort { $b->[1] <=> $a->[1] } @similar;
    my $why = "Message body is very similar to $similar[0]->[0]";
    for (my $i = 1; $i < 3 && $i < @similar; ++$i) {
        $why .= ", $similar[$i]->[0]";
    }
    $why .= sprintf(' and %d others', @similar - 3) if (@similar > 3);
    return $why;
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
                    if (!check_ip_in_uk($_[0]->{sender_ipaddr}));
            }
        ],

        # Extremely short messages, allow for length of signature (about 150)
        [
            'hold',
            sub ($) {
                return "Message is extremely short"
                    if (length($_[0]->{message}) - length($_[0]->{recipient_name}
                        ) < 250);
            }
        ],

        # Check for postcodes advertised on Google
        [
            'hold',
            sub ($) {
                return qq#Postcode "$_[0]->{sender_postcode}" appears in Google with term "faxyourmp" or "writetothem"#
                    if (google_for_postcode($_[0]->{sender_postcode}));
            }
        ],

        # Representative emailing themself
        # TODO Actually look this up in DaDem, as it won't work if they
        # are somebody who is faxed, even if we know their email.
        # This can also spot representatives emailing each other, is
        # that useful?
        [
            'hold',
            sub ($) {
                return "Representative is emailing themself"
                    if ($_[0]->{sender_email} eq $_[0]->{recipient_email});
            }
        ],

        # Body of message similar to other messages in queue.
        [
            'hold',
            \&check_similarity
        ]

    );

=item test MESSAGE

Perform abuse checks on the MESSAGE (hash of database fields). This returns in
list context one of: 'ok' to indicate that delivery should occur as normal,
'hold' to indicate that the message should be held for inspection by an
administrator, or 'reject' to indicate that the message should be discarded;
and, for 'hold' or 'reject', the reason for the result, to be displayed to the
administrator.

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
