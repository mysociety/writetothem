from __future__ import annotations

import datetime
from dataclasses import dataclass

from django.db import connection

import pandas as pd
from tqdm import tqdm

from wtt_tools.common.config import config
from wtt_tools.db import django_setup as django_setup

roles = {
    "LBW": "Councillor",
    "GLA": "Mayor",
    "LAC": "AM",
    "LAE": "AM",
    "CED": "Councillor",
    "DIW": "Councillor",
    "LGE": "Councillor",
    "UTE": "Councillor",
    "UTW": "Councillor",
    "MTW": "Councillor",
    "COP": "Councillor",
    "SPE": "MSP",
    "SPC": "MSP",
    "NIE": "MLA",
    "WAE": "SM",
    "WAC": "SM",
    "WMC": "MP",
    "HOC": "Lord",
    "EUR": "MEP",
}

export_query = """
select 
	ms.id,
    to_timestamp(ms.created)::timestamptz as created,
	ms.recipient_type,
    ms.recipient_name,
    ms.recipient_id,
	qa0.answer as got_response,
	qa1.answer as first_time,
	ad.message_summary,
	ad.analysis_data->>'reason' as reason,
	ad.analysis_data->>'lsoa_2010' as lsoa_2010,
	ad.analysis_data->>'gender_from_name' as gender_from_name
from message ms 
left join
	(select * from questionnaire_answer where question_id = 0) qa0  on qa0.message_id = ms.id
left join
	(select * from questionnaire_answer where question_id = 1) qa1 on qa1.message_id = ms.id
left join
	analysis_data ad on ad.message_id = ms.id
where
	confirmed is not null
and
	created between %s and %s
"""


@dataclass(eq=True, order=True, frozen=True)
class MonthYear:
    year: int
    month: int

    @classmethod
    def current_month(cls):
        now = datetime.datetime.now()
        return cls(month=now.month, year=now.year)

    @classmethod
    def analysis_start_month(cls):
        return cls(month=1, year=config.ANALYSIS_START_YEAR)

    @classmethod
    def all_months(cls):
        """
        Iterate from analysis_Start_month through current_month
        """
        current = cls.current_month()
        start = cls.analysis_start_month()
        while start <= current:
            yield start
            start += 1

    @classmethod
    def recent_months(cls):
        """
        Iterate through last 3 months
        """
        current = cls.current_month()
        start = current - 3
        while start <= current:
            yield start
            start += 1

    def epoch(self):
        return int(datetime.datetime(self.year, self.month, 1).timestamp())

    def __str__(self):
        return f"{self.year}_{self.month:02d}"

    def __sub__(self, months: int):
        new_month = self.month - months
        new_year = self.year
        if new_month < 1:
            new_month += 12
            new_year -= 1
        return MonthYear(month=new_month, year=new_year)

    def __add__(self, months: int):
        new_month = self.month + months
        new_year = self.year
        if new_month > 12:
            new_month -= 12
            new_year += 1
        return MonthYear(month=new_month, year=new_year)

    @classmethod
    def export_all(cls, quiet: bool = False):
        """
        Export messages for all months.
        """
        for month in tqdm(list(cls.all_months()), disable=quiet):
            month.export_month()

    @classmethod
    def export_recent(cls, quiet: bool = False):
        """
        Export messages for the last 3 months.
        """
        for month in tqdm(list(cls.recent_months()), disable=quiet):
            month.export_month()

    def export_month(self):
        """
        Export messages for the given month.
        """
        start_epoch = self.epoch()
        end_epoch = (self + 1).epoch()
        with connection.cursor() as cursor:
            cursor.execute(export_query, (start_epoch, end_epoch))
            rows = cursor.fetchall()
            columns = [col[0] for col in cursor.description]
            df = pd.DataFrame(rows, columns=columns)

        df["recipient_role"] = df["recipient_type"].map(roles)

        export_path = config.EXPORT_DATA_DIR / f"message_{self}.parquet"
        df.to_parquet(export_path)
