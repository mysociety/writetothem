#!/usr/bin/perl
#
# FYR/Queue.pm:
# Fax/email queue management for FYR.
#
# Copyright (c) 2004 UK Citizens Online Democracy. All rights reserved.
# Email: chris@mysociety.org; WWW: http://www.mysociety.org/
#
# $Id: Queue.pm,v 1.214 2006-08-09 15:37:47 chris Exp $
#

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
use Encode;
use Encode::Byte;       # for cp1252
use Error qw(:try);
use Fcntl;
use FindBin;
use HTML::Entities;
use IO::Socket;
use Mail::RFC822::Address;
use POSIX qw(strftime);
use Text::Wrap (); # don't pollute our namespace
use Time::HiRes ();
use Data::Dumper;

use utf8;

use mySociety::Config;
use mySociety::DaDem;
use mySociety::DBHandle qw(dbh new_dbh);
use mySociety::Email;
use mySociety::MaPit;
use mySociety::Util;
use mySociety::VotingArea;
use mySociety::StringUtils qw(trim merge_spaces string_diff);

use FYR;
use FYR::AbuseChecks;
use FYR::EmailTemplate;
use FYR::Fax;

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
    return unpack('h20', mySociety::Util::random_bytes(MESSAGE_ID_LENGTH / 2));
}

# get_via_representative ID
# Given a voting area ID, return reference to a hash of information about any
# 'via' representative (e.g. council) which can be used to contact
# representatives of that area.
sub get_via_representative ($) {
    my ($aid) = @_;
    my $ainfo = mySociety::MaPit::get_voting_area_info($aid);
    my $vainfo = mySociety::DaDem::get_representatives($ainfo->{parent_area_id});

    throw FYR::Error("Bad return from DaDem looking up contact via info")
        unless (ref($vainfo) eq 'ARRAY');
    throw FYR::Error("More than one via contact (shouldn't happen)")
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
        throw FYR::Error("Bad contact mehod for via contact (shouldn't happen)")
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
    throw FYR::Error("No RECIPIENT specified") if (!defined($recipient_id) or $recipient_id =~ /[^\d]/ or $recipient_id eq '');
    my $recipient = mySociety::DaDem::get_representative_info($recipient_id);
    throw FYR::Error("Bad RECIPIENT or error ($recipient) in DaDem") if (!$recipient or ref($recipient) ne 'HASH');

    # Decide how to send message
    work_out_destination($recipient);

    return 1
}

=item write ID SENDER RECIPIENT TEXT [COBRAND] [COCODE]

Write details of a message for sending. ID is the identity of the message,

SENDER is a reference to hash containing details of the sender including
elements: name, the sender's full name; email, their email address; address,
their full postal address; postcode, their post code; and optionally phone,
their phone number; ipaddr, their IP address; referrer, website that referred
them to this one. 

RECIPIENT is the DaDem ID number of the recipient of the message; and TEXT is
the text of the message, with line breaks. Returns true on success, or an error
code on failure.

COBRAND is the name of cobranding partner (e.g. "cheltenham"), and COCODE is
a reference code for them.

This function is called remotely and commits its changes.

