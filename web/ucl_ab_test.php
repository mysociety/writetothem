<?php

/**
 * ucl_ab_test.php
 *
 * Functionality to run A/B testing on user behaviour as part of a UCL project.
 */

class UCLTest
{

    private $pg_connection;

    public $test_token;
    public $show_numbers = FALSE;

    public function __construct($messageid = NULL)
    {
        require_once '../conf/general';

        $this->pg_connection = pg_connect("host=" . OPTION_FYR_QUEUE_DB_HOST . " port=" . OPTION_FYR_QUEUE_DB_PORT . " dbname=" . OPTION_FYR_QUEUE_DB_NAME . " user=" . OPTION_FYR_QUEUE_DB_USER . " password=" . OPTION_FYR_QUEUE_DB_PASS);

        // If there is a message ID passed during object creation, use that
        if ($messageid !== NULL)
        {
            $this->retrieve_test_by_message($messageid);
        }

        // If there is no message ID passed during creation, check cookies
        else if (isset($_COOKIE['ucl_test_token']))
        {
            $this->retrieve_test($_COOKIE['ucl_test_token']);
        }

        // There is no way of determining if this is an existing test. Create a new test.
        else
        {
            $this->new_test();
        }
    }

    public function get_rep_live_message_count($recipient_id)
    {
        $pg_count = pg_query($this->pg_connection, "SELECT COUNT(*) FROM message WHERE dispatched*'1 second'::interval+'epoch'::timestamp >= now()-'1 year'::interval AND recipient_id=" . $recipient_id);
        $pg_count_object = pg_fetch_object($pg_count);

        return $pg_count_object->count;
    }

    public function get_rep_message_count($recipient_id)
    {
        $pg_count = pg_query($this->pg_connection, "SELECT message_count FROM ucl_message_counts WHERE representative_id=" . $recipient_id);
        $pg_count_object = pg_fetch_object($pg_count);

        return $pg_count_object !== FALSE ? $pg_count_object->message_count : 0;
    }

    public function set_postcode($postcode)
    {
        pg_query($this->pg_connection, "UPDATE ucl_testing_log SET postcode = '" . pg_escape_string($postcode) . "' WHERE test_token = '" . pg_escape_string($this->test_token) . "'");
    }

    public function set_message_count($count)
    {
        pg_query($this->pg_connection, "UPDATE ucl_testing_log SET message_count = " . (int) $count . " WHERE test_token = '" . pg_escape_string($this->test_token) . "'");
    }

    public function set_message_id($id)
    {
        pg_query($this->pg_connection, "UPDATE ucl_testing_log SET message_id = '" . pg_escape_string($id) . "' WHERE test_token = '" . pg_escape_string($this->test_token) . "'");
    }

    public function record_compose_visit()
    {
        pg_query($this->pg_connection, "UPDATE ucl_testing_log SET visited_compose_page = TRUE WHERE test_token = '" . pg_escape_string($this->test_token) . "'");
    }

    public function record_preview_visit()
    {
        pg_query($this->pg_connection, "UPDATE ucl_testing_log SET visited_preview_page = TRUE WHERE test_token = '" . pg_escape_string($this->test_token) . "'");
    }

    public function record_message_send()
    {
        pg_query($this->pg_connection, "UPDATE ucl_testing_log SET message_sent = TRUE WHERE test_token = '" . pg_escape_string($this->test_token) . "'");
    }

    public function record_message_confirm()
    {
        pg_query($this->pg_connection, "UPDATE ucl_testing_log SET message_confirmed = TRUE WHERE test_token = '" . pg_escape_string($this->test_token) . "'");
    }

    public function record_survey_view()
    {
        pg_query($this->pg_connection, "UPDATE ucl_testing_log SET survey_visited = TRUE WHERE test_token = '" . pg_escape_string($this->test_token) . "'");
    }

    public function record_survey_submitted()
    {
        pg_query($this->pg_connection, "UPDATE ucl_testing_log SET survey_submitted = TRUE WHERE test_token = '" . pg_escape_string($this->test_token) . "'");
    }

    private function new_test()
    {
        // Generate the testing token and mode
        $test_token = uniqid();
        $test_mode = rand(0,1);

        // Assign the token and mode to the testing object.
        $this->show_numbers = (bool) $test_mode;
        $this->test_token = $test_token;

        // Write this information out to the test log.
        pg_query($this->pg_connection, "INSERT INTO ucl_testing_log (test_token, shown_number) VALUES ('" . pg_escape_string($test_token) . "', CAST(" .  $test_mode . " AS bool))");

        // Set the testing cookie
        setcookie("ucl_test_token", $test_token, time()+2419200, "/", OPTION_WEB_DOMAIN);
    }

    private function retrieve_test($token)
    {
        $test = pg_query($this->pg_connection, "SELECT * FROM ucl_testing_log WHERE test_token = '" . pg_escape_string($token) . "'");

        // Sanity check something has actually come back.
        if (pg_num_rows($test) === 1)
        {

            $test_object = pg_fetch_object($test);

            $this->test_token = $token;
            $this->show_numbers = $test_object->shown_number == 't' ? TRUE : FALSE;

            // Touch the testing cookie
            setcookie("ucl_test_token", $token, time()+2419200, "/", OPTION_WEB_DOMAIN);
        }

        // There is no test with this token. Generate a new one for completeness.
        // The value of the token *may* be gibberish, which is why it's not reused.
        else
        {
            $this->new_test();
        }
    }

    private function retrieve_test_by_message($messageid)
    {
        $test = pg_query($this->pg_connection, "SELECT * FROM ucl_testing_log WHERE message_id = '" . pg_escape_string($messageid) . "'");

        // Sanity check something has actually come back.
        if (pg_num_rows($test) === 1)
        {

            $test_object = pg_fetch_object($test);

            $this->test_token = $test_object->test_token;
            $this->show_numbers = $test_object->shown_number == 't' ? TRUE : FALSE;

            // Touch the testing cookie
            setcookie("ucl_test_token", $test_object->test_token, time()+2419200, "/", OPTION_WEB_DOMAIN);
        }

        // There is no test with this message ID. Generate a new one for completeness.
        else
        {
            $this->new_test();
        }
    }

}