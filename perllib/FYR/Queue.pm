#!/usr/bin/perl
#
# FYR/Queue.pm:
# Fax/email queue management for FYR.
#
# Copyright (c) 2004 UK Citizens Online Democracy. All rights reserved.
# Email: chris@mysociety.org; WWW: http://www.mysociety.org/
#
# $Id: Queue.pm,v 1.9 2004-11-10 17:30:37 francis Exp $
#

package FYR::Queue;

use strict;

use Convert::Base32;
use Crypt::CBC;
use Error qw(:try);
use HTML::Entities;
use Mail::RFC822::Address;
use MIME::Entity;
use MIME::Words;
use Text::Wrap (); # don't pollute our namespace
use utf8;

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
their full postal address; and optionally phone, their phone number. RECIPIENT
is the DaDem ID number of the recipient of the message; and TEXT is the text of
the message, with line breaks. Returns true on success, or an error code on
failure.

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
        foreach (qw(name email address)) {
            throw FYR::Error("Missing required '$_' element in SENDER") unless (exists($sender->{$_}));
        }
        throw FYR::Error("Email address '$sender->{email}' for SENDER is not valid") unless (Mail::RFC822::Address::valid($sender->{email}));

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
                sender_name, sender_email, sender_addr, sender_phone,
                recipient_id, recipient_name, recipient_position, recipient_email, recipient_fax,
                message,
                state,
                whencreated
            ) values (
                ?,
                ?, ?, ?, ?,
                ?, ?, ?, ?, ?,
                ?,
                'new',
                ?
            )#, {},
            $id,
            (map { $sender->{$_} || undef } qw(name email address phone)),
            $recipient_id, (map { $recipient->{$_} || undef } qw(name position email fax)),
            $text,
            time());

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

=item state ID [STATE]

Get/change the state of the message with the given ID to STATE. If STATE is the
same as the current state, just update the lastupdate field; if it isn't, then
also set the lastupdate field to null.

=cut
sub state ($$) {
    my ($id, $state) = @_;
    my %allowed = qw(
            new     pending
            pending ready
            ready   sent
            sent    done
        );
    if (defined($state)) {
        my $curr_state = FYR::DB::dbh()->selectrow_array('select state from message where id = ?', {}, $id);
        die "Can't go from state '$curr_state' to '$state'" unless ($state eq $curr_state or $state eq $allowed{$curr_state});
        if ($state ne $curr_state) {
            FYR::DB::dbh()->do('update message set lastupdate = null, state = ? where id = ?', {}, $state, $id);
            logmsg($id, "changed state to $state");
        } else {
            FYR::DB::dbh()->do('update message set lastupdate = ? where id = ?', {}, time(), $id);
        }
        return $curr_state;
    } else {
        return FYR::DB::dbh()->selectrow_array('select state from message where id = ?', {}, $id);
    }
}

# message ID [LOCK]
# Return a hash of data about message ID. If LOCK is true, retrieves the fields
# using SELECT ... FOR UPDATE.
sub message ($$) {
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
my %confirm_wordmap = qw(
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

    $word = $confirm_wordmap{$word} if (exists($confirm_wordmap{$word}));

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
    
    $word = $confirm_wordmap{$word} if (exists($confirm_wordmap{$word}));

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

# make_confirmation_email MESSAGE
# Return a MIME::Entity object for the given MESSAGE (reference to hash of db
# fields), suitable for sending to the constituent so that they can confirm
# their email address.
sub make_confirmation_email ($) {
    my ($msg) = @_;

    my $token = confirm_token($msg->{id});
    my $confirm_url = mySociety::Config::get('BASE_URL') . '/' . $token;

    # Note: (a) don't care about bounces from this mail (they result only from
    # transient failures or abuse; but (b) we can't use a reply to confirm that
    # a fax should be sent because a broken server which sends bounces to the
    # From: address would then automatically confirm any email address.
    my $confirm_sender = sprintf('%sbounce-null@%s',
                                mySociety::Config::get('EMAIL_PREFIX'),
                                mySociety::Config::get('EMAIL_DOMAIN'));

    # Don't insert linebreaks in the below except for paragraph marks-- let
    # Text::Wrap do the rest.
    my $text = wrap(EMAIL_COLUMNS, <<EOF);

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

# send_confirmation_email ID
# Send the necessary confirmation email for message ID.
sub send_confirmation_email ($) {
    my ($id) = @_;
    try {
        my $msg = message($id, 1);
        my $mail = make_confirmation_email($msg);
        my $result = mySociety::Util::send_email($mail->stringify(), $mail->head()->get('Sender'), $msg->{sender_email});
        throw FYR::Error($result) if ($result);
        logmsg($id, "sent confirmation email to $msg->{sender_email}");
        state($id, 'pending');
    } catch FYR::Error with {
        my $E = shift;
        logmsg($id, "unable to send confirmation mail: " . $E->text());
        state($id, 'new');  # force update of timestamp
        throw $E;
    } finally {
        FYR::DB::dbh()->commit();
    };
}

# deliver ID
# Attempt to deliver the message with the given ID.
sub deliver ($) {
    my ($id) = @_;
    try {
        my $msg = message($id, 1);
        if (defined($msg->{recipient_fax})) {
            throw FYR::Error("Can't send faxes yet");
        } elsif (defined($msg->{recipient_email})) {
            my $mail = make_representative_email($msg);
            my $sender = sprintf('%s-%s@%s',
                                mySociety::Config::get('EMAIL_PREFIX'),
                                bounce_token($id),
                                mySociety::Config::get('EMAIL_DOMAIN'));
            my $result = mySociety::Util::send_email($mail->stringify(), $mail->head()->get('Sender'), $msg->{recipient_email});
            throw FYR::Error($result) if ($result);
            logmsg($id, "sent mail to recipient $msg->{recipient_email}");
            state($id, 'sent');
        } else {
            throw FYR::Error("Message '$id' has neither fax nor email recipient");
        }
    } catch FYR::Error with {
        my $E = shift;
        logmsg($id, "unable to send message to recipient: " . $E->text());
        state($id, 'ready');    # force update of timestamp
    } finally {
        FYR::DB::dbh()->commit();
    };
}

=item run_queue

Run the queue, sending confirmation emails and delivering real messages.

=cut
sub run_queue () {
    # Obtain list of messages for which action is necessary.
    my $stmt = FYR::DB::dbh()->prepare(q#
        select id, state from message where (state = 'new' or state = 'ready') and (lastupdate is null or lastupdate < ?)#);
    $stmt->execute(time() - 1200);  # retry every twenty minutes.
    my ($total, $failures) = (0, 0);
    while (my ($id, $state) = $stmt->fetchrow_array()) {
        ++$total;
        try {
            if ($state eq 'new') {
                send_confirmation_email($id);
            } elsif ($state eq 'ready') {
                deliver($id);
            }
        } catch FYR::Error with {
            my $E = shift;
            ++$failures;
        };
    }
#    return ($total, $failures);


    # Now expire old messages etc.

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
        # poke a sending daemon?
        return 1;
    }
    return 0;
}

1;
