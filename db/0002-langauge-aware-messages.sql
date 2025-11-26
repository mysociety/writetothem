begin;

  alter table message add column language text not null default('en');

commit;
