<?php
/*
 * votingarea.php:
 * Stuff about voting and administrative areas.  "Voting Area" is the
 * terminology we use to mean any geographical region for which an
 * elected representative is returned.
 * 
 * Copyright (c) 2004 UK Citizens Online Democracy. All rights reserved.
 * Email: chris@mysociety.org; WWW: http://www.mysociety.org
 *
 * $Id: votingarea.php,v 1.49 2008-01-11 17:51:15 matthew Exp $
 * 
 */

/* va_inside
 * For any constant which refers to a voting area which is inside an
 * administrative area, there is an entry in this array saying which type of
 * area it's inside. */
$va_inside = array(
        'LBW' => 'LBO',

        'LAC' => 'LAS',
        'LAE' => 'LAS',

        'CED' => 'CTY',

        'DIW' => 'DIS',

        'UTE' => 'UTA',
        'UTW' => 'UTA',

        'LGE' => 'LGD',

        'COP' => 'COI',

        'MTW' => 'MTD',

        'SPE' => 'SPA',
        'SPC' => 'SPA',

        'WAE' => 'WAS',
        'WAC' => 'WAS',

        'NIE' => 'NIA',

        'WMC' => 'WMP',
        'HOC' => 'HOL',

        'EUR' => 'EUP'
    );

/* $va_parent_types
Types which are bodies, rather than constituencies/wards within them */
$va_parent_types = array_unique(array_values($va_inside));

/* $va_child_types
Types which are constituencies/wards, rather than the bodies they are in */
$va_child_types = array_keys($va_inside);

/* $va_council_parent_types
Types which are local councils, such as districts, counties,
unitary authorities and boroughs. */
$va_council_parent_types = array('DIS', 'LBO', 'MTD', 'UTA', 'LGD', 'CTY', 'COI');

/* $va_council_child_types
Types which are wards or electoral divisions in councils. */
$va_council_child_types = array('DIW', 'LBW', 'MTW', 'UTE', 'UTW', 'LGE', 'CED', 'COP');

/* $va_aliases
Names for sets of representative types */
$va_aliases = array(
    /* Councillors of whatever sort */
    'council' => $va_council_child_types,
    /* MPs */
    'westminstermp' => array('WMC'),
    /* Devolved assembly members / MSPs */
    'regionalmp' => array('SPC','SPE','WAC','WAE','LAC','LAE','NIE'),
    /* MEPs */
    'mep' => array('EUR')
);

/* $va_precise_names
Names of each child type. */
$va_precise_names = array(
        'LBW' => _('London Borough Councillors'),

        'LAC' => _('London Assembly Constituency Members'),
        'LAE' => _('London Assembly Party List Members'),

        'CED' => _('County Councillors'),

        'DIW' => _('District Councillors'),

        'UTE' => _('Unitary Authority ED Councillors'),
        'UTW' => _('Unitary Authority Ward Councillors'),

        'LGE' => _('Local Government District Councillors'),

        'COP' => _('Councillors of the Isles'),

        'MTW' => _('Metropolitan District Councillors'),

        'SPE' => _('Scottish Parliament Party List Members'),
        'SPC' => _('Scottish Parliament Constituency Members'),

        'WAE' => _('Senedd Party List Members'),
        'WAC' => _('Senedd Constituency Members'),

        'NIE' => _('Northern Ireland Assembly Members'),

        'WMC' => _('Members of Parliament'),
        'HOC' => _('Members of the House of Lords'),

        'EUR' => _('Members of the European Parliament')
    );


/* va_display_order
 * Suggested "increasing power" display order for representatives. In cases
 * where one category of representatives is elected on a constituency and an
 * electoral area, as with top-up lists in the Scottish Parliament, an array of
 * the equivalent types is placed in this array. XXX should this be in FYR? */
$va_display_order = array(
        /* District councils */
        'DIW', 'LBW',
        /* unitary-type councils */
        'MTW', 'UTW', 'UTE', 'LGE', 'COP',
        /* county council */
        'CED',
        /* various devolved assemblies */
        array('LAC', 'LAE'),
        array('WAC', 'WAE'),
        array('SPC', 'SPE'),
        'NIE',
        /* Westminster Parliament */
        'WMC',
    );

/* va_salaried
 * Array indicating whether representatives at the various levels typically
 * receive a salary for their work. */
$va_salaried = array(
        'LBW' => 0,

        'LAC' => 1,
        'LAE' => 1,

        'CED' => 0,

        'DIW' => 0,

        'UTE' => 0,
        'UTW' => 0,

        'LGE' => 0,

        'MTW' => 0,

        'COP' => 0, /* XXX don't know but assume unpaid -- check */

        'SPE' => 1,
        'SPC' => 1,

        'WAE' => 1,
        'WAC' => 1,

        'NIE' => 1,

        'WMC' => 1,
        'HOL' => 1, /* Although in contrast to MPs, Lords are paid according to attendance */

        'EUR' => 1
    );

