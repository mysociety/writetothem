#!/usr/bin/perl
#
# FYR/AbuseChecks.pm:
# Some automated abuse checks.
#
# Copyright (c) 2004 UK Citizens Online Democracy. All rights reserved.
# Email: chris@mysociety.org; WWW: http://www.mysociety.org/
#
# $Id: AbuseChecks.pm,v 1.23 2005-01-12 09:04:32 francis Exp $
#

package FYR::AbuseChecks;

use strict;

use DBD::Pg; # for BLOB (bytea) support
use Geo::IP;
use Net::Google::Search;
use Storable;
use Data::Dumper;

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

# get_similar_messages MESSAGE
# Return list of pairs of (message ids, similarity) for messages whose
# bodies are similar to MESSAGE.  "similarity" is between 0.0 and 1.0.
sub get_similar_messages ($) {
    my ($msg) = @_;
    die "get_similar_messages: must call in list context" unless (wantarray());

    # Compute and save hash of this message.
    
    # The beginning and end of each message are liable to be pretty similar, so
    # strip them off for purposes of hash computation. This is, frankly, a
    # hack.
    my $m = $msg->{message};
    # Salutation.
    $m =~ s#^\s+Dear\s+[^\n]+\n##gs;
    # Signoff.
    $m =~ s#^\s*Yours sincerely,?\s*\n##gs;
    # "Electronic signature".
    $m =~ s#[0-9a-f]+\s+\(Signed with an electronic signature in accordance with subsection 7\(3\) of the Electronic Communications Act 2000.\)##gs;
    my $h = FYR::SubstringHash::hash($m, SUBSTRING_LENGTH, NUM_BITS);

    FYR::DB::dbh()->do(q#delete from message_extradata where message_id = ? and name = 'substringhash'#, {}, $msg->{id});

    # Horrid. To insert a value into a BYTEA column we need to do a little
    # parameter-binding dance:
    my $s = FYR::DB::dbh()->prepare(q#insert into message_extradata (message_id, name, data) values (?, 'substringhash', ?)#);
    $s->bind_param(1, $msg->{id});
    $s->bind_param(2, Storable::nfreeze($h), { pg_type => DBD::Pg::PG_BYTEA });
    $s->execute();

    # Retrieve hashes of other messages and compare them. We don't want to
    # compare this message to others sent by the same individual to other
    # representatives, since it is legitimate for one person to copy a message
    # to (e.g.) all of their MEPs. We compare individuals by comparing postcode
    # and sending email address (so we should catch people spamming by using
    # lots of postcodes to send a single message to several MPs).
    my $stmt = FYR::DB::dbh()->prepare(q#
        select message_id, sender_postcode, sender_email, data
            from message, message_extradata
           where message.id = message_extradata.message_id
             and message_id <> ?
             and recipient_id <> ?
             and message_extradata.name = 'substringhash'
        #);
    $stmt->execute($msg->{id}, $msg->{recipient_id});
    my $thr = mySociety::Config::get('MAX_MESSAGE_SIMILARITY');
    my @similar = ( );

    my $pc = uc($msg->{sender_postcode});
    $pc =~ s#\s##g;
    my $email = lc($msg->{sender_email});
    $email =~ s#\s##g;

    while (my ($id2, $pc2, $email2, $h2) = $stmt->fetchrow_array()) {
        $email2 =~ s#\s##g;
        $pc2 =~ s#\s##g;
        next if ($email eq $email2 and $pc eq $pc2);

        $h2 = Storable::thaw($h2);
        my $similarity = FYR::SubstringHash::similarity($h, $h2);
        push(@similar, [$id2, $similarity]) if ($similarity > $thr);
    }

    return @similar;
}

# check_similarity MESSAGE
# Test MESSAGE for similarity to other messages in the queue.
sub check_similarity ($) {
    my ($msg) = @_;

    my @similar = get_similar_messages($msg);
    return 0 unless (@similar);

    @similar = sort { $b->[1] <=> $a->[1] } @similar;
    my $why = sprintf("Message body is very similar to $similar[0]->[0] (%.2f similar)", 
        $similar[0]->[1]);
    for (my $i = 1; $i < 3 && $i < @similar; ++$i) {
        $why .= sprintf(", $similar[$i]->[0] (%.2f similar)", $similar[$i]->[1]);
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
                    if (defined($_->{recipient_email}) and $_[0]->{sender_email} eq $_[0]->{recipient_email}
                        and !(mySociety::Config::get('FYR_REFLECT_EMAILS')));
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
