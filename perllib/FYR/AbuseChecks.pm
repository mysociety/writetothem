#!/usr/bin/perl
#
# FYR/AbuseChecks.pm:
# Some automated abuse checks.
#
# This is v2 of the automated abuse checks. Rather than applying checks here,
# we do a bunch of tests and kick their results through to Ratty using the
# "fyr-abuse" scope. Specific rules can then refer to the results we generate
# here.
#
# Copyright (c) 2004 UK Citizens Online Democracy. All rights reserved.
# Email: chris@mysociety.org; WWW: http://www.mysociety.org/
#
# $Id: AbuseChecks.pm,v 1.25 2005-01-13 10:25:54 chris Exp $
#

package FYR::AbuseChecks;

use strict;

use DBD::Pg; # for BLOB (bytea) support
use Geo::IP;
use Net::Google::Search;
use Storable;
use Data::Dumper;

use mySociety::Config;
use mySociety::Ratty;

use FYR;
use FYR::Queue qw(logmsg);
use FYR::SubstringHash;

# google_for_postcode POSTCODE
# Return the number of pages found on Google in which the POSTCODE occurs with
# the terms "faxyourmp" or "writetothem".
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

    return scalar(@$results);
}

# get_country_from_ip ADDRESS
# Return the country code for the given IP address, or undef if none could be
# found.
sub get_country_from_ip ($) {
    my ($addr) = @_;
    return 1 if $addr eq "127.0.0.1";
    our $geoip;
    $geoip ||= new Geo::IP(GEOIP_STANDARD);
    return $geoip->country_code_by_addr($addr);
}

# Constants for similarity hashing.
# Length of substrings we consider.
use constant SUBSTRING_LENGTH => 32;
# Number of low bits which must be zero for a hash to be accepted.
use constant NUM_BITS => 4;

# get_similar_messages MESSAGE
# Return list of pairs of (message ids, similarity) for messages whose
# bodies are similar to MESSAGE. "similarity" is between 0.0 and 1.0. This list
# excludes messages which are from the same email address and postcode (fixes
# ticket #108).
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

# @tests
# Tests to apply to messages to detect abuse. Each entry in the array is a code
# reference, which is passed the message as a reference to a hash; it should
# return a hash of rate limiting variables to their values. The convention is
# that a boolean value should be added to the results if true, and not added
# if false -- see the "representative emailing themself" case for an example.
# Tests may also log information, if they want.
my @tests = (
        # Country of origin of IP address
        sub ($) {
            my ($msg) = @_;
            my $cc = get_country_from_ip($msg->{sender_ipaddr});
            $cc ||= 'unknown';
            logmsg($msg->{id}, sprintf('sender IP address %s -> country %s', $msg->{sender_ipaddr}, $cc));
            return ( sender_ip_country => $cc );
        },

        # Length of message, in characters and words
        sub ($) {
            my ($msg) = @_;
            my $l1 = length($msg->{message});
            my $l2 = scalar(split(/[[:space:]]+/, $msg->{message}));
            logmsg($msg->{id}, sprintf('message length: %d words, %d characters', $l2, $l1));
            return (
                    message_length_characters => $l1,
                    message_length_words => $l2
                );
        },

        # Postcodes advertised in Google
        sub ($) {
            my ($msg) = @_;
            my $hits = google_for_postcode($msg->{sender_postcode});
            logmsg($msg->{id}, sprintf('postcode "%s" appears on Google with term "faxyourmp" or "writetothem" (%d hits)',
                $msg->{sender_postcode}, $hits))
                if ($hits > 0);
            return ( postcode_google_hits => $hits );
        },
        
        # Representative emailing themself
        # TODO Actually look this up in DaDem, as it won't work if they
        # are somebody who is faxed, even if we know their email.
        # This can also spot representatives emailing each other, is
        # that useful?
        sub ($) {
            my ($msg) = @_;
            if (!mySociety::Config::get('FYR_REFLECT_EMAILS')
                and defined($msg->{recipient_email})
                and $msg->{sender_email} eq $msg->{recipient_email}) {
                logmsg($msg->{id}, 'representative appears to be emailing themself');
                return ( representative_emailing_self => 'YES' );
            } else {
                return ( );
            }
        },

        # Body of message similar to other messages in queue.
        sub ($) {
            my ($msg) = @_;
            my @similar = sort { $b->[1] <=> $a->[1] } grep { $_->[1] > get_similar_messages($msg) } get_similar_messages($msg);
            return ( ) if (!@similar);

            my $why = sprintf('message body is very similar to %s (%.2f similar)', $similar[0]->[0], $similar[0]->[1]);
            for (my $i = 1; $i < 3 && $i < @similar; ++$i) {
                $why .= sprintf(", %s (%.2f similar)", $similar[$i]->[0], $similar[$i]->[1]);
            }

            $why .= sprintf(' and %d others', @similar - 3) if (@similar > 3);

            my %res = ( );
            
            # Generate a bunch of useful metrics
            $res{similarity_max} = $similar[0]->[0];

            foreach my $thr (qw(0.5 0.6 0.7 0.8 0.9 0.95 0.99)) {
                next if ($_ > mySociety::Config::get('MAX_MESSAGE_SIMILARITY'));
                my $n = scalar(grep { $_->[1] > $thr } @similar);
                $res{"similarity_num_$_"} = $n;     # "number of messages more than ... similar to this one"
            }
        }
    );

=item test MESSAGE

Perform abuse checks on the MESSAGE (hash of database fields). This performs
tests on the message (which may themselves log information), and passes the
message and the results of the tests to the rate limiter under scope
"fyr-abuse". The function returns undef to indicate that delivery should
proceed as normal, 'freeze' to indicate that the message should be frozen for
inspection by an administrator, or, if the message should be rejected
completely, the name of a template which should be displayed to the user to
explain why their message has been rejected.

=cut
sub test ($) {
    my ($msg) = @_;

    my %ratty_values = %$msg;
    foreach my $f (@tests) {
        %ratty_values = (%ratty_values, &$f($msg));
    }

    # Perform test.
    my ($ruleid, $result) = mySociety::Ratty::test('fyr-abuse', \%ratty_values);
    if (defined($ruleid)) {
        logmsg($msg->{id}, "fyr-abuse rule $ruleid fired for message; result: $result");
        return $result;
    } else {
        return undef;
    }
}

1;
