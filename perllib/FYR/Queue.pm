#!/usr/bin/perl
#
# FYR/Queue.pm:
# Fax/email queue management for FYR.
#
# Copyright (c) 2004 UK Citizens Online Democracy. All rights reserved.
# Email: chris@mysociety.org; WWW: http://www.mysociety.org/
#
# $Id: Queue.pm,v 1.104 2005-01-29 02:23:28 francis Exp $
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
use Error qw(:try);
use Fcntl;
use HTML::Entities;
use IO::Socket;
use Mail::RFC822::Address;
use MIME::Entity;
use MIME::Words;
use POSIX qw(strftime);
use String::Ediff;
use Text::Wrap (); # don't pollute our namespace
use Data::Dumper;

use utf8;

use mySociety::Config;
use mySociety::DaDem;
use mySociety::Util;
use mySociety::VotingArea;
use mySociety::StringUtils qw(trim merge_spaces);

use FYR;
use FYR::AbuseChecks;
use FYR::EmailTemplate;
use FYR::Fax;

=head1 NAME

FYR::Queue

=head1 DESCRIPTION

Management of queue of messages for FYR.

=head1 FUNCTIONS

=over 4

=item create

Return an ID for a new message. Message IDs are 20 characters long and consist
of characters [0-9a-f] only.

=cut
sub create () {
    # Assume collision probability == 0.
    return unpack('h20', mySociety::Util::random_bytes(10));
}

# work_out_destination RECIPIENT
# Internal use.  Takes a RECIPIENT (reference to hash of fields), and uses
# their contact method to set the fax or email fields.  Gives an error if
# contact method is inconsistent with them, and leaves exactly one of fax or
# email defined.
sub work_out_destination ($) {
    my ($recipient) = shift;
    
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

=item write ID SENDER RECIPIENT TEXT

Write details of a message for sending. ID is the identity of the message,
SENDER is a reference to hash containing details of the sender including
elements: name, the sender's full name; email, their email address; address,
their full postal address; postcode, their post code; and optionally phone,
their phone number; ipaddr, their IP address; referrer, website that
referred them to this one. RECIPIENT is the DaDem ID number of the
recipient of the message; and TEXT is the text of the message, with line
breaks. Returns true on success, or an error code on failure.

This function is called remotely and commits its changes.

=cut
sub write ($$$$) {
    my ($id, $sender, $recipient_id, $text) = @_;

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

        # Check to see if message has already been posted
        if (FYR::DB::dbh()->selectrow_array('select count(*) from message where id = ?', {}, $id) > 0) {
            throw FYR::Error("You've already sent this message, there's no need to send it twice.", FYR::Error::MESSAGE_ALREADY_QUEUED);
        }

        # Queue the message.
        FYR::DB::dbh()->do(q#
            insert into message (
                id,
                sender_name, sender_email, sender_addr, sender_phone,
                sender_postcode, sender_ipaddr, sender_referrer,
                recipient_id, recipient_name, recipient_type, recipient_email, recipient_fax,
                message,
                state,
                created, laststatechange,
                numactions, dispatched
            ) values (
                ?,
                ?, ?, ?, ?, ?, ?, ?,
                ?, ?, ?, ?, ?,
                ?,
                'new',
                ?, ?,
                0, null
            )#, {},
            $id,
            (map { $sender->{$_} || undef } qw(name email address phone postcode ipaddr referrer)),
            $recipient_id, (map { $recipient->{$_} || undef } qw(name type email fax)),
            $text,
            time(), time());

        # Log creation of message
        my $logaddr = $sender->{address};
        $logaddr =~ s#\n#, #gs;
        $logaddr =~ s#,+#,#g;
        $logaddr =~ s#, *$##;

        logmsg($id, sprintf("created new message from %s <%s>%s, %s, to %s via %s to %s",
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
                logmsg($id, "abuse system froze message");
                FYR::DB::dbh()->do("update message set frozen = 't' where id = ?", {}, $id);
            } else {
                logmsg($id, "abuse system REJECTED message");
                state($id, 'failed');   # XXX or should this be failed_closed?
                $ret = $abuse_result;
            }
        }
    
        # Commit changes
        FYR::DB::dbh()->commit();

        # Wake up the daemon to send the confirmation mail.
        notify_daemon();
    } otherwise {
        my $E = shift;
        warn "fyr queue rolling back transaction after error: " . $E->text() . "\n";
        FYR::DB::dbh()->rollback();
        throw $E;
    };

    return $ret;
}

# logmsg ID DIAGNOSTIC
# Log a DIAGNOSTIC about the message with the given ID.
# XXX should have a flag for "exceptional" to warn administrators.
sub logmsg ($$) {
    my ($id, $msg) = @_;
    our $dbh;
    $dbh ||= FYR::DB::new_dbh();
    $dbh->do('insert into message_log (message_id, whenlogged, state, message) values (?, ?, ?, ?)',
        {},
        $id,
        time(),
        state($id),
        $msg);
    $dbh->commit();
}

