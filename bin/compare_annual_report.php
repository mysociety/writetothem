<?php

$first = '2013';
$last = '2014';

function output($string) {
    echo $string . "\n";
}

function sort_league_first($a, $b)
{

    global $first;

    $varname = 'responserate_' . $first;

    if ($a[$varname] == $b[$varname]) {
        return 0;
    }
    return ($a[$varname] > $b[$varname]) ? -1 : 1;
}

function sort_league_last($a, $b)
{

    global $last;

    $varname = 'responserate_' . $last;

    if (!isset($a[$varname]) OR !isset($b[$varname])) {
        return -1;
    }

    if ($a[$varname] == $b[$varname]) {
        return 0;
    }
    return ($a[$varname] > $b[$varname]) ? -1 : 1;
}

output('Comparing reports from ' . $first . ' to ' . $last . '.');

require_once('../phplib/questionnaire_report_' . $first . '_WMC.php');

output('Loaded ' . $first . ' report.');

require_once('../phplib/questionnaire_report_' . $last . '_WMC.php');

output('Loaded ' . $last . ' report.');

$first_variable_name = 'questionnaire_report_' . $first . '_WMC';
$first_data = $$first_variable_name;

$last_variable_name = 'questionnaire_report_' . $last . '_WMC';
$last_data = $$last_variable_name;

output('Variables available.');

// Loop through each of the arrays and get the data.

// We can ignore these objects.

$ignore_objects = array(
    'total_dispatched_success',
    'total_responded',
    'total_responded_outof',
    'total_firsttime',
    'total_firsttime_outof',
);

$combined_data = array();

// Load up all the first data

foreach ($first_data as $id => $data) {
    if (!in_array($id, $ignore_objects)) {
        $combined_data[(string) $data['person_id']] = array(
            'person_id' => $data['person_id'],
            'recipient_ids' => $data['recipient_ids'],
            'name' => $data['name'],
            'party_' . $first => $data['party'],
            'constituency_' . $first => $data['area'],
            'sent_' . $first => (int) $data['dispatched_success'],
            'responded_' . $first => (int) $data['responded'],
            'responded_outof_' . $first => (int) $data['responded_outof'],
            'responserate_' . $first => round( (float) $data['responded_mean'], 4),
        );
    }
}

// Now we have the first set of data in, perform a sort!

usort($combined_data, 'sort_league_first');

// Slam through the array and add league positions for the first year.
// Usort helpfully mucks up the indexes. Fix that as well.

$i = 1;

$combined_data_sorted = array();

foreach ($combined_data as $id => $data){
    $combined_data_sorted[$data['person_id']] = $data;
    $combined_data_sorted[$data['person_id']]['position_' . $first] = $i;
    $i++;
}

$combined_data = $combined_data_sorted;

unset($combined_data_sorted);

// Load up all the last data

foreach ($last_data as $id => $data) {
    if (!in_array($id, $ignore_objects)) {

        if (isset($combined_data[$data['person_id']])) {
            $combined_data[$data['person_id']] = array_merge($combined_data[$data['person_id']], array(
                'party_' . $last => $data['party'],
                'constituency_' . $last => $data['area'],
                'sent_' . $last => (int) $data['dispatched_success'],
                'responded_' . $last => (int) $data['responded'],
                'responded_outof_' . $last => (int) $data['responded_outof'],
                'responserate_' . $last => round( (float) $data['responded_mean'], 4),
            ));
        } else {
            $combined_data[$data['person_id']] = array(
                'person_id' => $data['person_id'],
                'recipient_ids' => $data['recipient_ids'],
                'name' => $data['name'],
                'party_' . $last => $data['party'],
                'constituency_' . $last => $data['area'],
                'sent_' . $last => (int) $data['dispatched_success'],
                'responded_' . $last => (int) $data['responded'],
                'responded_outof_' . $last => (int) $data['responded_outof'],
                'responserate_' . $last => round( (float) $data['responded_mean'], 4),
            );
        }
    }
}

// Now we have the last set of data in, perform a sort!

$combined_data_sorted = array();

usort($combined_data, 'sort_league_last');

// Slam through the array and add league positions for the last year, as long as
// the member has a response rate

$i = 1;

$combined_data_sorted = array();

foreach ($combined_data as $id => $data){
    $combined_data_sorted[$data['person_id']] = $data;
    if (isset($data['responserate_' . $last])) {
        $combined_data_sorted[$data['person_id']]['position_' . $last] = $i;
        $i++;
    }
}

$combined_data = $combined_data_sorted;

unset($combined_data_sorted);

// Alright, it's time for the exciting comparisons!

foreach ($combined_data as $id => $data){

    // Party

    if (isset($data['party_' . $first]) AND isset($data['party_' . $last])) {
        if ($data['party_' . $first] != $data['party_' . $last]){
            $combined_data[$id]['party_changed'] = 1;
        } else {
            $combined_data[$id]['party_changed'] = 0;
        }
    }

    // Constituency

    if (isset($data['constituency_' . $first]) AND isset($data['constituency_' . $last])) {
        if ($data['constituency_' . $first] != $data['constituency_' . $last]){
            $combined_data[$id]['constituency_changed'] = 1;
        } else {
            $combined_data[$id]['constituency_changed'] = 0;
        }
    }

    // Sent

    if (isset($data['sent_' . $first]) AND isset($data['sent_' . $last])) {
        $combined_data[$id]['sent_difference'] = $data['sent_' . $last] - $data['sent_' . $first];
    }

    // Responded

    if (isset($data['responded_' . $first]) AND isset($data['responded_' . $last])) {
        $combined_data[$id]['responded_difference'] = $data['responded_' . $last] - $data['responded_' . $first];
    }

    // Responded Out Of

    if (isset($data['responded_outof_' . $first]) AND isset($data['responded_outof_' . $last])) {
        $combined_data[$id]['responded_outof_difference'] = $data['responded_outof_' . $last] - $data['responded_outof_' . $first];
    }

    // Response Rate

    if (isset($data['responserate_' . $first]) AND isset($data['responserate_' . $last])) {
        $combined_data[$id]['responserate_difference'] = $data['responserate_' . $last] - $data['responserate_' . $first];
    }

    // Position

    if (isset($data['position_' . $first]) AND isset($data['position_' . $last])) {
        $combined_data[$id]['position_difference'] = $data['position_' . $last] - $data['position_' . $first];
    }

}

// These are the columns we're going to dump to the CSV. Hold tight.

$output_columns = array(
    'person_id',
    'recipient_ids',
    'name',
    'party_' . $first,
    'party_' . $last,
    'party_changed',
    'constituency_' . $first,
    'constituency_' . $last,
    'constituency_changed',
    'sent_' . $first,
    'sent_' . $last,
    'sent_difference',
    'responded_' . $first,
    'responded_' . $last,
    'responded_difference',
    'responded_outof_' . $first,
    'responded_outof_' . $last,
    'responded_outof_difference',
    'responserate_' . $first,
    'responserate_' . $last,
    'responserate_difference',
    'position_' . $first,
    'position_' . $last,
    'position_difference',
);

$fp = fopen('comparison.csv', 'w');

fputcsv($fp, $output_columns);

foreach ($combined_data as $data) {
    $write_row = array();
    foreach ($output_columns as $column){
        if (isset($data[$column])) {
            $write_row[$column] = $data[$column];
        } else {
            $write_row[$column] = '';
        }
    }
    fputcsv($fp, $write_row);
}

fclose($fp);
