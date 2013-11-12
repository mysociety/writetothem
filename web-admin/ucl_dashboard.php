<?php

/**
 * ucl_dashboard.php
 *
 * Display headline numbers for the UCL testing.
 */

ini_set('display_errors', 1);

require_once('../conf/general');

$pg_connection = pg_connect("host=" . OPTION_FYR_QUEUE_DB_HOST . " port=" . OPTION_FYR_QUEUE_DB_PORT . " dbname=" . OPTION_FYR_QUEUE_DB_NAME . " user=" . OPTION_FYR_QUEUE_DB_USER . " password=" . OPTION_FYR_QUEUE_DB_PASS);


$query_t = 'SELECT COUNT(*) FROM ucl_testing_log';
$total_visits = pg_query($pg_connection, $query_t);
$total_visits_object = pg_fetch_object($total_visits);

$query_a = $query_t . ' WHERE shown_number = true';
$a_visits = pg_query($pg_connection, $query_a);
$a_visits_object = pg_fetch_object($a_visits);

$query_b = $query_t . ' WHERE shown_number = false';
$b_visits = pg_query($pg_connection, $query_b);
$b_visits_object = pg_fetch_object($b_visits);



$query_t .= ' WHERE visited_compose_page = true';
$total_composed = pg_query($pg_connection, $query_t);
$total_composed_object = pg_fetch_object($total_composed);

$query_a .= ' AND visited_compose_page = true';
$a_composed = pg_query($pg_connection, $query_a);
$a_composed_object = pg_fetch_object($a_composed);

$query_b .= ' AND visited_compose_page = true';
$b_composed = pg_query($pg_connection, $query_b);
$b_composed_object = pg_fetch_object($b_composed);



$query_t .= ' AND visited_preview_page = true';
$total_preview = pg_query($pg_connection, $query_t);
$total_preview_object = pg_fetch_object($total_preview);

$query_a .= ' AND visited_preview_page = true';
$a_preview = pg_query($pg_connection, $query_a);
$a_preview_object = pg_fetch_object($a_preview);

$query_b .= ' AND visited_preview_page = true';
$b_preview = pg_query($pg_connection, $query_b);
$b_preview_object = pg_fetch_object($b_preview);



$query_t .= ' AND message_sent = true';
$total_sent = pg_query($pg_connection, $query_t);
$total_sent_object = pg_fetch_object($total_sent);

$query_a .= ' AND message_sent = true';
$a_sent = pg_query($pg_connection, $query_a);
$a_sent_object = pg_fetch_object($a_sent);

$query_b .= ' AND message_sent = true';
$b_sent = pg_query($pg_connection, $query_b);
$b_sent_object = pg_fetch_object($b_sent);



$query_t .= ' AND message_confirmed = true';
$total_confirmed = pg_query($pg_connection, $query_t);
$total_confirmed_object = pg_fetch_object($total_confirmed);

$query_a .= ' AND message_confirmed = true';
$a_confirmed = pg_query($pg_connection, $query_a);
$a_confirmed_object = pg_fetch_object($a_confirmed);

$query_b .= ' AND message_confirmed = true';
$b_confirmed = pg_query($pg_connection, $query_b);
$b_confirmed_object = pg_fetch_object($b_confirmed);



$query_t .= ' AND survey_visited = true';
$total_shown = pg_query($pg_connection, $query_t);
$total_shown_object = pg_fetch_object($total_shown);

$query_a .= ' AND survey_visited = true';
$a_shown = pg_query($pg_connection, $query_a);
$a_shown_object = pg_fetch_object($a_shown);

$query_b .= ' AND survey_visited = true';
$b_shown = pg_query($pg_connection, $query_b);
$b_shown_object = pg_fetch_object($b_shown);



$query_t .= ' AND survey_submitted = true';
$total_submitted = pg_query($pg_connection, $query_t);
$total_submitted_object = pg_fetch_object($total_submitted);

$query_a .= ' AND survey_submitted = true';
$a_submitted = pg_query($pg_connection, $query_a);
$a_submitted_object = pg_fetch_object($a_submitted);

$query_b .= ' AND survey_submitted = true';
$b_submitted = pg_query($pg_connection, $query_b);
$b_submitted_object = pg_fetch_object($b_submitted);

?>

<h1>UCL A/B Test Dashboard</h1>

<table>
    <tr>
        <td></td>
        <th scope="col">Total</th>
        <th scope="col">Shown Numbers</th>
        <th scope="col">Not Shown Numbers</th>
    </tr>
    <tr>
        <th scope="row">Visits</th>
        <td><?=$total_visits_object->count?></td>
        <td><?=$a_visits_object->count?> (<?=round($a_visits_object->count/$total_visits_object->count*100)?>%)</td>
        <td><?=$b_visits_object->count?> (<?=round($b_visits_object->count/$total_visits_object->count*100)?>%)</td>
    </tr>
    <tr>
        <th scope="row">Visited Compose Page</th>
        <td><?=$total_composed_object->count?></td>
        <td><?=$a_composed_object->count?> (<?=round($a_composed_object->count/$total_composed_object->count*100)?>%)</td>
        <td><?=$b_composed_object->count?> (<?=round($b_composed_object->count/$total_composed_object->count*100)?>%)</td>
    </tr>
    <tr>
        <th scope="row">Previewed Message</th>
        <td><?=$total_preview_object->count?></td>
        <td><?=$a_preview_object->count?> (<?=round($a_preview_object->count/$total_preview_object->count*100)?>%)</td>
        <td><?=$b_preview_object->count?> (<?=round($b_preview_object->count/$total_preview_object->count*100)?>%)</td>
    </tr>
    <tr>
        <th scope="row">Sent Message</th>
        <td><?=$total_sent_object->count?></td>
        <td><?=$a_sent_object->count?> (<?=round($a_sent_object->count/$total_sent_object->count*100)?>%)</td>
        <td><?=$b_sent_object->count?> (<?=round($b_sent_object->count/$total_sent_object->count*100)?>%)</td>
    </tr>
    <tr>
        <th scope="row">Confirmed Message</th>
        <td><?=$total_confirmed_object->count?></td>
        <td><?=$a_confirmed_object->count?> (<?=round($a_confirmed_object->count/$total_confirmed_object->count*100)?>%)</td>
        <td><?=$b_confirmed_object->count?> (<?=round($b_confirmed_object->count/$total_confirmed_object->count*100)?>%)</td>
    </tr>
    <tr>
        <th scope="row">Shown Survey</th>
        <td><?=$total_shown_object->count?></td>
        <td><?=$a_shown_object->count?></td>
        <td><?=$b_shown_object->count?></td>
    </tr>
    <tr>
        <th scope="row">Submitted Survey</th>
        <td><?=$total_submitted_object->count?></td>
        <td><?=$a_submitted_object->count?></td>
        <td><?=$b_submitted_object->count?></td>
    </tr>
</table>

<p><small>These numbers represent those users who (in the test log) have completed all steps to that point. Numbers are rounded, and may not add exactly to 100%.</small></p>