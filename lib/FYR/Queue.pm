#!/usr/bin/perl
#
# FYR/Queue.pm:
# Fax/email queue management for FYR.
#
# Copyright (c) 2004 UK Citizens Online Democracy. All rights reserved.
# Email: chris@mysociety.org; WWW: http://www.mysociety.org/
#
# $Id: Queue.pm,v 1.2 2004-10-14 14:14:24 chris Exp $
#

package FYR::Queue;

use Error qw(:try);
use HTML::Entities;
use Mail::RFC822::Address;

use FYR;

=head1 NAME

FYR::Queue

=head1 DESCRIPTION

Management of queue of messages for FYR.

=head1 FUNCTIONS

=over 4
=cut

=item enqueue_message MESSAGE

Enqueue MESSAGE for sending. MESSAGE is a reference to a hash containing
information about this message. Its elements are:

=over 4

=item text

The text of the message.

=item sender

A reference to a hash containing elements name, the sender's name;
email, their email address; addr, their postal address; and (optionally) phone,
their phone number.

=item recipient

A reference to a hash containing elements id, the recipient's DaDem
representative ID; name, their name; position, their position, for instance
"Member of Parliament" or "County Councillor"; and exactly one of email, their
email address, or fax, their fax number.

=back

Throws an error on failure.

=cut
sub enqueue_message ($) {
    my ($msg) = @_;

    #
    # Paranoid validity checks.
    #
    throw FYR::Error("No MESSAGE specified") unless (defined($msg));
    throw FYR::Error("MESSAGE is not a reference to a hash") unless (ref($msg) eq 'HASH');
    throw FYR::Error("No recipient in MESSAGE") unless (exists($msg->{recipient}))
    throw FYR::Error("Recipient is not a reference-to-hash") unless (ref($msg->{recipient}) eq 'HASH');
    throw FYR::Error("No sender in MESSAGE") unless (exists($msg->{sender}))
    throw FYR::Error("Sender is not a reference-to-hash") unless (ref($msg->{sender}) eq 'HASH');
    throw FYR::Error("No text in MESSAGE") unless (exists($msg->{text}));

    throw FYR::Error("No $_ given for sender") unless (exists($msg->{sender}->{$_}) and defined($msg->{sender}->{$_})
        foreach (qw(name email addr));
        
    throw FYR::Error("Sender email address is not valid") if (exists($msg->{recipient}->{email}) and !Mail::RFC822::Address::valid($msg->{sender}->{email}));

    throw FYR::Error("No $_ given for recipient") unless (exists($msg->{sender}->{$_}) and defined($msg->{sender}->{$_})
        foreach (qw(id name position));

    my $n = 0;
    $n++ if (exists($msg->{recipient}->{$_})) foreach (qw(email fax));

    throw FYR::Error("Recipient must have an email address or a fax number") unless ($n == 0);
    throw FYR::Error("Recipient email address is not valid") if (exists($msg->{recipient}->{email}) and !Mail::RFC822::Address::valid($msg->{recipient}->{email}));

    # XXX should also check that the text bits are valid UTF-8.

    # Queue the message and obtain its ID.
    FYR::DB::dbh()->do('
        insert into queue (
            token,
            sender_name, sender_email, sender_addr, sender_phone,
            recipient_id, recipient_name, recipient_position, recipient_email, recipient_fax,
            message,
        ) values (
            ?,
            ?, ?, ?, ?,
            ?, ?, ?, ?, ?,
            ?
        )', {}, pack('h20', mySociety::Util::get_random_bytes(10)),
                map { $msg->{sender}->{$_} } qw(name email addr phone),
                map { $msg->{recipient}->{$_} } qw(id name position email fax),
                $msg->{text});

    my $id = FYR::DB::dbh()->selectrow_array(q(select currval('queue_id_seq')));
    FYR::DB::dbh()->commit();

    send_confirmation_email($id);
}

=item letter_to_html RECIPIENT SENDER ADDRESS TEXT

Return an HTML-formatted version of a letter entered by the user.

=cut
sub letter_to_html ($$$$) {
    my ($recip, $sender, $addr, $text) = @_;
}

=item send_confirmation_email ID

Send a confirmation email (asking the user to click a link to verify their
address) for the queued message with the given ID.

=cut
sub send_confirmation_email ($) {
    my ($id) = @_;
    try {
        throw FYR::Error("No message with the given ID") unless (my $h = FYR::DB::dbh()->selectrow_hashref('select token * from queue where id = ? for update of queue', {}, $id));
        throw FYR::Error("Message with given ID is not in state pending") unless ($h->{state} eq 'pending');

        # Idea is to send the user a URL with a parameter which contains the
        # token of the message.
        my $url = FYR::Config::get_value('baseurl') . "?confirm=$h->{token}";

        # XXX This is copied from the text FYMP uses. Do we want to reveal
        # whether we fax or email the target?
        my $how = defined($h->{recipient}->{email}) 'email' : 'fax';

        # list of paragraphs.
        # XXX branding!
        my $text = <<EOF;
<p>Please click on the link below to confirm that you wish
FaxYourRepresentative.com to $how the letter copied at the bottom
of this email to $h->{name}, your $h->{position}:</p>

<blockquote><a href="$url">$url</a></blockquote>

<p>If your email program does not let you click on this link, just copy and paste
it into your web browser and hit return. We'll send you a confirmation email
once your letter has been dispatched successfully.</p>

<p>If for some reason we can't send your $how, we will email you with details of
how to contact your $h->{position} by more traditional, albeit less fun,
means.</p>

<p>A copy of your letter is pasted to the bottom of this email.</p>

<p>Please feel free to email us on
<a href="info\@faxyourrepresentative.com">info\@faxyourrepresentative.com</a>
with any comments, problems or suggestions. And don't forget to let your
friends and family know about
<a href="http://www.faxyourrepresentative.com/">www.FaxYourRepresentative.com</a> .</p>

<p>If you did not request that a $how be sent to your $h->{position}, or
you've never heard of FaxYourRepresentative.com, please let us know at
<a href="mailto:abuse\@faxyourrepresentative.com">abuse\@faxyourrepresentative.com</a> .
Don't worry; nothing will be sent without your permission.

<p>Happy ${how}ing!</p>

<p>-- the FaxYourRepresentative.com team</p>

<hr>

<p><strong>This is the text of your letter:</strong></p>
EOF

        $text .= message_to_html($h);

    } finally {
        FYR::DB::dbh()->rollback();
        throw;
    }
}

=cut

1;
