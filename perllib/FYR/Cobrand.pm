#!/usr/bin/perl -w
#
# Cobrand.pm:
# Cobranding for WriteToThem.
#
# 
# Copyright (c) 2009 UK Citizens Online Democracy. All rights reserved.
# Email: louise@mysociety.org. WWW: http://www.mysociety.org
#
# $Id: Cobrand.pm,v 1.1 2009-09-29 16:51:46 louise Exp $

package FYR::Cobrand;
use strict;
use Carp;


=item cobrand_handle Q

Given a query that has the name of a site set, return a handle to the Util module for that
site, if one exists, or zero if not.

=cut
sub cobrand_handle {
    my $cobrand = shift;

    our %handles;

    # Once we have a handle defined, return it.
    return $handles{$cobrand} if defined $handles{$cobrand};

    my $cobrand_class = ucfirst($cobrand);
    my $class = "Cobrands::" . $cobrand_class . "::Util";
    eval "use $class";

    eval{ $handles{$cobrand} = $class->new };
    $handles{$cobrand} = 0 if $@;
    return $handles{$cobrand};
}


=item base_url_for_emails COBRAND

Return the base url to use in links in emails for the cobranded 
version of the site

=cut

sub base_url_for_emails {
    my ($cobrand) = @_;
    my $handle;
    if ($cobrand){
        $handle = cobrand_handle($cobrand);
    }
    if ( !$cobrand ) {
        return mySociety::Config::get('BASE_URL');
    }
    if ( !$handle || ! $handle->can('base_url_for_emails')){
        return "http://" . $cobrand . "." . mySociety::Config::get('WEB_DOMAIN');
    } else {
        return $handle->base_url_for_emails();
    }
}

1;
