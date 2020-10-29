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
// along with Moodle.  If not, see <https://www.gnu.org/licenses/>.

/**
 * Display contents of courses on a page.
 *
 * @package     mod_adastra
 * @copyright   2020 Your Name <you@example.com>
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require(__DIR__.'/../../config.php');
require_once(__DIR__.'/calls.php');

// Set the page information.
$PAGE->set_context(context_system::instance());
$PAGE->set_url('/mod/adastra/courses.php');
$PAGE->set_title("Courses");
$PAGE->set_heading("Courses");


// Get the information of the courses.
$apikey = get_config('mod_adastra', 'apikey');
$courses = adastra_call_api(
    'https://tie-plus-test.rd.tuni.fi/api/v2/courses/',
    $apikey,
    ['format=json']
);

echo('<b><h2>Plussa Courses:</h2></b>');
foreach ($courses->results as $course) {
    echo('<b>' . $course->name . '</b><br>');
    echo('ID: ' . $course->id . '<br>');
    echo('Code: ' . $course->code . '<br>');
    echo('Instance name: ' . $course->instance_name . '<br>');
    echo('URL: ' . $course->url . '<br>');
    echo('HTML URL: ' . $course->html_url . '<br>');
    echo('<br>');
}

