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

require(__DIR__.'/../../config.php');
require_once(__DIR__.'/teachers/editcourse_lib.php');
require_once(__DIR__.'/locallib.php');

$cid = required_param('course', PARAM_INT); // Course ID.
$course = $DB->get_record('course', array('id' => $cid), '*', MUST_EXIST);

require_login($course, false);
$context = context_course::intance($cid);
require_capability('mod/adastra:addinstance', $context);

if ($_SERVER['REQUEST_METHOD'] == 'POST') { // TODO: Find out if we can check the request method with some Moodle internal function.
    $modulenumbering = \mod_adastra\local\course_config::MODULE_NUMBERING_ARABIC;
    $requestmodulenumbering = optional_param('module_numbering', -1, PARAM_INT);
    if ($requestmodulenumbering >= 0) {
        $modulenumbering = $requestmodulenumbering;
    }
    $contentnumbering = \mod_adastra\local\course_config::CONTENT_NUMBERING_ARABIC;
    $requestcontentnumbering = optional_param('content_numbering', -1, PARAM_INT);
    if ($requestcontentnumbering >= 0) {
        $contentnumbering = $requestcontentnumbering;
    }

}

$PAGE->set_pagelayout('incourse');
$PAGE->set_url(\mod_adastra\local\urls\urls::editcourse($cid, true));
$PAGE->set_title(format_string(get_string('editcourse', \mod_adastra\local\exercise_round::MODNAME)));
$PAGE->set_heading(format_string($course->fullname));

