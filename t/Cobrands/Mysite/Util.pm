#!/usr/bin/perl -w
#
# Util.pm:
# Test Cobranding for WriteToThem.
#
#
# Copyright (c) 2009 UK Citizens Online Democracy. All rights reserved.
# Email: louise@mysociety.org. WWW: http://www.mysociety.org
#
# $Id: Util.pm,v 1.1 2009-09-29 16:51:45 louise Exp $

package Cobrands::Mysite::Util;
use strict;
use Carp;

sub new {
    my $class = shift;
    return bless {}, $class;
}

sub base_url_for_emails {
    return 'http://mysite.foremails.example.com';
}

1;
