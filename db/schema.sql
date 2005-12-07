--
-- schema.sql:
-- Schema for FYR queue database.
--
-- Copyright (c) 2004 UK Citizens Online Democracy. All rights reserved.
-- Email: chris@mysociety.org; WWW: http://www.mysociety.org/
--
-- $Id: schema.sql,v 1.32 2005-12-07 16:42:14 francis Exp $
--

set client_min_messages to error;

-- secret
-- A random secret.
create table secret (
    secret text not null
);

-- If a row is present, that is date which is "today".  Used for debugging
-- to advance time without having to wait.
create table debugdate (
    override_today date
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

-- Returns the timestamp of current time, but with possibly overriden "today".
create function fyr_current_timestamp()
    returns timestamp as '
    declare
        today date;
    begin
        today = (select override_today from debugdate);
        if today is not null then
           return today + current_time;
        else
           return current_timestamp;
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
    dispatched integer,

    -- cobranding (e.g. see http://cheltenham.writetothem.com)
    cobrand text check (cobrand ~* '^[a-z0-9]+$'), -- first part of domain (e.g. cheltenham, animalaid), NULL for no cobranding
    cocode text check (cocode ~* '^[a-zA-Z0-9-]+$') -- extra code for cobranding organisation
);

-- Various indices to make the queue pages quicker.
create index message_created_idx on message(created);
create index message_state_idx on message(state);
create index message_frozen_idx on message(frozen);
create index message_laststatechange_idx on message(laststatechange);
create index message_recipient_type_idx on message(recipient_type);

-- message_extradata
-- Additional (opaque) data about each message.
create table message_extradata (
    message_id char(20) not null references message(id),
    name varchar(255) not null,
    data bytea not null
);

-- message_log
-- Events relating to each message.
create table message_log (
    order_id serial not null primary key,       -- for ordering
    message_id char(20) not null,
    exceptional boolean not null default('f'),  -- is an exceptional (error) condition
    whenlogged integer not null,                -- UNIX time
    state text not null,                        -- state of message when log item added
    message text not null,
    editor text                                 -- administrator who performed this action, or NULL
);

create index message_log_order_id_idx on message_log(order_id);
create index message_log_message_id_idx on message_log(message_id);

-- statistics about messages
-- Postgres is very slow at doing select count(...) from tables (because it has
-- to visit and lock every row) so we accumulate statistics about messages in a
-- set of message_count_... tables and return these.

-- message_count_state
-- Number of messages in each state.
create table message_count_state (
    state text not null references state(name),
    messagecount integer not null default (0)
);

insert into message_count_state (state, messagecount)
    select state, count(id) as messagecount
        from message group by state;

-- message_count_recipient_type
-- Number of messages to each type of recipient.
create table message_count_recipient_type (
    recipient_type char(3) not null primary key,
    messagecount integer not null default(0)
);

insert into message_count_recipient_type (recipient_type, messagecount)
    select recipient_type, count(id) as messagecount
        from message group by recipient_type;

-- trigger which updates the stats tables based on operation on the message
-- table.
create function gather_stats() returns trigger as '
    begin
        if tg_op = ''UPDATE'' or tg_op = ''DELETE'' then
            update message_count_state
                set messagecount = messagecount - 1
                where state = old.state;
            update message_count_recipient_type
                set messagecount = messagecount - 1
                where recipient_type = old.recipient_type;
        end if;
        
        if tg_op = ''INSERT'' or tg_op = ''UPDATE'' then
            -- state
            perform messagecount -- perform is like select into ... but without returning a result...
                from message_count_state
                where state = new.state
                for update;
            if not found then
                insert into message_count_state (state, messagecount)
                    values (new.state, 1);
            else
                update message_count_state
                    set messagecount = messagecount + 1
                    where state = new.state;
            end if;
            -- recipient type
            perform messagecount
                from message_count_recipient_type
                where recipient_type = new.recipient_type
                for update;
            if not found then
                insert into message_count_recipient_type (recipient_type, messagecount)
                    values (new.recipient_type, 1);
            else
                update message_count_recipient_type
                    set messagecount = messagecount + 1
                    where recipient_type = new.recipient_type;
            end if;
        end if;
        return null;    -- trigger fired after event, so return value ignored
    end;
' language 'plpgsql';

-- Disabled, as there were deadlocks.  TODO Replace with a better
-- mechanism.
--create trigger message_gather_stats
--    after insert or update or delete on message
--   for each row execute procedure gather_stats();

-- questionnaire_answer
-- Results of the questionnaire we send to users.
create table questionnaire_answer (
    message_id char(20) not null references message(id) on delete cascade,
    question_id integer not null default(0),    -- reserved for future expansion
    answer text not null
);

create index questionnaire_answer_message_id_idx
    on questionnaire_answer(message_id);

-- message_bounce
-- Bounce messages received for emailed messages.
create table message_bounce (
    message_id char(20) not null references message(id) on delete cascade,
    whenreceived integer not null,
    bouncetext text not null
);

--
-- Statistical reports
--

-- Each report covers a certain period of time and contains a number of
-- sections. Each section contains a table of information about
-- representatives or categories of representatives.
create table report (
    id serial primary key,
    -- The period covered.
    start_date date not null,
    end_date date not null,
    title text not null,
    description text not null
);

create table section (
    id serial primary key,
    report_id integer not null references report(id),
    title text not null,
    description text not null,
    variable text not null
);

create table report_row (
    id serial primary key,
    section_id integer not null references section(id),
    -- Description of the value.
    what text not null,
    -- The number of individuals described in this row. For instance, 646 for
    -- "all MPs", or 1 for "Tony Blair".
    number_of_individuals integer not null default(1),
    -- The representative ID and area ID, if it covers a single representative.
    representative_id integer,
    area_id integer,
    -- Name or description of representative ("Tony Blair" or "all MPs").
    representative_name text not null,
        -- XXX should have a collating name here so that we can sort the things
        -- in a manner that doesn't make us look like total muppets, but DaDem
        -- doesn't supply data in this form, so that's hard.
    -- Name or description of areas covered ("Sedgfield" or "constituencies in
    -- the UK").
    area_name text not null,
    -- If all representatives covered are from a single party, name it here
    -- ("Labour" or null).
    representative_party text,
    -- The type of representative covered, or null if it covers more than one
    -- type.
    representative_type char(3),
    -- Actual value of the data.
    value double precision not null,
    -- Optional min/max and standard deviation.
    min double precision,
    max double precision,
    stddev double precision
);

-- Create a bunch of indices on this, partly for looking things up, and partly
-- so that we can sort on things.
create index report_row_section_id_idx
    on report_row(section_id);
create index report_row_section_id_area_name_idx
    on report_row(section_id, area_name);
create index report_row_section_id_representative_party_idx
    on report_row(section_id, representative_party);
create index report_row_section_id_representative_type_idx
    on report_row(section_id, representative_type);
create index report_row_section_id_value_idx
    on report_row(section_id, value);