// If you update this, also update in perllib/mySociety/VotingArea.pm
$va_type_name = array(
        'LBO' => _("London Borough"),
        'LBW' => _("ward"),

        'GLA' => _("Greater London Authority"),

        'LAS' => _("London Assembly"),
        'LAC' => _("constituency"),
        'LAE' => _("Electoral Region"),

        'CTY' => _("County"),
        'CED' => _("Electoral Division"),

        'DIS' => _("District"),
        'DIW' => _("ward"),

        'LGD' => _("Local Council"),
        'LGE' => _("Electoral Area"),

        'UTA' => _("Unitary Authority"),
        'UTE' => _("Electoral Division"),
        'UTW' => _("ward"),

        'MTD' => _("Metropolitan District"),
        'MTW' => _("ward"),

        'COI' => _("Council of the Isles"),
        'COP' => _("parish"),

        'SPA' => _("Scottish Parliament"),
        'SPE' => _("Electoral Region"),
        'SPC' => _("constituency"),

        'WAS' => _("Senedd"),
        'WAE' => _("Electoral Region"),
        'WAC' => _("constituency"),

        'NIA' => _("Northern Ireland Assembly"),
        'NIE' => _("constituency"), # These are the same as the Westminster
                                # constituencies but return several members
                                # using a proportional system. It looks like
                                # most people just refer to them as
                                # "constituencies".
        
        'WMP' => _("House of Commons"),
        'WMC' => _("constituency"),
        'HOL' => _("House of Lords"),
        'HOC' => _("constituency"),

        'EUP' => _("European Parliament"),
        'EUR' => _("region"),
    );

$va_rep_name = array(
    'LBW' => _('councillor'),
    'GLA' => _('Mayor'), # "of London"?
    'LAC' => _('London Assembly Member'),
    'LAE' => _('London Assembly Member'),
    'CED' => _('county councillor'),
    'DIW' => _('district councillor'),
    'LGE' => _('councillor'),
    'UTE' => _('councillor'),
    'UTW' => _('councillor'),
    'MTW' => _('councillor'),
    'COP' => _('councillor'),
    'SPE' => _('MSP'),
    'SPC' => _('MSP'),
    'WAE' => _('MS'),
    'WAC' => _('MS'),
    'NIE' => _('MLA'),
    'WMC' => _('MP'),
    'HOC' => _('Lord'),
    'EUR' => _('MEP'),
);

$va_rep_name_long = array(
    'LBW' => _('councillor'),
    'GLA' => _('Mayor'), # "of London"?
    'LAC' => _('London Assembly Member'),
    'LAE' => _('London Assembly Member'),
    'CED' => _('county councillor'),
    'DIW' => _('district councillor'),
    'LGE' => _('councillor'),
    'UTE' => _('councillor'),
    'UTW' => _('councillor'),
    'MTW' => _('councillor'),
    'COP' => _('councillor'),
    'SPE' => _('Member of the Scottish Parliament'),
    'SPC' => _('Member of the Scottish Parliament'),
    'WAE' => _('Member of the Senedd'),
    'WAC' => _('Member of the Senedd'),
    'NIE' => _('Member of the Legislative Assembly'),
    'WMC' => _('Member of Parliament'),
    'HOC' => _('Member of Parliament'),
    'EUR' => _('Member of the European Parliament')
);

$va_rep_name_plural = array(
    'LBW' => _('councillors'),
    'GLA' => _('Mayors'), # "of London"?
    'LAC' => _('London Assembly Members'),
    'LAE' => _('London Assembly Members'),
    'CED' => _('county councillors'),
    'DIW' => _('district councillors'),
    'UTE' => _('councillors'),
    'UTW' => _('councillors'),
    'LGE' => _('councillors'),
    'MTW' => _('councillors'),
    'COP' => _('councillors'),
    'SPE' => _('MSPs'),
    'SPC' => _('MSPs'),
    'WAE' => _('MSs'),
    'WAC' => _('MSs'),
    'NIE' => _('MLAs'),
    'WMC' => _('MPs'),
    'HOC' => _('Lords'),
    'EUR' => _('MEPs')
);

$va_rep_name_long_plural = array(
    'LBW' => _('councillors'),
    'GLA' => _('Mayors'), # "of London"?
    'LAC' => _('London Assembly Members'),
    'LAE' => _('London Assembly Members'),
    'CED' => _('county councillors'),
    'DIW' => _('district councillors'),
    'UTE' => _('councillors'),
    'UTW' => _('councillors'),
    'LGE' => _('councillors'),
    'MTW' => _('councillors'),
    'COP' => _('councillors'),
    'SPE' => _('Members of the Scottish Parliament'),
    'SPC' => _('Members of the Scottish Parliament'),
    'WAE' => _('Members of the Senedd'),
    'WAC' => _('Members of the Senedd'),
    'NIE' => _('Members of the Legislative Assembly'),
    'WMC' => _('Members of Parliament'),
    'HOC' => _('Members of Parliament'),
    'EUR' => _('Members of the European Parliament')
);

