from pathlib import Path

import rich
from typer import Typer

from wtt_tools.db.connection import get_wtt_db_connection

app = Typer()


@app.command()
def run_schema_update(name: str, quiet: bool = False):
    """
    Run a schema update from a SQL file in the db directory.
    """

    db_connection = get_wtt_db_connection()

    schema_dir = Path("db")

    # find any files in schema_dir that start with name
    update_files = sorted(schema_dir.glob(f"{name}*.sql"))

    if not update_files:
        raise ValueError(f"No schema update files found for {name}")

    # if more than one file - error
    if len(update_files) > 1:
        raise ValueError(f"Multiple schema update files found for {name}")

    update_file = update_files[0]
    update_command = update_file.read_text()

    # Execute the SQL update command
    with db_connection.cursor() as cursor:
        cursor.execute(update_command)
    db_connection.commit()
    if not quiet:
        rich.print(f"[green]Schema updated successfully from {update_file}[/green]")
    db_connection.close()


if __name__ == "__main__":
    app()
