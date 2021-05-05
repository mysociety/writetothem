#!/usr/bin/perl
#
# FYR/Queue.pm:
# Fax/email queue management for FYR.
#
# Copyright (c) 2012 UK Citizens Online Democracy. All rights reserved.
# Email: matthew@mysociety.org; WWW: http://www.mysociety.org/

package FYR::Queue;

use strict;

# This has to be right at the top. Probably because of a cyclic dependency
# between this and FYR::AbuseChecks.
BEGIN {
    use Exporter;
    @FYR::Queue::ISA = qw(Exporter);
    @FYR::Queue::EXPORT_OK = qw(logmsg);
};

use Convert::Base32;
use Crypt::CBC;
use DBI;
use Email::Address;
use Encode;
use Encode::Byte;       # for cp1252
use Error qw(:try);
use Fcntl;
use FindBin;
use HTML::Entities;
use IO::Socket;
use IO::All;
use Net::DNS::Resolver;
use POSIX qw(strftime);
use Text::Wrap (); # don't pollute our namespace
use Time::HiRes ();
use Data::Dumper;
use Email::MIME;

use utf8;

use mySociety::Config;
use mySociety::DaDem;
use mySociety::DBHandle qw(dbh new_dbh);
use mySociety::Email;
use mySociety::MaPit;
use mySociety::EmailUtil;
use mySociety::PostcodeUtil;
use mySociety::Random;
use mySociety::VotingArea;
use mySociety::StringUtils qw(trim merge_spaces string_diff);
use mySociety::SystemMisc qw(print_log);

use FYR;
use FYR::AbuseChecks;
use FYR::EmailTemplate;
use FYR::EmailSettings;
use FYR::Fax;
use FYR::Cobrand;

our $message_calculated_values = "
    length(message) as message_length,
    (select count(*) from questionnaire_answer where questionnaire_answer.message_id = message.id
        and question_id = 0 and answer = 'no') as questionnaire_0_no,
    (select count(*) from questionnaire_answer where questionnaire_answer.message_id = message.id
        and question_id = 0 and answer = 'yes') as questionnaire_0_yes,
    (select count(*) from questionnaire_answer where questionnaire_answer.message_id = message.id
        and question_id = 1 and answer = 'no') as questionnaire_1_no,
    (select count(*) from questionnaire_answer where questionnaire_answer.message_id = message.id
        and question_id = 1 and answer = 'yes') as questionnaire_1_yes
";


=head1 NAME

FYR.Queue

=head1 DESCRIPTION

Implementation of management of message queue for FYR.

=head1 CONSTANTS

=head2 Error codes

=over 4

=item MESSAGE_ALREADY_QUEUED 4001

Tried to send message which has already been sent.

=item MESSAGE_ALREADY_CONFIRMED 4002

Tried to confirm message which has already been confirmed.

=item MESSAGE_BAD_ADDRESS_DATA 4003

Contact data not available for that representative.

=item MESSAGE_SHAME 4004

Representative does not want to be contacted

=item GROUP_ALREADY_QUEUED 4006

Tried to send group of messages which has already been sent.

=back

=head1 FUNCTIONS

=over 4

=item create

Return an ID for a new message. Message IDs are 20 characters long and consist
of characters [0-9a-f] only.

=cut
use constant MESSAGE_ID_LENGTH => 20;
sub create () {
    # Assume collision probability == 0.
    return unpack('h20', mySociety::Random::random_bytes(MESSAGE_ID_LENGTH / 2, 1));
}

=item logmsg_handler ID TIME STATE MESSAGE IMPORTANT

Default callback for logmsg, so that we can log fyrqd messages to the system log or
standard error for easier debugging.

=cut
sub logmsg_handler ($$$$$) {
    mySociety::SystemMisc::log_to_stderr(0);
    my ($id, $time, $state, $msg, $important) = @_;
    print_log('info',
            "message $id($state): $msg");
    print_log('info',
            "last message delayed by " . (time() - $time) . " seconds")
                if ($time > time() + 5);
}

=item create_group

Return an ID for a new group of messages. Group IDs, like messages IDs, are 20
characters long and consist of characters [0-9a-f] only.

=cut
sub create_group () {
    return create();
}

=item check_group_unused GROUP_ID

Throws an error if the GROUP_ID is already present in the message table

=cut
sub check_group_unused($){
    my ($group_id) = @_;
    my $ret = undef;
    if (my $msg = dbh()->selectrow_hashref("select * from message where group_id = ?", {}, $group_id)){
        throw FYR::Error("You've already sent these messages, there's no need to send them twice.", FYR::Error::GROUP_ALREADY_QUEUED);
    }
    return $ret;
}

# get_via_representative ID
# Given a voting area ID, return reference to a hash of information about any
# 'via' representative (e.g. council) which can be used to contact
# representatives of that area.
sub get_via_representative ($) {
    my ($aid) = @_;
    my $ainfo = mySociety::MaPit::call('area', $aid);
    my $vainfo = mySociety::DaDem::get_representatives($ainfo->{parent_area});

    throw FYR::Error("Bad return from DaDem looking up contact via info", FYR::Error::BAD_DATA_PROVIDED)
        unless (ref($vainfo) eq 'ARRAY');
    throw FYR::Error("More than one via contact (shouldn't happen)", FYR::Error::BAD_DATA_PROVIDED)
        if (@$vainfo > 1);
    throw FYR::Error("Sorry, no contact details.", FYR::Error::MESSAGE_BAD_ADDRESS_DATA)
        if (!@$vainfo);

    my $viainfo = mySociety::DaDem::get_representative_info($vainfo->[0]);
    $viainfo->{id} = $vainfo->[0];
    return $viainfo;
}

# work_out_destination RECIPIENT
# Internal use. Takes a RECIPIENT (reference to hash of fields), and uses their
# contact method to set the fax or email fields. Gives an error if contact
# method is inconsistent with them, and leaves exactly one of fax or email
# defined. Sets the RECIPIENT->{via} if the message is to be sent via the
# elected body's Democratic Services or similar contact, and sets up fax and
# email fields for that delivery.
sub work_out_destination ($);
sub work_out_destination ($) {
    my ($recipient) = @_;

    $recipient->{via} = 0 if (!exists($recipient->{via}));

    # Normalise any false values to undef
    $recipient->{fax} ||= undef;
    $recipient->{email} ||= undef;

    # Decide how to send the message.
    if ($recipient->{method} eq "either") {
        throw FYR::Error("Either contact method specified, but fax not defined.", FYR::Error::MESSAGE_BAD_ADDRESS_DATA) if (!defined($recipient->{fax}));
        throw FYR::Error("Either contact method specified, but email not defined.", FYR::Error::MESSAGE_BAD_ADDRESS_DATA) if (!defined($recipient->{email}));
        if (rand(1) < 0.5) {
            $recipient->{fax} = undef;
        } else {
            $recipient->{email} = undef;
        }
    } elsif ($recipient->{method} eq "fax") {
        throw FYR::Error("Fax contact method specified, but not defined.", FYR::Error::MESSAGE_BAD_ADDRESS_DATA) if (!defined($recipient->{fax}));
        $recipient->{email} = undef;
    } elsif ($recipient->{method} eq "email") {
        throw FYR::Error("Email contact method specified, but not defined.", FYR::Error::MESSAGE_BAD_ADDRESS_DATA) if (!defined($recipient->{email}));
        $recipient->{fax} = undef;
    } elsif ($recipient->{method} eq "shame") {
        throw FYR::Error("Representative has told us they do not want WriteToThem.com to deliver messages for them.", FYR::Error::MESSAGE_SHAME);
    } elsif ($recipient->{method} eq "via") {
        # Representative should be contacted via the elected body on which they
        # sit.
        my $viainfo = get_via_representative($recipient->{voting_area});
        throw FYR::Error("Bad contact method for via contact (shouldn't happen)", FYR::Error::BAD_DATA_PROVIDED)
            if ($viainfo->{method} eq 'via');

        foreach (qw(method fax email)) {
            $recipient->{$_} = $viainfo->{$_};
        }
        $recipient->{via} = 1;
        work_out_destination($recipient);
    } elsif ($recipient->{method} eq "unknown") {
        throw FYR::Error("Sorry, no contact details.", FYR::Error::MESSAGE_BAD_ADDRESS_DATA);
    } else {
        throw FYR::Error("Unknown contact method '" .  $recipient->{method} . "'.", FYR::Error::MESSAGE_BAD_ADDRESS_DATA);
    }
}

=item recipient_test RECIPIENT

Verifies the contact method of the recipient.  Throws an error if they
do not have a fax or email address corresponding to the contact method
set for them.  RECIPIENT is the DaDem ID number of the recipient.

=cut
sub recipient_test ($) {
    my ($recipient_id) = @_;

    # Get details of the recipient.
    throw FYR::Error("No RECIPIENT specified", FYR::Error::BAD_DATA_PROVIDED)
        if (!defined($recipient_id) or $recipient_id =~ /[^\d]/ or $recipient_id eq '');
    my $recipient = mySociety::DaDem::get_representative_info($recipient_id);
    throw FYR::Error("Bad RECIPIENT or error ($recipient) in DaDem", FYR::Error::BAD_DATA_PROVIDED)
        if (!$recipient or ref($recipient) ne 'HASH');
    throw FYR::Error('This is a deleted representative', FYR::Error::REPRESENTATIVE_DELETED)
        if $recipient->{deleted};

    # Decide how to send message
    work_out_destination($recipient);

    return 1
}



=item write_messages IDLIST SENDER RECIPIENTLIST TEXT [COBRAND] [COCODE] [GROUP_ID] [NO_QUESTIONNAIRE]

Write details of a set of messages for sending in one transaction.

IDLIST is a list of the identities of the messages,

SENDER is a reference to hash containing details of the sender including
elements: name, the sender's full name; email, their email address; address,
their full postal address; postcode, their post code; and optionally phone,
their phone number; ipaddr, their IP address; referrer, website that referred
them to this one.

RECIPIENTLIST is a list of is the DaDem ID numbers of the recipients of the message;
and TEXT is the text of the message, with line breaks.

COBRAND is the name of cobranding partner (e.g. "cheltenham"), and COCODE is
a reference code for them.

GROUP_ID is the identity of a group of messages sent by the same sender at the
same time with the same content to a group of representatives.

NO_QUESTIONNAIRE is an optional flag indicating that no questionnaires should be sent
for these messages

Returns an associative array keyed on message ID. Each value is a associative array
with the following keys

'recipient_id' - DaDem id of the recipient
'status_code' - 0 = success, 1 = FYR::Error in queueing, 2 = Flagged for abuse
'abuse_result' - result of abuse flagging or undef
'error_code'  - FYR::Error code or undef
'error_text'- FYR::Error text or undef

This function is called remotely and commits its changes.

