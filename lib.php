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
 * Library of interface functions and constants.
 *
 * @package     mod_adastra
 * @copyright   2020 Your Name <you@example.com>
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

use mod_adastra\local\data\exercise_round;
use mod_adastra\local\data\submission;
use mod_adastra\local\data\learning_object;
use mod_adastra\local\data\category;
use mod_adastra\local\urls\urls;
/**
 * Return if the plugin supports $feature.
 *
 * @param string $feature Constant representing the feature.
 * @return true | null True if the feature is supported, null otherwise.
 */
function adastra_supports($feature) {
    switch ($feature) {
        case FEATURE_MOD_INTRO:
            return true;
        case FEATURE_SHOW_DESCRIPTION:
            return true;
        case FEATURE_GRADE_HAS_GRADE:
            return true;
        case FEATURE_CONTROLS_GRADE_VISIBILITY:
            return true;
        case FEATURE_BACKUP_MOODLE2:
            return true;
        default:
            return null;
    }
}

/**
 * Saves a new instance of Ad Astra into the database.
 *
 * Given an object containing all the necessary data, (defined by the form
 * in mod_form.php) this function will create a new instance and return the id
 * number of the instance.
 *
 * @param object $adastra Submitted data from the form in mod_form.php.
 * @param mod_adastra_mod_form $mform The form instance itself (if needed).
 * @return int The id of the newly inserted record, 0 if failed.
 */
function adastra_add_instance(stdClass $adastra, mod_adastra_mod_form $mform = null) {
    return exercise_round::add_instance($adastra);
}

/**
 * Updates an instance of Ad Astra in the database.
 *
 * Given an object containing all the necessary data (defined in mod_form.php),
 * this function will update an existing instance with new data.
 *
 * @param stdClass $adastra An object from the form in mod_form.php.
 * @param mod_adastra_mod_form $mform The form.
 * @return bool True if successful, false otherwise.
 */
function adastra_update_instance(stdClass $adastra, mod_adastra_mod_form $mform = null) {

    $adastra->id = $adastra->instance;
    return exercise_round::update_instance($adastra);
}

/**
 * Removes an instance of Ad Astra from the database.
 *
 * Given an ID of an instance of this module, this function will
 * permanently delete the instance and any data that depends on it.
 *
 * @param int $id Id of the module instance.
 * @return bool True if successful, false on failure.
 */
function adastra_delete_instance($id) {
    global $DB;

    $adastra = $DB->get_record(exercise_round::TABLE, array('id' => $id));
    if (!$adastra) {
        return false;
    }
    $exround = new exercise_round($adastra);
    return $exround->delete_instance();
}

/**
 * Returns a small object with summary information about what a user has done
 * with a given instance of this module. Used for user activity reports.
 *
 * $return->time = the time they did it.
 * $return->info = a short text description.
 *
 * @param stdClass $course The course record.
 * @param stdClass $user The user record.
 * @param cm_info|stdClass $cm The course module info object or record.
 * @param stdClass $adastra The Ad Astra instance record.
 * @return stdClass|null
 */
function adastra_user_outline($course, $user, $cm, $adastra) {
    // This callback is called from report/outline/user.php, a course totals for user activity report.
    // The report is accessible from the course user profile page. Site may disallow students from viewing their reports.
    // Return the user's best total grade in the round and nothing else as the outline.

    $exround = new exercise_round($adastra);
    $summary = new mod_adastra\local\summary\user_module_summary($exround, $user);

    $return = new stdClass();
    $return->time = null;

    if ($summary->is_submitted()) {
        $maxpoints = $summary->get_max_points();
        $points = $summary->get_total_points();
        $return->info = get_string('grade', exercise_round::MODNAME) . " {$points}/{$maxpoints}";
        $return->time = $summary->get_latest_submission_time();
    } else {
        $return->info = get_string('nosubmissions', exercise_round::MODNAME);
    }

    return $return;
}

