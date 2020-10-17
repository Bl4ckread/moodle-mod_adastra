<?php

require(__DIR__.'/../../config.php');
require('calls.php');

// Set the page information
$PAGE->set_context(context_system::instance());
$PAGE->set_url('/mod/adastra/courses.php');
$PAGE->set_title("Courses");
$PAGE->set_heading("Courses");


// Get the information of the courses
$courses = call_API(
    'https://tie-plus-test.rd.tuni.fi/api/v2/courses/', 
    '!! PUT HERE YOUR PRIVATE KEY !!', 
    ['format=json']
);

echo('<b><h2>Plussa Courses:</h2></b>');
foreach($courses->results as $course) {
    echo('<b>' . $course->name . '</b><br>');
    echo('ID: ' . $course->id . '<br>');
    echo('Code: ' . $course->code . '<br>');
    echo('Instance name: ' . $course->instance_name . '<br>');
    echo('URL: ' . $course->url . '<br>');
    echo('HTML URL: ' . $course->html_url . '<br>');
    echo('<br>');
}

