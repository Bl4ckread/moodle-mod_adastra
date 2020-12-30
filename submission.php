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

require(__DIR__.'/../../config.php');
require_once(__DIR__.'/locallib.php');

$id = required_param('id', PARAM_INT); // Submission ID.
$wait = (bool) optional_param('wait', 0, PARAM_INT);

$submission = mod_adastra\local\data\submission::create_from_id($id);
$exercise = $submission->get_exercise();
$exround = $exercise->get_exercise_round();
list($course, $cm) = get_course_and_cm_from_instance($exround->get_id(), mod_adastra\local\data\exercise_round::TABLE);

require_login($course, false, $cm);
$context = context_module::instance($cm->id);
// This should prevent guest access.
require_capability('mod/adastra:view', $context);
if (
        (!$cm->visible || $exround->is_hidden() || $exercise->is_hidden()) &&
        !has_capability('moodle/course:manageactivities', $context)
) {
    // Show hidden exercise only to teachers.
    throw new required_capability_exception($context, 'moodle/course:manageactivities', 'nopermissions', '');
}

// Check that the user is allowed to see the submission (students see only their own submissions).
if (
        $USER->id != $submission->get_submitter()->id &&
        !((has_capability('mod/adastra:viewallsubmissions', $context) && $exercise->is_assistant_viewing_allowed()) ||
                has_capability('mod/adastra:addinstance', $context))
) {
    throw new required_capability_exception($context, 'mod/adastra:viewallsubmissions', 'nopermissions', '');
}

if (adastra_is_ajax()) {
    // Render page content.
    $output = $PAGE->get_renderer(mod_adastra\local\data\exercise_round::MODNAME);

    $renderable = new mod_adastra\output\submission_plain_page($exround, $exercise, $submission);
    header('Content-Type: text/html');
    echo $output->render($renderable);
    // No Moodle header or footer in the output.
} else {

    if (
            !has_capability('mod/adastra:viewallsubmissions', $context) &&
            $exercise->get_parent_object() &&
            $exercise->is_unlisted()
    ) {
        // Students may not open the independet submissions page if the exercise is embedded in a chapter.
        // Redirect to the chapter page in which the student may open submission feedback in modal dialogs.
        redirect(mod_adastra\local\urls\urls::exercise($exercise, true, false));
        exit(0);
    }

    // Print the page header.
    // Add CSS and JS.
    adastra_page_require($PAGE);

    // Add Moodle navbar item for the exercise and the submission, round is already there.
    $exercisenav = adastra_navbar_add_exercise($PAGE, $cm->id, $exercise);
    $submissionnav = adastra_navbar_add_submission($exercisenav, $submission);
    $submissionnav->make_active();

    $PAGE->set_url(mod_adastra\local\urls\urls::submission($submission, true));
    $PAGE->set_title(format_string($exercise->get_name()));
    $PAGE->set_heading(format_string($course->fullname));

    // Render page content.
    $output = $PAGE->get_renderer(mod_adastra\local\data\exercise_round::MODNAME);

    echo $output->header();
    $renderable = new mod_adastra\output\submission_page($exround, $exercise, $submission, $wait);
    echo $output->render($renderable);

    echo $output->footer();
}