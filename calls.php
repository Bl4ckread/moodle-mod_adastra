<?php

defined('MOODLE_INTERNAL') || die();


/**
 * Function that sends a GET request and returns the response
 * @param $url The url to where the request is sent
 * @param $token The token of the user for authorization
 * @param $data Parameters to be sent with the request
 * @return $result The response from the server converted to an object
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
        throw new Exception('Curl error: ' . curl_error($curl));
    }

    curl_close($curl);

    $result = json_decode($result);

    if (!empty($result->detail)) {
        // If there was an error with token, throw an Exception
        if (substr($result->detail, 0, 20) === 'Invalid token header') {
            throw new Exception('Invalid token header');
        }
    }

    return $result;
}

