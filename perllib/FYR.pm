#!/usr/bin/perl
#
# FYR.pm:
# Utility stuff for FYR.
#
# Copyright (c) 2004 UK Citizens Online Democracy. All rights reserved.
# Email: chris@mysociety.org; WWW: http://www.mysociety.org/
#
# $Id: FYR.pm,v 1.3 2004-11-10 10:41:52 francis Exp $
#

use strict;

package FYR::Error;
# simplest of all exception classes.

use Error qw(:try);

@FYR::Error::ISA = qw(Error::Simple);

package FYR::DB;

use mySociety::Util;
use DBI;

=item new_dbh

Return a new handle on the database.

=cut
sub new_dbh () {
    my $dbh = DBI->connect('dbi:Pg:dbname=' .  mySociety::Config::get('FYR_QUEUE_DB_NAME'),
                        mySociety::Config::get('FYR_QUEUE_DB_USER'),
                        mySociety::Config::get('FYR_QUEUE_DB_PASS'),
                        { RaiseError => 1, AutoCommit => 0 });

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
