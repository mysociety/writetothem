#!/usr/bin/perl
#
# FYR.pm:
# Utility stuff for FYR.
#
# Copyright (c) 2004 UK Citizens Online Democracy. All rights reserved.
# Email: chris@mysociety.org; WWW: http://www.mysociety.org/
#
# $Id: FYR.pm,v 1.11 2005-01-31 20:49:06 chris Exp $
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

package FYR::DB;

use mySociety::Config;
use mySociety::DBHandle qw(dbh);
use mySociety::Util;
use DBI;

BEGIN {
    mySociety::DBHandle::configure(
            Name => mySociety::Config::get('FYR_QUEUE_DB_NAME'),
            User => mySociety::Config::get('FYR_QUEUE_DB_USER'),
            Password => mySociety::Config::get('FYR_QUEUE_DB_PASS'),
            Host => mySociety::Config::get('FYR_QUEUE_DB_HOST', undef),
            Port => mySociety::Config::get('FYR_QUEUE_DB_PORT', undef)
        );

    if (!dbh()->selectrow_array('select secret from secret for update of secret')) {
        dbh()->do('insert into secret (secret) values (?)', {}, unpack('h*', mySociety::Util::random_bytes(32)));
    }
    dbh()->commit();
}

=item secret

Return the site shared secret.

=cut
sub secret () {
    return scalar(dbh()->selectrow_array('select secret from secret'));
}

1;
