import os
from pathlib import Path
from typing import Any, Callable

from pydantic import BaseModel, model_validator
from pylib.mysociety import config as base_config

BaseConfigGet = Callable[[str], Any]

repository_path = Path(__file__).resolve().parents[2]

if os.getenv("IN_DOCKER") == "1":
    base_config.set_file(repository_path / "conf" / "general.docker")
else:
    base_config.set_file(repository_path / "conf" / "general")


class ConfigModel(BaseModel):
    """
    Shortcut to reveal to IDE the structure of the configuration.
    """

    MAPIT_URL: str
    FYR_QUEUE_DB_HOST: str
    FYR_QUEUE_DB_PORT: str
    FYR_QUEUE_DB_NAME: str
    FYR_QUEUE_DB_USER: str
    FYR_QUEUE_DB_PASS: str
    ANALYSIS_START_YEAR: int = 2025
    EXPORT_DATA_DIR: Path

    @model_validator(mode="after")
    def check_folders_exist(self):
        if not self.EXPORT_DATA_DIR.exists():
            self.EXPORT_DATA_DIR.mkdir(parents=True, exist_ok=True)
        return self

    @classmethod
    def from_php_config(cls, php_config_get: BaseConfigGet):
        # iterate over the fields of the model
        # and get the value from the php config

        items = {}

        for field, field_config in cls.model_fields.items():
            try:
                default = field_config.default
                if default:
                    items[field] = php_config_get(field, default=default)
                else:
                    items[field] = php_config_get(field)
            except Exception as e:
                raise ValueError(f"Error getting {field} from php config: {e}")

        return cls(**items)


config = ConfigModel.from_php_config(base_config.get)
