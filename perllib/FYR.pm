#!/usr/bin/perl
#
# FYR.pm:
# Utility stuff for FYR.
#
# Copyright (c) 2004 UK Citizens Online Democracy. All rights reserved.
# Email: chris@mysociety.org; WWW: http://www.mysociety.org/
#
# $Id: FYR.pm,v 1.6 2004-12-07 14:23:30 chris Exp $
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

package FYR::DB;

use mySociety::Util;
use DBI;

=head1 FUNCTIONS

=item new_dbh

Return a new handle on the database.

=cut
sub new_dbh () {

    my $host = mySociety::Config::get('FYR_QUEUE_DB_HOST', undef);
    my $port = mySociety::Config::get('FYR_QUEUE_DB_PORT', undef);
    
    my $connstr = 'dbi:Pg:dbname=' .  mySociety::Config::get('FYR_QUEUE_DB_NAME');
    $connstr .= ";host=$host" if (defined($host));
    $connstr .= ";port=$port" if (defined($port));

    my $dbh = DBI->connect($connstr,
                        mySociety::Config::get('FYR_QUEUE_DB_USER'),
                        mySociety::Config::get('FYR_QUEUE_DB_PASS'),
                        { RaiseError => 1, AutoCommit => 0, PrintWarn => 0, PrintError => 0 });

    # make sure we have a site shared secret
    if (!$dbh->selectrow_array('select secret from secret for update of secret')) {
        $dbh->do('insert into secret (secret) values (?)', {}, unpack('h*', mySociety::Util::random_bytes(32)));
        $dbh->commit();
    }

    return $dbh;
}


=item dbh

Return a shared handle on the database.

=cut
sub dbh () {
    our $dbh;
    $dbh ||= new_dbh();
    return $dbh;
}

=item secret

Return the site shared secret.

=cut
sub secret () {
    return scalar(dbh()->selectrow_array('select secret from secret'));
}

1;
