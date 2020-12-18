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
 * Display information about all the mod_adastra modules in the requested course.
 *
 * @package     mod_adastra
 * @copyright   2020 Your Name <you@example.com>
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require(__DIR__.'/../../config.php');

require_once(__DIR__.'/locallib.php');

$id = required_param('id', PARAM_INT); // Course ID.

$course = get_course($id);
require_course_login($course);

$coursecontext = \context_course::instance($course->id);

$event = \mod_adastra\event\course_module_instance_list_viewed::create(array(
    'context' => $coursecontext
));
$event->add_record_snapshot('course', $course);
$event->trigger();

$pluralname = get_string('modulenameplural', \mod_adastra\local\data\exercise_round::MODNAME);
$pageurl = \mod_adastra\local\urls\urls::rounds_index($id, true);

$PAGE->set_url($pageurl);
$PAGE->navbar->add($pluralname, $pageurl);
$PAGE->set_title("$course->shortname: $pluralname");
$PAGE->set_heading($course->fullname);
$PAGE->set_pagelayout('incourse');

// Add CSS and JS.
adastra_page_require($PAGE);

// Render page content.
$output = $PAGE->get_renderer(\mod_adastra\local\data\exercise_round::MODNAME);

// Print the page header (Moodle navbar etc.).
echo $output->header();

$renderable = new \mod_adastra\output\index_page($course, $USER);
echo $output->render($renderable);

echo $output->footer();