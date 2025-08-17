"""
This is a simple one file setup for using django's ORM models.
"""

from __future__ import annotations

import datetime
from enum import IntEnum
from typing import Optional

from django.db import models

from wtt_tools.db import django_setup as django_setup

from .model_helper import DummyManager, UnmanagedDataclassModel, field

datetime_min = datetime.datetime.min


class QuestionaireQuestion(IntEnum):
    GOT_RESPONSE = 0
    FIRST_NAME = 1


class Message(UnmanagedDataclassModel, db_table="message"):
    id: str = field(models.CharField, max_length=20, primary_key=True)
    sender_name: str = field(models.TextField)
    sender_email: str = field(models.TextField)
    sender_addr: str = field(models.TextField)
    sender_phone: Optional[str] = field(models.TextField, null=True, blank=True)
    sender_postcode: str = field(models.TextField)
    sender_ipaddr: str = field(models.TextField)
    sender_referrer: Optional[str] = field(models.TextField, null=True, blank=True)
    recipient_id: int = field(models.IntegerField)
    recipient_name: str = field(models.TextField)
    recipient_type: str = field(models.CharField, max_length=3)
    recipient_email: Optional[str] = field(models.TextField, null=True, blank=True)
    recipient_fax: Optional[str] = field(models.TextField, null=True, blank=True)
    recipient_via: bool = field(models.BooleanField, default=False)
    message: str = field(models.TextField)
    state: str = field(models.TextField)
    frozen: bool = field(models.BooleanField, default=False)
    no_questionnaire: bool = field(models.BooleanField, default=False)
    created: int = field(models.IntegerField)
    confirmed: Optional[int] = field(models.IntegerField, null=True, blank=True)
    laststatechange: int = field(models.IntegerField)
    lastaction: Optional[int] = field(models.IntegerField, null=True, blank=True)
    numactions: int = field(models.IntegerField, default=0)
    dispatched: Optional[int] = field(models.IntegerField, null=True, blank=True)
    cobrand: Optional[str] = field(models.TextField, null=True, blank=True)
    cocode: Optional[str] = field(models.TextField, null=True, blank=True)
    group_id: Optional[str] = field(
        models.CharField, max_length=20, null=True, blank=True
    )
    answers: DummyManager[QuestionaireAnswer]
    analysis_data: DummyManager[AnalysisData]

    @staticmethod
    def datetime_to_epoch(dt: datetime.datetime | datetime.date) -> int:
        """
        Convert a datetime or date object to a Unix timestamp (seconds since epoch).
        """
        if isinstance(dt, datetime.date):
            dt = datetime.datetime.combine(dt, datetime.datetime.min.time())
        return int(dt.timestamp())

    @staticmethod
    def epoch_to_datetime(epoch: int) -> datetime.datetime:
        """
        Convert a Unix timestamp (seconds since epoch) to a datetime object.
        """
        return datetime.datetime.fromtimestamp(epoch)

    def get_analysis(self) -> tuple[AnalysisData, bool]:
        existing = list(self.analysis_data.all())
        if existing:
            return existing[0], True
        else:
            return AnalysisData(message_id=self.id), False


class QuestionaireAnswer(UnmanagedDataclassModel, db_table="questionnaire_answer"):
    message: Message = field(
        models.ForeignKey,
        to=Message,
        on_delete=models.CASCADE,
        primary_key=True,
        related_name="answers",
    )
    message_id: str
    question_id: int = field(models.IntegerField, default=0)  # question number
    answer: str = field(models.TextField)
    whenanswered: Optional[int] = field(
        models.IntegerField, null=True, blank=True
    )  # unix time when question was answered


class AnalysisData(UnmanagedDataclassModel, db_table="analysis_data"):
    message = field(
        models.ForeignKey,
        to=Message,
        on_delete=models.CASCADE,
        primary_key=True,
        related_name="analysis_data",
    )
    message_id: str
    message_summary: Optional[str] = field(models.TextField, null=True, blank=True)
    analysis_data: dict = field(models.JSONField, default=dict)
    whenanswered: Optional[datetime.datetime] = field(
        models.DateTimeField, null=True, blank=True
    )

    def set_data(self, key: str, value: str) -> None:
        """
        Set a key-value pair in the analysis_data dictionary.
        """
        self.analysis_data[key] = value


class State(UnmanagedDataclassModel, db_table="state"):
    name: str = field(models.CharField, max_length=20, primary_key=True)
