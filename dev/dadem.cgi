#!/usr/bin/perl -w -I../perllib -I../commonlib/perllib
#
# dadem.cgi:
# RABX server for FYR queue - Development version to pass dummy data back.
#
# Copyright (c) 2004 UK Citizens Online Democracy. All rights reserved.
# Email: chris@mysociety.org; WWW: http://www.mysociety.org/
#

my $rcsid = ''; $rcsid .= '$Id: queue.cgi,v 1.36 2008-06-24 11:50:23 francis Exp $';

require 5.8.0;
use strict;

BEGIN {
    use mySociety::Config;
    mySociety::Config::set_file('/var/www/html/writetothem/conf/general');
}

use FCGI;
use RABX;
use Cache::FastMmap;
use LWP::UserAgent;
use HTTP::Request;
use JSON;

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

my $ua = LWP::UserAgent->new;
my $mapit_api_key = mySociety::Config::get('MAPIT_API_KEY', '');
my $mapit_url = mySociety::Config::get('MAPIT_URL', '');

sub get_area_type_from_mapit {
    my $area_id = shift;
        
    # Check cache first
    my $cache_key = "area_type_$area_id";
    if (my $type = $CACHE->get($cache_key)) {
        return $type;
    }
    
    # Call MapIt API to get area info
    my $url = "https://mapit.mysociety.org/area/$area_id";
        
    # Create request with API key header
    my $request = HTTP::Request->new('GET', $url);
    if ($mapit_api_key) {
        $request->header('X-Api-Key' => $mapit_api_key);
    }
    
    my $response = $ua->request($request);
    if ($response->is_success) {
        my $data = eval { JSON::decode_json($response->content) };
        if ($@) {
        } elsif ($data && $data->{type}) {
            my $type = $data->{type};
            $CACHE->set($cache_key, $type, 3600);  # Cache for 1 hour
            return $type;
        } else {
        }
    }
    
    # Default to LBW if we can't determine type
    return 'LBW';
}

# Template representatives by area type for generating dynamic dummy data
my $rep_templates = {
    'WMC' => { 'title' => 'MP', 'first_names' => ['Sarah', 'John', 'Emma', 'David'], 'surnames' => ['Wilson', 'Smith', 'Brown', 'Jones'] },
    'LBW' => { 'title' => 'Councillor', 'first_names' => ['Jane', 'Michael', 'Lisa', 'Robert'], 'surnames' => ['Taylor', 'Davis', 'Wilson', 'Miller'] },
    'LAC' => { 'title' => 'Assembly Member', 'first_names' => ['Mark', 'Sophie', 'James', 'Helen'], 'surnames' => ['Evans', 'Clarke', 'Lewis', 'Walker'] },
    'LAE' => { 'title' => 'Assembly Member', 'first_names' => ['Mark', 'Sophie', 'James', 'Helen'], 'surnames' => ['Evans', 'Clarke', 'Lewis', 'Walker'] },
    'LAS' => { 'title' => 'Assembly Member', 'first_names' => ['Mark', 'Sophie', 'James', 'Helen'], 'surnames' => ['Evans', 'Clarke', 'Lewis', 'Walker'] },
    'SPC' => { 'title' => 'MSP', 'first_names' => ['Fiona', 'Andrew', 'Nicola', 'Malcolm'], 'surnames' => ['MacDonald', 'Campbell', 'Stewart', 'Fraser'] },
    'SPE' => { 'title' => 'MSP', 'first_names' => ['Fiona', 'Andrew', 'Nicola', 'Malcolm'], 'surnames' => ['MacDonald', 'Campbell', 'Stewart', 'Fraser'] },
    'WAC' => { 'title' => 'MS', 'first_names' => ['Gareth', 'Cerys', 'Dylan', 'Sian'], 'surnames' => ['Williams', 'Davies', 'Thomas', 'Roberts'] },
    'NIE' => { 'title' => 'MLA', 'first_names' => ['Connor', 'Siobhan', 'Patrick', 'Aoife'], 'surnames' => ['Murphy', 'Kelly', 'ONeill', 'Lynch'] },
    'EUP' => { 'title' => 'MEP', 'first_names' => ['Edward', 'Catherine', 'William', 'Margaret'], 'surnames' => ['Harrison', 'Thompson', 'White', 'Green'] },
};

# Number of representatives to generate per area, by area type. Types not
# listed here get exactly one (e.g. a single MP or county councillor per
# area), which is correct for those types in reality.
my $rep_seats = {
    # Fixed multi-member counts, accurate for every area of these types:
    'WAC' => 6,  # Senedd constituencies, since the 2026 Senedd Cymru reform
    'SPE' => 7,  # Scottish Parliament regional list members, per region
    'NIE' => 5,  # Northern Ireland Assembly members, per constituency (since 2016)
    'LAE' => 11, # London Assembly list members (London-wide, not per-ward)

    # English/Welsh council wards:
    'LBW' => 3,
    'DIW' => 3,
    'UTE' => 3,
    'UTW' => 3,
    'MTW' => 3,
    'COP' => 3,
    'LGE' => 5, # NI local government District Electoral Areas typically elect 5-7
};

sub generate_representative {
    my ($area_id, $area_type, $seat_index) = @_;
    $seat_index ||= 0;

    # Get template for this area type, default to councillor if unknown
    my $template = $rep_templates->{$area_type} || $rep_templates->{'LBW'};

    # Generate deterministic but varied names based on area_id and seat
    srand($area_id * 100 + $seat_index);
    my $first_name = $template->{first_names}->[rand(@{$template->{first_names}})];
    my $surname = $template->{surnames}->[rand(@{$template->{surnames}})];

    # Generate unique rep ID based on area and seat
    my $rep_id = int($area_id / 10) + ($area_id % 1000);
    $rep_id += $seat_index * 100_000 if $seat_index;

    my $email_local = "$first_name.$surname";
    $email_local .= "-$seat_index" if $seat_index;

    return {
        'id' => $rep_id,
        'name' => "$first_name $surname $template->{title}",
        'type' => $area_type,
        'voting_area' => $area_id,
        'email' => lc("$email_local\@example.org"),
        'method' => 'email',
    };
}

our $rep_responses = {};

sub get_rep_ids_for_area {
    my $area_id = shift;

    # Look up area type from MapIt
    my $area_type = get_area_type_from_mapit($area_id);
    my $seats = $rep_seats->{$area_type} || 1;

    my @rep_ids;
    for my $seat_index (0 .. $seats - 1) {
        my $key = "area_${area_id}_type_${area_type}_seat_${seat_index}";
        my $rep_id = $CACHE->get($key);
        unless ($rep_id) {
            # Generate representative for this area/seat
            my $rep = generate_representative($area_id, $area_type, $seat_index);
            my $rep_key = "rep_$rep->{id}";

            # Cache both the representative and the area->rep mapping
            $CACHE->set($rep_key, $rep);
            $CACHE->set($key, $rep->{id});

            $rep_id = $rep->{id};
        }
        push @rep_ids, $rep_id;
    }

    return \@rep_ids;
}

while ($req->Accept() >= 0) {
    RABX::Server::CGI::dispatch(
            'DaDem.get_representatives' => [
                sub {
                  my $id = shift;

                  unless (ref $id) {
                      return get_rep_ids_for_area($id);
                  }

                  my $ret = {};
                  for my $id (@$id) {
                    $ret->{$id} = get_rep_ids_for_area($id);
                  }

                  return $ret;
                },
            ],
            'DaDem.get_area_status' => [
                sub { return 'none'; },
            ],
            'DaDem.get_area_statuses' => [
                sub { return {}; },
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