$va_rep_suffix = array(
    'LAC' => _('AM'),
    'LAE' => _('AM'),
    'SPE' => _('MSP'),
    'SPC' => _('MSP'),
    'WAE' => _('MS'),
    'WAC' => _('MS'),
    'NIE' => _('MLA'),
    'WMC' => _('MP'),
    'EUR' => _('MEP')
);

$va_rep_prefix = array(
    'LBW' => _('Cllr'),
    'GLA' => _('Mayor'), # "of London"?
    'CED' => _('Cllr'),
    'DIW' => _('Cllr'),
    'UTE' => _('Cllr'),
    'UTW' => _('Cllr'),
    'LGE' => _('Cllr'),
    'MTW' => _('Cllr'),
    'COP' => _('Cllr'),
);

/* va_responsibility_description
 * Responsibilities of each elected body. XXX should copy these out of
 * Whittaker's Almanac or whatever. */
$va_responsibility_description = array(
    'DIS' =>
            _("The District Council is responsible for
            <strong>local services</strong>, including <strong>planning</strong>, <strong>council housing</strong>, and
            <strong>rubbish collection</strong>."),
    'LBO' => _("
The Borough Council is responsible for <strong>local services</strong>,
including <strong>planning</strong>, <strong>council housing</strong>,
<strong>rubbish collection</strong>, <strong>local roads</strong>, and
<strong>public paths</strong>.
"),
    'LAS' => _("
Areas covered include the
Mayor's budget, <strong>culture</strong>, <strong>sport and tourism</strong>,
<strong>health</strong>, <strong>planning</strong>, <strong>transport</strong>,
and <strong>trunk roads</strong>.
"),
    'MTD' =>
            _("The Metropolitan District Council is
responsible for all aspects of <strong>local services and policy</strong>, including
<strong>planning</strong>, <strong>transport</strong>,
<strong>roads</strong> (except trunk roads and motorways), public rights of way,
<strong>education</strong>, <strong>social services</strong> and <strong>libraries</strong>."),
    'UTA' => 
            _("The Unitary Authority is
responsible for all aspects of <strong>local services and policy</strong>, including
<strong>planning</strong>, <strong>transport</strong>,
<strong>roads</strong> (except trunk roads and motorways), public rights of way,
<strong>education</strong>, <strong>social services</strong> and <strong>libraries</strong>."),
    'COI' => _("
The Council of the Isles is responsible for <strong>education</strong>,
<strong>housing</strong>, <strong>planning</strong>, <strong>water and
sewage</strong> and various other local matters including
<strong>tourism</strong>, <strong>development</strong> and running <strong>the
airport</strong>.
"),
    'CTY' =>
            _("The County Council is responsible for <strong>local
services</strong>, including <strong>education</strong>, <strong>social services</strong>,
<strong>transport</strong>, <strong>roads</strong>
(except trunk roads and motorways), public rights of way, and
<strong>libraries</strong>."),
    'LGD' =>
            _("The Local Council is responsible for
            <strong>local services</strong>, including 
            <strong>waste and recycling</strong>, 
            <strong>leisure and community</strong>, 
            <strong>building control</strong> and
            <strong>local economic and cultural development</strong>."),
    'WMP' =>
            _("The House of Commons is responsible for
            <strong>making laws in the UK and for overall scrutiny of all aspects of
            government</strong>."),
    'EUP' => _("
They <strong>scrutinise proposed European laws</strong> and the <strong>budget of the
European Union</strong>, and provide <strong>oversight of its other
decision-making bodies</strong>.
"),
    'SPA' => _("
The Scottish Parliament is responsible for a wide range of <strong>devolved
matters</strong> in which it sets policy independently of the London
Parliament. Devolved matters include <strong>education</strong>,
<strong>health</strong>, <strong>agriculture</strong>, <strong>justice</strong>
and <strong>prisons</strong>. It also has some tax-raising powers.
"),
    'WAS' => _("
The Senedd has a wide range of powers over areas including
<strong>economic development</strong>, <strong>transport</strong>,
<strong>finance</strong>, <strong>local government</strong>,
<strong>health</strong>, <strong>housing</strong> and <strong>the Welsh
Language</strong>.
"),
    'NIA' => _('
The Northern Ireland Assembly has full authority over "transferred matters",
which include <strong>agriculture</strong>, <strong>education</strong>,
<strong>employment</strong>, the <strong>environment</strong> and
<strong>health</strong>.
')
    );

/* va_is_fictional_area ID
 * Does ID refer to a test area (i.e., one invented for our own purposes)? */
function va_is_fictional_area($id) {
    if ($id >= 1000001 && $id <= 1000008)
        return true;
    else
        return false;
}

/* Special area IDs (see perllib/mysociety/VotingArea.pm for more) */
$HOC_AREA_ID = 900008;

