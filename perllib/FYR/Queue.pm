#!/usr/bin/perl
#
# FYR/Queue.pm:
# Fax/email queue management for FYR.
#
# Copyright (c) 2004 UK Citizens Online Democracy. All rights reserved.
# Email: chris@mysociety.org; WWW: http://www.mysociety.org/
#
# $Id: Queue.pm,v 1.2 2004-10-20 11:06:41 chris Exp $
#

package FYR::Queue;

use strict;

use Digest::SHA1;
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

Return an ID for a new message.

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
sub write ($$$$) {
    my ($id, $sender, $recipient_id, $text) = @_;

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
print Dumper($recipient);
print Dumper($sender);
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

#    send_confirmation_email($id);

    return $id;
}

=item logmsg ID DIAGNOSTIC

Log a DIAGNOSTIC about the message with the given ID.

=cut
sub logmsg ($$) {
    my ($id, $msg) = @_;
    FYR::DB::dbh()->do('insert into message_log (message_id, state, message) values (?, ?, ?)', {}, $id, state($id), $msg);
}

=item state ID [STATE]

Get/change the state of the message with the given ID to STATE. If STATE is the
same as the current state, just update the lastupdate field; if it isn't, then
also set the lastupdate field to null.

=cut
sub state ($;$) {
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
sub message ($;$) {
    my ($id, $forupdate) = @_;
    $forupdate = defined($forupdate) ? ' for update' : '';
    if (my $msg = FYR::DB::dbh()->selectrow_hashref("select * from message where id = ?$forupdate", {}, $id)) {
        return $msg;
    } else {
        throw FYR::Error("No message '$id'.");
    }
}

# format_mimeword STRING
# Return STRING, formatted for inclusion in an email header.
sub format_mimeword ($) {
    my ($s) = @_;
    utf8::downgrade($s);
    if ($s =~ m#[\x00-\x20\x80-\xff]#) {
        $s = MIME::Words::encode_mimeword($s, 'Q', 'utf-8');
    }
    utf8::upgrade($s);
    return $s;
}

# format_email_address NAME ADDRESS
# Return a suitably MIME-encoded version of "NAME <ADDRESS>" suitable for use
# in an email From:/To: header.
sub format_email_address ($$) {
    my ($name, $addr) = @_;
    return sprintf('%s <%s>', format_mimeword($name), $addr);
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

# make_representative_email ID
# Return a MIME::Entity object for the message with the given ID, suitable for
# immediate sending to the real recipient.
sub make_representative_email ($) {
    my ($id) = @_;
    my $msg = message($id);
    return MIME::Entity->build(
            Sender => $msg->{sender_email},
            From => format_email_address($msg->{sender_name}, $msg->{sender_email}),
            To => format_email_address($msg->{recipient_name}, $msg->{recipient_email}),
            Subject => "Letter from your constituent " . format_mimeword($msg->{sender_name}),
            Type => 'text/plain; charset="utf-8"',
            Data => format_email_body($msg)
        );
}

# confirm_token ID
# Return a suitable token for use in confirming a user's email address.
sub confirm_token ($) {
    my ($id) = @_;
    my $salt = unpack('h*', mySociety::Util::random_bytes(8));
    my $digest = lc(Digest::SHA1::sha1_hex("$id-$salt-" . FYR::DB::secret()));
    return "$id-$salt-$digest";
}

# verify_confirm_token TOKEN
# Attempt to verify the confirm TOKEN. Returns a message ID on success or undef
# on failure.
sub verify_confirm_token ($) {
    my ($token) = @_;
    $token = lc($token);    # may go through a channel, e.g. email, which is not case-preserving
    if (my ($id, $salt, $digest) = ($token =~ m#^([a-f0-9]+)-([a-f0-9]+)-([a-f0-9]+)$#)) {
        return $id if (lc(Digest::SHA1::sha1_hex("$id-$salt-" . FYR::DB::secret())) eq $digest);
    }
    return undef;
}

# bounce_token ID
# Return a suitable token for use in processing a bounce message.
sub bounce_token ($) {
    my ($id) = @_;
    my $salt = unpack('h*', mySociety::Util::random_bytes(8));
    my $digest = lc(Digest::SHA1::sha1_hex("bounce-$id-$salt-" . FYR::DB::secret()));
    return "bounce-$id-$salt-$digest";
}

# confirm_bounce_token TOKEN
# Attempt to verify the bounce TOKEN. Returns a message ID on success or undef
# on failure.
sub verify_bounce_token ($) {
    my ($token) = @_;
    $token = lc($token);
    if (my ($id, $salt, $digest) = ($token =~ m#^bounce-([a-f0-9]+)-([a-f0-9]+)-([a-f0-9]+)$#)) {
        return $id if (lc(Digest::SHA1::sha1_hex("bounce-$id-$salt-" . FYR::DB::secret())) eq $digest);
    }
    return undef;
}

# make_confirmation_email MESSAGE
# Return a MIME::Entity object for the given MESSAGE (reference to hash of db
# fields), suitable for sending to the constituent so that they can confirm
# their email address.
sub make_confirmation_email ($) {
    my ($msg) = @_;

    my $token = confirm_token($msg->{id});
    my $confirm_url = FYR::Config::get_value('baseurl') . '/confirm.php?token=' . $token;

    # Note: (a) don't care about bounces from this mail (they result only from
    # transient failures or abuse; but (b) we can't use a reply to confirm that
    # a fax should be sent because a broken server which sends bounces to the
    # From: address would then automatically confirm any email address.
    my $confirm_sender = sprintf('%sbounce-null@%s',
                                FYR::Config::get_value('emailprefix'),
                                FYR::Config::get_value('emaildomain'));

    my $text = wrap(EMAIL_COLUMNS, <<EOF);

Please click on the link below to confirm that you wish FaxYourRepresentative.com to send the letter copied at the bottom of this email to $msg->{recipient_name}, your $msg->{recipient_position}:

    $confirm_url

If your email program does not let you click on this link, just copy and paste it into your web browser and hit return.

We'll send you a confirmation email once your letter has been dispatched successfully. If for some reason we can't send your letter, we will email you with details of how to contact your $msg->{recipient_position} by more traditional, albeit less fun, means.

A copy of your letter is pasted to the bottom of this email.

Please feel free to email us on info\@FaxYourRepresentative.com with any comments, problems or suggestions. And don't forget to let your friends and family know about www.FaxYourRepresentative.com !

If you did not request that a letter be sent to $msg->{recipient_name}, please let us know by sending an email to abuse\@FaxYourRepresentative.com

Don't worry; nothing will be sent without your permission.

-- the FaxYourRepresentative.com team


-----------------------------------------------------------------------

A copy of your letter follows:


EOF

    $text .= format_email_body($msg);

    return MIME::Entity->build(
            Sender => $confirm_sender,
            From => format_email_address('FaxYourRepresentative', $confirm_sender),
            To => format_email_address($msg->{sender_name}, $msg->{sender_email}),
            Subject => sprintf('Please confirm that you want to send a letter to %s', format_mimeword($msg->{recipient_name})),
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
        if (defined($msg->{fax})) {
            throw FYR::Error("Can't send faxes yet");
        } elsif (defined($msg->{email})) {
            my $mail = make_representative_email($msg);
            my $sender = sprintf('%s-%s@%s',
                                FYR::Config::get_value('emailprefix'),
                                bounce_token($id),
                                FYR::Config::get_value('emaildomain'));
            my $result = mySociety::Util::send_email($mail->stringify(), $mail->head()->get('Sender'), $msg->{recipient_email});
            throw FYR::Error($result) if ($result);
            logmsg($id, "send mail to recipient $msg->{recipient_email}");
            state($id, 'sent');
        } else {
            throw FYR::Error("Message '$id' has neither fax nor email recipient");
        }
    } catch FYR::Error with {
        my $E = shift;
        logmsg($id, "unable to sent message to recipient: $E");
        state($id, 'ready');    # force update of timestamp
    } finally {
        FYD::DB::dbh()->commit();
    };
}

=item run_queue

Run the queue, sending confirmation emails and delivering real letters.

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

1;
