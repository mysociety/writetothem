#!/usr/bin/perl
#
# FYR/Queue.pm:
# Fax/email queue management for FYR.
#
# Copyright (c) 2004 UK Citizens Online Democracy. All rights reserved.
# Email: chris@mysociety.org; WWW: http://www.mysociety.org/
#
# $Id: Queue.pm,v 1.19 2004-11-18 10:59:16 chris Exp $
#

package FYR::Queue;

use strict;

use Convert::Base32;
use Crypt::CBC;
use Error qw(:try);
use Fcntl;
use HTML::Entities;
use IO::Socket;
use Mail::RFC822::Address;
use MIME::Entity;
use MIME::Words;
use Text::Wrap (); # don't pollute our namespace
use utf8;

use mySociety::Config;
use mySociety::DaDem;
use mySociety::Util;
use mySociety::VotingArea;
use FYR;

use Data::Dumper;

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

=item write ID SENDER RECIPIENT TEXT

Write details of a message for sending. ID is the identity of the message,
SENDER is a reference to hash containing details of the sender including
elements: name, the sender's full name; email, their email address; address,
their full postal address; postcode, their post code; and optionally phone,
their phone number. RECIPIENT is the DaDem ID number of the recipient of the
message; and TEXT is the text of the message, with line breaks. Returns true on
success, or an error code on failure.

This function is called remotely and commits its changes.

=cut
sub write ($$$$) {
    my ($id, $sender, $recipient_id, $text) = @_;

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

=pod
        # Decide how to send the message.
        $recipient->{fax} ||= undef;
        $recipient->{email} ||= undef;
        if (defined($recipient->{fax}) and defined($recipient->{email})) {
            if ($recipient->{method} == 0) {
                if (rand(1) < 0.5) {
                    $recipient->{fax} = undef;
                } else {
                    $recipient->{email} = undef;
                }
            } elsif ($recipient->{method} == 1) {
                $recipient->{email} = undef;
            } else {
                $recipient->{fax} = undef;
            }
        }
=cut
        # bodge things so that mails go to sender.
        $recipient->{email} = $sender->{email};
        $recipient->{fax} = undef;

        # XXX should also check that the text bits are valid UTF-8.

        # DBD::Pg will have begun a new transaction immediately we committed the
        # last one. That's fine, but it means that any timestamps we use are out
        # of date. So begin a new one.
        FYR::DB::dbh()->do('begin work');
        
        # Queue the message.
        FYR::DB::dbh()->do(q#
            insert into message (
                id,
                sender_name, sender_email, sender_addr, sender_phone, sender_postcode,
                recipient_id, recipient_name, recipient_type, recipient_email, recipient_fax,
                message,
                state,
                created, laststatechange, numactions, dispatched
            ) values (
                ?,
                ?, ?, ?, ?, ?,
                ?, ?, ?, ?, ?, ?,
                ?,
                'new',
                ?, ?, 0, null
            )#, {},
            $id,
            (map { $sender->{$_} || undef } qw(name email address phone postcode)),
            $recipient_id, (map { $recipient->{$_} || undef } qw(name type email fax)),
            $text,
            time(), time());

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

        FYR::DB::dbh()->commit();
    } otherwise {
        my $E = shift;
        warn "rolling back transaction after error: " . $E->text() . "\n";
        FYR::DB::dbh()->rollback();
        throw $E;
    };

    return 1;
}

# logmsg ID DIAGNOSTIC
# Log a DIAGNOSTIC about the message with the given ID.
# XXX should have a flag for "exceptional" to warn administrators.
sub logmsg ($$) {
    my ($id, $msg) = @_;
    FYR::DB::dbh()->do('insert into message_log (message_id, whenlogged, state, message) values (?, ?, ?)',
        {},
        $id,
        strftime('%Y-%m-%d %H:%M:%S', localtime(time())),
        state($id),
        $msg);
}

