<?php
// This file is part of Moodle - https://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * Library of API call functions.
 *
 * @package     mod_adastra
 * @copyright   2020 Your Name <you@example.com>
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();


/**
 * Function that sends a GET request and returns the response
 * @param $url The url to where the request is sent
 * @param $token The token of the user for authorization
 * @param $data Parameters to be sent with the request
 * @return $result The response from the server converted to an object
 */
function adastra_call_api($url, $token = false, $data = false) {
    $curl = curl_init();

    // If some parameters should be given with the request, add
    // them to the end of the URL.
    if ($data) {
        $url = sprintf('%s?%s', $url, http_build_query($data));
    }

    // Authentication.
    curl_setopt(
        $curl, CURLOPT_HTTPHEADER, [
            'Authorization: Token ' . $token
        ]
    );

    curl_setopt($curl, CURLOPT_URL, $url);
    curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);

    $result = curl_exec($curl);

    // If no result, then throw an Exception.
    if ($result === false) {
        throw new Exception('Curl error: ' . curl_error($curl));
    }

    curl_close($curl);

    $result = json_decode($result);

    if (!empty($result->detail)) {
        // If there was an error with token, throw an Exception.
        if (substr($result->detail, 0, 20) === 'Invalid token header') {
            throw new Exception('Invalid token header');
        }
    }

    return $result;
}