=cut
sub write ($$$$;$$) {
    my ($id, $sender, $recipient_id, $text, $cobrand, $cocode) = @_;

    throw FYR::Error("Bad ID specified")
        unless ($id =~ m/^[0-9a-f]{20}$/i);

    my $ret = undef;
    try {
        # Get details of the recipient.
        throw FYR::Error("No RECIPIENT specified") if (!defined($recipient_id) or $recipient_id =~ /[^\d]/ or $recipient_id eq '');
        my $recipient = mySociety::DaDem::get_representative_info($recipient_id);
        throw FYR::Error("Bad RECIPIENT or error ($recipient) in DaDem") if (!$recipient or ref($recipient) ne 'HASH');

        # Check that sender contains appropriate information.
        throw FYR::Error("Bad SENDER (not reference-to-hash") unless (ref($sender) eq 'HASH');
        foreach (qw(name email address postcode)) {
            throw FYR::Error("Missing required '$_' element in SENDER") unless (exists($sender->{$_}));
        }
        throw FYR::Error("Email address '$sender->{email}' for SENDER is not valid") unless (Mail::RFC822::Address::valid($sender->{email}));
        throw FYR::Error("Postcode '$sender->{postcode}' for SENDER is not valid") unless (mySociety::Util::is_valid_postcode($sender->{postcode}));

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

        # XXX should also check that the text bits are valid UTF-8.

        # Queue the message.
        try {
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
                    cobrand, cocode
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
                    ?, ?
                )#, {},
                $id,
                (map { $sender->{$_} || undef } qw(name email address phone postcode ipaddr referrer)),
                $recipient_id,
                    (map { $recipient->{$_} || undef } qw(name type email fax)),
                    $recipient->{via} ? 't' : 'f',
                $text,
                FYR::DB::Time(), FYR::DB::Time(),
                $cobrand, $cocode);
        } catch mySociety::DBHandle::Error with {
            # Assume this is a duplicate-insert error.
            # XXX check by a select?
            throw FYR::Error("You've already sent this message, there's no need to send it twice.", FYR::Error::MESSAGE_ALREADY_QUEUED);
        };

        dbh()->commit();

        # Log creation of message
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

        # Check for possible abuse
        my $abuse_result = FYR::AbuseChecks::test(message($id));
        if (defined($abuse_result)) {
            if ($abuse_result eq 'freeze') {
                logmsg($id, 1, "abuse system froze message");
                dbh()->do("update message set frozen = 't' where id = ?", {}, $id);
            } else {
                logmsg($id, 1, "abuse system REJECTED message");
                dbh()->do("update message set frozen = 't' where id = ?", {}, $id);
                state($id, 'failed_closed');
                # Delete the message, so people can go back and try again
                dbh()->do("delete from message_bounce where message_id = ?", {}, $id);
                dbh()->do("delete from message_extradata where message_id = ?", {}, $id);
                dbh()->do("delete from message_log where message_id = ?", {}, $id);
                dbh()->do("delete from message where id = ?", {}, $id);
                $ret = $abuse_result;
            }
        }
    
        # Commit changes
        dbh()->commit();

        # Wake up the daemon to send the confirmation mail.
        notify_daemon();
    } otherwise {
        my $E = shift;
        if ($E->value() && $E->value() != FYR::Error::MESSAGE_ALREADY_QUEUED) {
            warn "fyr queue rolling back transaction after error: " . $E->text() . "\n";
        }
        dbh()->rollback();
        throw $E;
    };

    return $ret;
}

my $logmsg_handler;
sub logmsg_set_handler ($) {
    $logmsg_handler = $_[0];
}

# logmsg ID IMPORTANT DIAGNOSTIC [EDITOR]
# Log a DIAGNOSTIC about the message with the given ID. If IMPORTANT is true,
# then mark the log message as exceptional. Optionally, EDITOR is the name
# of the human who performed the action relating to the log message.
sub logmsg ($$$;$) {
    my ($id, $important, $msg, $editor) = @_;
    our $dbh;
    # XXX should ping
    $dbh ||= new_dbh();
    $dbh->do('insert into message_log (message_id, whenlogged, state, message, exceptional, editor) values (?, ?, ?, ?, ?, ?)',
        {},
        $id,
        FYR::DB::Time(),
        state($id),
        $msg,
        $important ? 't' : 'f',
        $editor);
    $dbh->commit();
    &$logmsg_handler($id, FYR::DB::Time(), state($id), $msg, $important) if (defined($logmsg_handler));
}

# %allowed_transitions
# Transitions we're allowed to make in the state machine.
my %allowed_transitions = (
        new =>              [qw(pending failed)],
        pending =>          [qw(ready failed failed_closed)],
        ready =>            [qw(error bounce_wait sent)],
        bounce_wait =>      [qw(bounce_confirm sent ready)],
        bounce_confirm =>   [qw(bounce_wait error ready)],
        error =>            [qw(failed)],
        sent =>             [qw(finished)],
        failed =>           [qw(failed_closed)],
        finished =>         [],
        failed_closed =>    []
    );

