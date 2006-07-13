#!/usr/bin/perl -w -I../../perllib -I../../../perllib
#
# queue.cgi:
# RABX server for FYR queue.
#
# Copyright (c) 2004 UK Citizens Online Democracy. All rights reserved.
# Email: chris@mysociety.org; WWW: http://www.mysociety.org/
#

my $rcsid = ''; $rcsid .= '$Id: queue.cgi,v 1.24 2006-07-13 15:48:07 francis Exp $';

require 5.8.0;

BEGIN {
    use mySociety::Config;
    mySociety::Config::set_file('../../conf/general');
}

use FCGI;
use RABX;

use mySociety::DaDem;
use mySociety::WatchUpdate;

use FYR;
use FYR::Queue;

my $req = FCGI::Request();
my $W = new mySociety::WatchUpdate();

while ($req->Accept() >= 0) {
    RABX::Server::CGI::dispatch(
            'FYR.Queue.create' => sub {
                return FYR::Queue::create();
            },
            'FYR.Queue.write' => sub {
                return FYR::Queue::write($_[0], $_[1], $_[2], $_[3], $_[4], $_[5]);
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
            'FYR.Queue.get_time' => sub {
                return FYR::Queue::get_time();
            },
            'FYR.Queue.get_date' => sub {
                return FYR::Queue::get_date();
            },
            'FYR.Queue.admin_recent_events' => sub {
                return FYR::Queue::admin_recent_events($_[0], $_[1]);
            },
            'FYR.Queue.admin_message_events' => sub {
                return FYR::Queue::admin_message_events($_[0], $_[1]);
            },
            'FYR.Queue.admin_get_queue' => sub {
                return FYR::Queue::admin_get_queue($_[0], $_[1]);
            },
            'FYR.Queue.admin_get_message' => sub {
                return FYR::Queue::admin_get_message($_[0]);
            },
            'FYR.Queue.admin_get_stats' => sub {
                return FYR::Queue::admin_get_stats();
            },
            'FYR.Queue.admin_get_popular_referrers' => sub {
                return FYR::Queue::admin_get_popular_referrers($_[0]);
            },
            'FYR.Queue.admin_freeze_message' => sub {
                return FYR::Queue::admin_freeze_message($_[0], $_[1]);
            },
            'FYR.Queue.admin_thaw_message' => sub {
                return FYR::Queue::admin_thaw_message($_[0], $_[1]);
            },
            'FYR.Queue.admin_no_questionnaire_message' => sub {
                return FYR::Queue::admin_no_questionnaire_message($_[0], $_[1]);
            },
            'FYR.Queue.admin_yes_questionnaire_message' => sub {
                return FYR::Queue::admin_yes_questionnaire_message($_[0], $_[1]);
            },
            'FYR.Queue.admin_set_message_to_error' => sub {
                return FYR::Queue::admin_set_message_to_error($_[0], $_[1]);
            },
            'FYR.Queue.admin_set_message_to_failed' => sub {
                return FYR::Queue::admin_set_message_to_failed($_[0], $_[1]);
            },
            'FYR.Queue.admin_set_message_to_failed_closed' => sub {
                return FYR::Queue::admin_set_message_to_failed_closed($_[0], $_[1]);
            },
            'FYR.Queue.admin_set_message_to_bounce_wait' => sub {
                return FYR::Queue::admin_set_message_to_bounce_wait($_[0], $_[1]);
            },
            'FYR.Queue.admin_add_note_to_message' => sub {
                return FYR::Queue::admin_add_note_to_message($_[0], $_[1], $_[2]);
            },
            'FYR.Queue.admin_set_message_to_ready' => sub {
                return FYR::Queue::admin_set_message_to_ready($_[0], $_[1]);
            },
            'FYR.Queue.admin_get_diligency_queue' => sub {
                return FYR::Queue::admin_get_diligency_queue($_[0]);
            }
          );
    $W->exit_if_changed();
}




