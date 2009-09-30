#!/usr/bin/perl
#
# TestHarness.pm:
# Functions to support functional tests for WriteToThem
#
# Copyright (c) 2009 UK Citizens Online Democracy. All rights reserved.
# Email: louise@mysociety.org; WWW: http://www.mysociety.org/
#
# $Id: TestHarness.pm,v 1.1 2009-09-30 13:55:45 louise Exp $
#

package FYR::TestHarness;

use strict;
require 5.8.0;

use FindBin;
use lib "$FindBin::Bin/../../perllib";
use lib "$FindBin::Bin/../../../perllib";

use mySociety::Config;  
mySociety::Config::set_file('../../conf/general');


sub email_n { my $n = shift; return ($n == 666 ? "this-will-bounce\@" : "fyrharness+$n\@") . mySociety::Config::get('EMAIL_DOMAIN'); }
sub name_n { my $n = shift; return "Cate Constituent $n"; }