# turn this into hash-of-hashes
foreach (keys %allowed_transitions) {
    my $x = { map { $_ => 1 } @{$allowed_transitions{$_}} };
    $allowed_transitions{$_} = $x;
}

# scrubmessage ID
# Remove some personal data from message ID. This includes the text of the
# letter, any log messages (since they may contain email addresses etc.), and
# any bounce messages (since they usually contain quoted text).
sub scrubmessage ($) {
    my ($id) = @_;
    # We delete any information which has to do with the sender or their
    # message, except for information about the recipient which is needed for
    # our statistics gathering. At the end of this operation the message will
    # contain only the recipient ID and type, and a placeholder which indicates
    # whether the letter was delivered by fax or email.
    dbh()->do(q#
                update message 
                    set sender_ipaddr = '', sender_referrer = null,
                        message = '[ removed message of ' || length(message) || ' characters]'
                    where id = ?#, {}, $id);
    # The log, extra data, and bounce tables may also contain personal data.
    dbh()->do(q#delete from message_extradata where message_id = ?#, {}, $id);
    dbh()->do(q#delete from message_bounce where message_id = ?#, {}, $id);
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

=item actions ID

Get the number of actions taken on this message while in the current state.

=cut
sub actions ($) {
    my ($id) = @_;
    return scalar(dbh()->selectrow_array('select numactions from message where id = ?', {}, $id));
}

# message ID [LOCK]
# Return a hash of data about message ID. If LOCK is true, retrieves the fields
# using SELECT ... FOR UPDATE.
sub message ($;$) {
    my ($id, $forupdate) = @_;
    $forupdate = defined($forupdate) ? ' for update' : '';
    if (my $msg = dbh()->selectrow_hashref("select * from message where id = ?$forupdate", {}, $id)) {
        # Add some convenience fields.
        $msg->{recipient_position} = $mySociety::VotingArea::rep_name{$msg->{recipient_type}};
        $msg->{recipient_position_plural} = $mySociety::VotingArea::rep_name_plural{$msg->{recipient_type}};
        return $msg;
    } else {
        throw FYR::Error("No message '$id'.");
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
    die "STRING is not valid ASCII/UTF-8" unless (utf8::valid($s));
    utf8::encode($s);
    return $s;
}

# make_representative_email MESSAGE
# Return the on-the-wire text of an email for the passed MESSAGE (hash of db
# fields), suitable for immediate sending to the real recipient.
sub make_representative_email ($) {
    my ($msg) = (@_);

    my $subject = $msg->{recipient_type} eq 'HOC'
                        ? "Letter from $msg->{sender_name}"
                        : "Letter from your constituent $msg->{sender_name}";
    
    my $bodytext = '';

    # If this is being sent via some contact, we need to add blurb to the top
    # to that effect.
    if ($msg->{recipient_via} && $msg->{recipient_type} ne 'HOC') {
        $subject = "Letter from constituent $msg->{sender_name} to $msg->{recipient_name}";
        $bodytext = FYR::EmailTemplate::format(
                email_template('via-coversheet'),
                email_template_params($msg, representative_url => '')
            );
    }

    $bodytext .= "\n\n"
            . format_email_body($msg)
            . "\n\n" . ('x' x EMAIL_COLUMNS) . "\n\n"
            . FYR::EmailTemplate::format(
                email_template('footer'),
                email_template_params($msg, representative_url => '') # XXX
            );

    return mySociety::Email::construct_email({
            From => [$msg->{sender_email}, $msg->{sender_name}],
            To => [[$msg->{recipient_email}, $msg->{recipient_name}]],
            Subject => $subject,
            Date => strftime('%a, %e %b %Y %H:%M:%S %z', localtime(FYR::DB::Time())),
            'Message-ID' => email_message_id($msg->{id}),
            _body_ => $bodytext
        });
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
    $token =~ s#^(.{10})(.+)$#$1/$2#;
    return $token;
}

# check_token WORD TOKEN
# If TOKEN is valid, return the ID it encodes. Otherwise return undef.
sub check_token ($$) {
    my ($word, $token) = @_;

    $token = lc($token);
    $token =~ s#[./]##g;
    return undef if ($token !~ m#^[2-7a-z]{20,}$#);
    
    $word = $token_wordmap{$word} if (exists($token_wordmap{$word}));

    my ($rand, $enc) = unpack('na*', Convert::Base32::decode_base32($token));

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
    my $result = mySociety::Util::send_email($mail, do_not_reply_sender(), $msg->{sender_email});
    if ($result == mySociety::Util::EMAIL_SUCCESS) {
        logmsg($id, 1, "sent $descr mail to $msg->{sender_email}");
    } elsif ($result == mySociety::Util::EMAIL_SOFT_ERROR) {
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
sub email_template ($) {
    my ($name) = @_;
    my $fn = "$FindBin::Bin/../templates/emails/$name";
    die "unable to locate email template '$name'" if (!-e $fn);
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
    my $A = mySociety::MaPit::get_voting_area_info($r->{voting_area});
    $params{recipient_area_name} = $A->{name};
    
    return \%params;
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
    my $url_start = mySociety::Config::get('BASE_URL');
    if ($msg->{cobrand}) {
        $url_start = "http://" . $msg->{cobrand} . "." . mySociety::Config::get('WEB_DOMAIN');
    }
    my $confirm_url = $url_start . '/C/' . $token;

    my $bodytext = FYR::EmailTemplate::format(
                    email_template($reminder ? 'confirm-reminder' : 'confirm'),
                    email_template_params($msg, confirm_url => $confirm_url)
                );

    # XXX Monstrous hack. The AOL client software (in some versions?) doesn't
    # present URLs as hyperlinks in email bodies unless we enclose them in
    # <a href="...">...</a> (yes, in text/plain emails). So for users on AOL,
    # we manually make that transformation. Note that we're assuming here that
    # the confirm URLs have no characters which need to be entity-encoded,
    # which is bad, evil and wrong but actually true in this case.
    $bodytext =~ s#(http://.+$)#<a href="$1">$1</a>#m
        if ($msg->{sender_email} =~ m/\@aol\./i);

    # Append a separator and the text of the ms
    $bodytext .= "\n\n" . ('x' x EMAIL_COLUMNS) . "\n\n"
                . format_email_body($msg);

    # Add header if site in test mode
    my $reflecting_mails = mySociety::Config::get('FYR_REFLECT_EMAILS');
    if ($reflecting_mails) {
        $bodytext = wrap(EMAIL_COLUMNS, "(NOTE: THIS IS A TEST SITE, THE MESSAGE WILL BE SENT TO YOURSELF NOT YOUR REPRESENTATIVE.)") . "\n\n" . $bodytext;
    }


    return mySociety::Email::construct_email({
            From => [do_not_reply_sender(), 'WriteToThem'],
            To => [[$msg->{sender_email}, $msg->{sender_name}]],
            Subject => "Please confirm that you want to send a message to $msg->{recipient_name}",
            Date => strftime('%a, %e %b %Y %H:%M:%S %z', localtime(FYR::DB::Time())),
            'Message-ID' => email_message_id($msg->{id}),
            _body_ => $bodytext
        });
}

# do_not_reply_sender
# Return a do-not-reply sender address.
sub do_not_reply_sender () {
    our $s;
    $s ||= sprintf('%sDO-NOT-REPLY@%s',
                        mySociety::Config::get('EMAIL_PREFIX'),
                        mySociety::Config::get('EMAIL_DOMAIN'));
    return $s;
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

    my $text = FYR::EmailTemplate::format(
                    email_template('failure'),
                    email_template_params($msg)
                )
                . "\n\n" . ('x' x EMAIL_COLUMNS) . "\n\n"
                . format_email_body($msg);

    return mySociety::Email::construct_email({
            From => [do_not_reply_sender(), 'WriteToThem'],
            To => [[$msg->{sender_email}, $msg->{sender_name}]],
            Subject => "Unfortunately, we couldn't send your message to $msg->{recipient_name}",
            Date => strftime('%a, %e %b %Y %H:%M:%S %z', localtime(FYR::DB::Time())),
            'Message-ID' => email_message_id($msg->{id}),
            _body_ => $text
        });
}

# send_failure_email ID
# Send a failure report to the sender of message ID.
sub send_failure_email ($) {
    my ($id) = @_;
    my $msg = message($id);
    return send_user_email(
                $id,
                'failure report',
                make_failure_email($msg)
            );
}

# make_questionnaire_email MESSAGE [REMINDER]
# Return the on-the-wire text of an email for the given MESSAGE, asking the
# user to fill in a questionnaire. If REMINDER is true, this is a reminder
# mail.
sub make_questionnaire_email ($;$) {
    my ($msg, $reminder) = @_;
    $reminder ||= 0;

    my $token = make_token("questionnaire", $msg->{id});
    my $yes_url = mySociety::Config::get('BASE_URL') . '/Y/' . $token;
    my $no_url = mySociety::Config::get('BASE_URL') . '/N/' . $token;

    my $text = FYR::EmailTemplate::format(
                    email_template('questionnaire'),
                    email_template_params($msg, yes_url => $yes_url, no_url => $no_url,
                        weeks_ago => $reminder ? 'Three' : 'Two',
                        their_constituents => $msg->{recipient_type} eq 'HOC' ? 'the public' : 'their constituents'
                        )
                )
                . "\n\n" . ('x' x EMAIL_COLUMNS) . "\n\n"
                . format_email_body($msg);

    # XXX Monstrous hack. The AOL client software (in some versions?) doesn't
    # present URLs as hyperlinks in email bodies unless we enclose them in
    # <a href="...">...</a> (yes, in text/plain emails). So for users on AOL,
    # we manually make that transformation. Note that we're assuming here that
    # the confirm URLs have no characters which need to be entity-encoded,
    # which is bad, evil and wrong but actually true in this case.
    $text =~ s#(http://.+$)#<a href="$1">$1</a>#mg
        if ($msg->{sender_email} =~ m/\@aol\.com$/i);

    return mySociety::Email::construct_email({
            From => [do_not_reply_sender(), 'WriteToThem'],
            To => [[$msg->{sender_email}, $msg->{sender_name}]],
            Subject => "Did your $msg->{recipient_position} reply to your letter?",
            Date => strftime('%a, %e %b %Y %H:%M:%S %z', localtime(FYR::DB::Time())),
            'Message-ID' => email_message_id($msg->{id}),
            _body_ => $text
        });
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
    my $mail = make_representative_email($msg);
    my $sender = sprintf('%s%s@%s',
                            mySociety::Config::get('EMAIL_PREFIX'),
                            make_token("bounce", $id),
                            mySociety::Config::get('EMAIL_DOMAIN'));
    my $result = mySociety::Util::send_email($mail, $sender, $msg->{recipient_email});
    if ($result == mySociety::Util::EMAIL_SUCCESS) {
        logmsg($id, 1, "delivered message by email to $msg->{recipient_email}");
    } elsif ($result == mySociety::Util::EMAIL_SOFT_ERROR) {
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
        return $id if (state($id) ne 'pending');
        state($id, 'ready');
        logmsg($id, 1, "sender email address confirmed");
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
    throw FYR::Error("Bad QUESTION (should be '0' or '1')") if ($qn ne '0' and $qn ne '1');
    throw FYR::Error("Bad RESPONSE (should be 'YES' or 'NO')") if ($answer !~ /^(yes|no)$/i);
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

# How long do we retain log and other information from a failed message for
# operator inspection?
use constant FAILED_RETAIN_TIME => MESSAGE_RETAIN_TIME;

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

        # How long to hold on to body of failed messages? After this time they
        # go into failed_closed and after FAILED_RETAIN_TIME get scrubbed.
        failed          => DAY * 7 * 6
    );

# Where we time out to.
my %state_timeout_state = qw(
        new		failed_closed
        pending         failed_closed
        ready           error
        bounce_wait     sent
        sent            finished
        failed          failed_closed
    );


# How often we do various things to messages in various states.
my %state_action_interval = (
        # How often we attempt to send a confirmation mail in the face of
        # soft failures.
        new             => 300,

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
            # Construct confirmation email and send it to the sender.
            my $result = send_confirmation_email($id);
            if ($result == mySociety::Util::EMAIL_SUCCESS) {
                state($id, 'pending');
            } elsif ($result == mySociety::Util::EMAIL_HARD_ERROR) {
	    	state($id, 'failed_closed');
            } else {
                state($id, 'new');
            }
        },

        pending => sub ($$$) {
            my ($email, $fax, $id) = @_;
            return unless ($email);
            # Send reminder confirmation if necessary. We don't send one
            # immediately on entering this state, but do thereafter.
            if (actions($id) == 0) {
                # Bump action counter but don't do anything else.
                state($id, 'pending');
                return;
            } elsif (actions($id) < NUM_CONFIRM_MESSAGES) {
                my $result = send_confirmation_email($id, 1);
                if ($result == mySociety::Util::EMAIL_SUCCESS) {
                    state($id, 'pending');  # bump actions counter
                } elsif ($result == mySociety::Util::EMAIL_HARD_ERROR) {
	    	    state($id, 'failed_closed');
                } else {
                    logmsg($id, 1, "error sending confirmation reminder message (will retry)");
                }
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
                my $hour = (localtime(FYR::DB::Time()))[2];
                return if ($hour < 8 || $hour > 20);

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
                    return if ($msg->{dispatched} > time() - EMAIL_REDELIVERY_INTERVAL);
                    logmsg($id, 0, "making email redelivery attempt");
                }

                # XXX we should consider the case where we've made too many
                # redelivery attempts, but we can't just use the state counter
                # because redelivery occurs only when a message leaves this
                # state and then comes back.
            
                my $result = deliver_email($msg);
                if ($result == mySociety::Util::EMAIL_SUCCESS) {
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
                # XXX this is broken -- emailed MPs get two extra days to
                # answer, because of the bounce_wait state....
                if (actions($id) == 14) {
                    $dosend = 1;
                } elsif (actions($id) == 21) {
                    $dosend = $reminder = 1;
                }
            }
            if ($msg->{no_questionnaire}) {
                # don't send questionnaire for test messages
                $dosend = 0;
            }

            if ($dosend) {
                my $result = send_questionnaire_email($id, $reminder);
                if ($result == mySociety::Util::EMAIL_SUCCESS) {
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
            if ($result == mySociety::Util::EMAIL_SOFT_ERROR) {
                state($id, 'error');    # bump timer for redelivery
                return;
            }
            # Give up -- it's all really bad.
            logmsg($id, 1, "unable to send failure report to user") if ($result == mySociety::Util::EMAIL_HARD_ERROR);
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
            if ($msg->{sender_ipaddr} ne '' and $msg->{laststatechange} < (FYR::DB::Time() - FAILED_RETAIN_TIME)) {
                # clear data for privacy
                scrubmessage($id);
            }
            # bump timer
            state($id, 'failed_closed');
        }
    );

# process_queue EMAIL FAX
# Drive the state machine round; emails will be sent if EMAIL is true, and
# faxes if FAX is true.
sub process_queue ($$) {
    my ($email, $fax) = @_;
    $email ||= 0;
    $fax ||= 0;
    # Timeouts. Just lock the whole table to do this -- it should be reasonably
    # quick.
    my $stmt = dbh()->prepare(
        'select id, state from message where '
        . join(' or ',
            map { sprintf(q#(state = '%s' and laststatechange < %d)#, $_, FYR::DB::Time() - $state_timeout{$_}) }
                keys %state_timeout)
        . ' for update');
    $stmt->execute();
    while (my ($id, $state) = $stmt->fetchrow_array()) {
        state($id, $state_timeout_state{$state});
    }
    dbh()->commit();

    # Actions. These are slow (potentially) so lock row-by-row. Process
    # messages in a random order, so that bad ones don't block everything.
    if ($email) {
        $stmt = dbh()->prepare(
            'select id, state from message where ('
                . join(' or ', map { sprintf(q#state = '%s'#, $_); } keys %state_action)
            . ') and (lastaction is null or '
                . join(' or ',
                    map { sprintf(q#(state = '%s' and lastaction < %d)#, $_, FYR::DB::Time() - $state_action_interval{$_}) }
                        keys %state_action_interval)
            . q#) and (state <> 'ready' or not frozen) order by random()#);
    } else {
        $stmt = dbh()->prepare(sprintf(q#
                select id, state from message
                where state = 'ready' and not frozen
                    and (lastaction is null or lastaction < %d)
                #, FYR::DB::Time() - $state_action_interval{ready}));
    }
    $stmt->execute();
    while (my ($id, $state) = $stmt->fetchrow_array()) {
        # Now we need to lock the row. Once it's locked, check that the message
        # still meets the criteria for sending. Do things this way round so
        # that we can have several queue-running daemons operating
        # simultaneously.
        try {
            my $msg = message($id, 1);
            if ($msg->{state} eq $state
                and (!defined($msg->{lastaction})
                    or $msg->{lastaction} < FYR::DB::Time() - $state_action_interval{$state})) {
                # Check for ready+frozen again.
                if (!$msg->{frozen} or $msg->{state} ne 'ready') {
                    &{$state_action{$state}}($email, $fax, $id);
                }
            }
        } catch FYR::Error with {
            my $E = shift;
            logmsg($id, 1, "error while processing message: $E");
        } catch Error with {
            my $E = shift;
            logmsg($id, 1, "unexpected error (type " . ref($E) . ") while processing message: $E");
            $E->throw();
        } finally {
            dbh()->commit();
        };
    }
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

=item get_time

Returns the current time, in Unix seconds since epoch. This may not
be the real world time, as it can be overriden in the database for
the test script.

=cut
sub get_time () {
    my ($id, $user) = @_;
    return scalar(dbh()->selectrow_array("select extract(epoch from fyr_current_timestamp())", {}));
}

=item get_date

Returns the current date, in iso format. This may not be the real world date,
as it can be overriden in the database for the test script.

=cut
sub get_date () {
    my ($id, $user) = @_;
    return scalar(dbh()->selectrow_array("select fyr_current_date()", {}));
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
set.

=cut
sub admin_message_events ($;$) {
    my ($id, $imp) = @_;
    my $sth = dbh()->prepare('
                    select message_id, whenlogged, state, message, exceptional
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

Up to 100 of the messages which have most recently changed state.

=item recentcreated

Up to 100 of the messages which have recently been created.

=item similarbody

Messages which are similar to the message with ID given in PARAMS->{msgid}.

=item search

Messages which contain terms from PARAMS->{query}. Sender and recipient
details are searched, as well as matching confirmation tokens.

=item logsearch

Messages which contain the string in an item in their message log.  Deliberately
doesn't strip spaces or punctuation, and looks for whole strings, so you can
search for ' rule #6 ' and the like.

=item type

All messages which were sent to representative of type PARAMS->{type}. 

=item rep_id

All messages which were sent to representative PARAMS->{rep_id}.

=back

=cut
sub admin_get_queue ($$) {
    my ($filter, $params) = @_;

    my %allowed = map { $_ => 1 } qw(all needattention failing recentchanged recentcreated similarbody search logsearch type rep_id);
    throw FYR::Error("Bad filter type '$filter'") if (!exists($allowed{$filter}));
    
    my $where = "order by created desc";
    my $msg;
    my @params;
    # XXX "frozen = 't'" because if you just say "frozen", PG won't use an
    # index to do the scan. q.v. comments on the end of,
    #   http://www.postgresql.org/docs/7.4/interactive/indexes.html
    if ($filter eq 'needattention') {
        $where = q# where (frozen = 't' and state <> 'failed_closed' and state <> 'failed'
                           and state <> 'pending' and state <> 'new') 
                         or (state = 'bounce_confirm') order by created desc#;
    } elsif ($filter eq 'failing') {
        $where = q#
            where (state = 'failed'
                    or state = 'error')
                  and frozen = 'f' 
            order by created desc#;
    } elsif ($filter eq 'recentchanged') {
        $where = "order by laststatechange desc limit 100";
    } elsif ($filter eq 'recentcreated') {
        $where = "order by created desc limit 100";
    } elsif ($filter eq 'similarbody') {
        my $sth2 = dbh()->prepare("
                select *, length(message) as message_length from message where id = ?
            ");
        $sth2->execute($params->{msgid});
        $msg = $sth2->fetchrow_hashref();
        my @similar = FYR::AbuseChecks::get_similar_messages($msg);
        @params = map { $_->[0] } @similar;
        push @params, $params->{msgid};
        $where = "where id in (" . join(",", map { '?' } @params) .  ")";
    } elsif ($filter eq 'search') {
        my $tokenfound_id;
        if (length($params->{query}) >= 20) {
            $tokenfound_id = check_token("confirm", $params->{query});
            if (!defined($tokenfound_id)) {
                $tokenfound_id = check_token("questionnaire", $params->{query});
            }
        }
        if (defined($tokenfound_id)) {
            $where = "where id = ?";
            push @params, $tokenfound_id;
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
            $where .= " order by created desc";
        }
    } elsif ($filter eq 'logsearch') {
        my $logmatches = dbh()->selectcol_arrayref(q#select message_id from message_log
            where message ilike '%' || ? || '%'#, {}, $params->{query});
        push @params, @$logmatches;
        $where = q#where id in (# . join(',', map { '?' } @$logmatches) . q#) order by created desc#;
        $where = q#where 1 = 0# if (scalar(@$logmatches) == 0);
    } elsif ($filter eq 'type') {
        push @params, $params->{query};
        $where = "where recipient_type = ?";
    } elsif ($filter eq 'rep_id') {
        push @params, $params->{rep_id};
        $where = "where recipient_id = ?";
    }
    my $sth = dbh()->prepare("
            select *, $message_calculated_values
            from message 
            $where
        ");
    $sth->execute(@params);
    my @ret;
    while (my $other = $sth->fetchrow_hashref()) {
        if ($filter eq 'similarbody') {
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
    throw FYR::Error("admin_get_message: Message not found '$id'") if ($sth->rows == 0);
    throw FYR::Error("admin_get_message: Multiple messages with '$id' found") if ($sth->rows > 1);
    my $hash_ref = $sth->fetchrow_hashref();

    my $bounces = dbh()->selectcol_arrayref("select 
        bouncetext from message_bounce where message_id = ?", {}, $id);
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


=item admin_get_stats

Returns a hash of statistics about the queue.

=cut
sub admin_get_stats () {
    my %ret;

    my $rows = dbh()->selectall_arrayref('select recipient_type, state, count(*) from message group by recipient_type, state', {});
    foreach (@$rows) {
        my ($type, $state, $count) = @$_; 
        $ret{"both $type $state"} = $count;
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

=item admin_set_message_to_ready ID USER

Move message ID (from, probably, pending) to ready. USER is the name of the
administrator making the change.

=cut
sub admin_set_message_to_ready ($$) {
    my ($id, $user) = @_;
    state($id, 'ready');
    dbh()->commit();
    logmsg($id, 1, "$user put message in state 'ready'", $user);
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
        group by editor order by c desc", {}, $from_time);
    return $admin_activity;
}



1;
