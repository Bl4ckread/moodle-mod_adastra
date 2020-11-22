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

require(__DIR__.'/../../../config.php');
require_once(__DIR__.'/editcourse_lib.php');
require_once(__DIR__.'/../locallib.php');

$cid = required_param('course', PARAM_INT); // Course ID.
$course = $DB->get_record('course', array('id' => $cid), '*', MUST_EXIST);

require_login($course, false);
$context = \context_course::instance($cid);
require_capability('mod/adastra:addinstance', $context);

// Print the page header.
$PAGE->set_pagelayout('incourse');
$PAGE->set_url(\mod_adastra\local\urls\urls::edit_course($cid, true));
$PAGE->set_title(format_string(get_string('editcourse', \mod_adastra\local\data\exercise_round::MODNAME)));
$PAGE->set_heading(format_string($course->fullname));

// Navbar.
adastra_edit_course_navbar($PAGE, $cid, true);

// Output starts here.
$output = $PAGE->get_renderer(\mod_adastra\local\data\exercise_round::MODNAME);

echo $output->header();
$renderable = new \mod_adastra\output\edit_course_page($cid);
echo $output->render($renderable);

// Finish the page.
echo $output->footer();