/**
 * Prints a detailed representation of what a user has done with a given instance
 * of this module, for user activity reports. It is supposed to echo directly without returning a value.
 *
 * @param stdClass $course The current course record.
 * @param stdClass $user The record of the user we are generating the report for.
 * @param cm_info $cm Course module info.
 * @param stdClass $adastra The module instance record.
 * @return void
 */
function adastra_user_complete($course, $user, $cm, $adastra) {
    // This callback is called from report/outline/user.php, a course totals for user activity report.
    // The report is accessible from the course user profile page. Site may disallow students from viewing their reports.
    // Reuse the other callback that gathers all submissions in a round for a user.

    $activities = array();
    $index = 0;
    adastra_get_recent_mod_activity($activities, $index, 0, $course->id, $cm->id, $user->id);
    $modnames = get_module_types_names();
    foreach ($activities as $activity) {
        adastra_print_recent_mod_activity($activity, $course->id, true, $modnames, true);
    }
}

/**
 * Given a course and a time, this module should find recent activity that has occured
 * in Ad Astra activities and print it out.
 *
 * @param stdClass $course The course record.
 * @param bool $viewfullnames If true, full names should be displayed.
 * @param int $timestart Print activity since this timestamp.
 * @return bool True if anything was printed, otherwise false.
 */
function adastra_print_recent_activity($course, $viewfullnames, $timestart) {
    // This callback is used by the Moodle recent activity block.
    global $USER, $DB, $OUTPUT;

    // All submissions in the course since $timestart.
    $sql = 'SELECT s.*
            FROM {' . submission::TABLE . '} s
            WHERE s.exerciseid IN (
            SELECT id
            FROM {' . learning_object::TABLE . '}
            WHERE categoryid IN (
            SELECT id
            FROM {' . category::TABLE . '}
            WHERE course = ?
            )
            ) AND s.submissiontime > ?';
    $params = array($course->id, $timestart);

    $context = context_course::instance($course->id);
    $isteacher = has_capability('mod/adastra:viewallsubmissions', $context);
    if (!$isteacher) {
        // Students only see their own recent activity, nothing from other students.
        $sql .= ' AND s.submitter = ?';
        $params[] = $USER->id;
    }

    $submissionsbyexercise = array();
    $submissionrecords = $DB->get_recordset_sql($sql, $params);
    // Organize recent submissions by exercise.
    foreach ($submissionrecords as $sbmsrec) {
        $sbms = new submission($sbmsrec);
        if (isset($submissionsbyexercise[$sbmsrec->exerciseid])) {
            $submissionsbyexercise[$sbmsrec->exerciseid][] = $sbms;
        } else {
            $submissionsbyexercise[$sbmsrec->exerciseid] = array($sbms);
        }
    }
    $submissionrecords->close();

    if (empty($submissionsbyexercise)) {
        return false;
    }

    echo $OUTPUT->heading(get_string('exercisessubmitted', exercise_round::MODNAME) . ':', 3);

    if ($isteacher) {
        // Teacher: show the number of recent submissions in each exercise.
        foreach ($submissionsbyexercise as $submissions) {
            $exercise = $submissions[0]->get_exercise();
            $numsubmissions = count($submissions);
            $text = $exercise->get_name();

            // Echo similar HTML structure as function print_recent_activity_note in moodle/lib/weblib.php,
            // but without any specific user.
            $out = '';
            $out .= '<div class="head">';
            $out .= '<div class="date">'.userdate(time(), get_string('strftimerecent')).'</div>';
            $out .= '<div class="name">'.
                    get_string('submissionsreceived', exercise_round::MODNAME, $numsubmissions).'</div>';
            $out .= '</div>';
            $out .= '<div class="info"><a href="'. urls::submission_list($exercise) .'">'.
                    format_string($text, true).'</a></div>';
            echo $out;
        }
    } else {
        // Student: of recent submissions, show the best one in each exercise.
        foreach ($submissionsbyexercise as $submissions) {
            $best = $submissions[0];
            foreach ($submissions as $sbms) {
                if ($sbms->get_grade() > $best->get_grade()) {
                    $best = $sbms;
                }
            }

            $text = $best->get_exercise()->get_name();
            if ($best->is_graded()) {
                $grade = $best->get_grade();
                $maxpoints = $best->get_exercise()->get_max_points();
                $text .= ' ('. get_string('grade', exercise_round::MODNAME) . " {$grade}/{$maxPoints})";
            }
            print_recent_activity_note(
                    $best->get_submission_time(),
                    $USER,
                    $text,
                    urls::submission($best),
                    false,
                    $viewfullnames
            );
        }
    }

    return true;
}

