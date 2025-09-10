from wtt_tools.analysis import GenderAnalysis, LSOA2010Analysis
from wtt_tools.db.models import Message


def test_lsoa():
    messages = [Message(id="testa", sender_postcode="SP9 7BU")]

    items = LSOA2010Analysis().convert_data(messages)

    assert items["testa"] == "E01031880"


def test_gender():
    messages = [
        Message(id="testa", sender_name="James Wright"),
        Message(id="testb", sender_name="Mary Johnson"),
        Message(id="testc", sender_name="Alex Smith"),
        Message(id="testd", sender_name="ms. Alex Smith"),
        Message(id="testf", sender_name="Alex de Rosa Smith"),
    ]

    items = GenderAnalysis().convert_data(messages)

    assert items["testa"] == "male", "Expected gender to be male"
    assert items["testb"] == "female", "Expected gender to be female"
    assert items["testc"] == "unknown", "Expected gender to be unknown"
    assert items["testd"] == "female", "Expected gender to be female"
    assert items["testd"] == "male", "Expected gender to be male"
