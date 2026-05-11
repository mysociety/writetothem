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
    'NIA' => { 'title' => 'MLA', 'first_names' => ['Connor', 'Siobhan', 'Patrick', 'Aoife'], 'surnames' => ['Murphy', 'Kelly', 'ONeill', 'Lynch'] },
    'EUP' => { 'title' => 'MEP', 'first_names' => ['Edward', 'Catherine', 'William', 'Margaret'], 'surnames' => ['Harrison', 'Thompson', 'White', 'Green'] },
};

sub generate_representative {
    my ($area_id, $area_type) = @_;
    
    # Get template for this area type, default to councillor if unknown
    my $template = $rep_templates->{$area_type} || $rep_templates->{'LBW'};
    
    # Generate deterministic but varied names based on area_id
    srand($area_id);
    my $first_name = $template->{first_names}->[rand(@{$template->{first_names}})];
    my $surname = $template->{surnames}->[rand(@{$template->{surnames}})];
    
    # Generate unique rep ID based on area
    my $rep_id = int($area_id / 10) + ($area_id % 1000);
    
    return {
        'id' => $rep_id,
        'name' => "$first_name $surname $template->{title}",
        'type' => $area_type,
        'voting_area' => $area_id,
        'email' => lc("$first_name.$surname\@example.org"),
        'method' => 'email',
    };
}

our $rep_responses = {};

sub get_rep_id_for_area {
    my $area_id = shift;
    
    # Look up area type from MapIt
    my $area_type = get_area_type_from_mapit($area_id);
    
    my $key = "area_${area_id}_type_${area_type}";
    if (my $rep_id = $CACHE->get($key)) {
        return $rep_id;
    } else {
        # Generate representative for this area
        my $rep = generate_representative($area_id, $area_type);
        my $rep_key = "rep_$rep->{id}";
        
        # Cache both the representative and the area->rep mapping
        $CACHE->set($rep_key, $rep);
        $CACHE->set($key, $rep->{id});
        
        return $rep->{id};
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