/**
 * Prepares the recent activity data.
 *
 * This callback function is supposed to populate the passed array with custom activity records.
 * These records are then rendered into HTML via {@link adastra_print_recent_mod_activity()}.
 *
 * Returns void, it adds items into $activites and increases $index.
 *
 * @param array $activities A sequentially indexed array of objects with added 'cmid' property.
 * @param int $index The index in the $activities to sue for the next record.
 * @param int $timestart Append activities since this time.
 * @param int $courseid The id of the course we produce the report for.
 * @param int $cmid The course module id.
 * @param int $userid Check for a particular user's activity only, defaults to 0 (all users).
 * @param int $groupid Check for a particular group's activity only, defaults to 0 (all groups).
 * @return void
 */
function adastra_get_recent_mod_activity(&$activities, &$index, $timestart, $courseid, $cmid, $userid=0, $groupid=0) {
    // This callback is called from course/recent.php, which is linked from the recent activity block.
    global $USER, $DB;

    $context = context_course::instance($courseid);
    $isteacher = has_capability('mod/adastra:viewallsubmissions', $context);
    if ($userid != $USER->id && !$isteacher) {
        if ($userid == 0) {
            // All users requested but a student sees only themselves.
            $userid = $USER->id;
        } else {
            return; // Only teachers see other users' activity.
        }
    }

    $modinfo = get_fast_modinfo($courseid);
    $cm = $modinfo->get_cm($cmid);
    $exround = exercise_round::create_from_id($cm->instance);

    // All submissions in the round given by $cmid since $timestart.
    $sql = 'SELECT s.*
            FROM {' . submission::TABLE . '} s
            WHERE s.exerciseid IN (
            SELECT id
            FROM {' . learning_object::TABLE . '}
            WHERE roundid = ?
            ) AND s.submissiontime > ?';
    $params = array($exround->get_id(), $timestart);

    if ($userid != 0) {
        // Only one user.
        $sql .= ' AND s.submitter = ?';
        $params[] = $userid;
    }

    $sql .= ' ORDER BY s.submissiontime ASC';

    $submissionsbyexercise = array();
    $submissionrecords = $DB->get_recordset_sql($sql, $params);
    // Organize recent submissions by exercise.
    foreach ($submissionrecords as $sbmsrec) {
        $sbms = new submission($sbmsrec);
        if (isset($submissionsbyexercise[$sbmsrec->exerciseid])) {
            $submissionsbyexercise[$sbmsrec->exerciseid][] = $sbms;
        } else {
            $submissionsbyexercise[$sbmsrec->exerciseid] = array($sbms);
        }
    }
    $submissionrecords->close();

    foreach ($exround->get_exercises(true) as $exercise) {
        if (isset($submissionsbyexercise[$exercise->get_id()])) {
            foreach ($submissionsbyexercise[$exercise->get_id()] as $sbms) {
                $item = new stdClass();
                $item->user = $sbms->get_submitter();
                $item->time = $sbms->get_submission_time();
                $item->grade = $sbms->get_grade();
                $item->maxpoints = $exercise->get_max_points();
                $item->isgraded = $sbms->is_graded();
                $item->name = $exercise->get_name();
                $item->submission = $sbms;
                // The following fields are required by Moodle.
                $item->cmid = $cmid;
                $item->type = exercise_round::TABLE;

                $activities[$index++] = $item;
            }
        }
    }
}