# %allowed_transitions
# Transitions we're allowed to make in the state machine.
my %allowed_transitions = (
        new =>              [qw(pending failed)],
        pending =>          [qw(ready failed failed_closed)],
        ready =>            [qw(error bounce_wait sent)],
        bounce_wait =>      [qw(bounce_confirm sent)],
        bounce_confirm =>   [qw(bounce_wait error)],
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
# Remove all personal data from message ID. This includes the text of the
# letter, any log messages (since they may contain email addresses etc.), and
# any bounce messages (since they usually contain quoted text).
sub scrubmessage ($) {
    my ($id) = @_;
    # We delete any information which has to do with the sender or their
    # message, except for information about the recipient which is needed for
    # our statistics gathering. At the end of this operation the message will
    # contain only the recipient ID and type, and a placeholder which indicates
    # whether the letter was delivered by fax or email.
    FYR::DB::dbh()->do(q#
                update message 
                    set sender_name = '', sender_email = '',
                        sender_addr = '', sender_phone = null,
                        sender_postcode = '', 
                        sender_ipaddr = '', sender_referrer = null,
                        recipient_name = '',
                        recipient_email = '', recipient_fax = null,
                        message = ''
                    where id = ?#, {}, $id);
    # Scrub delivery information but make sure we can still tell how the
    # message was sent.
    FYR::DB::dbh()->do(q#
                update message
                    set recipient_email = ''
                    where id = ? and recipient_email is not null#, {}, $id);
    FYR::DB::dbh()->do(q#
                update message
                    set recipient_fax = ''
                    where id = ? and recipient_fax is not null#, {}, $id);
    # The log, extra data, and bounce tables may also contain personal data.
    FYR::DB::dbh()->do(q#delete from message_log where message_id = ?#, {}, $id);
    FYR::DB::dbh()->do(q#delete from message_extradata where message_id = ?#, {}, $id);
    FYR::DB::dbh()->do(q#delete from message_bounce where message_id = ?#, {}, $id);
    logmsg($id, 'Scrubbed message of all personal data');
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
        my ($curr_state, $curr_frozen) = FYR::DB::dbh()->selectrow_array('select state, frozen from message where id = ? for update', {}, $id);
        die "Bad state '$state'" unless (exists($allowed_transitions{$state}));
        die "Can't go from state '$curr_state' to '$state'" unless ($state eq $curr_state or $allowed_transitions{$curr_state}->{$state} or (($curr_frozen == 1) and ($state eq 'failed' or $state eq 'error' or $state eq 'failed_closed')));
        if ($state ne $curr_state) {
            FYR::DB::dbh()->do('update message set lastaction = null, numactions = 0, laststatechange = ?, state = ? where id = ?', {}, time(), $state, $id);
            logmsg($id, "changed state to $state");

            # On moving into the 'finished' state, scrub message of personal
            # information. Don't do this for 'failed' messages since we want to
            # keep log and other information around for a bit to debug
            # problems; the action for the failed state will scrub such
            # messages later on.
            if ($state eq 'finished') {
                scrubmessage($id);
            }
        } else {
            FYR::DB::dbh()->do('update message set lastaction = ?, numactions = numactions + 1 where id = ?', {}, time(), $id);
        }
        return $curr_state;
    } else {
        return scalar(FYR::DB::dbh()->selectrow_array('select state from message where id = ?', {}, $id));
    }
}

=item actions ID

Get the number of actions taken on this message while in the current state.

=cut
sub actions ($) {
    my ($id) = @_;
    return scalar(FYR::DB::dbh()->selectrow_array('select numactions from message where id = ?', {}, $id));
}

# message ID [LOCK]
# Return a hash of data about message ID. If LOCK is true, retrieves the fields
# using SELECT ... FOR UPDATE.
sub message ($;$) {
    my ($id, $forupdate) = @_;
    $forupdate = defined($forupdate) ? ' for update' : '';
    if (my $msg = FYR::DB::dbh()->selectrow_hashref("select * from message where id = ?$forupdate", {}, $id)) {
        # Add some convenience fields.
        $msg->{recipient_position} = $mySociety::VotingArea::rep_name{$msg->{recipient_type}};
        $msg->{recipient_position_plural} = $mySociety::VotingArea::rep_name_plural{$msg->{recipient_type}};
        return $msg;
    } else {
        throw FYR::Error("No message '$id'.");
    }
}

# format_mimewords STRING
# Return STRING, formatted for inclusion in an email header.
sub format_mimewords ($) {
    my ($text) = @_;
    my $out = '';
    foreach my $s (split(/(\s+)/, $text)) {
        utf8::encode($s); # turn to string of bytes
        if ($s =~ m#[\x00-\x1f\x80-\xff]#) {
            $s = MIME::Words::encode_mimeword($s, 'Q', 'utf-8');
        }
        utf8::decode($s);
        $out .= $s;
    }
    return $out;
}

# format_email_address NAME ADDRESS
# Return a suitably MIME-encoded version of "NAME <ADDRESS>" suitable for use
# in an email From:/To: header.
sub format_email_address ($$) {
    my ($name, $addr) = @_;
    return sprintf('%s <%s>', format_mimewords($name), $addr);
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

# format_email_body MESSAGE
# Format MESSAGE (a hash of database fields) suitable for sending as an email.
# We honour every carriage return, and wrap lines.
sub format_email_body ($) {
    my ($msg) = @_;

    my $addr = "$msg->{sender_name}\n$msg->{sender_addr}";
    $addr .= "\n\n" . "Phone: $msg->{sender_phone}" if (defined($msg->{sender_phone}));
    $addr .= "\n\n" . "Email: $msg->{sender_email}";
    my $text = format_postal_address($addr);
    $text .= "\n\n" . wrap(EMAIL_COLUMNS, $msg->{message});
    return $text;
}

# make_representative_email MESSAGE
# Return a MIME::Entity object for the passed MESSAGE (hash of db fields),
# suitable for immediate sending to the real recipient.
sub make_representative_email ($) {
    my ($msg) = (@_);
    return MIME::Entity->build(
            From => format_email_address($msg->{sender_name}, $msg->{sender_email}),
            To => format_email_address($msg->{recipient_name}, $msg->{recipient_email}),
            Subject => "Letter from your constituent " . format_mimewords($msg->{sender_name}),
            Type => 'text/plain; charset="utf-8"',
            # See note in make_confirmation_email.
            Encoding => 'quoted-printable',
            Data => format_email_body($msg)
                . "\n\n" . ('x' x EMAIL_COLUMNS) . "\n\n"
                . FYR::EmailTemplate::format(
                    email_template('footer'),
                    email_template_params($msg, representative_url => '') # XXX
                )
        );
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

    return Convert::Base32::encode_base32(pack('na*', $rand, $c->encrypt($string)));
}

# check_token WORD TOKEN
# If TOKEN is valid, return the ID it encodes. Otherwise return undef.
sub check_token ($$) {
    my ($word, $token) = @_;

    $token = lc($token);
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
    return $id;
}

# send_user_email ID DESCRIPTION MAIL
# Send MAIL (should be a MIME::Entity object) to the user who submitted the
# given message ID. DESCRIPTION says what the mail is, for instance
# "confirmation" or "failure report". Logs an explanatory message and returns
# one of the mySociety::Util::EMAIL_... codes.
sub send_user_email ($$$) {
    my ($id, $descr, $mail) = @_;
    my $msg = message($id);
    my $result = mySociety::Util::send_email($mail->stringify(), $mail->head()->get('Sender'), $msg->{sender_email});
    if ($result == mySociety::Util::EMAIL_SUCCESS) {
        logmsg($id, "sent $descr mail to $msg->{sender_email}");
    } elsif ($result == mySociety::Util::EMAIL_SOFT_ERROR) {
        logmsg($id, "temporary failure sending $descr email to $msg->{sender_email}");
    } else {
        logmsg($id, "permanent failure sending $descr email to $msg->{sender_email}");
    }
    return $result;
}

# email_template NAME
# Find the email template with the given NAME. We look for the templates
# directory in ../ and ../../. Nasty.
sub email_template ($) {
    my ($name) = @_;
    $name = "templates/emails/$name";
    foreach (qw(.. ../..)) {
        return "$_/$name" if (-e "$_/$name");
    }
    die "unable to locate email template for '$name'";
}

# email_template_params MESSAGE EXTRA
# Return a reference to a hash of information about the MESSAGE, and additional
# elements from EXTRA.
sub email_template_params ($%) {
    my ($msg, %params) = @_;
    foreach (qw(sender_name recipient_name recipient_position recipient_position_plural)) {
        $params{$_} = $msg->{$_};
    }
    return \%params;
}

# make_confirmation_email MESSAGE [REMINDER]
# Return a MIME::Entity object for the given MESSAGE (reference to hash of db
# fields), suitable for sending to the constituent so that they can confirm
# their email address. If REMINDER is true, this is the second and final mail
# which will be sent.
sub make_confirmation_email ($;$) {
    my ($msg, $reminder) = @_;
    $reminder ||= 0;

    my $token = make_token("confirm", $msg->{id});
    my $confirm_url = mySociety::Config::get('BASE_URL') . '/C/' . $token;

    # Note: (a) don't care about bounces from this mail (they result only from
    # transient failures or abuse; but (b) we can't use a reply to confirm that
    # a fax should be sent because a broken server which sends bounces to the
    # From: address would then automatically confirm any email address.
    my $confirm_sender = sprintf('%sbounce-null@%s',
                                mySociety::Config::get('EMAIL_PREFIX'),
                                mySociety::Config::get('EMAIL_DOMAIN'));

    my $text = FYR::EmailTemplate::format(
                    email_template($reminder ? 'confirm-reminder' : 'confirm'),
                    email_template_params($msg, confirm_url => $confirm_url)
                )
                . "\n\n" . ('x' x EMAIL_COLUMNS) . "\n\n"
                . format_email_body($msg);

    # Add header if site in test mode
    my $reflecting_mails = mySociety::Config::get('FYR_REFLECT_EMAILS');
    if ($reflecting_mails) {
        $text = wrap(EMAIL_COLUMNS, "(NOTE: THIS IS A TEST SITE, THE MESSAGE WILL BE SENT TO YOURSELF NOT YOUR REPRESENTATIVE.)") . "\n\n" . $text;
    } 

    return MIME::Entity->build(
            Sender => $confirm_sender,
            From => format_email_address('WriteToThem', $confirm_sender),
            To => format_email_address($msg->{sender_name}, $msg->{sender_email}),
            Subject => sprintf('Please confirm that you want to send a message to %s', format_mimewords($msg->{recipient_name})),
            Type => 'text/plain; charset="utf-8"',
            # XXX Ideally we'd use the 'binary' encoding (pass through all
            # characters unchanged, under the condition that no NULs appear)
            # here; mail servers now either support it or know to transcode it
            # into something else.  However, there are MUAs so talentless (ahem
            # IMP ahem) as to interpret 'Content-Transfer-Encoding: binary' as
            # meaning that the message-part is a *binary attachment* and so
            # refuse to display it to the user, making it difficult for them to
            # click the confirm link. So we use (ugly) quoted-printable insted.

            Encoding => 'quoted-printable',
            Data => $text
        );
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
    return send_user_email($id, 'confirmation' . ($reminder ? ' reminder' : ''), make_confirmation_email($msg, $reminder));
}

# make_failure_email MESSAGE
# Return a MIME::Entity object for the given MESSAGE (reference to hash of db
# fields), suitable for sending to the constituent to warn them that their
# message could not be delivered.
sub make_failure_email ($) {
    my ($msg) = @_;

    my $failure_sender = sprintf('%sbounce-null@%s',
                                mySociety::Config::get('EMAIL_PREFIX'),
                                mySociety::Config::get('EMAIL_DOMAIN'));

    my $text = FYR::EmailTemplate::format(
                    email_template('failure'),
                    email_template_params($msg)
                )
                . "\n\n" . ('x' x EMAIL_COLUMNS) . "\n\n"
                . format_email_body($msg);

    return MIME::Entity->build(
            Sender => $failure_sender,
            From => format_email_address('WriteToThem', $failure_sender),
            To => format_email_address($msg->{sender_name}, $msg->{sender_email}),
            Subject => sprintf(q#Unfortunately, we couldn't send your message to %s#, format_mimewords($msg->{recipient_name})),
            Type => 'text/plain; charset="utf-8"',
            Encoding => 'quoted-printable',
            Data => $text
        );
}

# send_failure_email ID
# Send a failure report to the sender of message ID.
sub send_failure_email ($) {
    my ($id) = @_;
    my $msg = message($id);
    return send_user_email($id, 'failure report', make_failure_email($msg));
}

# make_questionnaire_email MESSAGE [REMINDER]
# Return a MIME::Entity object for the given MESSAGE, asking the user to fill
# in a questionnaire. If REMINDER is true, this is a reminder mail.
sub make_questionnaire_email ($;$) {
    my ($msg, $reminder) = @_;
    $reminder ||= 0;

    my $token = make_token("questionnaire", $msg->{id});
    my $yes_url = mySociety::Config::get('BASE_URL') . '/Y/' . $token;
    my $no_url = mySociety::Config::get('BASE_URL') . '/N/' . $token;

    my $questionnaire_sender = sprintf('%sbounce-null@%s',
                                mySociety::Config::get('EMAIL_PREFIX'),
                                mySociety::Config::get('EMAIL_DOMAIN'));

    my $text = FYR::EmailTemplate::format(
                    email_template($reminder ? 'questionnaire' : 'questionnaire-reminder'),
                    email_template_params($msg, yes_url => $yes_url, no_url => $no_url)
                );

    return MIME::Entity->build(
            Sender => $questionnaire_sender,
            From => format_email_address('WriteToThem', $questionnaire_sender),
            To => format_email_address($msg->{sender_name}, $msg->{sender_email}),
            Subject => sprintf('Did your %s reply to your letter?', $msg->{recipient_position}),
            Type => 'text/plain; charset="utf-8"',
            # See note in make_confirmation_mail
            Encoding => 'quoted-printable',
            Data => $text
        );
}

# send_questionnaire_email ID [REMINDER]
# Send a (possibly REMINDER) failure report to the sender of message ID.
sub send_questionnaire_email ($;$) {
    my ($id, $reminder) = @_;
    $reminder ||= 0;
    my $msg = message($id);
    die "attempt to send questionnaire for message $id while in state '" . state($id) . "' (should be 'sent')"
        unless (state($id) eq 'sent');
    return send_user_email($id, 'questionnaire' . ($reminder ? ' reminder' : ''), make_questionnaire_email($msg, $reminder));
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
    my $result = mySociety::Util::send_email($mail->stringify(), $sender, $msg->{recipient_email});
    if ($result == mySociety::Util::EMAIL_SUCCESS) {
        logmsg($id, "delivered message by email to $msg->{recipient_email}");
    } elsif ($result == mySociety::Util::EMAIL_SOFT_ERROR) {
        logmsg($id, "temporary failure delivering message by email to $msg->{recipient_email}");
    } else {
        logmsg($id, "permanent failure delivering message by email to $msg->{recipient_email}");
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
        logmsg($id, "delivered message by fax to $msg->{recipient_fax}");
    } elsif ($result == FYR::Fax::FAX_SOFT_ERROR) {
        logmsg($id, "soft failure delivering message by fax to $msg->{recipient_fax}");
    } else {
        logmsg($id, "hard failure delivering message by fax to $msg->{recipient_fax}");
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
changes.

=cut
sub confirm_email ($) {
    my ($token) = @_;
    if (my $id = check_token("confirm", $token)) {
        throw FYR::Error("You've already confirmed this message.", FYR::Error::MESSAGE_ALREADY_CONFIRMED) if (state($id) ne 'pending');
        state($id, 'ready');
        logmsg($id, "sender email address confirmed");
        FYR::DB::dbh()->commit();
        notify_daemon();
        return 1;
    }
    return 0;
}

=item record_questionnaire_answer TOKEN QUESTION RESPONSE

Record a user's response to a questionnaire question. TOKEN is the token sent
them in the questionnaire email; QUESTION must be 0 or 1 and RESPONSE
must be "YES", indicating that they have received a reply, or "NO",
indicating that they have not.

=cut
sub record_questionnaire_answer ($$$) {
    my ($token, $qn, $answer) = @_;
    throw FYR::Error("Bad QUESTION (should be '0' or '1')") if ($qn ne '0' and $qn ne '1');
    throw FYR::Error("Bad RESPONSE (should be 'YES' or 'NO')") if ($answer !~ /^(yes|no)$/i);
    if (my $id = check_token("questionnaire", $token)) {
        FYR::DB::dbh()->do('delete from questionnaire_answer where message_id = ? and question_id = ?', {}, $id, $qn);
        FYR::DB::dbh()->do('insert into questionnaire_answer (message_id, question_id, answer) values (?, ?, ?)', {}, $id, $qn, $answer);
        logmsg($id, "answer of \"$answer\" received for questionnaire qn #$qn");
        FYR::DB::dbh()->commit();
        return 1;
    } else {
        return 0;
    }
}

#
# Implementation of the state machine.
#

use constant HOUR => 3600;
use constant DAY => (24 * HOUR);

# How many confirmation mails may be sent, in total.
use constant NUM_CONFIRM_MESSAGES => 2;

# How many questionnaire mails are sent, in total.
use constant NUM_QUESTIONNAIRE_MESSAGES => 2;

# How long after sending the message do we send the questionnaire email?
use constant QUESTIONNAIRE_DELAY => (14 * DAY);

# How often after that we send a questionnaire reminder.
use constant QUESTIONNAIRE_INTERVAL => (14 * DAY);

# How long after sending the message do we retain its text in case the
# recipient wants to forward it?
use constant MESSAGE_RETAIN_TIME => (21 * DAY);

# How long do we retain log and other information from a failed message for
# operator inspection?
use constant FAILED_RETAIN_TIME => MESSAGE_RETAIN_TIME;

# Total number of times we attempt delivery by fax.
use constant FAX_DELIVERY_ATTEMPTS => 4;

# Interval between fax sending attempts.
use constant FAX_DELIVERY_INTERVAL => 600;

use constant FAX_DELIVERY_BACKOFF => 3;

# Timeouts in the state machine:
my %state_timeout = (
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
        error           => DAY * 7
    );

# Where we time out to.
my %state_timeout_state = qw(
        pending         failed_closed
        ready           error
        bounce_wait     sent
        sent            finished
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
        error           => 43200,

        # How often we grind over failed messages to scrub old ones of personal
        # information.
        failed          => DAY
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
            } elsif ($result == mySociety::Util::EMAIL_SOFT_ERROR) {
                state($id, 'new');
            } else {
                logmsg($id, "abandoning message after failure to send confirmation email");
                state($id, 'failed');
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
                    # Shouldn't happen in this state.
                    logmsg($id, "abandoning message after failure to send confirmation email");
                    state($id, 'failed');
                } # otherwise no action; we'll get called again whenever the
                  # queue is next run.
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

                # Abandon faxes after a few failures.
                if ($msg->{numactions} > FAX_DELIVERY_ATTEMPTS) {
                    logmsg($id, "abandoning message after $msg->{numactions} failures to send by fax");
                    state($id, 'error');
                    return;
                }

                # Don't retry sending until a reasonable backoff time has
                # passed.
                my $howlong = time() - $msg->{laststatechange};
                return if ($msg->{numactions} > 0 && $howlong < FAX_DELIVERY_INTERVAL * (FAX_DELIVERY_BACKOFF ** $msg->{numactions}));
                
                my $result = deliver_fax($msg);
                if ($result == FYR::Fax::FAX_SUCCESS) {
                    FYR::DB::dbh()->do('update message set dispatched = ? where id = ?', {}, time(), $id);
                    state($id, 'sent');
                } elsif ($result == FYR::Fax::FAX_SOFT_ERROR) {
                    # Don't do anything here: this is a temporary, local
                    # failure.
                } else {
                    # Some kind of remote failure. Bump the counter so that we
                    # can abandon delivery after too many such failures.
                    logmsg($id, "abandoning message after failure to send to representative");
                    state($id, 'ready');    # bump counter
                }
            } elsif ($email && defined($msg->{recipient_email})) {
                my $result = deliver_email($msg);
                if ($result == mySociety::Util::EMAIL_SUCCESS) {
                    FYR::DB::dbh()->do('update message set dispatched = ? where id = ?', {}, time(), $id);
                    state($id, 'bounce_wait');
                } elsif ($result == mySociety::Util::EMAIL_SOFT_ERROR) {
                    state($id, 'ready');    # bump timer
                } else {
                    logmsg($id, "abandoning message after failure to send to representative");
                    state($id, 'error');
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
            if (0 == scalar(FYR::DB::dbh()->selectrow_array('select count(*) from questionnaire_answer where message_id = ?', {}, $id))) {
                if (actions($id) == 0) {
                    $dosend = 1 if ($msg->{dispatched} < (time() - QUESTIONNAIRE_DELAY));
                } elsif (actions($id) < NUM_QUESTIONNAIRE_MESSAGES) { 
                    $dosend = $reminder = 1 if ($msg->{lastaction} < (time() - QUESTIONNAIRE_INTERVAL));
                }
            }

            if ($dosend) {
                my $result = send_questionnaire_email($id, $reminder);
                if ($result == mySociety::Util::EMAIL_SUCCESS) {
                    state($id, 'sent');
                } # should trap hard error case
            }
         
            # If we've had the message for long enough, then scrub personal
            # information and mark it finished.
            if ($msg->{dispatched} < (time() - MESSAGE_RETAIN_TIME)) {
                state($id, 'finished');
            }
        },

        error => sub ($$$) {
            my ($email, $fax, $id) = @_;
            return unless ($email);

            # Send failure report to sender.
            my $result = send_failure_email($id);
            if ($result == mySociety::Util::EMAIL_SOFT_ERROR) {
                state($id, 'error');    # bump timer for redelivery
            } else {
                # Give up -- it's all really bad.
                logmsg($id, "Unable to send failure report to user") if ($result == mySociety::Util::EMAIL_HARD_ERROR);
                state($id, 'failed');
            }
        },

#        failed => sub ($$$) {
#            my ($email, $fax, $id) = @_;
#            # do nothing
#        },

        failed_closed => sub ($$$) {
            my ($email, $fax, $id) = @_;
            my $msg = message($id);
            if ($msg->{sender_name} ne '' and $msg->{laststatechange} < (time() - FAILED_RETAIN_TIME)) {
                scrubmessage($id);
                state($id, 'failed_closed');    # bump timer
            }
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
    my $stmt = FYR::DB::dbh()->prepare(
        'select id, state from message where '
        . join(' or ',
            map { sprintf(q#(state = '%s' and laststatechange < %d)#, $_, time() - $state_timeout{$_}) }
                keys %state_timeout)
        . ' for update');
    $stmt->execute();
    while (my ($id, $state) = $stmt->fetchrow_array()) {
        state($id, $state_timeout_state{$state});
    }
    FYR::DB::dbh()->commit();

    # Actions. These are slow (potentially) so lock row-by-row. Process
    # messages in a random order, so that bad ones don't block everything.
    $stmt = FYR::DB::dbh()->prepare(
        'select id, state from message where ('
            . join(' or ', map { sprintf(q#state = '%s'#, $_); } keys %state_action)
        . ') and (lastaction is null or '
            . join(' or ',
                map { sprintf(q#(state = '%s' and lastaction < %d)#, $_, time() - $state_action_interval{$_}) }
                    keys %state_action_interval)
        . q#) and (state <> 'ready' or not frozen) order by random()#);
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
                    or $msg->{lastaction} < time() - $state_action_interval{$state})) {
                # Check for ready+frozen again.
                if (!$msg->{frozen} or $msg->{state} ne 'ready') {
                    &{$state_action{$state}}($email, $fax, $id);
                }
            }
        } catch FYR::Error with {
            my $E = shift;
            logmsg($id, "error while processing message: $E");
        } finally {
            FYR::DB::dbh()->commit();
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

#
# Administrative interface
#

=item admin_recent_events COUNT

Returns an array of hashes of information about the most recent COUNT queue
events.

=cut
sub admin_recent_events ($) {
    my ($count) = @_;
    my $sth = FYR::DB::dbh()->prepare('select message_id, whenlogged, state, message from message_log order by order_id desc limit ?');
    $sth->execute(int($count));
    my @ret;
    while (my $hash_ref = $sth->fetchrow_hashref()) {
        push @ret, $hash_ref;
    }
    return \@ret;
}

=item admin_message_events ID

Returns an array of hashes of information about events for given message ID.

=cut
sub admin_message_events ($) {
    my ($id) = @_;
    my $sth = FYR::DB::dbh()->prepare('select message_id, whenlogged, state, message from message_log where message_id = ? order by order_id');
    $sth->execute($id);
    my @ret;
    while (my $hash_ref = $sth->fetchrow_hashref()) {
        push @ret, $hash_ref;
    }
    return \@ret;
}


=item admin_get_queue FILTER PARAMS

Returns an array of hashes of information about each message on the queue.
FILTER should be: 
    0 to return all messages; 
    1 to return only information about messages which may need operator attention; 
    2 to return messages which recently changed state; 
    3 to return messages which were created recently; 
    4 to return messages similar to 'msgid' from PARAMS;
    5 to return messages matching search 'query' (from PARAMS), where
         sender and recipient details are searched, as well as
         matching confirmation tokens.
PARAMS is a hash of parameters to the filter type.

=cut
sub admin_get_queue ($$) {
    my ($filter, $params) = @_;
    my $where = "order by created desc";
    my $msg;
    my @params;
    if (int($filter) == 1) {
        $where = q#
            where (state = 'bounce_confirm'
                    or state = 'failed'
                    or state = 'error'
                    or (state = 'ready' and numactions > 0)
                or frozen = 't')
            order by created desc#;
        # XXX "frozen = 't'" because if you just say "frozen", PG won't use an
        # index to do the scan. q.v. comments on the end of,
        #   http://www.postgresql.org/docs/7.4/interactive/indexes.html
    } elsif (int($filter) == 2) {
        $where = "order by laststatechange desc limit 100";
    } elsif (int($filter) == 3) {
        $where = "order by created desc limit 100";
    } elsif (int($filter) == 4) {
        my $sth2 = FYR::DB::dbh()->prepare("
                select *, length(message) as message_length from message where id = ?
            ");
        $sth2->execute($params->{msgid});
        $msg = $sth2->fetchrow_hashref();
        my @similar = FYR::AbuseChecks::get_similar_messages($msg);
        @params = map { $_->[0] } @similar;
        push @params, $params->{msgid};
        $where = "where id in (" . join(",", map { '?' } @params) .  ")";
    } elsif (int($filter) == 5) {
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
                my $term = $_;
                for (1 .. 11) {
                    push @params, '%'.$term.'%';
                }
                for (1 .. 2) {
                    push @params, $term;
                }
                q#
                         ((sender_name ilike ?) or
                          (sender_email ilike ?) or
                          (sender_addr ilike ?) or
                          (sender_phone ilike ?) or
                          (sender_postcode ilike ?) or
                          (sender_ipaddr ilike ?) or
                          (sender_referrer ilike ?) or
                          (recipient_name ilike ?) or
                          (recipient_email ilike ?) or
                          (recipient_fax ilike ?) or
                          (message ilike ?) or

                          (recipient_type = ?) or
                          (state = ?))#;
            } @terms);
            $where .= " order by created desc";
        }
    }
    my $sth = FYR::DB::dbh()->prepare("
            select *, length(message) as message_length from message $where
        ");
    $sth->execute(@params);
    my @ret;
    while (my $hash_ref = $sth->fetchrow_hashref()) {
        if (int($filter) == 4) {

            my $from = $msg->{message};
            my $to = $hash_ref->{message};
            $from = merge_spaces($from);
            $to = merge_spaces($to);
            $from =~ s/\[\[|\]\]//g; # remove the marker we use for diff
            $to =~ s/\[\[|\]\]//g;
            my $ixes = String::Ediff::ediff($from, $to);
            my @ix = split(" ", $ixes);
            my $s1at = 0;
            my $s2at = 0;
            my $diff; 
            my $add_diff = sub {
                my ($class, $string) = @_;
                return "" if (length($string) == 0);
                return "[[span class=$class]]" . $string . "[[/span]]";
            };
            for (my $i = 0; $i < scalar(@ix); $i+=8) {
                $diff .= &$add_diff("difffrom", substr($from, $s1at, $ix[$i+0]-$s1at));
                $s1at = $ix[$i+1];
                $diff .= &$add_diff("diffto", substr($to, $s2at, $ix[$i+4]-$s2at));
                $s2at = $ix[$i+5];
                my $commonpart = substr($from, $ix[$i+0], $ix[$i+1] - $ix[$i+0]);
                if (length($commonpart) < 200) {
                    $diff .= $commonpart;
                } else {
                    $diff .= substr($commonpart, 0, 95) . &$add_diff("diffsnip", " [ ...snipped... ] ") . substr($commonpart, -95);
                }
            }
            $diff .= &$add_diff("1", substr($from, $s1at));
            $diff .= &$add_diff("2", substr($to, $s2at));
            if ($msg->{id} eq $hash_ref->{id}) {
                $diff = " This is the message being compared against. ";
                $diff .= &$add_diff("difffrom", "Text that appears only in this message, ");
                $diff .= &$add_diff("diffto", "or only in the other message.");
            }
            $hash_ref->{diff} = $diff;
        }
        $hash_ref->{message} = undef;
        push @ret, $hash_ref;
    }
    return \@ret;
}

=item admin_get_message ID

Returns a hash of information about message with id ID.

=cut
sub admin_get_message ($) {
    my ($id) = @_;

    my $sth = FYR::DB::dbh()->prepare("select 
        *, length(message) as message_length from message where id =
        ?");
    $sth->execute($id);
    my $hash_ref = $sth->fetchrow_hashref();

    my $bounces = FYR::DB::dbh()->selectcol_arrayref("select 
        bouncetext from message_bounce where message_id = ?", {}, $id);
    $hash_ref->{bounces} = $bounces;

    $sth = FYR::DB::dbh()->prepare("select question_id, answer from
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

    my $rows = FYR::DB::dbh()->selectall_arrayref('select recipient_type, messagecount from message_count_recipient_type', {});
    foreach (@$rows) {
        my ($type, $count) = @$_; 
        $ret{"type $type"} = $count;
    }

    $rows = FYR::DB::dbh()->selectall_arrayref('select state, messagecount from message_count_state', {});
    foreach (@$rows) {
        my ($type, $count) = @$_; 
        $ret{"state $type"} = $count;
    }

    $ret{message_count} = FYR::DB::dbh()->selectrow_array('select sum(messagecount) from message_count_state', {});
    $ret{created_1}     = FYR::DB::dbh()->selectrow_array('select count(*) from message where created > ?', {}, time() - HOUR); 
    $ret{created_24}    = FYR::DB::dbh()->selectrow_array('select count(*) from message where created > ?', {}, time() - DAY); 

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
            order by c desc', {}, time() - $secs);

    return $result;
}


=item admin_freeze_message ID USER

Freezes the message with the given ID, so it won't be actually sent to
the representative until thawed. USER is the administrator's name.

=cut
sub admin_freeze_message ($$) {
    my ($id, $user) = @_;
    FYR::DB::dbh()->do("update message set frozen = 't' where id = ?", {}, $id);
    logmsg($id, "$user froze message");
    FYR::DB::dbh()->commit();
    return 0;
}

=item admin_thaw_message ID USER

Thaws the message with the given ID, so it will be sent to the representative.
USER is the administrator's name.

=cut
sub admin_thaw_message ($$) {
    my ($id, $user) = @_;
    FYR::DB::dbh()->do("update message set frozen = 'f' where id = ?", {}, $id);
    logmsg($id, "$user thawed message");
    FYR::DB::dbh()->commit();
    return 0;
}

=item admin_set_message_to_error ID USER

Moves message with given ID to error state, so aborting any further action, and
sending a delivery failure notice to the constituent. USER is the
administrator's name.

=cut
sub admin_set_message_to_error ($$) {
    my ($id, $user) = @_;
    my ($curr_state, $curr_frozen) = FYR::DB::dbh()->selectrow_array('select state, frozen from message where id = ? for update', {}, $id);
    if ($curr_state ne 'error') {
        state($id, 'error');
    }
    logmsg($id, "$user put message in state 'error'");
    FYR::DB::dbh()->commit();
    return 0;
}

=item admin_set_message_to_failed ID USER

Moves message with given ID to failed state, aborting any further action. The
constituent is not told. USER is the administrator's name.

=cut
sub admin_set_message_to_failed ($$) {
    my ($id, $user) = @_;
    my ($curr_state, $curr_frozen) = FYR::DB::dbh()->selectrow_array('select state, frozen from message where id = ? for update', {}, $id);
    if ($curr_state ne 'failed') {
        state($id, 'failed');
    }
    logmsg($id, "$user put message in state 'failed'");
    FYR::DB::dbh()->commit();
    return 0;
}

=item admin_set_message_to_failed_closed ID USER

Moves message from failed to failed_closed state to indicate that it has been
dealt with by an administrator. USER is the administrator's name.

=cut
sub admin_set_message_to_failed_closed ($$) {
    my ($id, $user) = @_;
    my ($curr_state, $curr_frozen) = FYR::DB::dbh()->selectrow_array('select state, frozen from message where id = ? for update', {}, $id);
    if ($curr_state ne 'failed_closed') {
        state($id, 'failed_closed');
    }
    FYR::DB::dbh()->do("update message set frozen = 'f' where id = ?", {}, $id);
    logmsg($id, "$user put message in state 'failed_closed'");
    FYR::DB::dbh()->commit();
    return 0;
}

=item admin_set_message_to_bounce_wait ID USER

Move message ID from bounce_confirm to bounce_wait. USER is the name of the
administrator making the change.

=cut

sub admin_set_message_to_bounce_wait ($$) {
    my ($id, $user) = @_;
    state($id, 'bounce_wait');
    logmsg($id, "$user put message in state 'bounce_wait'");
    FYR::DB::dbh()->commit();
    return 0;
}

=item admin_add_note_to_message ID USER NOTE

Add text in NOTE to the message log for the message ID; USER is the name of the
administrator leaving the note.

=cut
sub admin_add_note_to_message ($$$) {
    my ($id, $user, $note) = @_;
    logmsg($id, "$user added note: $note");
    return 0;
}

=item admin_set_message_to_ready ID USER

Move message ID (from, probably, pending) to ready. USER is the name of the
administrator making the change.

=cut

sub admin_set_message_to_ready ($$) {
    my ($id, $user) = @_;
    state($id, 'ready');
    logmsg($id, "$user put message in state 'ready'");
    FYR::DB::dbh()->commit();
    notify_daemon();
    return 0;
}


1;
