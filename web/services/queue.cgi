#!/usr/bin/perl -w -I../../perllib -I../../../perllib
#
# server:
#
# Copyright (c) 2004 UK Citizens Online Democracy. All rights reserved.
# Email: chris@mysociety.org; WWW: http://www.mysociety.org/
#

my $rcsid = ''; $rcsid .= '$Id: queue.cgi,v 1.9 2004-12-16 12:16:17 francis Exp $';

require 5.8.0;

use FCGI;
use FYR;
use FYR::Queue;
use mySociety::DaDem;
use RABX;

use mySociety::Config;
mySociety::Config::set_file('../../conf/general');

use mySociety::WatchUpdate;
my $W = new mySociety::WatchUpdate();

my $req = FCGI::Request();

while ($req->Accept() >= 0) {
    RABX::Server::CGI::dispatch(
            'FYR.Queue.create' => sub {
                return FYR::Queue::create();
            },
            'FYR.Queue.write' => sub {
                return FYR::Queue::write($_[0], $_[1], $_[2], $_[3]);
            },
            'FYR.Queue.recipient_test' => sub {
                return FYR::Queue::recipient_test($_[0]);
            },
            'FYR.Queue.secret' => sub {
                return FYR::Queue::secret();
            },
            'FYR.Queue.confirm_email' => sub {
                return FYR::Queue::confirm_email($_[0]);
            },
            'FYR.Queue.record_questionnaire_answer' => sub {
                return FYR::Queue::record_questionnaire_answer($_[0], $_[1], $_[2]);
            },
            'FYR.Queue.admin_recent_events' => sub {
                return FYR::Queue::admin_recent_events($_[0]);
            },
            'FYR.Queue.admin_message_events' => sub {
                return FYR::Queue::admin_message_events($_[0]);
            },
            'FYR.Queue.admin_get_queue' => sub {
                return FYR::Queue::admin_get_queue($_[0]);
            },
            'FYR.Queue.admin_get_stats' => sub {
                return FYR::Queue::admin_get_stats();
            },
            'FYR.Queue.admin_freeze_message' => sub {
                return FYR::Queue::admin_freeze_message($_[0]);
            },
            'FYR.Queue.admin_thaw_message' => sub {
                return FYR::Queue::admin_thaw_message($_[0]);
            }
          );
    $W->exit_if_changed();
}




