<?php

require(__DIR__.'/../../config.php');

// Set the page information
$PAGE->set_url('/mod/adastra/calls.php');
$PAGE->set_title("API Calls");
$PAGE->set_heading("API Calls");


/**
 * Function that sends a GET request and returns the response
 * @param $url The url to where the request is sent
 * @param $token The token of the user for authorization
 * @param $data Parameters to be sent with the request
 * @return $result The response from the server
 */
function call_API($url, $token = false, $data = false) {
    $curl = curl_init();

    // If some parameters should be given with the request, add
    // them to the end of the URL
    if ($data) {
        $url = sprintf("%s?%s", $url, http_build_query($data));
    }

    // Authentication:
    curl_setopt(
        $curl, CURLOPT_HTTPHEADER, [
            'Authorization: Token ' . $token
        ]
    );

    curl_setopt($curl, CURLOPT_URL, $url);
    curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);

    $result = curl_exec($curl);

    // If no result, then throw an Exception
    if ($result === false) {
        print_r('Curl error: ' . curl_error($curl));
    }

    curl_close($curl);

    return $result;
}


// Get the information of the course Testikurssi 1
$test_course = call_API(
    'https://tie-plus-test.rd.tuni.fi/api/v2/courses/1/', 
    '!! PUT HERE YOUR PRIVATE KEY !!', 
    'format=json'
);

// Print the information of Testikurssi 1
echo('Testikurssi 1 info:<br><br>');
echo($test_course);

