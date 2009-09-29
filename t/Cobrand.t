#!/usr/bin/perl -w
#
# Cobrand.t:
# Tests for the cobranding functions
#
#  Copyright (c) 2009 UK Citizens Online Democracy. All rights reserved.
# Email: louise@mysociety.org; WWW: http://www.mysociety.org/
#
# $Id: Cobrand.t,v 1.1 2009-09-29 16:51:46 louise Exp $
#

use strict;
use warnings;
use Test::More tests => 4;
use Test::Exception;

use FindBin;
use lib "$FindBin::Bin";
use lib "$FindBin::Bin/../perllib";
use lib "$FindBin::Bin/../../perllib";

use FYR::Cobrand;
use mySociety::Config;
BEGIN {
    mySociety::Config::set_file("$FindBin::Bin/../conf/general");
}

sub test_base_url_for_emails {
    my $cobrand = 'mysite';    

    # should get the results of the base_url_for_emails function in the cobrand module if one exists
    my $base_url = FYR::Cobrand::base_url_for_emails($cobrand);
    is('http://mysite.foremails.example.com', $base_url, 'base_url_for_emails returns output from cobrand module') ;

    $cobrand = 'animalaid';
    $base_url = FYR::Cobrand::base_url_for_emails($cobrand);
    is('http://animalaid.' . mySociety::Config::get('WEB_DOMAIN'), $base_url, "should return a cobrand subdomain if a cobrand exists but doesn't define its own function");

    $cobrand = undef;
    $base_url = FYR::Cobrand::base_url_for_emails($cobrand);
    is(mySociety::Config::get('BASE_URL'), $base_url, 'should return the base url from the config file if no cobrand is defined');

}

ok(test_base_url_for_emails() == 1, 'Ran all tests for base_url_for_emails');