# %allowed_transitions
# Transitions we're allowed to make in the state machine.
my %allowed_transitions = (
        new =>              [qw(pending failed)],
        pending =>          [qw(ready failed)],
        ready =>            [qw(error bounce_wait sent)],
        bounce_wait =>      [qw(bounce_confirm sent)],
        bounce_confirm =>   [qw(bounce_wait error)],
        error =>            [qw(failed)],
        sent =>             [qw(finished)],
        failed =>           []
    );

# turn this into hash-of-hashes
foreach (keys %allowed_transitions) {
    my $x = { map { $_ => 1 } @{$allowed_transitions{$_}} };
    $allowed_transitions{$_} = $x;
}

=item state ID [STATE]

Get/change the state of the message with the given ID to STATE. If STATE is the
same as the current state, just update the lastaction and numactions fields; if
it isn't, then set the lastaction field to null, the numactions field to 0, and
update the laststatechange field. If a message is moved to the "failed" or
"finished" states, then it is stripped of personal identifying information.

=cut
sub state ($$) {
    my ($id, $state) = @_;
    if (defined($state)) {
        my $curr_state = FYR::DB::dbh()->selectrow_array('select state from message where id = ? for update', {}, $id);
        die "Bad state '$state'" unless (exists($allowed_transitions{$state}));
        die "Can't go from state '$curr_state' to '$state'" unless ($state eq $curr_state or $allowed_transitions{$curr_state}->{$state});
        if ($state ne $curr_state) {
            FYR::DB::dbh()->do('update message set lastaction = null, numactions = 0, laststatechange = ?, state = ? where id = ?', {}, time(), $state, $id);
            logmsg($id, "changed state to $state");

            # If the new state is either finished or failed, we also remove any
            # personal information from the message. Once this is done only the
            # message ID, recipient ID, and state information remain.
            if ($state eq 'failed' or $state eq 'finished') {
                FYR::DB::dbh()->do(q#
                            update message set
                                    sender_name = '', sender_email = '',
                                    sender_addr = '', sender_phone = null,
                                    sender_postcode = '', recipient_name = '',
                                    recipient_type = '',
                                    recipient_email = '', recipient_fax = null,
                                    message = ''
                                where id = ?#, $id);
                logmsg($id, 'Scrubbed message of all personal data');
            }
        } else {
            FYR::DB::dbh()->do('update message set lastaction = ?, numactions = numactions + 1 where id = ?', {}, time(), $id);
        }
        return $curr_state;
    } else {
        return FYR::DB::dbh()->selectrow_array('select state from message where id = ?', {}, $id);
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
        $msg->{recipient_position} = $mySociety::VotingArea::rep_name{$msg->{type}};
        $msg->{recipient_position_plural} = $mySociety::VotingArea::rep_name_plural{$msg->{type}};
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
    my $text = format_postal_address($msg->{sender_addr});
    $text .= "\n\n" . format_postal_address("Phone: $msg->{sender_phone}") if (exists($msg->{sender_phone}));
    $text .= "\n\n" . format_postal_address("Email: $msg->{sender_email}");
    $text .= "\n\n" . wrap(EMAIL_COLUMNS, $msg->{message});
    return $text;
}

# make_representative_email MESSAGE
# Return a MIME::Entity object for the passed MESSAGE (hash of db fields),
# suitable for immediate sending to the real recipient.
sub make_representative_email ($) {
    my ($msg) = (@_);
    return MIME::Entity->build(
            Sender => $msg->{sender_email},
            From => format_email_address($msg->{sender_name}, $msg->{sender_email}),
            To => format_email_address($msg->{recipient_name}, $msg->{recipient_email}),
            Subject => "Letter from your constituent " . format_mimewords($msg->{sender_name}),
            Type => 'text/plain; charset="utf-8"',
            Data => format_email_body($msg)
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
sub email_template ($$) {
    my ($name) = @_;
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
                    email_template($reminder ? 'confirm' : 'confirm-reminder'),
                    email_template_params($msg, confirm_url => $confirm_url)
                )
                . "\n\n" . ('x' x EMAIL_COLUMNS) . "\n\n"
                . format_email_body($msg);

    return MIME::Entity->build(
            Sender => $confirm_sender,
            From => format_email_address('FaxYourRepresentative', $confirm_sender),
            To => format_email_address($msg->{sender_name}, $msg->{sender_email}),
            Subject => sprintf('Please confirm that you want to send a message to %s', format_mimewords($msg->{recipient_name})),
            Data => $text
        );
}

# send_confirmation_email ID [REMINDER]
# Send the necessary confirmation email for message ID. If REMINDER is true,
# this is a reminder mail.
sub send_confirmation_email ($;$) {
    my ($id, $reminder) = @_;
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
            From => format_email_address('FaxYourRepresentative', $failure_sender),
            To => format_email_address($msg->{sender_name}, $msg->{sender_email}),
            Subject => sprintf(q#Unfortunately, we couldn't send your message to %s#, format_mimewords($msg->{recipient_name})),
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
            From => format_email_address('FaxYourRepresentative', $questionnaire_sender),
            To => format_email_address($msg->{sender_name}, $msg->{sender_email}),
            Subject => sprintf('Did your %s reply to your letter?', $msg->{recipient_position}),
            Data => $text
        );
}

# send_questionnaire_email ID [REMINDER]
# Send a (possibly REMINDER) failure report to the sender of message ID.
sub send_failure_email ($;$) {
    my ($id, $reminder) = @_;
    $reminder ||= 0;
    my $msg = message($id);
    return send_user_email($id, 'questionnaire' . ($reminder ? ' reminder' : ''), make_questionnaire_email($msg, $reminder));
}


# deliver_email MESSAGE
# Attempt to deliver the MESSAGE by email.
sub deliver_email ($) {
    my ($msg) = @_;
    my $id = $msg->{id};
    my $mail = make_representative_email($msg);
    my $sender = sprintf('%s-%s@%s',
                            mySociety::Config::get('EMAIL_PREFIX'),
                            make_token("bounce", $id),
                            mySociety::Config::get('EMAIL_DOMAIN'));
    my $result = mySociety::Util::send_email($mail->stringify(), $mail->head()->get('Sender'), $msg->{recipient_email});
    if ($result == mySociety::Util::EMAIL_SUCCESS) {
        logmsg($id, "delivered message by email to $msg->{recipient_email}");
    } elsif ($result == mySociety::Util::EMAIL_SOFT_ERROR) {
        logmsg($id, "temporary failure delivering message by email to $msg->{sender_email}");
    } else {
        logmsg($id, "permanent failure delivering message by email to $msg->{sender_email}");
    }
    return $result;
}

# deliver_fax MESSAGE
# Attempt to deliver the MESSAGE by fax.
sub deliver_fax ($) {
    my ($msg) = @_;
    my $id = $msg->{id};
    logmsg($id, 'Not yet able to send faxes');
    return 0;
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
        # DBD::Pg will have begun a new transaction immediately we committed the
        # last one. That's fine, but it means that any timestamps we use are out
        # of date. So begin a new one.
        FYR::DB::dbh()->do('begin work');

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
them in the questionnaire email; QUESTION must be 0 and RESPONSE must be "YES",
indicating that they have received a reply, or "NO", indicating that they have
not.

=cut
sub record_questionnaire_answer ($$$) {
    my ($token, $qn, $answer) = @_;
    throw FYR::Error("Bad QUESTION (should be '0')") if ($qn ne '0');
    throw FYR::Error("Bad RESPONSE (should be 'YES' or 'NO')") if ($response !~ /^(yes|no)$/i);
    if (my $id = check_token("questionnaire", $token)) {
        FYR::DB::dbh()->do('begin work');
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

use constant DAY => 86400;

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
        pending         failed
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

        # How often we attempt delivery in the face of soft failures.
        ready           => 1800,

        # How often we grind over sent messages to send questionnaires or
        # expire messages into the "finished" state.
        sent            => DAY,

        # How often we attempt delivery of an error report in the face of soft
        # failures.
        error           => 43200
    );


# What we do to messages in various states. When these are called, the row
# representing the message will be locked; any transaction will be committed
# after they return.
my %state_action = (
        new => sub ($) {
            my ($id) = @_;
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

        pending => sub ($) {
            my ($id) = @_;
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

        ready => sub ($) {
            my ($id) = @_;
            # Send email or fax to recipient.
            my $msg = message($id);
            if (defined($msg->{recipient_fax})) {
                my $result = deliver_fax($msg);
                if (!$result) {
                    logmsg($id, "abandoning message after failure to send to representative");
                    state($id, 'error');
                } else {
                    FYR::DB::dbh()->do('update message set dispatched = ? where id = ?', {}, time(), $id);
                    state($id, 'sent');
                }
            } else {
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

        sent => sub ($) {
            my ($id) = @_;
            my $msg = message($id);

            # If we haven't got a questionnaire response, and it's been long
            # enough since the message was sent or since the last questionnaire
            # email was sent, then send another one.
            my ($dosend, $reminder) = (0, 0); 
            if (0 == scalar(FYR::DB::dbh()->selectrow_array('select count(*) from questionnaire_answer where message_id = ?', {}, $id))) {
                if (actions($id) == 0 && $msg->{dispatched} < (time() - QUESTIONNAIRE_DELAY)) {
                    $dosend = 1;
                } elsif (actions($id) < NUM_QUESTIONNAIRE_MESSAGES && $msg->{lastaction} < (time() - QUESTIONNAIRE_INTERVAL)) {
                    $dosend = $reminder = 1;
                }
            }

            if ($dosend) {
                my $result = send_questionnaire_email($id, $reminder);
                if ($result == mySociety::Util::EMAIL_SUCCESS) {
                    state($id, 'sent');
                } # should trap hard error case
            }
         
            # If we've had the message for long enough, then 
            if ($msg->{dispatched} < (time() - MESSAGE_RETAIN_TIME)) {
                state($id, 'finished');
            }
        },

        error => sub ($) {
            my ($id) = @_;
            # Send failure report to sender.
            my $result = send_failure_email($id);
            if ($result == mySociety::Util::EMAIL_SOFT_ERROR) {
                state($id, 'error');    # bump timer for redelivery
            } else {
                # Give up -- it's all really bad.
                logmsg($id, "Unable to send failure report to user") if ($result == mySociety::Util::EMAIL_HARD_ERROR);
                state($id, 'failed');
            }
        }
    );

# process_queue
# Drive the state machine round.
sub process_queue () {

    # DBD::Pg will have begun a new transaction immediately we committed the
    # last one. That's fine, but it means that any timestamps we use are out
    # of date. So begin a new one.
    FYR::DB::dbh()->do('begin work');

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
        . ') order by random()');
    $stmt->execute();
    while (my ($id, $state) = $stmt->fetchrow_array()) {
        # Now we need to lock the row. Once it's locked, check that the message
        # still meets the criteria for sending. Do things this way round so
        # that we can have several queue-running daemons operating
        # simultaneously.
        my $msg = message($id);
        next if (!exists($state_action{$msg->{state}})
                    or (defined($msg->{lastaction})
                        and $msg->{lastaction} > time() - $state_action_interval{$msg->{state}}));
        try {
            &{$state_action{$state}}($id);
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

Returns an array of hashes of information about recent queue events.
Specify the number of events you'd like.

=cut
sub admin_recent_events ($) {
    my ($count) = @_;
    my $sth = FYR::DB::dbh()->prepare('select * from message_log order by order_id desc limit '. int($count));
    $sth->execute();
    my @ret;
    while (my $hash_ref = $sth->fetchrow_hashref()) {
        push @ret, $hash_ref;
    }
    return \@ret;
}

=item admin_get_queue

Returns an array of hashes of information about each message on the queue.

=cut
sub admin_get_queue () {
    my () = @_;
    my $sth = FYR::DB::dbh()->prepare('select * from message order by created desc');
    $sth->execute();
    my @ret;
    while (my $hash_ref = $sth->fetchrow_hashref()) {
        push @ret, $hash_ref;
    }
    return \@ret;
}
1;
