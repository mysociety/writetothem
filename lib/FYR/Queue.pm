#!/usr/bin/perl
#
# FYR/Queue.pm:
# Fax/email queue management for FYR.
#
# Copyright (c) 2004 UK Citizens Online Democracy. All rights reserved.
# Email: chris@mysociety.org; WWW: http://www.mysociety.org/
#
# $Id: Queue.pm,v 1.1 2004-10-05 14:45:09 chris Exp $
#

package FYR::Queue;

use Error qw(:try);
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

the text of the message

=item sender

a reference to a hash containing elements name, the sender's name;
email, their email address; addr, their postal address; and (optionally) phone,
their phone number.

=item recipient

a reference to a hash containing elements id, the recipient's DaDem
representative ID; name, their name; and exactly one of email, their email
address, or fax, their fax number.

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
        foreach (qw(id name));

    my $n = 0;
    $n++ if (exists($msg->{recipient}->{$_})) foreach (qw(email fax));

    throw FYR::Error("Recipient must have an email address or a fax number") unless ($n == 0);
    throw FYR::Error("Recipient email address is not valid") if (exists($msg->{recipient}->{email}) and !Mail::RFC822::Address::valid($msg->{recipient}->{email}));

    # XXX should also check that the text bits are valid UTF-8.

    # Queue the message and obtain its ID.
    FYR::DB::dbh->do('
        insert into queue (
            token,
            sender_name, sender_email, sender_addr, sender_phone,
            recipient_id, recipient_name, recipient_email, recipient_fax,
            message,
        ) values (
            ?,
            ?, ?, ?, ?,
            ?, ?, ?, ?,
            ?
        )', {}, pack('h20', mySociety::Util::get_random_bytes(10)),
                map { $msg->{sender}->{$_} } qw(name email addr phone),
                map { $msg->{recipient}->{$_} } qw(id name email fax),
                $msg->{text});

    # could obtain ID as currval('queue_id_seq') but don't need it.
    FYR::DB->commit();

    
}



1;
