#!/usr/bin/perl
#
# FYR/FormatHTML.pm:
# Formatting HTML as text.
#
# Copyright (c) 2004 UK Citizens Online Democracy. All rights reserved.
# Email: chris@mysociety.org; WWW: http://www.mysociety.org/
#
# $Id: FormatHTML.pm,v 1.1 2004-10-05 16:42:45 chris Exp $
#

package FYR::FormatHTML;

use mySociety::Util;

use IO::File;

=item as_text HTML

Take the given HTML fragment, which should be a valid string in text/html;
charset=utf-8 which could appear between <body> and </body>, and format it as
plain text. Returns text/plain; charset=utf-8.

=cut
sub as_text ($) {
    my ($html) = @_;

    # we do this the thick-as-pig-shit way to start with.
    my ($h, $name) = mySociety::Util::named_tempfile('.html');
    $h->print(
        '<html><head><meta http-equiv="Content-Type" content="text/html; charset=utf-8"><title></title></head><body>',
        $html,
        '</body></html')
        or die "unable to save HTML in temporary file \"$name\"; error was $!";
    $h->close();
    
    open(TEXT, "LC_ALL=en_GB.UTF8 COLUMNS=60 lynx -dump '$name'|")
        or die "lynx: $!";  # XXX need to do something more intelligent here.

    my $output = join('', <TEXT>);
    close(TEXT);
    unlink($name);

    # lynx likes to "justify" things.
    $output =~ s/([^\n ]) {1,}([^ ])/$1 $2/mg;

    return $output . "\n";
}

1;
