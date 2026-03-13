"""
Basic database connection for TheyWorkForYou database.
"""

from typing import cast

import MySQLdb
from sqlalchemy import URL, create_engine

from wtt_tools.common.config import config


def get_wtt_db_connection() -> MySQLdb.Connection:
    db_connection = cast(
        MySQLdb.Connection,
        MySQLdb.connect(
            host=config.FYR_QUEUE_DB_HOST,
            db=config.FYR_QUEUE_DB_NAME,
            user=config.FYR_QUEUE_DB_USER,
            passwd=config.FYR_QUEUE_DB_PASS,
            charset="utf8",
        ),
    )
    return db_connection


engine = create_engine(
    URL.create(
        drivername="mysql+mysqldb",
        username=config.FYR_QUEUE_DB_USER,
        password=config.FYR_QUEUE_DB_PASS,
        host=config.FYR_QUEUE_DB_HOST,
        database=config.FYR_QUEUE_DB_NAME,
    )
)
