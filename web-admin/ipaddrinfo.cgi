#!/usr/bin/perl -w
#
# ipaddrinfo.cgi:
# Show information on an IP address.
#
# Copyright (c) 2005 UK Citizens Online Democracy. All rights reserved.
# Email: chris@mysociety.org; WWW: http://www.mysociety.org/
#

my $rcsid = ''; $rcsid .= '$Id: ipaddrinfo.cgi,v 1.3 2008-02-04 22:50:29 matthew Exp $';

use strict;

require 5.8.0;

use CGI qw(-nosticky);
use mySociety::CGIFast;
use Geo::IP;
use Geography::Countries;
use HTML::Entities;
use IO::Handle;
use Regexp::Common;
use Socket;

my $g = new Geo::IP(GEOIP_STANDARD);

while (my $q = new mySociety::CGIFast()) {
    STDOUT->autoflush(1);
    my $addr = $q->param('ipaddr');
    print $q->header(),
            $q->start_html($addr ? "IP address lookup: $addr" : "IP address lookup"),
            $q->start_form(-method => 'GET'),
            $q->p(
                "Look up:",
                $q->textfield(-name => 'ipaddr'),
                $q->submit(-label => 'Go')
            ),
            $q->end_form();

    if ($addr && $addr =~ m#^$RE{net}{IPv4}$#) {
        print $q->h1('DNS name');

        my $name = undef;
        eval {
            local $SIG{ALRM} = sub { die "timed out\n"; };
            alarm(10);
            $name = gethostbyaddr(inet_aton($addr), AF_INET);
            alarm(0);
        };

        if (defined($name)) {
            print $q->p(encode_entities($name));
        } elsif ($@) {
            print $q->p($q->em('DNS lookup timed out'));
        } else {
            print $q->p($q->em(encode_entities($!)));
        }
        
        print $q->h1('Country');

        my $cc = $g->country_code_by_addr($addr);
        if ($cc) {
            print $q->table(
                        $q->Tr([
                            $q->th('Code') . $q->td($cc),
                            $q->th('Name') . $q->td(encode_entities(country($cc)))
                        ])
                    );
        } else {
            print $q->p($q->em('No country information available'));
        }

        print $q->h1('WHOIS data');

        # XXX we rely on a smart-ish whois program
        open(WHOIS, '-|', "whois", $addr);

        print $q->start_pre();
        while (defined($_ = <WHOIS>)) {
            chomp();
            print encode_entities($_), "\n";
        }
        print $q->end_pre();
        close(WHOIS);
    }
}
