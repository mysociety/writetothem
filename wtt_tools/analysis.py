import datetime
from typing import Collection

from django.db.models import Q

import pandas as pd
import probablepeople
from gender_detect import GenderDetect
from mini_postcode_lookup import AllowedAreaTypes, MiniPostcodeLookup

from wtt_tools.common.config import config
from wtt_tools.db.models import AnalysisData, Message


def get_title_gender(title: str | None) -> str:
    """
    If using a gender title, return the corresponding gender.
    """
    if not title:
        return None

    title = title.strip().lower()
    # remove dot if at end
    if title.endswith("."):
        title = title[:-1]
    return {"mr": "male", "mrs": "female", "miss": "female", "ms": "female"}.get(
        title, None
    )


def get_message_queryset(q: Q):
    """
    Get a queryset of messages filtered by the provided Q object.
    """
    return Message.objects.filter(q).prefetch_related("analysis_data")


class BaseAnalysis:
    analysis_key: str = ""

    def get_value(self, message: Message):
        """
        Get the value for the analysis based on the message.
        """
        raise NotImplementedError("Subclasses must implement this method.")

    def get_messages(self) -> Collection[Message]:
        raise NotImplementedError("Subclasses must implement this method.")

    def convert_data(messages: Collection[Message]) -> dict[str, str]:
        """
        Do the analysis on the collection and return a lookup into a message_id to value format.
        """
        raise NotImplementedError("Subclasses must implement this method.")

    def run(self):
        messages = self.get_messages()

        analysis_data = self.convert_data(messages)

        analysis_to_update: list[AnalysisData] = []
        analysis_to_create: list[AnalysisData] = []

        for message in messages:
            value = analysis_data.get(message.id)
            analysis, existing = message.get_analysis()
            analysis.analysis_data[self.analysis_key] = value

            if existing:
                analysis_to_update.append(analysis)
            else:
                analysis_to_create.append(analysis)

        AnalysisData.objects.bulk_update(analysis_to_update, ["analysis_data"])
        AnalysisData.objects.bulk_create(analysis_to_create)


class LSOA2010Analysis(BaseAnalysis):
    analysis_key = "lsoa_2010"

    def get_messages(self) -> Collection[Message]:
        """
        Get all messages that are missing LSOA 2010 data
        after the start of the analysis window.
        """

        start_date = datetime.date.fromisoformat(f"{config.ANALYSIS_START_YEAR}-01-01")

        return list(
            get_message_queryset(
                Q(analysis_data__isnull=True)
                | Q(analysis_data__analysis_data__lsoa_2010__isnull=True)
            ).filter(created__gte=int(Message.datetime_to_epoch(start_date)))
        )

    def convert_data(self, messages: Collection[Message]) -> dict[str, str]:
        """
        Do the analysis on the collection and return a lookup into a message_id to value format.
        """
        lookup = MiniPostcodeLookup()

        series = pd.Series(
            [x.sender_postcode for x in messages], index=[x.id for x in messages]
        )

        results = lookup.get_series(series, area_type=AllowedAreaTypes.LSOA)
        return results.to_dict()


class GenderAnalysis(BaseAnalysis):
    analysis_key = "gender_from_name"

    def get_messages(self) -> Collection[Message]:
        """
        Get all messages that are missing gender_from_name data
        after the start of the analysis window.
        """

        start_date = datetime.date.fromisoformat(f"{config.ANALYSIS_START_YEAR}-01-01")

        return list(
            get_message_queryset(
                Q(analysis_data__isnull=True)
                | Q(analysis_data__analysis_data__gender_from_name__isnull=True)
            ).filter(created__gte=int(Message.datetime_to_epoch(start_date)))
        )

    def convert_data(self, messages: Collection[Message]) -> dict[str, str]:
        """
        Convert the name column into a derived gender column.
        """
        detect = GenderDetect()

        name_tags = [
            probablepeople.tag(x.sender_name, type="person")[0] for x in messages
        ]

        titles = [x.get("PrefixMarital", None) for x in name_tags]
        first_names = [x.get("GivenName", None) for x in name_tags]

        series = pd.Series(first_names, index=[x.id for x in messages])

        name_gender = detect.process_series(series)

        title_gender = pd.Series(
            [get_title_gender(x) for x in titles], index=[x.id for x in messages]
        )

        # if title_gender is not None, prefer it to name gender
        results = title_gender.combine_first(name_gender)

        return results.to_dict()


def add_lsoas():
    LSOA2010Analysis().run()


def add_gender():
    GenderAnalysis().run()