=cut
sub write_messages($$$$;$$$$){

    my ($msgidlist, $sender, $recipient_list, $text, $cobrand, $cocode, $group_id, $no_questionnaire) = @_;
    my %ret = ();
    my $recipient_id;
    my $id;

    # should have the same number of msgids as recipients
    if (scalar(@$msgidlist) != scalar(@$recipient_list)) {
        throw FYR::Error("Mismatch in MSG_ID_LIST and RECIPIENT_LIST params", FYR::Error::BAD_DATA_PROVIDED);
    }
    # If there are multiple messages, there should be a group id
    if (scalar(@$msgidlist) > 1 && !defined($group_id)) {
        throw FYR::Error("No group ID supplied for multiple messages", FYR::Error::BAD_DATA_PROVIDED);
    }

    if ($no_questionnaire){
        $no_questionnaire = 't';
    }else{
        $no_questionnaire = 'f';
    }

    try{

        # Check that sender contains appropriate information.
        throw FYR::Error("Bad SENDER (not reference-to-hash", FYR::Error::BAD_DATA_PROVIDED)
            unless (ref($sender) eq 'HASH');
        foreach (qw(name email address postcode)) {
            throw FYR::Error("Missing required '$_' element in SENDER", FYR::Error::BAD_DATA_PROVIDED)
                unless (exists($sender->{$_}));
        }
        throw FYR::Error("Email address '$sender->{email}' for SENDER is not valid", FYR::Error::BAD_DATA_PROVIDED)
            unless $sender->{email} =~ $Email::Address::addr_spec;
        throw FYR::Error("Postcode '$sender->{postcode}' for SENDER is not valid", FYR::Error::BAD_DATA_PROVIDED)
            unless (mySociety::PostcodeUtil::is_valid_postcode($sender->{postcode}));

        foreach $id (@$msgidlist){
            try{

                $recipient_id = pop(@$recipient_list);
                $ret{$id} = {recipient_id => $recipient_id,
                             status_code  => undef,
                             abuse_result => undef,
                             error_code   => undef,
                             error_text   => undef};

                #pre insertion checks
                throw FYR::Error("Bad ID specified", FYR::Error::BAD_DATA_PROVIDED)
                    unless ($id =~ m/^[0-9a-f]{20}$/i);
                # Get details of the recipient.
                throw FYR::Error("No RECIPIENT specified", FYR::Error::BAD_DATA_PROVIDED)
                    if (!defined($recipient_id) or $recipient_id =~ /[^\d]/ or $recipient_id eq '');
                my $recipient = mySociety::DaDem::get_representative_info($recipient_id);
                throw FYR::Error("Bad RECIPIENT or error ($recipient) in DaDem", FYR::Error::BAD_DATA_PROVIDED)
                    if (!$recipient or ref($recipient) ne 'HASH');

                $recipient->{position} = $mySociety::VotingArea::rep_name{$recipient->{type}};

                # Give recipient their proper prefixes/suffixes.
                $recipient->{name} = mySociety::VotingArea::style_rep($recipient->{type}, $recipient->{name});

                # Decide how to send message
                work_out_destination($recipient);

                # Bodge things so that mails go to sender if in testing
                # ids > 2000000 are the ZZ9 9ZZ postcode test addresses.
                if ($recipient_id < 2000000) {
                    if (mySociety::Config::get('FYR_REFLECT_EMAILS')) {
                    $recipient->{email} = $sender->{email};
                    $recipient->{fax} = undef;
                    }
                }

                # Strip any leading spaces from the address.
                $sender->{address} = join("\n", map { s#^\s+##; $_ } split("\n", $sender->{address}));

                #check for existing message with this ID
                if (my $msg = dbh()->selectrow_hashref("select * from message where id = ?", {}, $id)){
                    throw FYR::Error("You've already sent this message, there's no need to send it twice.", FYR::Error::MESSAGE_ALREADY_QUEUED);
                }

                # XXX should also check that the text bits are valid UTF-8.

                # Queue the message.

                dbh()->do(q#
                    insert into message (
                        id,
                        sender_name, sender_email, sender_addr, sender_phone,
                        sender_postcode, sender_ipaddr, sender_referrer,
                        recipient_id, recipient_name, recipient_type,
                        recipient_email, recipient_fax,
                        recipient_via,
                        message,
                        state,
                        created, laststatechange,
                        numactions, dispatched,
                        cobrand, cocode, group_id, no_questionnaire
                    ) values (
                        ?,
                        ?, ?, ?, ?, ?, ?, ?,
                        ?, ?, ?,
                        ?, ?,
                        ?,
                        ?,
                        'new',
                        ?, ?,
                        0, null,
                        ?, ?, ?, ?
                    )#, {},
                        $id,
                        (map { as_utf8_octets($sender->{$_}) || undef } qw(name email address phone postcode ipaddr referrer)),
                        $recipient_id,
                        (map { as_utf8_octets($recipient->{$_}) || undef } qw(name type email fax)),
                        $recipient->{via} ? 't' : 'f',
                        as_utf8_octets($text),
                        FYR::DB::Time(), FYR::DB::Time(),
                        $cobrand, $cocode, $group_id, $no_questionnaire);

                # Log creation of message but don't commit yet
                my $logaddr = $sender->{address};
                $logaddr =~ s#\n#, #gs;
                $logaddr =~ s#,+#,#g;
                $logaddr =~ s#, *$##;

                logmsg($id, 1,
                    sprintf("created new message from %s <%s>%s, %s, to %s via %s to %s",
                    $sender->{name},
                    $sender->{email},
                    $sender->{phone} ? " $sender->{phone}" : "",
                    $logaddr,
                    $recipient->{name},
                    defined($recipient->{fax}) ? "fax" : "email",
                    $recipient->{fax} || $recipient->{email}));
                $ret{$id}{status_code} = 0;
            }catch FYR::Error with{
                #add the error to the result for this message
                my $E = shift;
                $ret{$id}{status_code} = 1;
                $ret{$id}{error_code} = $E->value();
                $ret{$id}{error_text} = $E->text();
            };
        }
    }otherwise{

        # If there's a real problem inserting one of the messages,
        # rollback the whole set
        my $E = shift;
        if ($E->value()) {
           warn "fyr queue rolling back transaction after error: " . $E->text() . "\n";
        }
        dbh()->rollback();
        throw $E;
    }finally{
        dbh()->commit();
    };

     try{
         # now do abuse checks on the messages
         my $abuse_result;
         # lock the group until we know if they're OK
         # otherwise the queue could send the confirmation
         if (defined($group_id)){
             lock_group($group_id);
         }

         # make a hash of the messages that have been created
         my %msg_hash;
         foreach $id (@$msgidlist){
             # If the message already threw an error
             # it won't be in the queue
             if ($ret{$id}{status_code} == 0){
                 $msg_hash{$id} = message($id);
             }
         }

         # Check for possible abuse
         if (keys %msg_hash){
             my $abuse_results = FYR::AbuseChecks::test(\%msg_hash);
             foreach $id (keys %$abuse_results) {
                 $abuse_result = $abuse_results->{$id};
                 if (defined($abuse_result)) {
                     if ($abuse_result eq 'freeze') {
                         logmsg($id, 1, "abuse system froze message");
                         dbh()->do("update message set frozen = 't' where id = ?", {}, $id);
                     } else {
                         # Delete the message, so people can go back and try again
                         dbh()->do("delete from message_bounce where message_id = ?", {}, $id);
                         dbh()->do("delete from message_extradata where message_id = ?", {}, $id);
                         dbh()->do("delete from message_log where message_id = ?", {}, $id);
                         dbh()->do("delete from message where id = ?", {}, $id);
                         $ret{$id}{status_code} = 2;
                         $ret{$id}{abuse_result} = $abuse_result;
                     }
                 }
             }
         }
         # Commit changes
         dbh()->commit();

         # Wake up the daemon to send the confirmation mail.
         notify_daemon();
     }otherwise{
         # If there's a real problem with the abuse stuff
         # rollback all the abuse checks
         my $E = shift;
         if ($E->value()) {
             warn "fyr queue rolling back transaction after error in abuse check: " . $E->text() . "\n";
         }
         dbh()->rollback();
         throw $E;
     };

    return \%ret;
}

my $logmsg_handler;
sub logmsg_set_handler ($) {
    $logmsg_handler = $_[0];
}
# Set default handler
#logmsg_set_handler(\&logmsg_handler);

# logmsg ID IMPORTANT DIAGNOSTIC [EDITOR]
# Log a DIAGNOSTIC about the message with the given ID. If IMPORTANT is true,
# then mark the log message as exceptional. Optionally, EDITOR is the name
# of the human who performed the action relating to the log message.
sub logmsg ($$$;$) {
    my ($id, $important, $msg, $editor) = @_;
    our $dbh;
    ($dbh) = mySociety::DBHandle::dbh_test($dbh);
    our $log_hostname;
    $log_hostname ||= (POSIX::uname())[1];
    $dbh->do('
        insert into message_log (
            message_id,
            hostname, whenlogged, state,
            message, exceptional,
            editor
        ) values (?, ?, ?, ?, ?, ?, ?)',
        {},
        $id,
        $log_hostname, FYR::DB::Time(), state($id),
        as_utf8_octets($msg), $important ? 't' : 'f',
        $editor);
    $dbh->commit();
    # XXX should we pass the hostname to the handler?
    &$logmsg_handler($id, FYR::DB::Time(), state($id), $msg, $important)
        if (defined($logmsg_handler));
}

# log_to_handler IMPORTANT DIAGNOSTIC [EDITOR]
# log a DIAGNOSTIC only to any log handler that has been set, not to the database.
# If IMPORTANT is true, then mark the log message as exceptional. Optionally,
# EDITOR is the name of the human who performed the action relating to the log message.
sub log_to_handler($$$;$){
    my ($id, $important, $msg, $editor) = @_;
    &$logmsg_handler($id, FYR::DB::Time(), state($id), $msg, $important)
        if (defined($logmsg_handler));
}

# %allowed_transitions
# Transitions we're allowed to make in the state machine.
my %allowed_transitions = (
        new =>              [qw(pending failed_closed)],
        pending =>          [qw(ready failed_closed)],
        ready =>            [qw(error bounce_wait sent)],
        bounce_wait =>      [qw(bounce_confirm sent ready error)],
        bounce_confirm =>   [qw(bounce_wait error ready)],
        error =>            [qw(failed)],
        sent =>             [qw(finished)],
        failed =>           [qw(ready failed_closed)],
        finished =>         [],
        failed_closed =>    [qw(ready)]
    );

# turn this into hash-of-hashes
foreach (keys %allowed_transitions) {
    my $x = { map { $_ => 1 } @{$allowed_transitions{$_}} };
    $allowed_transitions{$_} = $x;
}

# scrubmessage ID
# Remove some personal data from message ID. This includes the text of
# the letter, any extradata and bounce messages (since they usually
# contain quoted text).
sub scrubmessage ($) {
    my ($id) = @_;
    FYR::DB::scrub_message($id);
    logmsg($id, 1, 'Scrubbed message of (some) personal data');
}

=item state ID [STATE]

Get/change the state of the message with the given ID to STATE. If STATE is the
same as the current state, just update the lastaction and numactions fields; if
it isn't, then set the lastaction field to null, the numactions field to 0, and
update the laststatechange field. If a message is moved to the "failed" or
"finished" states, then it is stripped of personal identifying information.

=cut
sub state ($;$) {
    my ($id, $state) = @_;
    if (defined($state)) {
        my ($curr_state, $curr_frozen) = dbh()->selectrow_array('select state, frozen from message where id = ? for update', {}, $id);
        die "Bad state '$state'" unless (exists($allowed_transitions{$state}));
        die "Can't go from state '$curr_state' to '$state'" unless ($state eq $curr_state or $allowed_transitions{$curr_state}->{$state} or ($state eq 'failed' or $state eq 'error' or $state eq 'failed_closed'));
        if ($state ne $curr_state) {
            dbh()->do('update message set lastaction = null, numactions = 0, laststatechange = ?, state = ? where id = ?', {}, FYR::DB::Time(), $state, $id);
            logmsg($id, 0, "changed state to $state");

            # On moving into the 'finished' state, scrub message of personal
            # information. Don't do this for 'failed' messages since we want to
            # keep log and other information around for a bit to debug
            # problems; the action for the failed state will scrub such
            # messages later on.
            if ($state eq 'finished') {
                scrubmessage($id);
            }
        } else {
            dbh()->do('update message set lastaction = ?, numactions = numactions + 1 where id = ?', {}, FYR::DB::Time(), $id);
        }
        return $curr_state;
    } else {
        return scalar(dbh()->selectrow_array('select state from message where id = ?', {}, $id));
    }
}

=item group_state ID [STATE] [GROUP_ID]

If the message with the ID passed belongs to a group, get/change the state
of the messages in the group with the given ID to STATE, using the state
method. Otherwise get/change the state of the message, using the state
method.

=cut
sub group_state ($;$$){
    my ($id, $state, $group_id) = @_;
    if (defined($group_id)){
        if (defined($state)) {

            # Get all the message IDs in the group
            my $msgs = group_messages($group_id);

            # Set the state for each one
            foreach $id (@$msgs){
                state($id, $state);
            }
        }
        my $states = dbh()->selectcol_arrayref("select distinct
         state from message where group_id = ?", {}, $group_id);
        return $states;
    }else{
        return state($id, $state);
    }

}

=item group_messages GROUP_ID

Return an array of the IDs for messages in the group GROUP_ID

=cut

sub group_messages($){
    my ($group_id) = @_;
     # Get all the message IDs in the group
    my $msgs = dbh()->selectcol_arrayref("select id from message where group_id = ? order by id", {}, $group_id);
    return $msgs;
}

=item other_recipient_list GROUP_ID ID

Return a string consisting of comma-delimited recipient names for other messages in GROUP_ID, omitting the recipient of message ID

=cut

sub other_recipient_list($$){
    my ($group_id, $id) = @_;
    my @recipients;
    my $recipient_string;
    my $memberid;
    my $msgs = group_messages($group_id);
    foreach $memberid (@$msgs){
        if ($memberid ne $id){
            my $msg = message($memberid);
            push @recipients, $msg->{recipient_name};
        }
    }
    $recipient_string = join(", ", @recipients);
    return $recipient_string;
}

# message ID [LOCK] [NOWAIT]
# Return a hash of data about message ID. If LOCK is true, retrieves the fields
# using SELECT ... FOR UPDATE. If NOWAIT is true, uses the NOWAIT option of the FOR UPDATE
# clause, returning undef if no lock can be obtained on the message.
sub message ($;$$) {
    my ($id, $forupdate, $nowait) = @_;
    $forupdate = defined($forupdate) ? ' for update' : '';
    my $nowait_str = defined($nowait) ? ' nowait' : '';
    my $msg;
    try{
        $msg = dbh()->selectrow_hashref("select * from message where id = ?$forupdate$nowait_str", {}, $id);
        if ($msg) {
            # Add some convenience fields.
            my $recipient_position = $mySociety::VotingArea::rep_name{$msg->{recipient_type}};
            my $recipient_position_plural = $mySociety::VotingArea::rep_name_plural{$msg->{recipient_type}};
            $recipient_position = FYR::Cobrand::recipient_position($msg->{cobrand}, $recipient_position);
            $recipient_position_plural = FYR::Cobrand::recipient_position_plural($msg->{cobrand}, $recipient_position_plural);
            $msg->{recipient_position} = $recipient_position;
            $msg->{recipient_position_plural} = $recipient_position_plural;
            return $msg;
        } else {
            throw FYR::Error("No message '$id'.", FYR::Error::BAD_DATA_PROVIDED);
        }
    } catch mySociety::DBHandle::Error with {
        my $E = shift;
        if ($E->text() =~ /could not obtain lock on row/ && defined($nowait)){
            return undef;
        }else{
            throw mySociety::DBHandle::Error($E->text());
        }
    };
}

# lock_group GROUP_ID
# Lock the GROUP_ID group of messages using SELECT ... FOR UPDATE
sub lock_group($) {
    my ($group_id) = @_;
    my $memberid;
    my $msgs = group_messages($group_id);
    throw FYR::Error("No group '$group_id'.", FYR::Error::BAD_DATA_PROVIDED) unless ($msgs > 0);
    foreach $memberid (@$msgs){
        my $sth = dbh()->prepare("select * from message where id = ? for update");
        $sth->execute($memberid);
    }
}


# EMAIL_COLUMNS
# How long a line we permit in an email.
use constant EMAIL_COLUMNS => 72;

# wrap WIDTH TEXT
# Wrap TEXT to WIDTH.
sub wrap ($$) {
    my ($width, $text) = @_;
    local($Text::Wrap::columns) = $width;
    local($Text::Wrap::huge) = 'overflow';      # user may include URLs which shouldn't be wrapped
    return Text::Wrap::wrap('', '', $text);
}

# format_postal_address TEXT
# Format TEXT as a postal address, right-aligned.
sub format_postal_address ($) {
    my ($text) = @_;
    my @lines = split(/\n/, $text);
    $text = '';
    local($Text::Wrap::columns) = EMAIL_COLUMNS / 2;
    local($Text::Wrap::huge) = 'overflow';
    my $maxlen = 0;
    foreach (@lines) {
        my $l = length($_);

        $_ .= "\n";
        if ($l > EMAIL_COLUMNS / 2) {
            $l = EMAIL_COLUMNS / 2;
            $_ = Text::Wrap::wrap('', '  ', $_);
        }

        $maxlen = $l if ($l > $maxlen);

        $text .= $_;
    }

    $text =~ s#^#" " x (EMAIL_COLUMNS - $maxlen)#gme;

    return $text;
}

# make_house_of_lords_address PEER
# Return the address of the named PEER at the House of Lords.
sub make_house_of_lords_address ($) {
    my $n = shift;

    # forms of address on envelope -- see,
    #   http://www.parliament.uk/directories/house_of_lords_information_office/address.cfm
    my %a = (
            'Baron' => 'The Lord',
            'Baroness' => 'The Baroness',
            'Countess' => 'The Countess',
            'Duke' => 'His Grace, the Duke',
            'Earl' => 'The Earl',
            'Lady' => 'The Lady',
            'Marquess' => 'The Most Hon. the Marquess',
            'Viscount' => 'The Viscount',
            'Archbishop' => 'The Most Rev. and the Rt Hon. the Archbishop',
            'Bishop' => 'The Rt Rev. the Lord Bishop'
        );

    my $re = '(' . join('|', map { quotemeta($_) } keys(%a)) . ')';
    $n =~ s/^($re)/$a{$1}/;

    return "$n\nHouse of Lords\nLondon\nSW1A 0PW\n";
}

# format_email_body MESSAGE
# Format MESSAGE (a hash of database fields) suitable for sending as an email.
# We honour every carriage return, and wrap lines.
sub format_email_body ($) {
    my ($msg) = @_;

    my $addr = "$msg->{sender_name}\n$msg->{sender_addr}";
    $addr .= "\n\n" . "Phone: $msg->{sender_phone}" if (defined($msg->{sender_phone}));
    $addr .= "\n\n" . "Email: $msg->{sender_email}";

    # Also stick the date in, formatted under the address as it would be in a
    # real letter. Of course, it'll be in the Date: header too, but we may as
    # well be consistent with how the faxes look.
    my $formatted_date = strftime('%A %e %B %Y', localtime($msg->{created}));
    $formatted_date =~ s/  / /g; # remove zero pad
    $addr .= "\n\n$formatted_date";

    my $text = format_postal_address($addr)
                . "\n\n"
                . "\n";

    # If the message is going to a peer via, then stick their House of Lords
    # address on it to.
    if ($msg->{recipient_via} && $msg->{recipient_type} eq 'HOC') {
        $text .= make_house_of_lords_address($msg->{recipient_name}) . "\n";
    }

    # and now the actual text.
    $text .= "\n" . wrap(EMAIL_COLUMNS, $msg->{message});

    # Strip any lines which consist only of spaces. Because we send the mails
    # as quoted-printable, such lines get formatted as ugly strings of "=20".
    $text =~ s/^\s+$//gm;

    return $text;
}

# as_ascii_octets STRING
# Given a UNICODE STRING, return a byte string giving that string's encoding
# in ASCII, if it can be so encoded; or undef otherwise.
sub as_ascii_octets ($) {
    my $octets = as_utf8_octets($_[0]);
    if ($octets !~ /[\x80-\xff]/) {
        return $octets;
    } else {
        return undef;
    }
}

# as_cp1252_octets STRING
# Given a UNICODE STRING, return a byte string giving that string's encoding
# in CP1252, if it can be so encoded; or undef otherwise.
sub as_cp1252_octets ($) {
    my $s = shift;
    die "STRING is not valid ASCII/UTF-8" unless (utf8::valid($s));
    my $out;
    eval {
        my $octets = encode('windows-1252', $s, Encode::FB_CROAK);
        $out = $octets;
    };
    return $out;
}

# as_utf8_octets STRING
# Given a UNICODE STRING, return a byte string giving that string's encoding
# in UTF-8.
sub as_utf8_octets ($) {
    my $s = shift;
    return $s unless $s;
    die "STRING is not valid ASCII/UTF-8" unless (utf8::valid($s));
    utf8::encode($s);
    return $s;
}

# make_representative_email MESSAGE SENDER
# Return the on-the-wire text of an email for the passed MESSAGE (hash of db
# fields), suitable for immediate sending to the real recipient.
sub make_representative_email ($$) {
    my ($msg, $sender) = @_;

    my $subject = $msg->{recipient_type} eq 'HOC'
                        ? "Letter from $msg->{sender_name}"
                        : "Letter from your constituent $msg->{sender_name}";

    my $bodytext = '';

    # If this is being sent via some contact, we need to add blurb to the top
    # to that effect.
    if ($msg->{recipient_via} && $msg->{recipient_type} ne 'HOC') {
        $subject = "Letter from constituent $msg->{sender_name} to $msg->{recipient_name}";
        $bodytext = FYR::EmailTemplate::format(
                email_template('via-coversheet', $msg->{cobrand}),
                email_template_params($msg, representative_url => '')
            );
    }

    # If this message was sent as part of a group to all a user's representatives
    # add text letting the recipient know who the other recipients are.
    if ($msg->{group_id}){
        $bodytext .= "\n\n"
            . FYR::EmailTemplate::format(
               email_template('group', $msg->{cobrand}),
               email_template_params($msg, other_recipient_list => other_recipient_list($msg->{group_id},$msg->{id}))
            );
    }

    my $footer_template = 'footer';
    my %council_child_type  = map { $_ => 1 } @$mySociety::VotingArea::council_child_types;
    $footer_template .= '-cllr' if $council_child_type{$msg->{recipient_type}};
    $bodytext .= "\n\n"
            . format_email_body($msg)
            . "\n\n\n"
            . FYR::EmailTemplate::format(
                email_template($footer_template, $msg->{cobrand}),
                email_template_params($msg, representative_url => '') # XXX
            );

    my $headers = {
        From => mySociety::Email::format_email_address($msg->{sender_name}, $msg->{sender_email}),
        To => mySociety::Email::format_email_address($msg->{recipient_name}, $msg->{recipient_email}),
        Subject => $subject,
        Date => strftime('%a, %e %b %Y %H:%M:%S %z', localtime(FYR::DB::Time())),
        'Message-ID' => email_message_id($msg->{id}),
    };
    if (test_dmarc($msg->{sender_email})) {
        $headers->{From} = mySociety::Email::format_email_address($msg->{sender_name}, $sender);
        $headers->{'Reply-To'} = mySociety::Email::format_email_address($msg->{sender_name}, $msg->{sender_email});
    }

    $bodytext = build_text_email($bodytext);

    return Email::MIME->create(
        header_str => [%$headers],
        parts => [ $bodytext ],
        attributes => {
            charset => 'utf-8',
            encoding => 'quoted-printable',
        },
    )->as_string;
}

sub test_dmarc {
    my $email = shift;

    my $addr = (Email::Address->parse($email))[0];
    return unless $addr;

    my $domain = $addr->host;
    my @answers = _resolver_send(Net::DNS::Resolver->new, "_dmarc.$domain", 'TXT');
    @answers = map { $_->txtdata } @answers;
    my $dmarc = join(' ', @answers);
    return unless $dmarc =~ /p *= *(reject|quarantine)/;

    return 1;
}

# Same as send->answer, but follows one CNAME and returns only matching results
sub _resolver_send {
    my ($resolver, $domain, $type) = @_;
    my $packet = $resolver->send($domain, $type);
    my @answers;
    foreach my $rr ($packet->answer) {
        if ($rr->type eq 'CNAME') {
            push @answers, $resolver->send($rr->cname, $type)->answer;
        } else {
            push @answers, $rr;
        }
    }
    return grep { $_->type eq $type } @answers;
}

#
# Tokens.
#
# Tokens are used to represent message IDs in the outside world. We want to
# prevent attackers from being able to construct tokens from message IDs;
# this leaves us with two options: (a) construct random tokens, recode the
# mapping from tokens to IDs; (b) encrypt the message ID with some other data,
# and assume that the attacker will not infer the key and other parameters used
# to generate them. Use (b), basically because (a) isn't as neat. Other
# requirements are that tokens are as short as possible, because we will want
# to transmit them as URL suffixes in emails; and that tokens are
# case-insensitive, since we use them in VERPs to detect mail bounces. We
# therefore use base32 to encode the data.
#

# Arbitrary IV for the encryption; arguably this should be per-installation and
# in the database.
use constant token_iv => 'nk"49{8y';

# Shorten common token types to single characters.
my %token_wordmap = qw(
        confirm         C
        bounce          B
        questionnaire   Q
        message_id      M
    );

# make_token WORD ID
# Returns a token for WORD (e.g. "bounce", "confirm", etc.) and the given
# message ID. The token will contain only letters and digits between 2 and 7
# inclusive, so is suitable for transmission through case-insensitive channels
# (e.g. as part of an email address).
sub make_token ($$) {
    my ($word, $id) = @_;

    $word = $token_wordmap{$word} if (exists($token_wordmap{$word}));

    # Try to keep these as short as possible. In particular, since the message
    # ID itself is composed only of characters [0-9a-f], pack it into the
    # equivalent octets.
    my $rand = int(rand(0xffff));
    my $string = $word . pack('nh*', $rand, $id);

    my $c = Crypt::CBC->new({
                    key => $word . FYR::DB::secret(),
                    cipher => 'IDEA',
                    prepend_iv => 0,
                    iv => token_iv
                });

    my $token = Convert::Base32::encode_base32(pack('na*', $rand, $c->encrypt($string)));
    # The separator is there to get around certain spam filter rules which find
    # long random strings supicious.
    $token =~ s#^(.{10})(.+)$#$1_$2#;
    return $token;
}

# check_token WORD TOKEN
# If TOKEN is valid, return the ID it encodes. Otherwise return undef.
sub check_token ($$) {
    my ($word, $token) = @_;

    $token = lc($token);
    $token =~ s#[./_]##g;
    return undef if ($token !~ m#^[2-7a-z]{20,}$#);

    $word = $token_wordmap{$word} if (exists($token_wordmap{$word}));
    my $rand;
    my $enc;
    eval { ($rand, $enc) = unpack('na*', Convert::Base32::decode_base32($token));};
    return undef if $@;

    my $c = Crypt::CBC->new({
                    key => $word . FYR::DB::secret(),
                    cipher => 'IDEA',
                    prepend_iv => 0,
                    iv => token_iv
                });

    my $dec = $c->decrypt($enc);

    my $word2 = substr($dec, 0, length($word));
    return undef if ($word ne $word2);
    my $rand2 = unpack('n', substr($dec, length($word), 2));
    return undef if ($rand2 != $rand);
    my $id = unpack('h*', substr($dec, length($word) + 2));
    return undef if (length($id) != MESSAGE_ID_LENGTH);
    return $id;
}

# send_user_email ID DESCRIPTION MAIL
# Send MAIL (should be full text of an on-the-wire email) to the user who
# submitted the given message ID. DESCRIPTION says what the mail is, for
# instance "confirmation" or "failure report". All such messages are sent with
# a do-not-reply sender. Logs an explanatory message and returns one of the
# mySociety::Util::EMAIL_... codes.
sub send_user_email ($$$) {
    my ($id, $descr, $mail) = @_;
    my $msg = message($id);
    my $result = mySociety::EmailUtil::send_email($mail, do_not_reply_sender($msg->{cobrand}, $msg->{cocode}), $msg->{sender_email});
    if ($result == mySociety::EmailUtil::EMAIL_SUCCESS) {
        logmsg($id, 1, "sent $descr mail to $msg->{sender_email}");
    } elsif ($result == mySociety::EmailUtil::EMAIL_SOFT_ERROR) {
        logmsg($id, 1, "soft error sending $descr email to $msg->{sender_email}");
    } else {
        logmsg($id, 1, "hard error sending $descr email to $msg->{sender_email}");
    }
    return $result;
}

# email_message_id ID
# Return a string suitable for use as the value of an email Message-ID: header
# for an email related to message ID.
sub email_message_id ($) {
    my $id = shift;
    # the Message-ID: we use will be related to the internal ID of the message,
    # but will not reveal it, and will be different for each email.
    return sprintf('<%s.%08x@%s>',
                    make_token('message_id', $id),
                    int(rand(0xffffffff)),      # add some more entropy...
                    mySociety::Config::get('EMAIL_DOMAIN'));
}

# email_template NAME
# Find the email template with the given NAME. We look for the templates
# directory in ../ and ../../. Nasty.
sub email_template ($;$) {
    my ($name, $cobrand) = @_;
    my $fn;
    if ($cobrand) {
        $fn = "$FindBin::Bin/../templates/$cobrand/emails/$name";
        $fn = "$FindBin::Bin/../../templates/$cobrand/emails/$name" if (!-e $fn);
    }
    $fn = "$FindBin::Bin/../templates/emails/$name" if (!$fn || !-e $fn);
    $fn = "$FindBin::Bin/../../templates/emails/$name" if (!-e $fn);
    die "unable to locate email template '$name', tried from '$FindBin::Bin'" if (!-e $fn);
    return $fn;
}

# email_template_params MESSAGE EXTRA
# Return a reference to a hash of information about the MESSAGE, and additional
# elements from EXTRA.
sub email_template_params ($%) {
    my ($msg, %params) = @_;
    foreach (qw(sender_name sender_addr recipient_name recipient_position recipient_position_plural recipient_id recipient_email)) {
        $params{$_} = $msg->{$_};
    }
    $params{sender_addr} =~ s#[,.]?\n+#, #g;

    # Also obtain the voting area name -- needed for the via template.
    my $r = mySociety::DaDem::get_representative_info($msg->{recipient_id});
    my $A = mySociety::MaPit::call('area', $r->{voting_area});
    $params{recipient_area_name} = $A->{name};

    $params{contact_email} = mySociety::Config::get('CONTACT_EMAIL');

    return \%params;
}

sub build_html_email {
    my ($template, $msg, $settings) = @_;

    my $html_settings = FYR::EmailSettings::get_settings;
    foreach my $setting (keys %$settings) {
        $html_settings->{$setting} = $settings->{$setting};
    }

    # Convert the plain text message into html so it displays
    # more or less correctly in HTML emails. This splits the
    # address portion and the message portion as they have
    # different formatting requirements
    if ($html_settings->{email_text}) {
        my $msg = $html_settings->{email_text};
        my ($address, $body) = split('Email:', $msg, 2);
        $address =~ s%\n%<br/>%gs;
        if ($body =~ /\d\s\w+\s\d{4}\n\s*Dear/s) {
            my ($email_and_date, $message) = split(/^Dear /m, $body, 2);
            $email_and_date =~ s%\n\n%</p>\n<p align="right">%s;
            $message =~ s%\n\n%</p>\n<p>%gs;
            $html_settings->{email_text} = $address .
                '</p><p align="right">Email:' .
                $email_and_date .
                '</p><p>Dear ' .
                $message;
        } else {
            $body =~ s%\n\n%</p>\n<p>%gs;
            $html_settings->{email_text} = $address .
                '</p><p>Email:' .
                $body;
        }
    }

    my $logo = Email::MIME->create(
       attributes => {
            filename     => "logo.gif",
            content_type => "image/gif",
            encoding     => "base64",
            name         => "logo.gif",
       },
       body => io( email_template('logo.gif', $msg->{cobrand}) )->binary->all
    );
    $logo->header_set('Content-ID', '<logo.gif>');

    # bounce emails don't have a message so we don't pass them to
    # email_template_params as it needs one.
    my $params = $html_settings;
    if ( keys %$msg ) {
        $params = email_template_params($msg, %$html_settings);
    }

    my $bodyhtml = FYR::EmailTemplate::format(
                email_template('_top.html', $msg->{cobrand}),
                $params
            );
    $bodyhtml .= FYR::EmailTemplate::format(
                email_template($template . '.html', $msg->{cobrand}),
                $params
            );
    $bodyhtml .= FYR::EmailTemplate::format(
                email_template('_bottom.html', $msg->{cobrand}),
                $params
            );

    my $html = Email::MIME->create(
        body_str => $bodyhtml,
        attributes => {
            charset => 'utf-8',
            encoding => 'quoted-printable',
            content_type => 'text/html'
        }
    );

    foreach ($logo, $html) {
        $_->header_set('Date');
        $_->header_set('MIME-Version');
    }

    my $mail = Email::MIME->create(
        attributes => {
            charset => 'utf-8',
            content_type => 'multipart/related'
        },
        parts => [ $html, $logo ]
    );

    $mail->header_set('Date');
    $mail->header_set('MIME-Version');

    return $mail;
}

sub build_text_email($) {
    my $text = shift;

    my $email = Email::MIME->create(
        body_str => $text,
        attributes => {
            charset => 'utf-8',
            encoding => 'quoted-printable',
            content_type => 'text/plain',
        },
    );

    $email->header_set('Date');
    $email->header_set('MIME-Version');

    return $email;
}

# make_confirmation_email MESSAGE [REMINDER]
# Return the on-the-wire text of an email for the given MESSAGE (reference to
# hash of db fields), suitable for sending to the constituent so that they can
# confirm their email address. If REMINDER is true, this is the second and
# final mail which will be sent.
sub make_confirmation_email ($;$) {
    my ($msg, $reminder) = @_;
    $reminder ||= 0;

    my $token = make_token("confirm", $msg->{id});
    my $url_start = FYR::Cobrand::base_url_for_emails($msg->{cobrand}, $msg->{cocode});
    my $confirm_url = $url_start . '/C/' . $token;

    my ($bodytext, $bodyhtml);
    if ($msg->{group_id}){
        my $template = $reminder ? 'confirm-reminder-group' : 'confirm-group';
        $bodytext = FYR::EmailTemplate::format(
                    email_template($reminder ? 'confirm-reminder-group' : 'confirm-group', $msg->{cobrand}),
                    email_template_params($msg, confirm_url => $confirm_url)
                );

        my $settings = {
            confirm_url => $confirm_url,
            email_text => format_email_body($msg),
        };

        $bodyhtml = build_html_email($template, $msg, $settings)
    }else{
        my $template = $reminder ? 'confirm-reminder' : 'confirm';
        $bodytext = FYR::EmailTemplate::format(
                    email_template($template, $msg->{cobrand}),
                    email_template_params($msg, confirm_url => $confirm_url)
                );

        my $settings = {
            confirm_url => $confirm_url,
            email_text => format_email_body($msg),
        };

        $bodyhtml = build_html_email($template, $msg, $settings)
    }

    # XXX Monstrous hack. The AOL client software (in some versions?) doesn't
    # present URLs as hyperlinks in email bodies unless we enclose them in
    # <a href="...">...</a> (yes, in text/plain emails). So for users on AOL,
    # we manually make that transformation. Note that we're assuming here that
    # the confirm URLs have no characters which need to be entity-encoded,
    # which is bad, evil and wrong but actually true in this case.
    $bodytext =~ s#(https://.+$)#<a href=" $1 ">$1</a>#m
        if ($msg->{sender_email} =~ m/\@aol\./i);

    # Append a separator and the text of the ms
    $bodytext .= "\n\n\n"
                . format_email_body($msg);

    # Add header if site in test mode
    my $reflecting_mails = mySociety::Config::get('FYR_REFLECT_EMAILS');
    if ($reflecting_mails) {
        $bodytext = wrap(EMAIL_COLUMNS, "(NOTE: THIS IS A TEST SITE, THE MESSAGE WILL BE SENT TO YOURSELF NOT YOUR REPRESENTATIVE.)") . "\n\n" . $bodytext;
    }

    $bodytext = build_text_email($bodytext);

    my $subject_text;
    if ($msg->{group_id}){
        $subject_text = "your $msg->{recipient_position_plural}";
    }else{
        $subject_text = $msg->{recipient_name};
    }

    return Email::MIME->create(
        header_str => [
            From => mySociety::Email::format_email_address(email_sender_name($msg->{cobrand}, $msg->{cocode}), do_not_reply_sender($msg->{cobrand})),
            To => mySociety::Email::format_email_address($msg->{sender_name}, $msg->{sender_email}),
            Subject => "Please confirm that you want to send a message to $subject_text",
            Date => strftime('%a, %e %b %Y %H:%M:%S %z', localtime(FYR::DB::Time())),
            'Message-ID' => email_message_id($msg->{id}),
        ],
        parts => [ $bodytext, $bodyhtml ],
        attributes => {
            charset => 'utf-8',
            content_type => 'multipart/alternative',
        },
    )->as_string
}

# do_not_reply_sender
# Return a do-not-reply sender address.
sub do_not_reply_sender {
    my ($cobrand, $cocode) = @_;
    my $s = create_do_not_reply_sender($cobrand, $cocode);
    return $s;
}

# create_do_not_reply_sender
# Generate a do-not-reply sender address.
sub create_do_not_reply_sender {
    my ($cobrand, $cocode) = @_;
    my $sender = FYR::Cobrand::do_not_reply_sender($cobrand, $cocode);
    if (!$sender) {
         $sender = sprintf('%sDO-NOT-REPLY@%s',
                        mySociety::Config::get('EMAIL_PREFIX'),
                        mySociety::Config::get('EMAIL_DOMAIN'));
    }

    return $sender;
}

# email_sender_name
# Return a sender name for emails.
sub email_sender_name {
    my ($cobrand, $cocode) = @_;
    my $s_name = create_email_sender_name($cobrand, $cocode);
    return $s_name;
}

# create_email_sender_name
# Generate a sender name for emails.
sub create_email_sender_name {
    my ($cobrand, $cocode) = @_;
    my $sender_name = FYR::Cobrand::email_sender_name($cobrand, $cocode);
    if (!$sender_name) {
        $sender_name = 'WriteToThem';
    }
    return $sender_name;
}

# send_confirmation_email ID [REMINDER]
# Send the necessary confirmation email for message ID. If REMINDER is true,
# this is a reminder mail.
sub send_confirmation_email ($;$) {
    my ($id, $reminder) = @_;
    die "attempt to send confirmation mail for message $id while in state '" . state($id) . "' (should be 'new' or 'pending')"
        unless (state($id) eq 'new' or state($id) eq 'pending');
    $reminder ||= 0;
    my $msg = message($id);
    return send_user_email(
                $id,
                'confirmation' . ($reminder ? ' reminder' : ''),
                make_confirmation_email($msg, $reminder)
            );
}

# make_failure_email MESSAGE
# Return the on-the-wire text of an email for the given MESSAGE (reference to
# hash of db fields), suitable for sending to the constituent to warn them that
# their message could not be delivered.
sub make_failure_email ($) {
    my ($msg) = @_;

    my $bounced = dbh()->selectrow_array("
        select message_id from message_log
        where message_id = ? and editor='handlemail'
            and message like 'message bounced because recipient''s mailbox is full%'
        limit 1
    ", {}, $msg->{id});
    my $template = $bounced ? 'failure-mailbox-full' : 'failure';

    my $text = FYR::EmailTemplate::format(
                    email_template($template, $msg->{cobrand}),
                    email_template_params($msg)
                )
                . "\n\n\n"
                . format_email_body($msg);

    $text = build_text_email($text);

    my $html = build_html_email($template, $msg, {email_text => format_email_body($msg)});

    my $mail = Email::MIME->create(
        header_str => [
            From => mySociety::Email::format_email_address(email_sender_name($msg->{cobrand}, $msg->{cocode}), do_not_reply_sender($msg->{cobrand})),
            To => mySociety::Email::format_email_address($msg->{sender_name}, $msg->{sender_email}),
            Subject => "Unfortunately, we couldn't send your message to $msg->{recipient_name}",
            Date => strftime('%a, %e %b %Y %H:%M:%S %z', localtime(FYR::DB::Time())),
            'Message-ID' => email_message_id($msg->{id}),
        ],
        parts => [ $text, $html ],
        attributes => {
            charset => 'utf-8',
            content_type => 'multipart/alternative',
        },
    )->as_string;

    # the stock version of Email::MIME on wheezy uses Encode to MIME encode
    # headers and it seems to be broken in that it adds a line break in the
    # subject which is then squashed to a space elsewhere. This fixes that.
    $mail =~ s/couldn' t/couldn't/s;
    return ($bounced, $mail)
}

# send_failure_email ID
# Send a failure report to the sender of message ID.
sub send_failure_email ($) {
    my ($id) = @_;
    my $msg = message($id);
    my ($bounced, $email) = make_failure_email($msg);
    my $descr = $bounced ? 'mailbox full' : 'normal';
    return send_user_email($id, "failure report ($descr)", $email);
}

# make_questionnaire_email MESSAGE [REMINDER]
# Return the on-the-wire text of an email for the given MESSAGE, asking the
# user to fill in a questionnaire. If REMINDER is true, this is a reminder
# mail.
sub make_questionnaire_email ($;$) {
    my ($msg, $reminder) = @_;
    $reminder ||= 0;

    my $base_url = FYR::Cobrand::base_url_for_emails($msg->{cobrand}, $msg->{cocode});
    my $token = make_token("questionnaire", $msg->{id});
    my $yes_url = $base_url . '/Y/' . $token;
    my $unsatisfactory_url = $base_url . '/U/' . $token;
    my $not_expected_url = $base_url . '/E/' . $token;
    my $no_url = $base_url . '/N/' . $token;

    my $settings = {
        yes_url => $yes_url,
        no_url => $no_url,
        unsatisfactory_url => $unsatisfactory_url,
        not_expected_url => $not_expected_url,
        weeks_ago => $reminder ? 'Three' : 'Two',
        their_constituents => $msg->{recipient_type} eq 'HOC' ? 'the public' : 'their constituents'
    };
    my $params;
    try {
        $params = email_template_params($msg, %$settings);
    } catch RABX::Error::User with {
        # If representative ID no longer exists (councillors can be fully deleted), that is caught here.
        my $E = shift;
        if ($E->value() == mySociety::DaDem::REP_NOT_FOUND) {
            state($msg->{id}, 'failed');
            dbh()->commit();
            throw FYR::Error('This representative is no longer in the database', FYR::Error::REPRESENTATIVE_DELETED);
        }
        $E->throw(); # Otherwise, throw it upwards
    };

    my $text = FYR::EmailTemplate::format(
                    email_template('questionnaire', $msg->{cobrand}),
                    $params
                )
                . "\n\n\n"
                . format_email_body($msg);

    $settings->{'email_text'} = format_email_body($msg);
    my $html = build_html_email('questionnaire', $msg, $settings);

    # XXX Monstrous hack. The AOL client software (in some versions?) doesn't
    # present URLs as hyperlinks in email bodies unless we enclose them in
    # <a href="...">...</a> (yes, in text/plain emails). So for users on AOL,
    # we manually make that transformation. Note that we're assuming here that
    # the confirm URLs have no characters which need to be entity-encoded,
    # which is bad, evil and wrong but actually true in this case.
    $text =~ s#(https://.+$)#<a href=" $1 ">$1</a>#mg
        if ($msg->{sender_email} =~ m/\@aol\.com$/i);

    $text = build_text_email($text);

    return Email::MIME->create(
        header_str => [
            From => mySociety::Email::format_email_address(email_sender_name($msg->{cobrand}, $msg->{cocode}), do_not_reply_sender($msg->{cobrand})),
            To => mySociety::Email::format_email_address($msg->{sender_name}, $msg->{sender_email}),
            Subject => "Did your $msg->{recipient_position} reply to your letter?",
            Date => strftime('%a, %e %b %Y %H:%M:%S %z', localtime(FYR::DB::Time())),
            'Message-ID' => email_message_id($msg->{id}),
        ],
        parts => [ $text, $html ],
        attributes => {
            charset => 'utf-8',
            content_type => 'multipart/alternative',
        },
    )->as_string
}

# send_questionnaire_email ID [REMINDER]
# Send a (possibly REMINDER) failure report to the sender of message ID.
sub send_questionnaire_email ($;$) {
    my ($id, $reminder) = @_;
    $reminder ||= 0;
    my $msg = message($id);
    die "attempt to send questionnaire for message $id while in state '" . state($id) . "' (should be 'sent')"
        unless (state($id) eq 'sent');
    return send_user_email(
                $id,
                'questionnaire' . ($reminder ? ' reminder' : ''),
                make_questionnaire_email($msg, $reminder)
            );
}


# deliver_email MESSAGE
# Attempt to deliver the MESSAGE by email.
sub deliver_email ($) {
    my ($msg) = @_;
    my $id = $msg->{id};
    die "attempt to deliver message $id while in state '" . state($id) . "' (should be 'ready')"
        unless (state($id) eq 'ready');
    my $sender = sprintf('%s%s@%s',
                            mySociety::Config::get('EMAIL_PREFIX'),
                            make_token("bounce", $id),
                            mySociety::Config::get('EMAIL_DOMAIN'));
    my $mail = make_representative_email($msg, $sender);
    my $result = mySociety::EmailUtil::send_email($mail, $sender, $msg->{recipient_email});
    if ($result == mySociety::EmailUtil::EMAIL_SUCCESS) {
        logmsg($id, 1, "delivered message by email to $msg->{recipient_email}");
    } elsif ($result == mySociety::EmailUtil::EMAIL_SOFT_ERROR) {
        logmsg($id, 1, "soft error delivering message by email to $msg->{recipient_email}");
    } else {
        logmsg($id, 1, "hard error delivering message by email to $msg->{recipient_email}");
    }
    return $result;
}

# deliver_fax MESSAGE
# Attempt to deliver the MESSAGE by fax.
sub deliver_fax ($) {
    my ($msg) = @_;
    my $id = $msg->{id};
    my $result = FYR::Fax::deliver($msg);
    if ($result == FYR::Fax::FAX_SUCCESS) {
        logmsg($id, 1, "delivered message by fax to $msg->{recipient_fax}");
    } elsif ($result == FYR::Fax::FAX_SOFT_ERROR) {
        logmsg($id, 1, "soft failure delivering message by fax to $msg->{recipient_fax}");
    } else {
        logmsg($id, 1, "hard failure delivering message by fax to $msg->{recipient_fax}");
    }
    return $result;
}

=item secret

Wrapper for FYR::DB::secret, for remote clients.

=cut
sub secret () {
    return FYR::DB::secret();
}

=item confirm_email TOKEN

Confirm a user's email address, based on the TOKEN they've supplied in a URL
which they've clicked on. This function is called remotely and commits its
changes. Returns id of the message on success, or 0 for failure. Can be
called multiple times for the same message with no harm, just returns the id
again.

=cut
sub confirm_email ($) {
    my ($token) = @_;
    if (my $id = check_token("confirm", $token)) {
        # Has it been so long that the message has in fact timed out?
        # If so, return an error message
        if (grep {$_ eq state($id)} ('failed', 'failed_closed')) {
            throw FYR::Error("This message has expired", FYR::Error::MESSAGE_EXPIRED);
        }
        return $id if (state($id) ne 'pending');
        # Check to see if this message belongs to a group - if it does,
        # all the emails in the group can be confirmed
        my $msg = message($id);
        if (defined($msg->{group_id})){
            my $group_id = $msg->{group_id};
            lock_group($group_id);
            group_state($id, 'ready', $group_id);
            dbh()->do('update message set confirmed = ? where group_id = ?', {}, time(), $group_id);
            logmsg($id, 1, "sender email address confirmed (group confirmation)");
            my $memberid;
            my $msgs = group_messages($group_id);
            foreach $memberid (@$msgs){
                if ($memberid ne $id){
                    logmsg($memberid, 1, "sender email address confirmed (via group confirmation from message $id)");
                }
            }
        }else{
            state($id, 'ready');
            dbh()->do('update message set confirmed = ? where id = ?', {}, time(), $id);
            logmsg($id, 1, "sender email address confirmed");
        }
        dbh()->commit();
        notify_daemon();
        return $id;
    }
    return 0;
}

=item record_questionnaire_answer TOKEN QUESTION RESPONSE

Record a user's response to a questionnaire question. TOKEN is the token sent
them in the questionnaire email; QUESTION must be 0 or 1 and RESPONSE
must be "YES", indicating that they have received a reply, or "NO",
indicating that they have not. Returns 0 upon failure, or msgid upon
success.

=cut
sub record_questionnaire_answer ($$$) {
    my ($token, $qn, $answer) = @_;
    throw FYR::Error("Bad QUESTION (should be '0' or '1')", FYR::Error::BAD_DATA_PROVIDED)
        if ($qn ne '0' and $qn ne '1');
    throw FYR::Error("Bad RESPONSE (should be 'YES', 'NO', 'UNSATISFACTORY' or 'NOT_EXPECTED')", FYR::Error::BAD_DATA_PROVIDED)
        if ($answer !~ /^(yes|no|unsatisfactory|not_expected)$/i);
    if (my $id = check_token("questionnaire", $token)) {
        my $msg = message($id);

        # silently don't record responses for no_questionnaire type
        if ($msg->{no_questionnaire}) {
            logmsg($id, 1, "silently ignored answer of \"$answer\" received for questionnaire qn #$qn");
            return $id;
        }

        # record response, replacing existing response to same question
        dbh()->do('delete from questionnaire_answer where message_id = ? and question_id = ?', {}, $id, $qn);
        dbh()->do('insert into questionnaire_answer (message_id, question_id, answer, whenanswered) values (?, ?, ?, ?)', {}, $id, $qn, $answer, time());
        logmsg($id, 1, "answer of \"$answer\" received for questionnaire qn #$qn");
        dbh()->commit();
        return $id;
    } else {
        return 0;
    }
}

=item get_questionnaire_message TOKEN

Return id of the message associated with a questionnaire email. TOKEN is the token
sent them in the questionnaire email;.

=cut
sub get_questionnaire_message ($) {
    my ($token) = @_;
    if (my $id = check_token("questionnaire", $token)) {
        return $id;
    }
}

#
# Implementation of the state machine.
#

use constant HOUR => 3600;
use constant DAY => (24 * HOUR);
use constant WEEK => (7 * DAY);

# How many confirmation mails may be sent, in total.
use constant NUM_CONFIRM_MESSAGES => 2;

# How many questionnaire mails are sent, in total.
use constant NUM_QUESTIONNAIRE_MESSAGES => 2;

# How long after sending the message do we send the questionnaire email?
use constant QUESTIONNAIRE_DELAY => (14 * DAY);

# How often after that we send a questionnaire reminder.
use constant QUESTIONNAIRE_INTERVAL => (7 * DAY);

# How long after sending the message do we retain its text in case the
# recipient wants to forward it? Make sure the time is longer than the
# questionnaire delay, as the questionnaire includes a copy of the message.
use constant MESSAGE_RETAIN_TIME => (28 * DAY);

# How long do we retain log and other information from a failed closed message
# for operator inspection?
use constant FAILED_CLOSED_RETAIN_TIME => MESSAGE_RETAIN_TIME;

# Total number of times we attempt delivery by fax.
# (note that 'ready' state timeout, below, is 7 days and will most likely cause
# failure before FAX_DELIVERY_ATTEMPTS is reached)
use constant FAX_DELIVERY_ATTEMPTS => 10;
# Interval between fax sending attempts.
use constant FAX_DELIVERY_INTERVAL => 1200; # 20 minutes
use constant FAX_DELIVERY_BACKOFF => 1.9;
# Python command line to print out roughly how many days attempts happen at:
# for x in range(1,11): print 1200 * (1.9**x) / 60 / 60 / 24;
# 0.0263888888889
# 0.0501388888889
# 0.0952638888889
# 0.181001388889
# 0.343902638889
# 0.653415013889
# 1.24148852639
# 2.35882820014
# 4.48177358026
# 8.5153698025

# Interval before we re-send email in the case of a permanent delivery error
# resulting from a transient condition.
use constant EMAIL_REDELIVERY_INTERVAL => 7200; # 2 hours

# Timeouts in the state machine:
my %state_timeout = (
        # How long a message may be "new" (awaiting sending of confirmation
        # mail) before it is discarded.
        new             => DAY,

        # How long a message may be "pending" (awaiting confirmation) before it
        # is discarded.
        pending         => DAY * 7,

        # How long a message may be "ready" (awaiting sending) before it is
        # treated as having failed to be delivered.
        ready           => DAY * 7,

        # How long we wait for a bounce to be received before assuming that the
        # message was delivered successfully.
        bounce_wait     => DAY * 2,

        # How long we hang around trying to deliver a failure report back to
        # the user for a failed message.
        error           => DAY * 7,

        # How long to hold on to failed messages? After this time they go into
        # failed_closed and then after an additional FAILED_CLOSED_RETAIN_TIME
        # they get scrubbed.
        failed          => DAY * 7 * 4
    );

# Where we time out to.
my %state_timeout_state = qw(
        new             failed_closed
        pending         failed_closed
        ready           error
        bounce_wait     sent
        error           failed
        failed          failed_closed
    );


# How often we do various things to messages in various states.
my %state_action_interval = (
        # How often we attempt to send a confirmation mail in the face of
        # soft failures.
        new             => 30,

        # How often we confirmation mail reminders.
        pending         => DAY * 1,

        # How often we consider delivery attempts in the face of soft failures.
        ready           => 120,

        # How often we grind over sent messages to send questionnaires or
        # expire messages into the "finished" state.
        sent            => DAY,

        # How often we attempt delivery of an error report in the face of soft
        # failures.
        error           => DAY / 2,

        # How often we grind over failed messages to scrub old ones of personal
        # information.
        failed_closed   => DAY
    );


# What we do to messages in various states. When these are called, the row
# representing the message will be locked; any transaction will be committed
# after they return.
my %state_action = (
        new => sub ($$$) {
            my ($email, $fax, $id) = @_;
            return unless ($email);

            my $msg = message($id);
            # Early on we make sending attempts frequently, then back off to
            # the old five minute interval later.
            if ($msg->{numactions} > 10 && ($msg->{numactions} % 10)) {
                # Bump state counter but don't do anything.
                # For group messages, bump the state counter of all
                # messages in the group
                group_state($id, 'new', $msg->{group_id});
                return;
            }

            # Construct confirmation email and send it to the sender.
            my $result = send_confirmation_email($id);
            if ($result == mySociety::EmailUtil::EMAIL_SUCCESS) {
                group_state($id, 'pending', $msg->{group_id});
            } elsif ($result == mySociety::EmailUtil::EMAIL_HARD_ERROR) {
                group_state($id, 'failed_closed', $msg->{group_id});
            } else {
                group_state($id, 'new', $msg->{group_id});
            }
        },

        pending => sub ($$$) {
            my ($email, $fax, $id) = @_;
            return unless ($email);
            my $msg = message($id);
            # Send reminder confirmation if necessary. We don't send one
            # immediately on entering this state, but do thereafter.
            if ($msg->{numactions} > 0 && $msg->{numactions} < NUM_CONFIRM_MESSAGES) {
                my $result = send_confirmation_email($id, 1);
                if ($result == mySociety::EmailUtil::EMAIL_SUCCESS) {
                    group_state($id, 'pending', $msg->{group_id});  # bump actions counter
                } elsif ($result == mySociety::EmailUtil::EMAIL_HARD_ERROR) {
                    group_state($id, 'failed_closed', $msg->{group_id});
                } else {
                    logmsg($id, 1, "error sending confirmation reminder message (will retry)");
                }
            } else {
                # Bump action counter but don't do anything else.
                group_state($id, 'pending', $msg->{group_id});
            }
        },

        ready => sub ($$$) {
            my ($email, $fax, $id) = @_;
            # Send email or fax to recipient.
            my $msg = message($id);
            if ($fax && defined($msg->{recipient_fax})) {
                #
                # We want an exponential backoff for fax sending, because some
                # sending errors (e.g. send to voice number not fax number)
                # will *really* irritate people and we don't want to annoy them
                # too much....
                #

                # Don't attempt to send faxes outside reasonably sane hours --
                # if we have a typo in a phone number we don't want to call the
                # victim at all hours of the night.
                # Allow for the option to fax the Lords fax machine out of hours.
                if ($msg->{recipient_via} && $msg->{recipient_type} eq 'HOC') {
                    return if outside_fax_hours() && ! mySociety::Config::get('FAX_LORDS_OUTSIDE_FAX_HOURS', 0);
                }else{
                    return if outside_fax_hours();
                }

                # Abandon faxes after a few failures.
                if ($msg->{numactions} > FAX_DELIVERY_ATTEMPTS) {
                    logmsg($id, 1, "abandoning message after $msg->{numactions} failures to send by fax");
                    state($id, 'error');
                    return;
                }

                # Don't retry sending until a reasonable backoff time has
                # passed.
                my $howlong = FYR::DB::Time() - $msg->{laststatechange};
                return if ($msg->{numactions} > 0 && $howlong < FAX_DELIVERY_INTERVAL * (FAX_DELIVERY_BACKOFF ** $msg->{numactions}));

                my $result = deliver_fax($msg);
                if ($result == FYR::Fax::FAX_SUCCESS) {
                    dbh()->do('update message set dispatched = ? where id = ?', {}, FYR::DB::Time(), $id);
                    state($id, 'sent');
                } elsif ($result == FYR::Fax::FAX_SOFT_ERROR) {
                    # Don't do anything here: this is a temporary, local
                    # failure.
                    logmsg($id, 0, "local failure in fax sending");
                } else {
                    # Some kind of remote failure. Bump the counter so that we
                    # can abandon delivery after too many such failures.
                    logmsg($id, 1, "remote failure in fax sending");
                    state($id, 'ready');    # bump counter
                }
            } elsif ($email && defined($msg->{recipient_email})) {
                # It's possible that a message has been put back in to this
                # state because a previous delivery failed with an permanent
                # error resulting from a transient condition (for instance, if
                # the recipient's mailbox is over-quota). In that case we
                # should try not to send mail too often.
                if (defined($msg->{dispatched})) {
                    if ($msg->{dispatched} > time() - EMAIL_REDELIVERY_INTERVAL) {
                        # Don't attempt redelivery too often.
                        return;
                    } elsif ($msg->{confirmed} < time() - $state_timeout{ready}) {
                        # Time out messages after the same interval they'd have
                        # lasted if stuck in the "ready" state (i.e. with
                        # locally-detected delivery errors).
                        logmsg($id, 1, "message bouncing for too long; failing");
                        state($id, 'error');
                        return;
                    }
                    logmsg($id, 0, "making email redelivery attempt");
                }

                my $result = deliver_email($msg);
                if ($result == mySociety::EmailUtil::EMAIL_SUCCESS) {
                    dbh()->do('update message set dispatched = ? where id = ?', {}, FYR::DB::Time(), $id);
                    state($id, 'bounce_wait');
                } else {
                    # XXX for the moment we do not distinguish soft/hard errors.
                    state($id, 'ready');    # bump timer
                    logmsg($id, 1, "error sending message by email (will retry)");
                }
            }
        },

        sent => sub ($$$) {
            my ($email, $fax, $id) = @_;
            return unless ($email);
            my $msg = message($id);

            # If we haven't got a questionnaire response, and it's been long
            # enough since the message was sent or since the last questionnaire
            # email was sent, then send another one.
            my ($dosend, $reminder) = (0, 0);
            if (0 == scalar(dbh()->selectrow_array('select count(*) from questionnaire_answer where message_id = ?', {}, $id))) {
                # Emailed messages have already spent 2 days in the bounce_wait state,
                # so give them two days less in the sent state than faxed messages
                my $days_questionnaire = defined($msg->{recipient_email}) ? 12 : 14;
                my $days_questionnaire_reminder = $days_questionnaire + 7;
                if ($msg->{numactions} == $days_questionnaire) {
                    $dosend = 1;
                } elsif ($msg->{numactions} == $days_questionnaire_reminder) {
                    $dosend = $reminder = 1;
                }
            }
            if ($msg->{no_questionnaire}) {
                # don't send questionnaire for test messages
                $dosend = 0;
            }

            if ($dosend) {
                my $result = send_questionnaire_email($id, $reminder);
                if ($result == mySociety::EmailUtil::EMAIL_SUCCESS) {
                    logmsg($id, 1, "sent questionnaire " . ($reminder ? 'reminder ' : '') . "email");
                } # should trap hard error case
            }

            # If we've had the message for long enough, then scrub personal
            # information and mark it finished.
            if ($msg->{dispatched} < (FYR::DB::Time() - MESSAGE_RETAIN_TIME)) {
                state($id, 'finished');
            } else {
                # Bump timer in any case.
                state($id, 'sent');
            }
        },

        error => sub ($$$) {
            my ($email, $fax, $id) = @_;
            return unless ($email);

            # Send failure report to sender.
            my $result = send_failure_email($id);
            if ($result == mySociety::EmailUtil::EMAIL_SOFT_ERROR) {
                state($id, 'error');    # bump timer for redelivery
                return;
            }
            # Give up -- it's all really bad.
            logmsg($id, 1, "unable to send failure report to user") if ($result == mySociety::EmailUtil::EMAIL_HARD_ERROR);
            state($id, 'failed');

            # Now try to mark the contact as failing, if the message is not
            # frozen. It's not guaranteed that this will succeed, and we can't
            # sensibly do very much if it doesn't. So just ignore any error.
            my $msg = message($id);
            if (!$msg->{frozen} && mySociety::Config::get('FYR_MARK_CONTACTS_FAILING', 0)) {
                try {
                    my $msg = message($id);
                    my $method = defined($msg->{recipient_email}) ? 'email' : 'fax';
                    if ($msg->{recipient_via}) {
                        my $R = mySociety::DaDem::get_representative_info($msg->{recipient_id});
                        my $viainfo = get_via_representative($R->{voting_area});
                        mySociety::DaDem::admin_mark_failing_contact($viainfo->{id}, $method, $msg->{"recipient_$method"}, 'fyr-queue', "msg $id");
                        logmsg($id, 1, qq#marked representative 'via' contact ($method to $msg->{"recipient_$method"}) as failing#);
                    } else {
                        mySociety::DaDem::admin_mark_failing_contact($msg->{recipient_id}, $method, $msg->{"recipient_$method"}, 'fyr-queue', "msg $id");
                        logmsg($id, 1, qq#marked representative $msg->{recipient_id} contact ($method to $msg->{"recipient_$method"}) as failing#);
                    }
                } catch Error with {
                    my $E = shift;
                    logmsg($id, 1, "unable to mark contact details as failing: " . $E->text());
                };
            }
        },

#        failed => sub ($$$) {
#            my ($email, $fax, $id) = @_;
#            # do nothing
#        },

        failed_closed => sub ($$$) {
            my ($email, $fax, $id) = @_;
            my $msg = message($id);
            if ($msg->{sender_ipaddr} ne '' and $msg->{laststatechange} < (FYR::DB::Time() - FAILED_CLOSED_RETAIN_TIME)) {
                # clear data for privacy
                scrubmessage($id);
            }
            # bump timer
            state($id, 'failed_closed');
        }
    );


# outside_fax_hours
# Returns true if time of day is not suitable for sending faxes.
sub outside_fax_hours() {
    my $hour = (localtime(FYR::DB::Time()))[2];
    return 1 if ($hour < 8 || $hour > 20);
    return 0;
}

# process_queue EMAIL FAX [FOAD] [MSGID]
# Drive the state machine round; emails will be sent if EMAIL is true, and
# faxes if FAX is true. If passed, FOAD should be a reference to a scalar which
# will be tested periodically for an early abort condition. If the referenced
# scalar ever becomes true, the queue run will be aborted at the earliest
# convenient opportunity. If passed, MSGID should be a message identifier,
# and the queue is run only for that one message. Returns the number of
# "significant" message actions taken (ones which result in changes to the
# database, roughly).
sub process_queue ($$;$$) {
    my ($email, $fax, $foad, $msgid) = @_;
    $email ||= 0;
    $fax ||= 0;
    my $f = 0;
    $foad ||= \$f;

    return 0 if (!$email && !$fax);

    my $stmt;
    if ($email) {
        # Timeouts. Just lock the whole table to do this -- it should be
        # reasonably quick.
        $stmt = dbh()->prepare(
            'select id, state from message where '
            . join(' or ',
                map { sprintf(q#(state = '%s' and laststatechange < %d)#,
                            $_, FYR::DB::Time() - $state_timeout{$_}) }
                    keys %state_timeout)
            . ' for update');
        $stmt->execute();
        while (my ($id, $state) = $stmt->fetchrow_array()) {
            state($id, $state_timeout_state{$state});
            last if ($$foad);
        }
        dbh()->commit();
    }

    return 0 if ($$foad);

    # Actions. These are slow (potentially) so lock row-by-row. Process
    # messages in a random order, so that bad ones don't block everything.
    my $nactions = 0;
    if ($msgid) {
        # Command line override for just one message
        $stmt = dbh()->prepare(q#
                select id, state, group_id from message
                where id = ?#);
        $stmt->execute($msgid);
    } elsif ($email) {
        $stmt = dbh()->prepare(
            'select id, state, group_id from message where ('
                . join(' or ', map { sprintf(q#state = '%s'#, $_); } keys %state_action)
            . ') and (lastaction is null or '
                . join(' or ',
                    map { sprintf(q#(state = '%s' and lastaction < %d)#,
                                $_, FYR::DB::Time() - $state_action_interval{$_}) }
                        keys %state_action_interval)
            . q#) and (state <> 'ready' or not frozen) order by random()#);
        $stmt->execute();
    } else {
        $stmt = dbh()->prepare(sprintf(q#
                select id, state, group_id from message
                where state = 'ready' and not frozen
                    and recipient_fax is not null
                    and (lastaction is null or lastaction < %d)
                order by confirmed
                #, FYR::DB::Time() - $state_action_interval{ready}));
        $stmt->execute();
    }
    my $process_msg = 0;
    while (my ($id, $state, $group_id) = $stmt->fetchrow_array()) {
        # Now we need to lock the row. Once it's locked, check that the message
        # still meets the criteria for sending. Do things this way round so
        # that we can have several queue-running daemons operating
        # simultaneously.
        try {
            # If the email belongs to a group and we are sending confirmation emails,
            # lock every email in the group - we are going to update all their states
            # as a result of the action
            my $msg;
            $process_msg = 0;
            if (defined($group_id) and $email and ($state eq 'new' or $state eq 'pending')){
                $msg = message($id);
                # Group messages are more likely to have been updated by another process
                # so check again before spending time locking them
                if ($msg->{state} eq $state
                and (!defined($msg->{lastaction})
                     or $msg->{lastaction} < FYR::DB::Time() - $state_action_interval{$state})){
                    lock_group($group_id);
                    $msg = message($id);
                    $process_msg = 1;
                }
            }else{
                my $lock = 1;
                my $nowait = 1;
                $msg = message($id, $lock, $nowait);
                if (defined($msg)){
                    $process_msg = 1;
                }
            }
            if ($process_msg and $msg->{state} eq $state
                and (!defined($msg->{lastaction})
                    or $msg->{lastaction} < FYR::DB::Time() - $state_action_interval{$state})) {
                # Check for ready+frozen again.
                if (!$msg->{frozen} or $msg->{state} ne 'ready') {
                    &{$state_action{$state}}($email, $fax, $id);

                    # See whether anything changed, and if it did update the
                    # action counter.
                    my $msg2 = message($id);

                    ++$nactions
                        if ($msg2->{state} ne $msg->{state}
                            || $msg2->{numactions} != $msg->{numactions}
                            || $msg2->{frozen} != $msg->{frozen});
                }
            }
        } catch Error with {
            # We caught some kind of exception. First log the error and freeze
            # the message, but catch any exception that might occur while we
            # do. But it's possible that the error is a transient database
            # error, so try to connect to the database anew to do this. Errors
            # of type FYR::Error are "expected" (we throw them ourselves);
            # anything else we regard as "unexpected" and allow to propagate
            # further (presumably killing this fyrqd child process).
            my $E = shift;
            my $msg;
            if ($E->isa('FYR::Error')) {
                $msg = "error while processing message: $E";
            } else {
                $msg = "unexpected error (type " . ref($E) . ") while processing message: $E";
            }
            try {
                dbh()->commit();    # avoid potential deadlock against freeze
            } catch Error with {
                # "Never test for an error condition you don't know how to
                # handle."
            };
            try {
                logmsg($id, 1, $msg);
                my $dbh = new_dbh();
                $dbh->do("update message set frozen = 't' where id = ?", {}, $id);
                $dbh->commit();
                logmsg($id, 1, "froze message following error");
            } catch Error with {
                # Again, not much we can do here.
            };
            $E->throw() if (!$E->isa('FYR::Error'));
        } finally {
            # Actually superfluous if we caught the error now.
            dbh()->commit();
        };
        last if ($$foad);
    }
    return $nactions;
}

# notify_daemon
# Notify a waiting daemon that a queue run should be started. The system doesn't
# rely on this, it's just there to improve latency.
sub notify_daemon () {
    my $name = mySociety::Config::get('QUEUE_DAEMON_SOCKET', undef) or return;
    my $s = new IO::Socket::UNIX(Type => SOCK_DGRAM, Peer => $name) or return;
    my $flags = fcntl($s, F_GETFL, 0) or return;
    fcntl($s, F_SETFL, O_NONBLOCK | $flags) or return;
    $s->print("\0") or return;
    $s->close();
}

#
# Administrative interface
#

=item admin_recent_events COUNT [IMPORTANT]

Returns an array of hashes of information about the most recent COUNT queue
events. If IMPORTANT is true, only return information about "exceptional"
messages.

=cut
sub admin_recent_events ($;$) {
    my ($count, $imp) = @_;
    my $sth = dbh()->prepare('
                    select message_id, whenlogged, state, message, exceptional
                      from message_log ' .
                      ($imp ? 'where exceptional' : '')
                    . ' order by order_id desc limit ?');
    $sth->execute(int($count));
    my @ret;
    while (my $hash_ref = $sth->fetchrow_hashref()) {
        push @ret, $hash_ref;
    }
    return \@ret;
}

=item admin_message_events ID [IMPORTANT]

Returns an array of hashes of information about events for given message ID. If
IMPORTANT is true, only request messages for which the "exceptional" flag is
set. You probably don't need to display the 'editor' field as it is also shown
in the message content.

=cut
sub admin_message_events ($;$) {
    my ($id, $imp) = @_;
    my $sth = dbh()->prepare('
                    select message_id, whenlogged, state, message, exceptional, hostname, editor
                      from message_log
                     where message_id = ? ' . ($imp ? 'and exceptional' : '') .
                   ' order by order_id');
    $sth->execute($id);
    my @ret;
    while (my $hash_ref = $sth->fetchrow_hashref()) {
        push @ret, $hash_ref;
    }
    return \@ret;
}


=item admin_get_queue WHICH PARAMS

Returns an array of hashes of information about each message on the queue.
WHICH and PARAMS indicates which messages should be returned; values of WHICH
are as follows:

=over 4

=item all

All messages on the queue.

=item needattention

Messages which need attention. This means frozen in states ready, or in
bounce_confirm. (new and pending are excluded as often people sent test
messages to themselves which break rules, but they never confirm).

=item failing

Messages which are failing or have failed to be delivered, which have not
been frozen.  Combined with 'frozen' makes all 'important' messages.

=item recentchanged

Messages which have most recently changed state.

=item recentcreated

Messages which have recently been created.

=item similarbody

Messages which are similar to the message with ID given in PARAMS->{msgid}.

=item similarbodysamerep

Messages which are similar to the message with ID given in PARAMS->{msgid},
and were sent to the same representative.

=item search

Messages which contain terms from PARAMS->{query}. First confirmation and
questionnaire tokens are looked for, then an exact match on sender email is
done. Failing that, sender and recipient details are substring searched.

=item logsearch

Messages which contain the string in an item in their message log.  Deliberately
doesn't strip spaces or punctuation, and looks for whole strings, so you can
search for ' rule #6 ' and the like.

=item type

All messages which were sent to representative of type PARAMS->{type}.

=item rep_id

All messages which were sent to representative PARAMS->{rep_id} or
PARAMS->{rep_ids}.

=item limit

Number of results to return (default 100).
Ignored for needattention/failing/similarbody*.

=item page

Page of results to return (default 1).
Ignored for needattention/failing/similarbody*.

=back

=cut
sub admin_get_queue ($$) {
    my ($filter, $params) = @_;

    my %allowed = map { $_ => 1 } qw(all needattention failing recentchanged recentcreated similarbody similarbodysamerep search logsearch type rep_id);
    throw FYR::Error("Bad filter type '$filter'", FYR::Error::BAD_DATA_PROVIDED)
        if (!exists($allowed{$filter}));

    my $where = "order by created desc";
    my $limit_sql = "offset 0 limit 100";
    if (ref $params eq 'HASH') {
        my $page = ($params->{page} || "") =~ /^\d+\z/ ? $params->{page} : 1;
        my $limit = ($params->{limit} || "") =~ /^(\d+|NULL)\z/ ? $params->{limit} : 100;
        my $offset = ($page - 1) * $limit;
        $limit_sql = "offset $offset limit $limit";
    }

    my $msg;
    my @params;
    # XXX "frozen = 't'" because if you just say "frozen", PG won't use an
    # index to do the scan. q.v. comments on the end of,
    #   http://www.postgresql.org/docs/7.4/interactive/indexes.html
    if ($filter eq 'needattention') {
        $where = q# where (frozen = 't' and state <> 'failed_closed' and state <> 'failed'
                           and state <> 'pending' and state <> 'new' and state <> 'anonymised')
                         or (state = 'bounce_confirm') order by created desc#;
        $limit_sql = ""; # Want all results
    } elsif ($filter eq 'failing') {
        $where = q#
            where (state = 'failed'
                    or state = 'error')
                  and frozen = 'f'
            order by created desc#;
    } elsif ($filter eq 'recentchanged') {
        $where = "order by laststatechange desc";
    } elsif ($filter eq 'recentcreated') {
        $where = "order by created desc";
    } elsif ($filter eq 'similarbody') {
        my $sth2 = dbh()->prepare("
                select *, length(message) as message_length from message where id = ?
            ");
        $sth2->execute($params->{msgid});
        $msg = $sth2->fetchrow_hashref();
        my @similar = FYR::AbuseChecks::get_similar_messages($msg);
        @params = map { $_->[0] } grep { $_->[1] > 0.7 } @similar;
        push @params, $params->{msgid};
        $where = "where id in (" . join(",", map { '?' } @params) .  ")";
    } elsif ($filter eq 'similarbodysamerep') {
        my $sth2 = dbh()->prepare("
                select *, length(message) as message_length from message where id = ?
            ");
        $sth2->execute($params->{msgid});
        $msg = $sth2->fetchrow_hashref();
        my @similar = FYR::AbuseChecks::get_similar_messages($msg, 1);
        @params = map { $_->[0] } grep { $_->[1] > 0.7 } @similar;
        push @params, $params->{msgid};
        $where = "where id in (" . join(",", map { '?' } @params) .  ")";
    } elsif ($filter eq 'search') {
        my $token_found_id;
        if (length($params->{query}) >= 20) {
            $token_found_id = check_token("confirm", $params->{query});
            if (!defined($token_found_id)) {
                $token_found_id = check_token("questionnaire", $params->{query});
            }
        }
        if (defined($token_found_id)) {
            $where = "where id = ?";
            push @params, $token_found_id;
        } elsif (mySociety::EmailUtil::is_valid_email($params->{query})) {
            $where = "where lower(sender_email) = lower(?) or lower(recipient_email) = lower(?)";
            push @params, $params->{query};
            push @params, $params->{query};
        } elsif ($params->{query} =~ /^(ready) (CED|COP|DIW|HOC|LAC|LAE|LBW|LGE|MTW|NIE|SPC|SPE|UTE|UTW|WAC|WAE|WMC)$/) {
            $where = "where state = ? and recipient_type = ?";
            push @params, $1;
            push @params, $2;
        } else {
            my $query = merge_spaces($params->{query});
            my @terms = split m/ /, $query;

            $where = "where ";
            $where .= join(" and ", map {
                        for (my $i=1; $i<=14; ++$i) {
                            push(@params, $_)
                        }
                        q#
                             ((recipient_type = ?) or
                              (state = ?) or

                              (sender_name ilike '%' || ? || '%') or
                              (sender_email ilike '%' || ? || '%') or
                              (sender_addr ilike '%' || ? || '%') or
                              (sender_phone ilike '%' || ? || '%') or
                              (sender_postcode ilike '%' || ? || '%') or
                              (sender_ipaddr ilike '%' || ? || '%') or
                              (sender_referrer ilike '%' || ? || '%') or
                              (recipient_name ilike '%' || ? || '%') or
                              (recipient_email ilike '%' || ? || '%') or
                              (recipient_fax ilike '%' || ? || '%') or
                              (id ilike '%' || ? || '%') or
                              (message ilike '%' || ? || '%'))
                        #
                    } @terms
                );
        }
        $where .= " order by created desc";
    } elsif ($filter eq 'logsearch') {
        my $logmatches = dbh()->selectcol_arrayref(q#select message_id from message_log
            where message ilike '%' || ? || '%'#, {}, $params->{query});
        push @params, @$logmatches;
        $where = q#where id in (# . join(',', map { '?' } @$logmatches) . q#) order by created desc#;
        $where = q#where 1 = 0# if (scalar(@$logmatches) == 0);
    } elsif ($filter eq 'type') {
        push @params, $params->{query};
        $where = "where recipient_type = ? order by created desc";
    } elsif ($filter eq 'rep_id') {
        $params->{rep_ids} = [ $params->{rep_id} ] if $params->{rep_id};
        push @params, @{$params->{rep_ids}};
        my $qs = join(',', map { '?' } @{$params->{rep_ids}});
        $where = "where recipient_id in ($qs) order by created desc";
    }
    my $sth = dbh()->prepare("
            select *, $message_calculated_values
            from message
            $where $limit_sql
        ");
    $sth->execute(@params);
    my @ret;
    while (my $other = $sth->fetchrow_hashref()) {
        if ($filter eq 'similarbody' || $filter eq 'similarbodysamerep') {
            # Obtain diff, but elide long common substrings.
            $other->{diff} = [map {
                                if (ref($_) || length($_) < 200) {
                                    $_;
                                } else {
                                    (substr($_, 0, 95), undef, substr($_, -95))
                                }
                            } @{string_diff($msg->{message}, $other->{message})}];
        }
        $other->{message} = undef;
        push @ret, $other;
    }
    return \@ret;
}

=item admin_get_message ID

Returns a hash of information about message with id ID.

=cut
sub admin_get_message ($) {
    my ($id) = @_;

    my $sth = dbh()->prepare("select
        *, $message_calculated_values from message where id =
        ?");
    $sth->execute($id);
    throw FYR::Error("admin_get_message: Message not found '$id'", FYR::Error::BAD_DATA_PROVIDED)
        if ($sth->rows == 0);
    throw FYR::Error("admin_get_message: Multiple messages with '$id' found", FYR::Error::BAD_DATA_PROVIDED)
        if ($sth->rows > 1);
    my $hash_ref = $sth->fetchrow_hashref();

    my $bounces = dbh()->selectcol_arrayref("select
        bouncetext from message_bounce where message_id = ? order by whenreceived", {}, $id);
    $hash_ref->{bounces} = $bounces;

    $sth = dbh()->prepare("select question_id, answer from
        questionnaire_answer where message_id = ?");
    $sth->execute($id);
    my @ret;

    while (my $hash_ref = $sth->fetchrow_hashref()) {
        push @ret, $hash_ref;
    }
    $hash_ref->{questionnaires} = \@ret;

    return $hash_ref;
}


=item admin_get_stats [AMOUNT]

Returns a hash of statistics about the queue. AMOUNT is not present, or 0 to
get just basic stats, 1 to get more details (slower).

=cut
sub admin_get_stats ($) {
    my ($amount) = @_;
    my %ret;

    if ($amount) {
        my $rows = dbh()->selectall_arrayref('select recipient_type, state, count(*) from message group by recipient_type, state', {});
        foreach (@$rows) {
            my ($type, $state, $count) = @$_;
            $ret{"alltime $type $state"} = $count;
        }

        $rows = dbh()->selectall_arrayref('select recipient_type, state, count(*) from message where created > ? group by recipient_type, state', {}, FYR::DB::Time() - DAY);
        foreach (@$rows) {
            my ($type, $state, $count) = @$_;
            $ret{"day $type $state"} = $count;
        }

        $rows = dbh()->selectall_arrayref('select recipient_type, state, count(*) from message where created > ? group by recipient_type, state', {}, FYR::DB::Time() - WEEK);
        foreach (@$rows) {
            my ($type, $state, $count) = @$_;
            $ret{"week $type $state"} = $count;
        }

        $rows = dbh()->selectall_arrayref('select recipient_type, state, count(*) from message where created > ? group by recipient_type, state', {}, FYR::DB::Time() - 4 * WEEK);
        foreach (@$rows) {
            my ($type, $state, $count) = @$_;
            $ret{"four $type $state"} = $count;
        }
    }

    $ret{message_count} = dbh()->selectrow_array('select count(*) from message', {});
    $ret{created_1}     = dbh()->selectrow_array('select count(*) from message where created > ?', {}, FYR::DB::Time() - HOUR);
    $ret{created_24}    = dbh()->selectrow_array('select count(*) from message where created > ?', {}, FYR::DB::Time() - DAY);
    $ret{created_168}    = dbh()->selectrow_array('select count(*) from message where created > ?', {}, FYR::DB::Time() - WEEK);

    $ret{last_fax_time} = dbh()->selectrow_array('select dispatched from message where dispatched is not null and recipient_fax is not null and recipient_email is null order by dispatched desc limit 1', {});
    $ret{last_email_time} = dbh()->selectrow_array('select dispatched from message where dispatched is not null and recipient_fax is null and recipient_email is not null order by dispatched desc limit 1', {});

    return \%ret;
}

=item admin_get_popular_referrers TIME

Returns list of pairs of popular referrers and how many times they appeared
in the last TIME seconds.

=cut
sub admin_get_popular_referrers($) {
    my ($secs) = @_;
    my $result = FYR::DB::dbh()->selectall_arrayref('
        select sender_referrer, count(*) as c from message
            where created > ?
            group by sender_referrer
            order by c desc', {}, FYR::DB::Time() - $secs);

    return $result;
}


=item admin_freeze_message ID USER

Freezes the message with the given ID, so it won't be actually sent to
the representative until thawed. USER is the administrator's name.

=cut
sub admin_freeze_message ($$) {
    my ($id, $user) = @_;
    dbh()->do("update message set frozen = 't' where id = ?", {}, $id);
    dbh()->commit();
    logmsg($id, 1, "$user froze message", $user);
    return 0;
}

=item admin_thaw_message ID USER

Thaws the message with the given ID, so it will be sent to the representative.
USER is the administrator's name.

=cut
sub admin_thaw_message ($$) {
    my ($id, $user) = @_;
    dbh()->do("update message set frozen = 'f' where id = ?", {}, $id);
    dbh()->commit();
    logmsg($id, 1, "$user thawed message", $user);
    notify_daemon();
    return 0;
}

=item admin_no_questionnaire_message ID USER

Mark the message as being one for which a questionnaire is not sent.  Deletes
any existing questionnaires. USER is the administrator's name.

=cut
sub admin_no_questionnaire_message ($$) {
    my ($id, $user) = @_;
    dbh()->do("update message set no_questionnaire = 't' where id = ?", {}, $id);
    dbh()->do("delete from questionnaire_answer where message_id = ?", {}, $id);
    dbh()->commit();
    logmsg($id, 1, "$user set message to not send questionnaire, and deleted any existing responses", $user);
    return 0;
}

=item admin_yes_questionnaire_message ID USER

Mark the message as being one for which a quesionnaire is sent.
USER is the administrator's name.

=cut
sub admin_yes_questionnaire_message ($$) {
    my ($id, $user) = @_;
    dbh()->do("update message set no_questionnaire = 'f' where id = ?", {}, $id);
    dbh()->commit();
    logmsg($id, 1, "$user set message to send quesionnaire", $user);
    notify_daemon();
    return 0;
}

=item admin_set_message_to_error ID USER

Moves message with given ID to error state, so aborting any further action, and
sending a delivery failure notice to the constituent. USER is the
administrator's name.

=cut
sub admin_set_message_to_error ($$) {
    my ($id, $user) = @_;
    my ($curr_state, $curr_frozen) = dbh()->selectrow_array('select state, frozen from message where id = ? for update', {}, $id);
    if ($curr_state ne 'error') {
        state($id, 'error');
    }
    dbh()->commit();
    logmsg($id, 1, "$user put message in state 'error'", $user);
    return 0;
}

=item admin_set_message_to_failed ID USER

Moves message with given ID to failed state, aborting any further action. The
constituent is not told. USER is the administrator's name.

=cut
sub admin_set_message_to_failed ($$) {
    my ($id, $user) = @_;
    my ($curr_state, $curr_frozen) = dbh()->selectrow_array('select state, frozen from message where id = ? for update', {}, $id);
    if ($curr_state ne 'failed') {
        state($id, 'failed');
    }
    dbh()->commit();
    logmsg($id, 1, "$user put message in state 'failed'", $user);
    return 0;
}

=item admin_set_message_to_failed_closed ID USER

Moves message from failed to failed_closed state to indicate that it has been
dealt with by an administrator. USER is the administrator's name.

=cut
sub admin_set_message_to_failed_closed ($$) {
    my ($id, $user) = @_;
    my ($curr_state, $curr_frozen) = dbh()->selectrow_array('select state, frozen from message where id = ? for update', {}, $id);
    if ($curr_state ne 'failed_closed') {
        state($id, 'failed_closed');
    }
    dbh()->commit();
    logmsg($id, 1, "$user put message in state 'failed_closed'", $user);
    return 0;
}

=item admin_set_message_to_bounce_wait ID USER

Move message ID from bounce_confirm to bounce_wait. USER is the name of the
administrator making the change.

=cut

sub admin_set_message_to_bounce_wait ($$) {
    my ($id, $user) = @_;
    state($id, 'bounce_wait');
    dbh()->commit();
    logmsg($id, 1, "$user put message in state 'bounce_wait'", $user);
    return 0;
}

=item admin_add_note_to_message ID USER NOTE

Add text in NOTE to the message log for the message ID; USER is the name of the
administrator leaving the note.

=cut
sub admin_add_note_to_message ($$$) {
    my ($id, $user, $note) = @_;
    logmsg($id, 1, "$user added note: $note", $user);
    return 0;
}

=item admin_get_wire_email ID TYPE

Returns the text of an email as would be sent for this message in various
circumstances. The TYPE can be representative, confirm, confirm-reminder,
failure, questionnaire or questionnaire-reminder.

=cut
sub admin_get_wire_email ($$) {
    my ($id, $type) = @_;
    my $msg = message($id);

    if ($type eq 'representative') {
        return make_representative_email($msg, 'unknown');
    } elsif ($type eq 'confirm') {
        return make_confirmation_email($msg);
    } elsif ($type eq 'confirm-reminder') {
        return make_confirmation_email($msg, 1);
    } elsif ($type eq 'failure') {
        return make_failure_email($msg);
    } elsif ($type eq 'questionnaire') {
        return make_questionnaire_email($msg);
    } elsif ($type eq 'questionnaire-reminder') {
        return make_questionnaire_email($msg, 1);
    } else {
        throw FYR::Error("admin_get_wire_email: Type not known '$type'", FYR::Error::BAD_DATA_PROVIDED);
    }
}

=item admin_set_message_to_ready ID USER

Move message ID (from, probably, pending) to ready. USER is the name of the
administrator making the change.

=cut
sub admin_set_message_to_ready ($$) {
    my ($id, $user) = @_;

    my $group_id;
    my $group_states = [];

    my $msg = message($id);
    if (defined($msg->{group_id})) {
        $group_id = $msg->{group_id};
        $group_states = group_state(undef, undef, $group_id);
    }

    if ($group_id && @$group_states == 1 && $group_states->[0] eq 'pending') {
        # *All* messages in this group are in pending, confirm them all
        lock_group($group_id);
        group_state($id, 'ready', $group_id);
        dbh()->do('update message set confirmed = ? where group_id = ?', {}, time(), $group_id);
        logmsg($id, 1, "$user put message in state 'ready'", $user);
        my $msgs = group_messages($group_id);
        foreach my $memberid (@$msgs) {
            next if $memberid eq $id;
            logmsg($memberid, 1, "$user put message in state 'ready' (via group confirmation from message $id)", $user);
        }
    } else {
        state($id, 'ready');
        dbh()->do('update message set confirmed = ? where id = ?', {}, time(), $id);
        logmsg($id, 1, "$user put message in state 'ready'", $user);
    }
    dbh()->commit();
    notify_daemon();
    return 0;
}

=item admin_get_diligency_queue TIME

Returns how many actions each administrator has done to the queue since unix
time TIME.  Data is returned as an array of pairs of count, name with largest
counts first.

=cut

sub admin_get_diligency_queue($) {
    my ($from_time) = @_;
    my $admin_activity = dbh()->selectall_arrayref("select count(*) as c, editor
        from message_log where whenlogged >= ? and editor is not null
        and message not like '% viewed body of message in admin interface'
        group by editor order by c desc", {}, $from_time);
    return $admin_activity;
}

=item admin_update_recipient ID NEW-CONTACT VIA

Updates any pending messages in the queue for recipient ID to now go to
NEW-CONTACT (which could be a VIA). This is for cases where someone edits a
representative with the admin interface and there is a current message waiting
to be sent.

=cut

sub admin_update_recipient($$$) {
    my ($id, $contact, $via) = @_;
    if ( $contact =~ /@/ ) {
        dbh()->do("update message set recipient_email=?, recipient_fax=null, recipient_via=?
         where recipient_id = ? and state in ('new','pending','ready','bounce_confirm')",
         {}, $contact, $via ? 't' : 'f', $id);
    } else {
        dbh()->do("update message set recipient_email=null, recipient_fax=?, recipient_via=?
         where recipient_id = ? and state in ('new','pending','ready','bounce_confirm')",
         {}, $contact, $via ? 't' : 'f', $id);
    }
    dbh()->commit();
}

=item admin_scrub_data ID USER

Remove all personal data from all messages with same sender_email as message ID.

=cut
sub admin_scrub_data($$) {
    my ($id, $user) = @_;

    my $msg = message($id);
    return unless $msg->{sender_email};

    my $msgs = admin_get_queue('search', { page => 1, limit => 'NULL', query => $msg->{sender_email} });
    foreach (@$msgs) {
        next unless $msg->{sender_email} eq $_->{sender_email};
        FYR::DB::scrub_data($_->{id});
        logmsg($_->{id}, 1, "Scrubbed message of personal data", $user);
    }
}

1;
