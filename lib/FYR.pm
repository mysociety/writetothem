#!/usr/bin/perl
#
# FYR.pm:
# Utility stuff for FYR.
#
# Copyright (c) 2004 UK Citizens Online Democracy. All rights reserved.
# Email: chris@mysociety.org; WWW: http://www.mysociety.org/
#
# $Id: FYR.pm,v 1.2 2004-10-05 14:47:41 chris Exp $
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
        dbpass => ''
    );

=item get_value NAME [DEFAULT]

Return the value with the given NAME, or, if it not specified, DEFAULT, or, if
that is not specified, undef.

=cut
sub get_value ($;$) {
    my ($name, $def) = @_;
    return $config->{$name} || $def;
}

package FYR::DB;

use DBI;
use FYR::Config;

=item new_dbh

Return a new handle on the database.

=cut
sub new_dbh () {
    return DBI->connect('dbi:Pg:%s' . FYR::Config::get_value('dbname', 'fyr'),
                        FYR::Config::get_value('dbuser', 'fyr'),
                        FYR::Config::get_value('dbpass', ''),
                        { RaiseError => 1, AutoCommit => 0 });
}


=item dbh

Return a shared handle on the database.

=cut
sub dbh () {
    our $dbh;
    $dbh ||= another_dbh();
    return $dbh;
}

1;
