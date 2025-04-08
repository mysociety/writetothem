begin;

  create table analysis_data (
      message_id char(20) not null references message(id) on delete cascade,
      message_summary text,
      analysis_data jsonb,
      whenanswered integer
  );

  create unique index analysis_data_message_id_idx
      on analysis_data(message_id);

commit;
