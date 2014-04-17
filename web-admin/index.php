<?
/*
 * index.php:
 * Admin index for FaxYourRepresentative.
 * 
 * Copyright (c) 2004 UK Citizens Online Democracy. All rights reserved.
 * Email: francis@mysociety.org. WWW: http://www.mysociety.org
 *
 * $Id: index.php,v 1.27 2010-01-18 12:16:28 louise Exp $
 * 
 */

require_once "../conf/general";
require_once "../phplib/admin-fyrqueue.php";
require_once "../phplib/admin-reps.php";
require_once "../commonlib/phplib/admin-ratty.php";
require_once "../commonlib/phplib/admin-phpinfo.php";
require_once "../commonlib/phplib/admin-serverinfo.php";
require_once "../commonlib/phplib/admin-configinfo.php";
require_once "../commonlib/phplib/admin-embed.php";
require_once "../commonlib/phplib/admin.php";

$pages = array(
    new ADMIN_PAGE_FYR_QUEUE(),
    new ADMIN_PAGE_RATTY(
            'fyr-abuse',
            "Message Abuse",
            "These rules apply to messages when they are first submitted onto the message queue by the user.",
            <<<EOF
What to do with a message for which this rule fires. This should be either:
<dl>
<dt>freeze</dt>
<dd>to freeze the message for inspection by an administrator (preventing
delivery); or</dd>
<dt><em>TEMPLATE</em></dt>
<dd>to drop (abandon) the message and display the named <em>TEMPLATE</em>
(for instance 'problem-similar' will display
<a href="https://www.writetothem.com/problem-similar">the notice about
cut-and-paste messages</a>). Use
"<a href="https://www.writetothem.com/problem-generic">problem-generic</a>"
if no more specific explanation of why the message was rejected is
available.</dd>
</dl>
EOF
            ),
    /* Disabled for now - see fyr_rate_limit in fyr.php
        new ADMIN_PAGE_RATTY(
            'fyr-web',
            "Web Abuse",
            "These rules limit access to the WriteToThem website.",
            <<<EOF
An HTML fragment which is displayed to the user in an error page when
the rule fires. If left blank, the message "Sorry, we are experiencing
technical difficulties" is displayed.
EOF
        ), */
    null, // space separator on menu
    new ADMIN_PAGE_REPS,
);

if (OPTION_ADMIN_SERVICES_CGI)
    $pages[] = new ADMIN_PAGE_EMBED('fyrmatch', 'Councillor Data', OPTION_ADMIN_SERVICES_CGI . 'match.cgi');

array_push($pages,
    new ADMIN_PAGE_EMBED('fyrsignupgraph', 'Sent messages graph', OPTION_BASE_URL . '/fyr-live-signups.png'),
    new ADMIN_PAGE_EMBED('fyrfaxgraph', 'Faxes created graph', OPTION_BASE_URL . '/fyr-live-faxes.png')
);

array_push($pages,
    null, // space separator on menu
    new ADMIN_PAGE_SERVERINFO,
    new ADMIN_PAGE_CONFIGINFO,
    new ADMIN_PAGE_PHPINFO
);

admin_page_display(str_replace("http://", "", OPTION_BASE_URL), $pages);

?>

