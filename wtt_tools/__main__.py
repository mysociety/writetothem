from enum import StrEnum

import typer


class AnalysisOptions(StrEnum):
    ALL = "all"
    LSOA = "lsoa"
    GENDER = "gender"


app = typer.Typer(
    name="wtt-tools",
    help="A collection of tools for working with WTT data.",
    no_args_is_help=True,
)


@app.command()
def analysis(option: AnalysisOptions = AnalysisOptions.ALL):
    """
    Run analysis to store extra calculated information in the database
    """
    from .analysis import add_gender, add_lsoas

    if option in [AnalysisOptions.ALL, AnalysisOptions.LSOA]:
        add_lsoas()
    # disable this while we sort out some issues with name classification
    # if option in [AnalysisOptions.ALL, AnalysisOptions.GENDER]:
    #    add_gender()


@app.command()
def export(all: bool = False, quiet: bool = False):
    """
    Export data from the database to parquet files
    """
    from wtt_tools.export import MonthYear

    if all:
        MonthYear.export_all(quiet)
    else:
        MonthYear.export_recent(quiet)


def main():
    """
    Main entry point for the WTT Tools command line interface.
    """
    app()
