#!/usr/bin/perl -w -I../perllib -I../commonlib/perllib
#
# queue.cgi:
# RABX server for FYR queue.
#
# Copyright (c) 2004 UK Citizens Online Democracy. All rights reserved.
# Email: chris@mysociety.org; WWW: http://www.mysociety.org/
#

my $rcsid = ''; $rcsid .= '$Id: queue.cgi,v 1.36 2008-06-24 11:50:23 francis Exp $';

require 5.8.0;
use strict;

BEGIN {
    use mySociety::Config;
    mySociety::Config::set_file('../../conf/general');
}

use FCGI;
use RABX;
use Cache::FastMmap;

use mySociety::DaDem;
use mySociety::WatchUpdate;

my $req = FCGI::Request( \*STDIN, \*STDOUT, \*STDERR, \%ENV, 0, 1 );
my $W = new mySociety::WatchUpdate();

# Signal handling, so as to die after current request, not during
my $exit_requested = 0;
$SIG{TERM} = $SIG{USR1} = sub {
    $exit_requested = 1;
};

my $CACHE = Cache::FastMmap->new(
 share_file => '/tmp/dadem_cache',
 cache_size => '10m',
);

my $all_reps = {
    1 => {
        'id' => 1, 'name' => 'John Smith MP', 'type' => 'WMC', voting_area => 169516, email => 'email@example.org', 'method' => 'email',
    },
    2 => {
        'id' => 2, 'name' => 'Jane Smith councillor', 'type' => 'UTE', voting_area => '166032', email => 'email@example.org', 'method' => 'email',
    },
    3 => {
        'id' => 3, 'name' => 'Mark Smith Senedd Member', 'type' => 'WAE', voting_area => '144304', email => 'email@example.org', 'method' => 'email',
    },
    4 => {
        'id' => 4, 'name' => 'Mandy Smith Area Senedd Member', 'type' => 'WAC', voting_area => '148026', email => 'email@example.org', 'method' => 'email',
    },
    5 => {
        'id' => 5, 'name' => 'James Jones MSP', 'type' => 'SPC', voting_area => '134935', email => 'email@example.org', 'method' => 'email',
    },
    6 => {
        'id' => 6, 'name' => 'Tony Andrews Councillor', 'type' => 'DIW', voting_area => '145297', email => 'email@example.org', 'method' => 'email',
    },
    7 => {
        'id' => 7, 'name' => 'Tim Smith Councillor', 'type' => 'DIW', voting_area => '145297', email => 'email@example.org', 'method' => 'email',
    }
};

our $default_rep = {
    'id' => 7, 'name' => 'Default Councillor', 'type' => 'UTE', voting_area => '166032',
};

our $rep_responses = {};

sub get_rep_id_for_area {
    my $id = shift;
    my $key = "area_$id";
    if (my $rep_id = $CACHE->get($key)) {
        return $rep_id;
    } else {
        my $rep_id = int rand(10000);
        my $rep_key = "rep_$rep_id";
        while ($CACHE->get($rep_key)) {
            $rep_id = int rand(10000);
            $rep_key = "rep_$rep_id";
        }

        my $pick = int rand(7) + 1;
        my $rep = $all_reps->{$pick};
        $rep->{id} = $rep_id;
        $rep->{voting_area} = $id;
        $CACHE->set($rep_key, $rep);

        return $rep_id;
    }
}

while ($req->Accept() >= 0) {
    RABX::Server::CGI::dispatch(
            'DaDem.get_representatives' => [
                sub {
                  my $id = shift;

                  unless (ref $id) {
                      return [get_rep_id_for_area($id)];
                  }

                  my $ret = {};
                  for my $id (@$id) {
                    $ret->{$id} = [get_rep_id_for_area($id)];
                  }

                  return $ret;
                },
            ],
            'DaDem.get_area_status' => [
                sub { return []; return DaDem::get_area_status($_[0]); },
            ],
            'DaDem.get_area_statuses' => [
                sub { return []; return DaDem::get_area_statuses(); },
            ],
            'DaDem.search_representatives' => [
                sub { return []; return DaDem::search_representatives($_[0]); },
            ],
            'DaDem.get_bad_contacts' => sub {
                return [];
                return DaDem::get_bad_contacts();
            },
            'DaDem.get_user_corrections' => sub {
                return [];
                return DaDem::get_user_corrections();
            },
            'DaDem.get_representative_info' => [
                sub {
                    my $id = shift;
                    my $key = "rep_$id";
                    return $CACHE->get($key);
                },
            ],
            'DaDem.get_representatives_info' => [
                sub {
                    my $ids = shift;
                    my %ret = ();
                    for my $id (@$ids) {
                        my $key = "rep_$id";
                        $ret{$id} = $CACHE->get($key);
                    }

                    return \%ret;
                },
            ],
            'Gaze.get_country_from_ip' => [
                sub { return 'uk'; },
            ],
            'Ratty.test' => [
                sub { return undef; },
            ],
          );
    $W->exit_if_changed();
    last if $exit_requested;
}




