#!/usr/bin/perl
#
# FYR.pm:
# Utility stuff for FYR.
#
# Copyright (c) 2004 UK Citizens Online Democracy. All rights reserved.
# Email: chris@mysociety.org; WWW: http://www.mysociety.org/
#
# $Id: FYR.pm,v 1.2 2004-10-20 11:06:41 chris Exp $
#

package FYR::Error;
# simplest of all exception classes.

use Error qw(:try);

@FYR::Error::ISA = qw(Error::Simple);

package FYR::Config;
# configuration -- we need to flesh this out later.

my %config = (
        dbname => 'fyr',
        dbuser => 'fyr',
        dbpass => '',
        baseurl => 'http://caesious.beasts.org/~chris/tmp/fyr',
        emaildomain => 'caesious.beasts.org',
        emailprefix => 'fyr-'
    );

=item get_value NAME [DEFAULT]

Return the value with the given NAME, or, if it not specified, DEFAULT, or, if
that is not specified, undef.

=cut
sub get_value ($;$) {
    my ($name, $def) = @_;
    my $x = $config{$name} || $def;
    die "No config value for '$name', and no default" unless (defined($x));
    return $x;
}

package FYR::DB;

use mySociety::Util;
use DBI;

=item new_dbh

Return a new handle on the database.

=cut
sub new_dbh () {
    my $dbh = DBI->connect('dbi:Pg:dbname=' . FYR::Config::get_value('dbname', 'fyr'),
                        FYR::Config::get_value('dbuser', 'fyr'),
                        FYR::Config::get_value('dbpass', ''),
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
