#!/usr/bin/perl -w -I../../perllib -I../../commonlib/perllib
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

use mySociety::DaDem;
use mySociety::WatchUpdate;

use FYR;
use FYR::Queue;

my $req = FCGI::Request( \*STDIN, \*STDOUT, \*STDERR, \%ENV, 0, 1 );
my $W = new mySociety::WatchUpdate();

# Signal handling, so as to die after current request, not during
my $exit_requested = 0;
$SIG{TERM} = $SIG{USR1} = sub {
    $exit_requested = 1;
};

while ($req->Accept() >= 0) {
    RABX::Server::CGI::dispatch(
            'FYR.Queue.create' => sub {
                return FYR::Queue::create();
            },
            'FYR.Queue.create_group' => sub {
                return FYR::Queue::create_group();
            },
            'FYR.Queue.check_group_unused' => sub {
                return FYR::Queue::check_group_unused($_[0]);
            },
            'FYR.Queue.write_messages' => sub {
                return FYR::Queue::write_messages($_[0], $_[1], $_[2], $_[3], $_[4], $_[5], $_[6], $_[7]);
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
            'FYR.Queue.get_questionnaire_message' => sub {
                return FYR::Queue::get_questionnaire_message($_[0]);
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
                return FYR::Queue::admin_get_stats($_[0]);
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
            'FYR.Queue.admin_scrub_data' => sub {
                return FYR::Queue::admin_scrub_data($_[0], $_[1]);
            },
            'FYR.Queue.admin_set_message_to_ready' => sub {
                return FYR::Queue::admin_set_message_to_ready($_[0], $_[1]);
            },
            'FYR.Queue.admin_get_diligency_queue' => sub {
                return FYR::Queue::admin_get_diligency_queue($_[0]);
            },
            'FYR.Queue.admin_get_wire_email' => sub {
                return FYR::Queue::admin_get_wire_email($_[0], $_[1]);
            },
            'FYR.Queue.admin_update_recipient' => sub {
                return FYR::Queue::admin_update_recipient($_[0], $_[1], $_[2]);
            },
          );
    $W->exit_if_changed();
    last if $exit_requested;
}




