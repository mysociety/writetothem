--
-- schema.sql:
-- Schema for FYR queue database.
--
-- Copyright (c) 2004 UK Citizens Online Democracy. All rights reserved.
-- Email: chris@mysociety.org; WWW: http://www.mysociety.org/
--
-- $Id: schema.sql,v 1.1 2004-10-05 16:42:14 chris Exp $
--

-- secret
-- A random secret.
create table secret (
    secret text not null
);

-- queue
-- List of messages to be sent.
create table queue (
    id serial not null primary key,
    token char(20) not null,

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

    -- Text of message (UTF-8 with linebreaks).
    message text not null,

    -- State information. Messages start off pending (awaiting email
    -- confirmation by the sender); they are then put in state send, to be
    -- sent; then they sit in state sent for a while awaiting a questionnaire
    -- response from the user.
    state text not null default ('pending') check (state = 'pending' or state = 'send' or state = 'sent');

    -- when the message was originally queued
    whencreated timestamp(0) without time zone not null default(now()),

    -- when we last changed the state or sent a message to the user to confirm
    -- their address.
    lastupdate timestamp without time zone
);

create unique index queue_token_idx on queue(token);
