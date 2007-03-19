<?
/*
 * index.php:
 * Admin index for FaxYourRepresentative.
 * 
 * Copyright (c) 2004 UK Citizens Online Democracy. All rights reserved.
 * Email: francis@mysociety.org. WWW: http://www.mysociety.org
 *
 * $Id: index.php,v 1.23 2007-03-19 10:29:49 francis Exp $
 * 
 */

require_once "../conf/general";
require_once "../phplib/admin-fyrqueue.php";
require_once "../../services/Ratty/phplib/admin-ratty.php";
require_once "../../services/phplib/admin-reps.php";
require_once "../../phplib/admin-phpinfo.php";
require_once "../../phplib/admin-serverinfo.php";
require_once "../../phplib/admin-configinfo.php";
require_once "../../phplib/admin-embed.php";
require_once "../../phplib/admin.php";

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
<a href="http://www.writetothem.com/problem-similar">the notice about
cut-and-paste messages</a>). Use
"<a href="http://www.writetothem.com/problem-generic">problem-generic</a>"
if no more specific explanation of why the message was rejected is
available.</dd>
</dl>
EOF
            ),
    new ADMIN_PAGE_RATTY(
            'fyr-web',
            "Web Abuse",
            "These rules limit access to the WriteToThem website.",
            <<<EOF
An HTML fragment which is displayed to the user in an error page when
the rule fires. If left blank, the message "Sorry, we are experiencing
technical difficulties" is displayed.
EOF
        ),
    null, // space separator on menu
    new ADMIN_PAGE_REPS,
    new ADMIN_PAGE_EMBED('fyrmatch', 'Councillor Data', OPTION_ADMIN_SERVICES_CGI . 'match.cgi'),
    new ADMIN_PAGE_EMBED('fyremails', 'Standard Emails', 'https://secure.mysociety.org/cvstrac/wiki?p=StandardEmails'),
    new ADMIN_PAGE_EMBED('fyrsignupgraph', 'Sent messages graph', OPTION_BASE_URL . '/fyr-live-signups.png'),
    new ADMIN_PAGE_EMBED('fyrfaxgraph', 'Faxes created graph', OPTION_BASE_URL . '/fyr-live-faxes.png'),
    null, // space separator on menu
    new ADMIN_PAGE_SERVERINFO,
    new ADMIN_PAGE_CONFIGINFO,
    new ADMIN_PAGE_PHPINFO,
);

admin_page_display(str_replace("http://", "", OPTION_BASE_URL), $pages);

?>

