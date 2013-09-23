--
-- schema.sql:
-- Schema for FYR queue database.
--
-- Copyright (c) 2004 UK Citizens Online Democracy. All rights reserved.
-- Email: chris@mysociety.org; WWW: http://www.mysociety.org/
--
-- $Id: schema.sql,v 1.59 2009-05-13 11:51:37 louise Exp $
--

begin;

set client_min_messages to error;

-- secret
-- A random secret.
create table secret (
    secret text not null
);

-- If a row is present, that is date which is "today".  Used for debugging
-- to advance time without having to wait.
create table debugdate (
    override_today date,
    override_time time
);

-- Returns the date of "today", which can be overriden for testing.
create function fyr_current_date()
    returns date as '
    declare
        today date;
    begin
        today = (select override_today from debugdate);
        if today is not null then
           return today;
        else
           return current_date;
        end if;

    end;
' language 'plpgsql';

-- Returns the timestamp of current time, but with possibly overriden "today" and "time".
create function fyr_current_timestamp()
    returns timestamp as '
    declare
      time_now time;
    begin
        time_now = (select override_time from debugdate);
        if time_now is not null then
            return fyr_current_date() + time_now;
        else
          return fyr_current_date() + current_time;
        end if;
    end;
' language 'plpgsql';

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
insert into state (name) values ('failed');
insert into state (name) values ('failed_closed');


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

    -- data for anti-abuse measures
    sender_ipaddr text not null,    -- IP address used to submit the message
    sender_referrer text,  -- any external Referer: header we saw

    -- Recipient info; one of email or fax must be non-NULL; the ID
    recipient_id integer not null,      -- DaDem ID
    recipient_name text not null,
    recipient_type char(3) not null,    -- e.g. "WMC" or whatever
    recipient_email text,
    recipient_fax text,
    check((recipient_email is not null and recipient_fax is null) or (recipient_fax is not null and recipient_email is null)),
    -- true if this is being sent via some other contact point, for instance a
    -- Democratic Services office
    recipient_via boolean not null default('f'),

    -- Text of message (UTF-8 with line-breaks).
    message text not null,

    -- State information.
    state text not null references state(name),

    -- Frozen messages don't leave ready state
    frozen boolean not null default('f'),
    -- Test messages don't send questionnaire
    no_questionnaire boolean not null default('f'),

    -- when the message was originally queued (UNIX time)
    created integer not null,

    -- when the message was confirmed (UNIX time)
    confirmed integer,

    -- when the message last changed state
    laststatechange integer not null,

    -- when we last sent a message to the user to confirm their address,
    -- or made a delivery attempt (UNIX time)
    lastaction integer,

    -- how many actions (delivery attempts or whatever) have taken place while
    -- the message has been in this state
    numactions integer not null default (0),

    -- when the message was dispatched to the representative (UNIX time)
    dispatched integer,

    -- cobranding (e.g. see http://cheltenham.writetothem.com)
    cobrand text check (cobrand ~* '^[a-z0-9]+$'), -- first part of domain (e.g. cheltenham, animalaid), NULL for no cobranding
    cocode text check (cocode ~* '^[a-zA-Z0-9-]+$'), -- extra code for cobranding organisation

    -- a group of messages can be sent at one time with the same text 
    -- to multiple representatives. A group_id identifies the group 
    -- that the message belongs to.
    group_id char(20) 
);

-- Various indices to make the queue pages quicker.
create index message_created_idx on message(created);
create index message_state_idx on message(state);
create index message_frozen_idx on message(frozen);
create index message_laststatechange_idx on message(laststatechange);
create index message_recipient_type_idx on message(recipient_type);
create index message_recipient_id_idx on message(recipient_id);
-- This one's to make the monitoring script faster.
create index message_dispatched_idx on message(dispatched);
create index message_dispatched_notnull_idx on message(dispatched) where dispatched is not null;
-- This as when doing customer support, we often look up by email
create index message_sender_email on message(sender_email);
create index message_lower_sender_email on message(lower(sender_email));
create index message_recipient_email on message(recipient_email);
create index message_lower_recipient_email on message(lower(recipient_email));
-- Group actions look up messages by group
create index message_group_id on message(group_id);
create index message_cobrand_idx on message(cobrand);
alter table message cluster on message_pkey;

-- message_extradata
-- Additional (opaque) data about each message.
create table message_extradata (
    message_id char(20) not null references message(id),
    name varchar(255) not null,
    data bytea not null
);

create index message_extradata_message_id_idx
    on message_extradata(message_id);
create unique index message_extradata_message_id_name_idx
    on message_extradata(message_id, name);

-- message_log
-- Events relating to each message.
create table message_log (
    order_id serial not null primary key,       -- for ordering
    message_id char(20) not null,
    exceptional boolean not null default('f'),  -- is an exceptional (error) condition
    hostname text,                              -- host where logged
    whenlogged integer not null,                -- UNIX time
    state text not null,                        -- state of message when log item added
    message text not null,
    editor text                                 -- administrator who performed this action, or NULL.
                                                -- Note that for historical reasons this is also shown 
                                                -- in the message content
);

create index message_log_order_id_idx on message_log(order_id);
create index message_log_message_id_idx on message_log(message_id);
create index message_log_whenlogged_idx on message_log(whenlogged);
create index message_log_editor_idx on message_log(editor);

alter table message_log cluster on message_log_pkey;

-- questionnaire_answer
-- Results of the questionnaire we send to users.
create table questionnaire_answer (
    message_id char(20) not null references message(id) on delete cascade,
    question_id integer not null default(0),    -- question number
    answer text not null,
    whenanswered integer -- unix time when question was answered
);

create index questionnaire_answer_message_id_idx on questionnaire_answer(message_id);
create index questionnaire_answer_question_id_idx on questionnaire_answer(question_id);
create index questionnaire_answer_answer_idx on questionnaire_answer(answer);

-- message_bounce
-- Bounce messages received for emailed messages.
create table message_bounce (
    message_id char(20) not null references message(id) on delete cascade,
    whenreceived integer not null,
    bouncetext text not null
);

-- confirmation_message_autoreply
-- Record URLs in emails to which we've auto-replied, so that we never send
-- more than one.
create table confirmation_mail_autoreply (
    url text not null primary key,
    whenreceived integer not null
);

--
-- Name: ucl_testing_log; Type: TABLE; Schema: public; Owner: -; Tablespace:
--

CREATE TABLE ucl_testing_log (
    test_id serial,
    test_token text,
    message_id text,
    shown_number boolean,
    postcode text,
    message_count integer,
    visited_compose_page boolean DEFAULT false NOT NULL,
    visited_preview_page boolean DEFAULT false NOT NULL,
    message_sent boolean DEFAULT false NOT NULL,
    message_confirmed boolean DEFAULT false NOT NULL,
    survey_visited boolean DEFAULT false NOT NULL,
    survey_submitted boolean DEFAULT false NOT NULL
);


--
-- Name: ucl_testing_log_pkey; Type: CONSTRAINT; Schema: public; Owner: -; Tablespace:
--

ALTER TABLE ONLY ucl_testing_log
    ADD CONSTRAINT ucl_testing_log_pkey PRIMARY KEY (test_id);

commit;
