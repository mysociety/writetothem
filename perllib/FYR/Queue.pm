#!/usr/bin/perl
#
# FYR/Queue.pm:
# Fax/email queue management for FYR.
#
# Copyright (c) 2004 UK Citizens Online Democracy. All rights reserved.
# Email: chris@mysociety.org; WWW: http://www.mysociety.org/
#
# $Id: Queue.pm,v 1.10 2004-11-11 13:46:59 chris Exp $
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

This function commits its changes.

=cut
sub write ($$$$$) {
    my ($x, $id, $sender, $recipient_id, $text) = @_;

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

        # XXX should also check that the text bits are valid UTF-8.
        
        # Queue the message.
        FYR::DB::dbh()->do(q#
            insert into message (
                id,
                sender_name, sender_email, sender_addr, sender_phone, sender_postcode,
                recipient_id, recipient_name, recipient_position, recipient_email, recipient_fax,
                message,
                state,
                created, laststatechange, numactions
            ) values (
                ?,
                ?, ?, ?, ?, ?,
                ?, ?, ?, ?, ?,
                ?,
                'new',
                ?, ?, 0
            )#, {},
            $id,
            (map { $sender->{$_} || undef } qw(name email address phone postcode)),
            $recipient_id, (map { $recipient->{$_} || undef } qw(name position email fax)),
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
sub logmsg ($$) {
    my ($id, $msg) = @_;
    FYR::DB::dbh()->do('insert into message_log (message_id, state, message) values (?, ?, ?)', {}, $id, state($id), $msg);
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
            # personal information from the message.
            if ($state eq 'failed' or $state eq 'finished') {
                FYR::DB::dbh()->do(q#
                            update message set
                                    sender_name = '', sender_email = '',
                                    sender_addr = '', sender_phone = null,
                                    sender_postcode = '', recipient_name = '',
                                    recipient_position = '',
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
my %token_wormap = qw(
        confirm C
        bounce  B
    );

# make_token WORD ID
# Returns a token for WORD (e.g. "bounce", "confirm", etc.) and the given
# message ID. The token will contain only letters and digits between 2 and 7
# inclusive, so is suitable for transmission through case-insensitive channels
# (e.g. as part of an email address).
sub make_token ($$) {
    my ($word, $id) = @_;

    $word = $token_wormap{$word} if (exists($token_wormap{$word}));

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
    
    $word = $token_wormap{$word} if (exists($token_wormap{$word}));

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

# confirm_token ID
# Return a suitable token for use in confirming a user's email address.
sub confirm_token ($) {
    return make_token("confirm", $_[0]);
}

# verify_confirm_token TOKEN
# Attempt to verify the confirm TOKEN. Returns a message ID on success or undef
# on failure.
sub verify_confirm_token ($) {
    return check_token("confirm", $_[0]);
}

# bounce_token ID
# Return a suitable token for use in processing a bounce message.
sub bounce_token ($) {
    return make_token("bounce", $_[0]);
}

# confirm_bounce_token TOKEN
# Attempt to verify the bounce TOKEN. Returns a message ID on success or undef
# on failure.
sub verify_bounce_token ($) {
    return check_token("bounce", $_[0]);
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

# make_confirmation_email MESSAGE [REMINDER]
# Return a MIME::Entity object for the given MESSAGE (reference to hash of db
# fields), suitable for sending to the constituent so that they can confirm
# their email address. If REMINDER is true, this is the second and final mail
# which will be sent.
sub make_confirmation_email ($;$) {
    my ($msg, $reminder) = @_;

    $reminder ||= 0;

    my $token = confirm_token($msg->{id});
    my $confirm_url = mySociety::Config::get('BASE_URL') . '/' . $token;

    # Note: (a) don't care about bounces from this mail (they result only from
    # transient failures or abuse; but (b) we can't use a reply to confirm that
    # a fax should be sent because a broken server which sends bounces to the
    # From: address would then automatically confirm any email address.
    my $confirm_sender = sprintf('%sbounce-null@%s',
                                mySociety::Config::get('EMAIL_PREFIX'),
                                mySociety::Config::get('EMAIL_DOMAIN'));

    if ($reminder) {
        $reminder = qq#(This is a reminder email. It seems that the first one didn't reach you.)\n\n#;
    } else {
        $reminder = '';
    }

    # Don't insert linebreaks in the below except for paragraph marks-- let
    # Text::Wrap do the rest.
    my $text = wrap(EMAIL_COLUMNS, 
        <<EOF);
$reminder
Please click on the link below to confirm that you wish FaxYourRepresentative.com to send the message copied at the bottom of this email to $msg->{recipient_name}, your $msg->{recipient_position}:

    $confirm_url

If your email program does not let you click on this link, just copy and paste it into your web browser and hit return.

We'll send you a confirmation email once your message has been dispatched successfully. If for some reason we can't send your message, we will email you with details of how to contact your $msg->{recipient_position} by more traditional, albeit less fun, means.

A copy of your message is pasted to the bottom of this email.

Please feel free to email us on info\@FaxYourRepresentative.com with any comments, problems or suggestions. And don't forget to let your friends and family know about www.FaxYourRepresentative.com !

If you did not request that a message be sent to $msg->{recipient_name}, please let us know by sending an email to abuse\@FaxYourRepresentative.com

Don't worry; nothing will be sent without your permission.

-- the FaxYourRepresentative.com team


-----------------------------------------------------------------------

A copy of your message follows:


EOF

    $text .= format_email_body($msg);

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
    my $msg = message($id, 1);
    return send_user_email($id, 'confirmation', make_confirmation_email($msg, $reminder));
}

# make_failure_email ID
# Return a MIME::Entity object for the given MESSAGE (reference to hash of db
# fields), suitable for sending to the constituent to warn them that their
# message could not be delivered.
sub make_failure_email ($) {
    my ($msg) = @_;

    my $failure_sender = sprintf('%sbounce-null@%s',
                                mySociety::Config::get('EMAIL_PREFIX'),
                                mySociety::Config::get('EMAIL_DOMAIN'));

    # Don't insert linebreaks in the below except for paragraph marks-- let
    # Text::Wrap do the rest.
    my $text = wrap(EMAIL_COLUMNS, 
        <<EOF);
We're very sorry, but it wasn't possible to send your letter to $msg->{recipient_name}, your $msg->{recipient_position}. Unfortunately, our system isn't 100% reliable and from time to time a message doesn't get through. We've attached a copy of your letter to the bottom of your email, so that you can print it out and send it by more traditional means. Or, try again via our site in a week or so -- hopefully we'll have tracked down the problem by then.

-- the FaxYourRepresentative.com team


-----------------------------------------------------------------------

A copy of your message follows:


EOF

    $text .= format_email_body($msg);

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

# deliver_email MESSAGE
# Attempt to deliver the MESSAGE by email.
sub deliver_email ($) {
    my ($msg) = @_;
    my $id = $msg->{id};
    my $mail = make_representative_email($msg);
    my $sender = sprintf('%s-%s@%s',
                            mySociety::Config::get('EMAIL_PREFIX'),
                            bounce_token($id),
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
which they've clicked on.

=cut
sub confirm_email ($$) {
    my ($x,$token) = @_;
    if (my $id = verify_confirm_token($token)) {
        state($id, 'ready');
        logmsg($id, "sender email address confirmed");
        FYR::DB::dbh()->commit();
        notify_daemon();
        return 1;
    }
    return 0;
}

#
# Implementation of the state machine.
#

use constant DAY => 86400;

# How many confirmation mails may be sent, in total.
use constant NUM_CONFIRM_MESSAGES => 2;

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

        # How long after a message has been sent we hang around waiting for
        # questionnaire responses and allowing the recipient to forward the
        # message to other representatives for the sender.
        sent            => DAY * 21,

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

        # How often we attempt delivery of a questionnaire in the face of soft
        # failures.
        sent            => 1800,

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
            # Send reminder confirmation if necessary. Note that actions($id)
            # is the number of reminders sent in the pending state, not the
            # total number.
            if (1 + actions($id) < NUM_CONFIRM_MESSAGES) {
                my $result = send_confirmation_email($id);
                if ($result == mySociety::Util::EMAIL_SUCCESS) {
                    state($id, 'pending');  # bump actions counter
                } elsif ($result == mySociety::Util::EMAIL_HARD_ERROR) {
                    # Shouldn't happen in this state.
                    logmsg($id, "abandoning message after failure to send confirmation email");
                    state($id, 'failed');
                } # otherwise no action
            }
        },

        ready => sub ($) {
            my ($id) = @_;
            # Send email or fax to recipient.
            my $msg = message($id);
            if (defined($msg->{recipient_fax})) {
                my $result = deliver_fax($msg);
                if (!$result) {
                    logmsg($id, "abandoning message after failure to sent to representative");
                    state($id, 'error');
                }
            } else {
                my $result = deliver_email($msg);
                if ($result == mySociety::Util::EMAIL_SUCCESS) {
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
            # XXX This is where we send the questionnaire email, but that's not
            # implemented yet.
            if (actions($id) == 0) {
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

1;
