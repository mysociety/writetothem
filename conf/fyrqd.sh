#!/bin/sh

# NOTE: FOR FAX DAEMON ONLY

# FreeBSD script for FYR queue fax daemon.  
# Put it in /usr/local/etc/rc.d, and add this to /etc/rc.conf
#   fyrqd_enable="YES"
# And then to start it
#   /usr/local/etc/rc.d/fyrqd.sh start

# PROVIDE: fyrqd
# REQUIRE: LOGIN
# BEFORE:  securelevel
# KEYWORD: FreeBSD shutdown

. "/etc/rc.subr"

name="fyrqd"
rcvar=`set_rcvar`

command="/home/fyr/mysociety/fyr/bin/fyrqd"
command_args="--fax"
pidfile="/home/fyr/$name.pid"

# read configuration and set defaults
load_rc_config "$name"

: ${fyrqd_user="fyr"}
: ${fyrqd_chdir="/home/fyr/mysociety/fyr/bin"}
: ${command_interpreter="/usr/bin/perl"}
run_rc_command "$1"
