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

$id = required_param('id', PARAM_INT); // Learning object ID.

$learningobject = mod_adastra\local\data\learning_object::create_from_id($id);
$exround = $learningobject->get_exercise_round();
$category = $learningobject->get_category();
list($course, $cm) = get_course_and_cm_from_instance($exround->get_id(), mod_adastra\local\data\exercise_round::TABLE);

require_login($course, false, $cm);
// Check additionally that the user is enrolled in the course.
$context = context_module::instance($cm->id);
// This should prevent guest access.
require_capability('mod/adastra:view', $context);
if (
        (!$cm->visible || $exround->is_hidden() || $learningobject->is_hidden() || $category->is_hidden()) &&
        !has_capability('moodle/course:manageactivities', $context)
) {
    // Show hidden exercise only for teachers.
    throw new required_capability_exception($context, 'moodle/course:manageactivities', 'nopermissions', '');
}

$sbms = null;
$waitforasyncgrading = false; // If frontend JS should poll for the submission status.
$errormsg = null;

// TODO: handle submission

// Event for logging (viewing the page).
$event = mod_adastra\event\exercise_viewed::create(array(
        'objectid' => $id,
        'context' => $PAGE->context,
));
$event->trigger();

if (adastra_is_ajax()) {
    // Render page content.
    $output = $PAGE->get_renderer(\mod_adastra\local\data\exercise_round::MODNAME);

    $renderable = new mod_adastra\output\exercise_plain_page(
            $exround,
            $learningobject,
            $USER,
            $errormsg,
            $sbms,
            $waitforasyncgrading
    );
    header('Content-Type: text/html');
    echo $output->render($renderable);
    // No Moodle header/footer in the output.
} else {
    if (
            !has_capability('mod/adastra:viewallsubmissions', $context) &&
            $learningobject->get_parent_object() &&
            $learningobject->is_unlisted()
    ) {
        // Students may not open the independent exercise page if the exercise is embedded in a chapter.
        redirect(mod_adastra\local\urls\urls::exercise($learningobject, true, false));
        exit(0);
    }

    // Add CSS and JS.
    adastra_page_require($PAGE);

    // Add Moodle navbar item for the exercise, round is already there.
    $exercisenav = adastra_navbar_add_exercise($PAGE, $cm->id, $learningobject);
    $exercisenav->make_active();

    $PAGE->set_url(mod_adastra\local\urls\urls::exercise($learningobject, true));
    $PAGE->set_title(format_string($learningobject->get_name()));
    $PAGE->set_heading(format_string($course->fullname));

    // Render page content.
    $output = $PAGE->get_renderer(mod_adastra\local\data\exercise_round::MODNAME);

    $renderable = new mod_adastra\output\exercise_page($exround, $learningobject, $USER, $PAGE->requires, $errormsg);
    // Must call render before outputting any page content (header), since the exercise page must add page
    // requirements (CSS, JS) based on the remote page downloaded from the exercise service.
    $pagecontent = $output->render($renderable);

    echo $output->header();

    echo $pagecontent;

    echo $output->footer();
}