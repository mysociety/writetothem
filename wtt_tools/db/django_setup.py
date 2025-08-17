"""
This is a simple minimal setup for using Django ORMs.
Import this when creating models and then the models can be used as normal.
"""

import os

import django
from django.conf import settings

from wtt_tools.common.config import config

# Allow use in notebooks
os.environ["DJANGO_ALLOW_ASYNC_UNSAFE"] = "true"

if not settings.configured:
    settings.configure(
        DEBUG=True,
        SECRET_KEY="your-secret-key",
        ALLOWED_HOSTS=["*"],
        INSTALLED_APPS=[
            "wtt_tools",
        ],
        DATABASES={
            "default": {
                "ENGINE": "django.db.backends.postgresql",
                "NAME": config.FYR_QUEUE_DB_NAME,
                "USER": config.FYR_QUEUE_DB_USER,
                "PASSWORD": config.FYR_QUEUE_DB_PASS,
                "HOST": config.FYR_QUEUE_DB_HOST,
                "PORT": config.FYR_QUEUE_DB_PORT,
            }
        },
    )

django.setup()