/**
 * Prints a single activity item prepared by {@link adastra_get_recent_mod_activity()}
 *
 * @param stdClass $activity An activity record with added 'cmid' property.
 * @param int $courseid The id of the course we produce the report for.
 * @param bool $detail If true, print a detailed report.
 * @param array $modnames Mod names as returned by {@link get_module_types_names()}.
 * @param bool $viewfullnames If true, display users' full names.
 */
function adastra_print_recent_mod_activity($activity, $courseid, $detail, $modnames, $viewfullnames) {
    // Modified from the corresponding function in mod_assign (function assign_print_recent_mod_activity in lib.php).
    global $CFG, $OUTPUT;

    echo '<table class="assignment-recent">';

    echo '<tr><td class="userpicture">';
    echo $OUTPUT->user_picture($activity->user);
    echo '</td><td>';

    $modname = $modnames[exercise_round::TABLE]; // A localized module name.
    echo '<div class="title">';
    echo $OUTPUT->pix_icon('icon', $modname, exercise_round::MODNAME);
    echo '<a href="' . urls::submission($activity->submission) . '">';
    echo $activity->name;
    echo '</a>';
    echo '</div>';

    if ($activity->isgraded) {
        echo '<div class="grade">';
        echo get_string('grade', exercise_round::MODNAME) . ': ';
        echo "{$activity->grade}/{$activity->maxpoints}";
        echo '</div>';
    }

    echo '<div class="user">';
    echo "<a href=\"{$CFG->wwwroot}/user/view.php?id={$activity->user->id}&amp;course={$courseid}\">";
    echo fullname($activity->user) . '</a>  - ' . userdate($activity->time);
    echo '</div>';

    echo '</td></tr></table>';
}

/**
 * Function to be run periodically according to the Moodle cron.
 *
 * This function searches for things that need to be done, such
 * as sending out mail, toggling flags etc ...
 *
 * Note that this has been deprecated in favour of scheduled task API.
 *
 * @return boolean
 */
function adastra_cron () {
    return true; // No failures.
}

/**
 * Returns all other caps used in the module.
 *
 * For example, this could be array('moodle/site:accessallgroups') if the
 * module uses that capability.
 *
 * @return array
 */
function adastra_get_extra_capabilities() {
    return array(
        'moodle/course:manageactivities', // Used in submission.php and exercise.php.
        'moodle/role:assign', // Used in auto_setup.php.
        'enrol/manual:enrol', // Used in auto_setup.php.
    );
}

/* Gradebook API */

/**
 * Is a given scale used by the instance of Ad Astra?
 *
 * This function returns true if a scale is being used by one Ad Astra
 * instance and if it has support for grading and scales.
 *
 * @param int $adastraid The ID of an instance of this module.
 * @param int $scaleid The ID of the scale.
 * @return bool True if the scale is used by the given Ad Astra instance.
 */
function adastra_scale_used($adastraid, $scaleid) {
    return false; // Ad Astra does not use scales.
}

/**
 * Checks if a scale is being used by any instances of Ad Astra.
 *
 * This is used to find out if a scale is used anywhere.
 *
 * @param int $scaleid The ID of the scale.
 * @return boolean True if the scale is used by any Ad Astra instances.
 */
function adastra_scale_used_anywhere($scaleid) {
    return false; // Ad Astra does not use scales.
}

/**
 * Creates or updates the grade item for the given Ad Astra instance (exercise round).
 *
 * Needed by {@link grade_update_mod_grades()}.
 *
 * @param stdClass $adastra An instance object with extra cmidnumber and modname properties.
 * @param $grades Save grades in the gradebook, or give string reset to delete all grades.
 * @return void
 */
function adastra_grade_item_update(stdClass $adastra, $grades=null) {
    $exround = new exercise_round($adastra);
    $reset = $grades === 'reset';
    $exround->update_gradebook_item($reset);

    if ($grades !== null && !$reset) {
        $exround->update_grades($grades);
    }
}

/**
 * Delete the grade item for a given Ad Astra instance.
 *
 * @param stdClass $adastra The instance object.
 * @return grade_item
 */
