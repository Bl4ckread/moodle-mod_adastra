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
 * Prints an instance of ad astra (exercise round).
 *
 * @package     mod_adastra
 * @copyright   2020 Your Name <you@example.com>
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require(__DIR__.'/../../config.php');
require_once(__DIR__.'/locallib.php');

$id = optional_param('id', 0, PARAM_INT); // Course module ID, or
$n = optional_param('s', 0, PARAM_INT); // ... exercise round ID.


if ($id) {
    list($course, $cm) = get_course_and_cm_from_cmid($id, mod_adastra\local\data\exercise_round::TABLE);
    $adastra = $DB->get_record(mod_adastra\local\data\exercise_round::TABLE, array('id' => $cm->instance), '*', MUST_EXIST);
} else if ($n) {
    $adastra = $DB->get_record(mod_adastra\local\data\exercise_round::TABLE, array('id' => $n), '*', MUST_EXIST);
    list($course, $cm) = get_course_and_cm_from_cmid($adastra->id, mod_adastra\local\data\exercise_round::TABLE);
} else {
    print_error('missingparam', '', '', 'id');
}

require_login($course, false, $cm);
$context = context_module::instance($cm->id);

$exround = new mod_adastra\local\data\exercise_round($adastra);

// This should prevent guest access.
require_capability('mod/adastra:view', $context);
if (
        (!$cm->visible || $exround->is_hidden()) &&
        !has_capability('moodle/course:manageactivities', $context)
) {
    // Show hidden activity (exercise round page) only to teachers.
    throw new required_capability_exception($context, 'moodle/course:manageactivities', 'nopermissions', '');
}

// Event for logging (viewing the page).
$event = mod_adastra\event\course_module_viewed::create(array(
        'objectid' => $PAGE->cm->instance,
        'context' => $PAGE->context,
));
$event->add_record_snapshot('course', $PAGE->course);
$event->add_record_snapshot($PAGE->cm->modname, $adastra);
$event->trigger();

$PAGE->set_url('/mod/' . mod_adastra\local\data\exercise_round::TABLE . '/view.php', array('id' => $cm->id));
$PAGE->set_title(format_string($exround->get_name()));
$PAGE->set_heading(format_string($course->fullname));

// Render page content.
$output = $PAGE->get_renderer(mod_adastra\local\data\exercise_round::MODNAME);

// Print the page header (Moodle navbar etc.).
echo $output->header();

$renderable = new mod_adastra\output\exercise_round_page($exround, $USER);
echo $output->render($renderable);

echo $output->footer();
