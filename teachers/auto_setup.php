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

$cid = required_param('course', PARAM_INT);
$course = get_course($cid);

require_login($course, false);
$context = context_course::instance($cid);
require_capability('mod/adastra::addinstance', $context);

$pageurl = mod_adastra\local\urls\urls::auto_setup($cid, true);

// Print the page header.
$PAGE->set_pagelayout('incourse');
$PAGE->set_url($pageurl);
$PAGE->set_title(format_string(get_string('autosetup', mod_adastra\local\data\exercise_round::MODNAME)));
$PAGE->set_heading(format_string($course->$fullname));

// Setup the navbar.
adastra_edit_course_navbar_add(
    $PAGE,
    $cid,
    get_string('automaticsetup', mod_adastra\local\data\exercise_round::MODNAME),
    $pageurl,
    'autosetup'
);

$defaultvalues = $DB->get_record(mod_adastra\local\data\course_config::TABLE, array('course' => $cid));
if ($defaultvalues === false) {
    $defaultvalues = null;
}

// Output starts here.
// Moodle forms should be initialized before $output->header.
$form = new \mod_adastra\form\autosetup_form($defaultvalues, 'autosetup.php?course=' . $cid);
if ($form->is_cancelled()) {
    // Handle form cancel operation, if cancel button is present on form.
    redirect(\mod_adastra\local\urls\urls::edit_course($cid, true));
    exit(0);
}
$output = $PAGE->get_renderer(mod_adastra\local\data\exercise_round::MODNAME);

echo $output->header();
echo $output->heading_with_help(
    get_string('autosetup', mod_adastra\local\data\exercise_round::MODNAME),
    'autosetup',
    mod_adastra\local\data\exercise_round::MODNAME
);

// Form processing and displaying is done here.
if ($fromform = $form->get_data()) {
    // In this case you process validated data. Method $mform->get_data() returns data posted in form.
    $errors = mod_adastra\local\autosetup\auto_setup::configure_content_from_url(
        $cid,
        $fromform->sectionnum,
        $fromform->configurl,
        $fromform->apikey
    );
}