function adastra_grade_item_delete($adastra) {
    $exround = new exercise_round($adastra);
    return $exround->delete_gradebook_item();
}

/**
 * Update Ad Astra grades in the gradebook.
 *
 * Needed by {@link grade_update_mod_grades()}.
 *
 * @param stdClass $adastra An instance object with extra cmidnumber and modname properties.
 * @param int $userid Update the grade of a specific user only, 0 means all participants.
 * @param bool $nullifnone If a single user is specified, $nullifnone is true and
 *     the user has no grade then a grade item with a null rawgrade should be inserted.
 * @return void
 */
function adastra_update_grades(stdClass $adastra, $userid = 0, $nullifnone = true) {
    // This function has no grades parameter, so the grades should be read
    // from some plugin database or an external server.
    $exround = new exercise_round($adastra);
    $exround->write_all_grades_to_gradebook($userid, $nullifnone);
}

/* File API */

/**
 * Returns the lists of all browsable file areas within the given module context.
 *
 * The file area 'intro' for the activity introduction field is added automatically
 * by {@link file_browser::get_file_info_context_module()}
 *
 * @param stdClass $course
 * @param stdClass $cm
 * @param stdClass $context
 * @return array An array of [(string)filearea] => (string)description items.
 */
function adastra_get_file_areas($course, $cm, $context) {
    return array(
            submission::SUBMITTED_FILES_FILEAREA => get_string('submittedfilesareadescription', exercise_round::MODNAME),
    );
}

/**
 * File browsing support for Ad Astra file areas.
 *
 * @package mod_adastra
 * @category files
 *
 * @param file_browser $browser
 * @param array $areas
 * @param stdClass $course
 * @param stdClass $cm
 * @param stdClass $context
 * @param string $filearea
 * @param int $itemid
 * @param string $filepath
 * @param string $filename
 * @return file_info instance or null if not found
 */
function adastra_get_file_info($browser, $areas, $course, $cm, $context, $filearea, $itemid, $filepath, $filename) {
    global $CFG, $DB, $USER;
    if ($context->contextlevel != CONTEXT_MODULE) {
        return null;
    }
    // Make sure the filearea is one of those used by the plugin.
    if ($filearea !== submission::SUBMITTED_FILES_FILEAREA) {
        return null;
    }
    // Check the relevant capabilities - these may vary depending on the filearea being accessed.
    if (!has_capability('mod/adastra:view', $context)) {
        return null;
    }
    // Parameter itemid is the ID of the submission which the file was submitted to.
    $submissionrecord = $DB->get_record(submission::TABLE, array('id' => $itemid), '*', IGNORE_MISSING);
    if ($submissionrecord === false) {
        return null;
    }
    // Check that the user may view the file.
    if ($submissionrecord->submitter != $USER->id && !has_capability('mod/adastra:viewallsubmissions', $context)) {
        return null;
    }

    // Retrieve the file from the Files API.
    $fs = get_file_storage();
    $file = $fs->get_file($context->id, exercise_round::MODNAME, $filearea, $itemid, $filepath, $filename);
    if (!$file) {
        return null; // The file does not exist.
    }

    $urlbase = $CFG->wwwroot.'/pluginfile.php'; // The standard Moodle script for serving files.

    return new file_info_stored($browser, $context, $file, $urlbase, $filearea, $itemid, true, true, false);
}

/**
 * Serves the files from the Ad Astra file areas.
 *
 * @package mod_adastra
 * @category files
 *
 * @param stdClass $course The course object.
 * @param stdClass $cm The course module object.
 * @param stdClass $context Ad Astra's context.
 * @param string $filearea The name of the file area.
 * @param array $args Extra arguments (itemid, path).
 * @param bool $forcedownload If true, force download.
 * @param array $options Additional options affecting the file serving.
 */
