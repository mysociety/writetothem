--
-- schema.sql:
-- Schema for FYR queue database.
--
-- Copyright (c) 2004 UK Citizens Online Democracy. All rights reserved.
-- Email: chris@mysociety.org; WWW: http://www.mysociety.org/
--
-- $Id: schema.sql,v 1.2 2004-10-20 09:06:14 chris Exp $
--

-- secret
-- A random secret.
create table secret (
    secret text not null
);

-- state
-- States a message can be in.
create table state (
    name varchar(10) not null primary key
);

-- new: awaiting sending of confirmation email.
insert into state (name) values ('new');
-- pending: awaiting confirmation of user's email address.
insert into state (name) values ('pending');
-- ready: ready to be sent.
insert into state (name) values ('ready');
-- sent: dispatched, awaiting failure message or questionnaire response.
insert into state (name) values ('sent');

-- message
-- List of messages to be sent.
create table message (
    id char(20) not null primary key,

    -- Sender info
    sender_name text not null,
    sender_email text not null,
    sender_addr text not null,
    sender_phone text,

    -- Recipient info; one of email or fax must be non-NULL; the ID
    recipient_id integer not null,      -- DaDem ID
    recipient_name text not null,
    recipient_position text not null,   -- e.g. "Member of Parliament"
    recipient_email text,
    recipient_fax text,
    check((recipient_email is not null and recipient_fax is null) or (recipient_fax is not null and recipient_email is null)),

    -- Text of message (UTF-8 with line-breaks).
    message text not null,

    -- State information.
    state text not null references state(name),

    -- when the message was originally queued (UNIX time)
    whencreated integer not null,

    -- when we last changed the state, sent a message to the user to confirm
    -- their address (UNIX time), or made a delivery attempt
    lastupdate integer
);

-- message_log
-- Events relating to each message.
create table message_log (
    message_id char(20) not null references message(id),
    whenlogged timestamp(0) without time zone not null default(now()),
    state text not null,     -- state of message when log item added
    message text not null
);

