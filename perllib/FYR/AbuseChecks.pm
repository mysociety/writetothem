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
# $Id: AbuseChecks.pm,v 1.71 2009-06-23 08:36:01 louise Exp $
#

package FYR::AbuseChecks;

use strict;

use Data::Dumper;
use DBD::Pg; # for BLOB (bytea) support
use Error qw(:try);
use POSIX;  # strftime
use Storable;
use Time::HiRes;

use mySociety::Config;
use mySociety::DaDem;
use mySociety::DBHandle qw(dbh);
use mySociety::Gaze;
use mySociety::MaPit;
use mySociety::Ratty;

use FYR;
use FYR::Queue;
use FYR::SubstringHash;

# Constants for similarity hashing.
# Length of substrings we consider.
use constant SUBSTRING_LENGTH => 32;
# Number of low bits which must be zero for a hash to be accepted.
use constant NUM_BITS => 4;

# get_similar_messages MESSAGE [SAME_REP]
# Return list of pairs of (message ids, similarity) for messages whose
# bodies are similar to MESSAGE. "similarity" is between 0.0 and 1.0. This list
# excludes messages which are from the same email address and postcode (fixes
# ticket #108). If SAME_REP is present and true (e.g. has value 1), then
# only checks against other messages to the same representative. The default is
# to only return other similar messages NOT to the same representative, so you
# need to call the function with both values for SAME_REP to get everything.
sub get_similar_messages ($;$) {
    my ($msg, $same_rep) = @_;
    die "get_similar_messages: must call in list context" unless (wantarray());

    # Compute and save hash of this message.
    # The beginning and end of each message are liable to be pretty similar, so
    # strip them off for purposes of hash computation. This is, frankly, a
    # hack.
    my $m = $msg->{message};
    # Salutation.
    $m =~ s#^\s+Dear\s+[^\n]+\n##gs;
    # Signoff.
    $m =~ s#^\s*Yours sincerely,?\s*\n##gsm;
    # "Electronic signature".
    $m =~ s#[0-9a-f]+\s+\(Signed with an electronic signature in accordance with section 7\(3\) of the Electronic Communications Act 2000.\)##gs;
   
        
    my $start_time = Time::HiRes::time();
    my $elapsed_time;
    my $h = FYR::SubstringHash::hash($m, SUBSTRING_LENGTH, NUM_BITS);

    $elapsed_time = Time::HiRes::time() - $start_time;
    FYR::Queue::log_to_handler($msg->{id}, 1, "Made hash. Time taken: $elapsed_time");

    # XXX we should test whether the message is in the database, so that we can
    # run a fake test message through the abuse tests without having to create
    # a message in the database.

    dbh()->do(q#delete from message_extradata where message_id = ? and name = 'substringhash'#, {}, $msg->{id});

    # Horrid. To insert a value into a BYTEA column we need to do a little
    # parameter-binding dance:
    my $s = dbh()->prepare(q#insert into message_extradata (message_id, name, data) values (?, 'substringhash', ?)#);
    $s->bind_param(1, $msg->{id});
    my $ser = Storable::nfreeze($h); # XXX no need for the temporary variable, except that we are getting "Bad free() ignored" errors, and want to know if it's Storable or DBD::Pg which is causing them....
    $s->bind_param(2, $ser, { pg_type => DBD::Pg::PG_BYTEA });
    $s->execute();

    # Retrieve hashes of other messages and compare them. We don't want to
    # compare this message to others sent by the same individual to other
    # representatives, since it is legitimate for one person to copy a message
    # to (e.g.) all of their MEPs. We compare individuals by comparing postcode
    # and sending email address (so we should catch people spamming by using
    # lots of postcodes to send a single message to several MPs).
    # We only look at messages that might be or have been sent to representatives.
    $start_time = Time::HiRes::time();
    my $same_rep_check = "recipient_id <> ?";
    if ($same_rep){
        $same_rep_check = "recipient_id = ?"; 
    }
    my $stmt = dbh()->prepare(q#
        select message_id, sender_postcode, sender_email, data
            from message, message_extradata
           where message.id = message_extradata.message_id
             and message_id <> ?
             and #.$same_rep_check.q#
             and message_extradata.name = 'substringhash'
             and state not in ('error', 'failed', 'failed_closed', 'finished')
             #);
    $stmt->execute($msg->{id}, $msg->{recipient_id});
    $elapsed_time = Time::HiRes::time() - $start_time;
    # FYR::Queue::log_to_handler($msg->{id}, 1, "Made hash query, samerep : $same_rep. Time taken: $elapsed_time");

    my $thr = mySociety::Config::get('MESSAGE_SIMILARITY_THRESHOLD');
    my @similar = ( );

    my $pc = uc($msg->{sender_postcode});
    $pc =~ s#\s##g;
    my $email = lc($msg->{sender_email});
    $email =~ s#\s##g;

    $start_time = Time::HiRes::time();
    my $rows = 0;
    while (my ($id2, $pc2, $email2, $h2) = $stmt->fetchrow_array()) {
        $pc2 =~ s#\s##g;
        $email2 =~ s#\s##g;
        next if ($email eq lc($email2) and $pc eq uc($pc2));

        $h2 = Storable::thaw($h2);
        my $similarity = FYR::SubstringHash::similarity($h, $h2);
        #warn "$id2 $similarity\n";
        push(@similar, [$id2, $similarity]) if ($similarity > $thr);
        $rows++;  
    }
    $elapsed_time = Time::HiRes::time() - $start_time;
    # FYR::Queue::log_to_handler($msg->{id}, 1, "Made hash similarity comparison, samerep : $same_rep. Num hashes: $rows. Time taken: $elapsed_time");
    return @similar;
}

# @individual_tests, @group_tests
# Tests to apply to messages to detect abuse. Each entry in each array is a code
# reference, which is passed the message as a reference to a hash; it should
# return a hash of rate limiting variables to their values. The convention is
# that a boolean value should be added to the results if true, and not added
# if false -- see the "representative emailing themself" case for an example.
# Tests may also log information, if they want. Tests in @individual_tests need 
# to be run on every message in a group, but tests in @group_tests can be run
# on one message as they will produce the same results for every message in a 
# message group. 
my @individual_tests = (
        # Representative emailing themself, i.e. same email address
        # TODO Actually look up email address in DaDem, as it won't work if
        # they are somebody who is faxed, even if we know their email.  This
        # can also spot representatives emailing each other, is that useful?
      sub ($) {
            my ($msg) = @_;
            my $rep_self = undef;
            if (!mySociety::Config::get('FYR_REFLECT_EMAILS')
                and defined($msg->{recipient_email})
                and $msg->{sender_email} eq $msg->{recipient_email}) {
                FYR::Queue::logmsg($msg->{id}, 0, 'representative appears to be emailing themself (same email address)');
                $rep_self = 'YES';
            }
            return ( representative_emailing_self => [$rep_self, 'Present if representative appears to be emailing themself (same email)'] );
        },

 
        # Representative emailing themself, i.e. similar name
        sub ($) {
            my ($msg) = @_;
            my $rep_self = undef;
            if (defined($msg->{recipient_name}) and defined($msg->{sender_name})
                and ($msg->{sender_name} =~ m/\Q$msg->{recipient_name}\E/i
                    or $msg->{recipient_name} =~ m/\Q$msg->{sender_name}\E/i)) {
                FYR::Queue::logmsg($msg->{id}, 0, 'representative appears to be emailing themself (similar name)');
                $rep_self = 'YES';
            }
            return ( representative_emailing_self_name => [$rep_self, 'Present if representative appears to be emailing themself (similar name)'] );
        },

        # Body of message similar to other messages to same recipient in queue
        sub ($) {
            my ($msg) = @_;
            my @similar = sort { $b->[1] <=> $a->[1] } get_similar_messages($msg, 1); # 1 means to same rep

            my %res = ( );
            if (@similar) {
                my $why = sprintf('message body is very similar to same recipient messages %s (%.2f similar)', $similar[0]->[0], $similar[0]->[1]);
                for (my $i = 1; $i < 3 && $i < @similar; ++$i) {
                    $why .= sprintf(", %s (%.2f similar)", $similar[$i]->[0], $similar[$i]->[1]);
                }

                $why .= sprintf(' and %d others', @similar - 3) if (@similar > 3);
                FYR::Queue::logmsg($msg->{id}, 0, $why);
            }

            # Generate a bunch of useful metrics

            my $similarity_max;
            if (@similar) {
                $similarity_max = $similar[0]->[1];
            } else {
                $similarity_max = undef;
            }
            $res{similarity_samerep_max} = [$similarity_max, 
                'Similarity score of the message to the same recipient whose body is most similar to this one, or absent if none is more than ' .  
                mySociety::Config::get('MESSAGE_SIMILARITY_THRESHOLD')];

            foreach my $thr (qw(0.5 0.6 0.7 0.8 0.9 0.95 0.99)) {
                next if ($thr < mySociety::Config::get('MESSAGE_SIMILARITY_THRESHOLD'));
                my $n = scalar(grep { $_->[1] > $thr } @similar);
                $res{"similarity_samerep_num_$thr"} = [$n, "Number of messages to the same recipient at least $thr similar to this one"];
            }

            return %res;
        }

    );

my @group_tests = (
        # Country of origin of IP address
        sub ($) {
            my ($msg) = @_;
            my $cc = mySociety::Gaze::get_country_from_ip($msg->{sender_ipaddr});
            $cc ||= 'unknown';
            return ( sender_ip_country => [$cc, 
                "Country of constituent's IP address, or localhost if 127.0.0.1"] );
        },

        # Length of message, in characters and words
        sub ($) {
            my ($msg) = @_;
            my $l1 = length($msg->{message});
            my @words = split(/[[:space:]]+/, $msg->{message});
            my $l2 = scalar(@words);
            FYR::Queue::logmsg($msg->{id}, 0, sprintf('message length: %d words, %d characters', $l2, $l1));
            return (
                    message_length_characters => [$l1, 'Number of characters in the message, including salutation and signature'],
                    message_length_words => [$l2, 'Number of words in the message, where words are separated by whitespace']
                );
        },

        # Body of message similar to other messages in queue to different recipients
        sub ($) {
	    
            my ($msg) = @_;
            my @similar = sort { $b->[1] <=> $a->[1] } get_similar_messages($msg);

            my %res = ( );
            if (@similar) {
                my $why = sprintf('message body is very similar to %s (%.2f similar)', $similar[0]->[0], $similar[0]->[1]);
                for (my $i = 1; $i < 3 && $i < @similar; ++$i) {
                    $why .= sprintf(", %s (%.2f similar)", $similar[$i]->[0], $similar[$i]->[1]);
                }

                $why .= sprintf(' and %d others', @similar - 3) if (@similar > 3);
                FYR::Queue::logmsg($msg->{id}, 0, $why);
            }

            # Generate a bunch of useful metrics

            my $similarity_max;
            if (@similar) {
                $similarity_max = $similar[0]->[1];
            } else {
                $similarity_max = undef;
            }
            $res{similarity_max} = [$similarity_max, 
                'Similarity score of the message whose body is most similar to this one, or absent if none is more than ' .  
                mySociety::Config::get('MESSAGE_SIMILARITY_THRESHOLD')];

            foreach my $thr (qw(0.5 0.6 0.7 0.8 0.9 0.95 0.99)) {
                next if ($thr < mySociety::Config::get('MESSAGE_SIMILARITY_THRESHOLD'));
                my $n = scalar(grep { $_->[1] > $thr } @similar);
                $res{"similarity_num_$thr"} = [$n, "Number of messages at least $thr similar to this one"];
            }

            return %res;
        },

        # Second postcode given in address.
        sub ($) {
            my $msg = shift;

            my $pc = $msg->{sender_postcode};

            # Construct address without postcode.
            my $addr = $msg->{sender_addr};
            $addr =~ s/$pc\s*$//s;
            
            # Now look for a postcode in the address.
            $addr =~ /([A-Z][A-Z]?[0-9][0-9A-Z]?\s*[0-9][A-Z][A-Z])/i;
            my ($newpc) = $1;
            return () unless defined($newpc);

            my $newpc_nospaces = $newpc;
            $newpc_nospaces =~ s/\s//g;
            my $pc_nospaces = $pc;
            $pc_nospaces =~ s/\s//g;
            return () if (lc($newpc_nospaces) eq lc($pc_nospaces));

            # See whether (a) the postcode they've given is known to us; and
            # (b) whether it gives the same voting area as the other one.
            my $is_known = 0;
            my $yields_same_voting_area = 0;
            try {
                my $generation = mySociety::Config::get('MAPIT_GENERATION');
                my $areas = mySociety::MaPit::call('postcode', $newpc, $generation ? (generation => $generation) : ());
                $is_known = 1;
                my $rep = mySociety::DaDem::get_representative_info($msg->{recipient_id});
                #warn "va: $rep->{voting_area} dump: ".  Dumper(\%h). Dumper($rep);
                $yields_same_voting_area = 1 if (exists($areas->{areas}->{$rep->{voting_area}}));
            } catch RABX::Error with {
                # don't much care about the error -- presumably it'll be area
                # not found, though it might (rarely) be some transient error
                # which we can ignore.
            };

            return ( sender_addr_second_postcode_unknown =>
                        [$newpc, 'Additional postcode in the address, if present and not known to MaPit'] )
                if (!$is_known);

            return ( sender_addr_second_postcode_same_voting_area =>
                        [$newpc, 'Additional postcode in the address, if present and lying within the same voting area as the supplied postcode'] )
                if ($yields_same_voting_area);

            return ( sender_addr_second_postcode_different_voting_area =>
                        [$newpc, 'Additional postcode in the address, if present and lying within a different voting area to the supplied postcode'] );
        }
    );      

=item test MESSAGEHASH

Perform abuse checks on each MESSAGE (hash of database fields) in MESSAGEHASH. 
This performs tests on each message (which may themselves log information), and 
passes the message and the results of the tests to the rate limiter under scope
"fyr-abuse". The function a hash keyed on message id. The values in the hash have
the following meanings: undef to indicate that delivery should proceed as normal, 
'freeze' to indicate that the message should be frozen for inspection by an 
administrator, or, if the message should be rejected completely, the name of a 
template which should be displayed to the user to explain why their message has 
been rejected.

=cut 
sub test ($) {
    my ($msg_hash) = @_;
    my %ratty_hash;
    my %abuse_results;
    my %new_ratty_values;
    foreach my $id (keys %$msg_hash){
        my $msg = $msg_hash->{$id};
        my $pc = $msg->{sender_postcode};
        $pc =~ s#\s##g;
        $pc = uc($pc);
        my ($pc_a, $pc_b) = ($pc =~ m#^(.*)(\d[A-Z]{2})$#);
        (my $sender_addr_nopostcode = $msg->{sender_addr}) =~ s/\Q$pc_a\E\s*\Q$pc_b\E//gi;
        $ratty_hash{$id} = {
            # Useful fields for sending to Ratty
            message => [$msg->{message}, "Body text of message"],
            recipient_email => [$msg->{recipient_email}, "Email address of representative"],
            recipient_id => [$msg->{recipient_id}, "DaDem identifier of representative"],
            recipient_name => [$msg->{recipient_name}, "Name of representative"],
            recipient_position => [$msg->{recipient_position}, "Office held by representative"],
            recipient_type => [$msg->{recipient_type}, "Type of voting area representative represents"],
            sender_addr => [$msg->{sender_addr}, "Postal address of constituent"],
            sender_addr_nopostcode => [$sender_addr_nopostcode, "Postal address of constituent, without any instances of their postcode"],
            sender_email => [$msg->{sender_email}, "Email address of constituent"],
            sender_ipaddr => [$msg->{sender_ipaddr}, "IP address of constituent"],
            sender_name => [$msg->{sender_name}, "Name of constituent"],
            sender_postcode => [$msg->{sender_postcode}, "Postcode of constituent"],
            sender_referrer => [$msg->{sender_referrer}, "Webpage from which constituent came to our site"],

            # These aren't much use, but could conceivably be
            created => [POSIX::strftime('%Y-%m-%dT%H:%M:%S', localtime($msg->{created})), "When message was created (local time, ISO format)"],
            id => [$msg->{id}, "Unique identifier of message"],
        };
    }
    foreach my $id (keys %ratty_hash){
        # Carry out individual abuse tests, and store new fields they generate
        foreach my $f (@individual_tests) {
            %new_ratty_values = &$f($msg_hash->{$id});           
            foreach my $what (keys %new_ratty_values) {
                $ratty_hash{$id}{$what} = $new_ratty_values{$what};
            }
        }

    }
    # Carry out group abuse tests, and store new fields they generate.
    # These tests will return the same values for every message in a group,
    # so just run them with one message and share the results.
    foreach my $f (@group_tests) {
        my @ids = (keys %ratty_hash);
        %new_ratty_values = &$f($msg_hash->{$ids[0]});           
        foreach my $id (keys %ratty_hash){
            foreach my $what (keys %new_ratty_values) {
                $ratty_hash{$id}{$what} = $new_ratty_values{$what};
            }
        }
    }
     
    foreach my $id (keys %ratty_hash){
        # Perform test.
        my $result = mySociety::Ratty::test('fyr-abuse', $ratty_hash{$id});
        if (defined($result)) {
            my ($ruleid, $action, $title) = @$result;
            FYR::Queue::logmsg($id, 1, "fyr-abuse rule #$ruleid '$title' fired for message; result: $action");
            $abuse_results{$id} = $action;
        } else {
            $abuse_results{$id} = undef;
        }
    } 
    return \%abuse_results;
}

1;