function adastra_pluginfile($course, $cm, $context, $filearea, array $args, $forcedownload, array $options=array()) {
    global $DB, $USER;
    // Check the contextlevel is as expected - if your plugin is a block, this becomes CONTEXT_BLOCK, etc.
    if ($context->contextlevel != CONTEXT_MODULE) {
        return false;
    }

    // Make sure the filearea is one of those used by the plugin.
    if ($filearea !== submission::SUBMITTED_FILES_FILEAREA) {
        return false;
    }

    // Make sure the user is logged in and has access to the module.
    // Plugins that are not course modules should leave out the 'cm' part.
    require_login($course, true, $cm);

    // Check the relevant capabilities - these may vary depending on the filearea being accessed.
    if (!has_capability('mod/adastra:view', $context)) {
        return false;
    }

    // Leave this line out if you set the itemid to null in moodle_url::make_pluginfile_url (set $itemid to 0 instead).
    $itemid = (int) array_shift($args); // The first item in the $args array.

    // Use the itemid to retrieve any relevant data records and perform any security checks to see if the
    // user really does have access to the file in question.
    // Parameter itemid is the ID of the submission which the file was submitted to.
    $submissionrecord = $DB->get_record(submission::TABLE, array('id' => $itemid), '*', IGNORE_MISSING);
    if ($submissionrecord === false) {
        return false;
    }
    if ($submissionrecord->submitter != $USER->id && !has_capability('mod/adastra:viewallsubmissions', $context)) {
        return false;
    }

    // Extract the filename / filepath from the $args array.
    $filename = array_pop($args); // The last item in the $args array.
    if (!$args) {
        $filepath = '/'; // If $args is empty the path is '/'.
    } else {
        $filepath = '/'.implode('/', $args).'/'; // Elements of the file path are contained in $args.
    }

    // Retrieve the file from the Files API.
    $fs = get_file_storage();
    $file = $fs->get_file($context->id, exercise_round::MODNAME, $filearea, $itemid, $filepath, $filename);
    if (!$file) {
        return false; // The file does not exist.
    }

    // We can now send the file back to the browser.
    send_stored_file($file, 0, 0, $forcedownload, $options);
}

/* Calendar API */

/**
 * Is the event visible?
 *
 * This is used to determine global visibility of an event in all places throughout Moodle.
 * For example, the visibility could change based on the current user's role
 * (student or teacher).
 *
 * @param calendar_event $event
 * @param int $userid The user id to use for all capability checks. Set to 0 for current user (default).
 * @return bool Returns true if the event is visible to the current user, false otherwise.
 */
function adastra_core_calendar_is_event_visible(calendar_event $event, $userid = 0) {
    // Hidden rounds are not shown to students.
    $exround = exercise_round::create_from_id($event->instance);
    $cm = $exround->get_course_module();
    $context = context_module::instance($cm->id);

    $visible = $cm->visible && !$exround->is_hidden();
    if ($visible) {
        return true;
    } else if (has_capability('mod/adastra:addinstance', $context)) {
        // A teacher sees everything.
        return true;
    }
    return false;
}

/**
 * This function receives a calendar event and returns the action associated with it, or null if there is none.
 *
 * This is used by block_myoverview in order to display the event appropriately. If null is returned then the event
 * is not displayed on the block (but can still be displayed in the calendar).
 *
 * @param calendar_event $event
 * @param \core_calendar\action_factory $factory
 * @param int $userid The user id to use for all capability checks. Set to 0 for the current user (default).
 * @return \core_calendar\local\event\entities\action_interface|null
 */
function adastra_core_calendar_provide_event_action(
        calendar_event $event,
        \core_calendar\action_factory $factory,
        $userid = 0
    ) {
    $exround = exercise_round::create_from_id($event->instance);

    // Do not display the event after the round has closed.
    // If a student has a deadline extension in some exercise, it is not taken into account here.
    if ($exround->has_expired(null, true)) {
        return null;
    }

    return $factory->create_instance(
            get_string('deadline', exercise_round::MODNAME) . ': ' . $exround->get_name(),
            urls::exercise_round($exround, true),
            1,
            $exround->is_open() || $exround->is_late_submission_open()
    );
}