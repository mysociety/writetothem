--
-- schema.sql:
-- Schema for FYR queue database.
--
-- Copyright (c) 2004 UK Citizens Online Democracy. All rights reserved.
-- Email: chris@mysociety.org; WWW: http://www.mysociety.org/
--
-- $Id: schema.sql,v 1.10 2004-11-18 17:22:20 chris Exp $
--

-- secret
-- A random secret.
create table secret (
    secret text not null
);

-- state
-- States a message can be in.
create table state (
    name varchar(20) not null primary key
);

insert into state (name) values ('new');
insert into state (name) values ('pending');
insert into state (name) values ('ready');
insert into state (name) values ('bounce_wait');
insert into state (name) values ('bounce_confirm');
insert into state (name) values ('error');
insert into state (name) values ('sent');
insert into state (name) values ('finished');

-- message
-- List of messages to be sent.
create table message (
    id char(20) not null primary key,

    -- Sender info
    sender_name text not null,
    sender_email text not null,
    sender_addr text not null,
    sender_phone text,
    -- this is so that the message can later be forwarded to other
    -- representatives for the sender
    sender_postcode text not null,

    -- Recipient info; one of email or fax must be non-NULL; the ID
    recipient_id integer not null,      -- DaDem ID
    recipient_name text not null,
    recipient_type char(3) not null,    -- e.g. "WMC" or whatever
    recipient_email text,
    recipient_fax text,
    check((recipient_email is not null and recipient_fax is null) or (recipient_fax is not null and recipient_email is null)),

    -- Text of message (UTF-8 with line-breaks).
    message text not null,

    -- State information.
    state text not null references state(name),

    -- when the message was originally queued (UNIX time)
    created integer not null,

    -- when the message last changed state
    laststatechange integer not null,

    -- when we last sent a message to the user to confirm their address,
    -- or made a delivery attempt (UNIX time)
    lastaction integer,

    -- how many actions (delivery attempts or whatever) have taken place while
    -- the message has been in this state
    numactions integer not null default (0),

    -- when the message was dispatched to the representative (UNIX time)
    dispatched integer
);

-- message_log
-- Events relating to each message.
create table message_log (
    order_id serial not null primary key,       -- for ordering
    message_id char(20) not null references message(id) on delete cascade,
    exceptional boolean not null default('f'),  -- is an exceptional (error) condition
    whenlogged integer not null,                -- UNIX time
    state text not null,                        -- state of message when log item added
    message text not null
);

-- questionnaire_answer
-- Results of the questionnaire we send to users.
create table questionnaire_answer (
    message_id char(20) not null references message(id) on delete cascade,
    question_id integer not null default(0),    -- reserved for future expansion
    answer text not null
);

