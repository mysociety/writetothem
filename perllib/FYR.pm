#!/usr/bin/perl
#
# FYR.pm:
# Utility stuff for FYR.
#
# Copyright (c) 2004 UK Citizens Online Democracy. All rights reserved.
# Email: chris@mysociety.org; WWW: http://www.mysociety.org/
#
# $Id: FYR.pm,v 1.25 2009-12-07 11:20:54 louise Exp $
#

use strict;

package FYR::Error;
# simplest of all exception classes.

use Error qw(:try);
use RABX;

@FYR::Error::ISA = qw(RABX::Error);

=head1 CONSTANTS

=head2 Error codes

=over 4

=item MESSAGE_ALREADY_QUEUED

When a message id has already been used to send a message, and a second
attempt is made to use it.

=item MESSAGE_ALREADY_CONFIRMED

When a message has already been confirmed, and a second attempt is
made to confirm it.

=back

=cut

use constant MESSAGE_ALREADY_QUEUED  => 4001;
use constant MESSAGE_ALREADY_CONFIRMED  => 4002;
use constant MESSAGE_BAD_ADDRESS_DATA  => 4003;
use constant MESSAGE_SHAME  => 4004;
use constant MESSAGE_SUSPECTED_ABUSE => 4005;
use constant GROUP_ALREADY_QUEUED => 4006;
use constant BAD_DATA_PROVIDED => 4007; # Currently both from client and from server
use constant REPRESENTATIVE_DELETED => 4008;
use constant MESSAGE_EXPIRED => 4009;

package FYR::DB;

use mySociety::Config;
use mySociety::DBHandle qw(dbh);
use mySociety::Random qw(random_bytes);
use DBI;

BEGIN {
    mySociety::DBHandle::configure(
            Name => mySociety::Config::get('FYR_QUEUE_DB_NAME'),
            User => mySociety::Config::get('FYR_QUEUE_DB_USER'),
            Password => mySociety::Config::get('FYR_QUEUE_DB_PASS'),
            Host => mySociety::Config::get('FYR_QUEUE_DB_HOST', undef),
            Port => mySociety::Config::get('FYR_QUEUE_DB_PORT', undef),
            OnFirstUse => sub {
                if (!dbh()->selectrow_array('select secret from secret')) {
                    local dbh()->{HandleError};
                    dbh()->do('insert into secret (secret) values (?)',
                                {}, unpack('h*', random_bytes(32)));
                    dbh()->commit();
                }
            }
        );

}

=item secret

Return the site shared secret.

=cut
sub secret () {
    return scalar(dbh()->selectrow_array('select secret from secret'));
}

=item Time

Return time, offset to debug time.

=cut
my $time_offset;
sub Time () {
    if (mySociety::Config::get('FYR_REFLECT_EMAILS')) {
        $time_offset =
            FYR::DB::dbh()->selectrow_array('
                        select extract(epoch from fyr_current_timestamp()) -
                        extract(epoch from current_timestamp)');
    } else {
       $time_offset = 0;
    }
    return time() + int($time_offset);
}

# scrub_message ID
# Remove some personal data from message ID. This includes the text of
# the letter, any extradata and bounce messages (since they usually
# contain quoted text).
sub scrub_message ($) {
    my ($id) = @_;
    dbh()->do(q#update message
        set sender_ipaddr = '', sender_referrer = null,
            message = '[ removed message of ' || length(message) || ' characters ]'
        where id = ?#, {}, $id);
    # The extra data, and bounce tables may also contain personal data.
    dbh()->do(q#delete from message_extradata where message_id = ?#, {}, $id);
    dbh()->do(q#delete from message_bounce where message_id = ?#, {}, $id);
}

# scrub_data ID
# Remove all sender personal data from message ID, including name,
# email, address, etc. Also remove all log messages.
sub scrub_data ($) {
    my ($id) = @_;
    scrub_message($id);
    dbh()->do("update message
        set sender_name = '', sender_email = '', sender_addr = '',
            sender_phone = '', sender_postcode = '', message = '',
            state = 'anonymised'
        where id = ?", {}, $id);
    dbh()->do(q#delete from message_log where message_id = ?#, {}, $id);
}

1;
