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

namespace mod_adastra\privacy;

defined('MOODLE_INTERNAL') || die();

use context;
use core_privacy\local\metadata\collection;
use core_privacy\local\request\approved_contextlist;
use core_privacy\local\request\approved_userlist;
use core_privacy\local\request\contextlist;
use core_privacy\local\request\transform;
use core_privacy\local\request\userlist;
use core_privacy\local\request\writer;

use mod_adastra\local\data\exercise_round;
use mod_adastra\local\data\learning_object;
use mod_adastra\local\data\submission;
use mod_adastra\local\data\deadline_deviation;
use mod_adastra\local\data\submission_limit_deviation;

class provider implements
        \core_privacy\local\metadata\provider,
        \core_privacy\local\request\plugin\provider,
        \core_privacy\local\request\core_userlist_provider {

    public static function get_metadata(collection $collection) : collection {
        $collection->add_subsystem_link(
                'core_files',
                [],
                'privacy:metadata:core_files'
        );
        $collection->add_subsystem_link(
                'core_message',
                [],
                'privacy:metadata:core_message'
        );

        $collection->add_database_table(
                submission::TABLE,
                [
                        'submitter' => 'privacy:metadata:' . submission::TABLE . ':submitter',
                        'submissiontime' => 'privacy:metadata:' . submission::TABLE . ':submissiontime',
                        'exerciseid' => 'privacy:metadata:' . submission::TABLE . ':exerciseid',
                        'feedback' => 'privacy:metadata:' . submission::TABLE . ':feedback',
                        'assistfeedback' => 'privacy:metadata:' . submission::TABLE . ':assistfeedback',
                        'grade' => 'privacy:metadata:' . submission::TABLE . ':grade',
                        'gradingtime' => 'privacy:metadata:' . submission::TABLE . ':gradingtime',
                        'latepenaltyapplied' => 'privacy:metadata:' . submission::TABLE . ':latepenaltyapplied',
                        'servicepoints' => 'privacy:metadata:' . submission::TABLE . ':servicepoints',
                        'submissiondata' => 'privacy:metadata:' . submission::TABLE . ':submissiondata',
                        'gradingdata' => 'privacy:metadata:' . submission::TABLE . ':gradingdata',
                ],
                'privacy:metadata:' . submission::TABLE
        );
        $collection->add_database_table(
                deadline_deviation::TABLE,
                [
                        'submitter' => 'privacy:metadata:' . deadline_deviation::TABLE . ':submitter',
                        'exerciseid' => 'privacy:metadata:' . deadline_deviation::TABLE . ':exerciseid',
                        'extraminutes' => 'privacy:metadata:' . deadline_deviation::TABLE . ':extraminutes',
                ],
                'privacy:metadata:' . deadline_deviation::TABLE
        );
        $collection->add_database_table(
                submission_limit_deviation::TABLE,
                [
                        'submitter' => 'privacy:metadata:' . submission_limit_deviation::TABLE . ':submitter',
                        'exerciseid' => 'privacy:metadata:' . submission_limit_deviation::TABLE . ':exerciseid',
                        'extrasubmissions' => 'privacy:metadata:' . submission_limit_deviation::TABLE . ':extrasubmissions',
                ],
                'privacy:metadata:' . submission_limit_deviation::TABLE
        );
        $collection->add_external_location_link(
                'exerciseservice',
                [
                        'submissiondata' => 'privacy:metadata:exerciseservice:submissiondata',
                ],
                'privacy:metadata:exerciseservice'
        );
        return $collection;
    }

    /**
     * Get the list of contexts that contain user information for the specified user.
     *
     * @param integer $userid The user to search.
     * @return contextlist The list of contexts used in this plugin.
     */
    public static function get_contexts_for_userid(int $userid) : contextlist {
        $contextlist = new contextlist();
        $sql = "SELECT DISTINCT c.id
                FROM {context} c
                JOIN {course_modules} cm ON cm.id = c.instanceid AND c.contextlevel = :contextlevel
                JOIN {" . exercise_round::TABLE . "} exround ON exround.id = cm.instance
                JOIN {" . learning_object::TABLE . "} lobject ON lobject.roundid = exround.id
                JOIN {" . submission::TABLE . "} sbms ON sbms.exerciseid = lobject.id
                WHERE sbms.submitter = :submitter";
        $params = array(
                'contextlevel' => CONTEXT_MODULE,
                'submitter' => $userid,
        );
        $contextlist->add_from_sql($sql, $params);

        $sql = "SELECT DISTINCT c.id
                FROM {context} c
                JOIN {course_modules} cm ON cm.id = c.instanceid AND c.contextlevel = :contextlevel
                JOIN {" . exercise_round::TABLE . "} exround ON exround.id = cm.instance
                JOIN {" . learning_object::TABLE . "} lobject ON lobject.roundid = exround.id
                JOIN {" . deadline_deviation::TABLE . "} dldeviations ON dldeviations.exerciseid = lobject.id
                WHERE dldeviations.submitter = :submitter";
        $contextlist->add_from_sql($sql, $params);

        $sql = "SELECT DISTINCT c.id
                FROM {context} c
                JOIN {course_modules} cm ON cm.id = c.instanceid AND c.contextlevel = :contextlevel
                JOIN {" . exercise_round::TABLE . "} exround ON exround.id = cm.instance
                JOIN {" . learning_object::TABLE . "} lobject ON lobject.roundid = exround.id
                JOIN {" . submission_limit_deviation::TABLE . "} sbmsdevs ON sbmsdevs.exerciseid = lobject.id
                WHERE sbmsdevs.submitter = :submitter";
        $contextlist->add_from_sql($sql, $params);

        return $contextlist;
    }

    /**
     * Get the list of users who have data within a context
     *
     * @param userlist $userlist The userlist containing the list of users who have data in this
     * context/plugin combination.
     * @return void
     */
    public static function get_users_in_context(userlist $userlist) {
        $context = $userlist->get_context();

        if (!$context instanceof \context_module) {
            return;
        }

        $params = array(
                'instanceid' => $context->instanceid,
        );
        $sql = "SELECT sbms.submitter
                FROM {course_modules} cm
                JOIN {" . exercise_round::TABLE . "} exround ON exround.id = cm.instance
                JOIN {" . learning_object::TABLE . "} lobject ON lobject.roundid = exround.id
                JOIN {" . submission::TABLE . "} sbms ON sbms.exerciseid = lobject.id
                WHERE cm.id = :instanceid";
        $userlist->add_from_sql('submitter', $sql, $params);

        $sql = "SELECT dldev.submitter
                FROM {course_modules} cm
                JOIN {" . exercise_round::TABLE . "} exround ON exround.id = cm.instance
                JOIN {" . learning_object::TABLE . "} lobject ON lobject.roundid = exround.id
                JOIN {" . deadline_deviation::TABLE . "} dldev ON dldev.exerciseid = lobject.id
                WHERE cm.id = :instanceid";
        $userlist->add_from_sql('submitter', $sql, $params);

        $sql = "SELECT sbmsdev.submitter
                FROM {course_modules} cm
                JOIN {" . exercise_round::TABLE . "} exround ON exround.id = cm.instance
                JOIN {" . learning_object::TABLE . "} lobject ON lobject.roundid = exround.id
                JOIN {" . submission_limit_deviation::TABLE . "} sbmsdev ON sbmsdev.exerciseid = lobject.id
                WHERE cm.id = :instanceid";
        $userlist->add_from_sql('submitter', $sql, $params);
    }

    /**
     * Export all user data for the specified user, in the specified contexts, using the
     * supplied exporter instance.
     *
     * @param approved_contextlist $contextlist The approved contexts to export the information for.
     * @return void
     */
    public static function export_user_data(approved_contextlist $contextlist) {
        global $DB;
        if ($contextlist->count() < 1) {
            return;
        }
        $user = $contextlist->get_user();
        $userid = $user->id;
        list($contextsql, $contextparams) = $DB->get_in_or_equal($contextlist->get_contextids(), SQL_PARAMS_NAMED);

        // All submissions.
        $sql = "SELECT sbms.*, lobject.name AS exercisename, exround.name AS roundname, c.id AS contextid
                FROM {context} c
                JOIN {course_modules} cm ON cm.id = c.instanceid
                JOIN {" . exercise_round::TABLE . "} exround ON exround.id = cm.instance
                JOIN {" . learning_object::TABLE . "} lobject ON lobject.roundid = exround.id
                JOIN {" . submission::TABLE . "} sbms ON sbms.exerciseid = lobject.id
                WHERE (
                sbms.submitter = :submitter AND
                c.id {$contextsql}
                )";
        $params = array(
                'submitter' => $userid,
        ) + $contextparams;
        $submissions = $DB->get_recordset_sql($sql, $params);
        foreach ($submissions as $sbms) {
            $context = context::instance_by_id($sbms->contextid);
            $subcontext = array('exerciseid_' . $sbms->exerciseid, 'submissions', "sid_{$sbms->id}");
            $submission = new submission($sbms);
            // Convert fields to human-readable format.
            $sbms->status = $submission->get_status(true, true);
            $sbms->submissiontime = transform::datetime($sbms->submissiontime);
            $sbms->submitter_is_you = transform::yesno($sbms->submitter == $userid);
            $sbms->grader = ($sbms->grader !== null ? transform::user($sbms->grader) : null);
            $sbms->gradingtime = transform::datetime($sbms->gradingtime);
            // Remove fields that must not be visible to students.
            unset($sbms->hash, $sbms->contextid, $sbms->submitter);
            writer::with_context($context)
                ->export_data($subcontext, $sbms)
                ->export_area_files(
                        $subcontext,
                        exercise_round::MODNAME,
                        submission::SUBMITTED_FILES_FILEAREA,
                        $sbms->id
                );
        }
        $submissions->close();

        // All deadline extensions.
        $sql = "SELECT dldev.*,
                lobject.name AS exercisename,
                exround.name AS roundname,
                c.id AS contextid
                FROM {context} c
                JOIN {course_modules} cm ON cm.id = c.instanceid
                JOIN {" . exercise_round::TABLE . "} exround ON exround.id = cm.instance
                JOIN {" . learning_object::TABLE . "} lobject ON lobject.roundid = exround.id
                JOIN {" . deadline_deviation::TABLE . "} dldev ON dldev.exerciseid = lobject.id
                WHERE (
                dldev.submitter = :submitter AND
                c.id {$contextsql}
                )";
        $dldevs = $DB->get_recordset_sql($sql, $params);
        foreach ($dldevs as $dev) {
            $context = context::instance_by_id($dev->contextid);
            $subcontext = array('exerciseid_' . $dev->exerciseid, 'deadline_extensions', "id_{$dev->id}");
            // Convert fields to human-readable format.
            $dev->submitter_is_you = transform::yesno($dev->submitter == $userid);
            $dev->withoutlatepenalty = transform::yesno($dev->withoutlatepenalty);
            // Remove fields that must not be visible to students.
            unset($dev->contextid, $dev->submitter, $dev->id);
            writer::with_context($context)
                ->export_data($subcontext, $dev);
        }
        $dldevs->close();

        // All submission limit extensions.
        $sql = "SELECT sbmsdev.*,
                lobject.name AS exercisename,
                exround.name AS roundname,
                c.id AS contextid
                FROM {context} c
                JOIN {course_modules} cm ON cm.id = c.instanceid
                JOIN {" . exercise_round::TABLE . "} exround ON exround.id = cm.instance
                JOIN {" . learning_object::TABLE . "} lobject ON lobject.roundid = exround.id
                JOIN {" . submission_limit_deviation::TABLE . "} sbmsdev ON sbmsdev.exerciseid = lobject.id
                WHERE (
                sbmsdev.submitter = :submitter AND
                c.id {$contextsql}
                )";
        $sbmsdevs = $DB->get_recordset_sql($sql, $params);
        foreach ($sbmsdevs as $dev) {
            $context = context::instance_by_id($dev->contextid);
            $subcontext = array('exerciseid_' . $dev->exerciseid, 'submission_limit_extensions', "id_{$dev->id}");
            // Convert fields to human-readable format.
            $dev->submitter_is_you = transform::yesno($dev->submitter == $userid);
            // Remove fields that must not be visible to students.
            unset($dev->contextid, $dev->submitter, $dev->id);
            writer::with_context($context)
                ->export_data($subcontext, $dev);
        }
        $sbmsdevs->close();
    }

    /**
     * Delete all personal data for all users in the specified context.
     *
     * @param context $context Context to delete data from.
     * @return void
     */
    public static function delete_data_for_all_users_in_context(context $context) {
        global $DB;

        if ($context->contextlevel != CONTEXT_MODULE) {
            return;
        }

        $cm = get_coursemodule_from_id(exercise_round::TABLE, $context->instanceid);
        if (!$cm) {
            return;
        }
        $exroundid = $cm->instance;

        // Delete all submissions of the exercise round from the database.
        $DB->delete_records_select(
                submission::TABLE,
                "exerciseid IN (
                SELECT id
                FROM {" . learning_object::TABLE . "} lobject
                WHERE roundid = :exroundid
                )",
                array(
                        'exroundid' => $exroundid,
                )
        );
        // Delete submitted files.
        $fs = get_file_storage();
        $fs->delete_area_files(
                $context->id,
                exercise_round::MODNAME,
                submission::SUBMITTED_FILES_FILEAREA
        );

        // Delete deadline extensions.
        $DB->delete_records_select(
                deadline_deviation::TABLE,
                "exerciseid IN (
                SELECT id
                FROM {" . learning_object::TABLE . "} lobject
                WHERE roundid = :exroundid
                )",
                array(
                        'exroundid' => $exroundid,
                )
        );

        // Delete submission limit extensions.
        $DB->delete_records_select(
                submission_limit_deviation::TABLE,
                "exerciseid IN (
                SELECT id
                FROM {" . learning_object::TABLE . "} lobject
                WHERE roundid = :exroundid
                )",
                array(
                        'exroundid' => $exroundid,
                )
        );
    }

    /**
     * User data related to the user in the given contexts should either be
     * completely deleted, or overwritten if a structure needs to be maintained.
     * This will be called when a user has requested the right to be forgotten.
     *
     * @param approved_contextlist $contextlist
     * @return void
     */
    public static function delete_data_for_user(approved_contextlist $contextlist) {
        global $DB;
        if ($contextlist->count() < 1) {
            return;
        }
        $userid = $contextlist->get_user()->id;
        $contextids = $contextlist->get_contextids();
        if (empty($contextids)) {
            return;
        }

        // Delete submitted files before the submissions since the submission ids
        // must be available for selecting the corresponding files.
        $fs = get_file_storage();
        foreach ($contextids as $cid) {
            $fs->delete_area_files_select(
                    $cid,
                    exercise_round::MODNAME,
                    submission::SUBMITTED_FILES_FILEAREA,
                    "IN (
                    SELECT sbms.id
                    FROM {" . submission::TABLE . "} sbms
                    JOIN {" . learning_object::TABLE . "} lobject ON lobject.id = sbms.exerciseid
                    JOIN {" . exercise_round::TABLE . "} exround ON exround.id = lobject.roundid
                    JOIN {course_modules} cm ON cm.instance = exround.id
                    JOIN {context} c ON c.instanceid = cm.id
                    WHERE sbms.submitter = :submitter AND c.id = :sbmscontextid
                    )",
                    array(
                            'submitter' => $userid,
                            'sbmscontextid' => $cid,
                    )
            );
        }

        list($contextsql, $contextparams) = $DB->get_in_or_equal($contextids, SQL_PARAMS_NAMED);
        $params = array(
                'submitter' => $userid,
                'contextlevel' => CONTEXT_MODULE,
        ) + $contextparams;

        // Delete the user's submissions in the given contexts.
        $DB->delete_records_select(
                submission::TABLE,
                "submitter = :submitter AND exerciseid IN (
                SELECT lobject.id
                FROM {" . learning_object::TABLE . "} lobject
                JOIN {" . exercise_round::TABLE . "} exround ON exround.id = lobject.roundid
                JOIN {course_modules} cm ON cm.instance = exround.id
                JOIN {context} c ON c.instanceid = cm.id AND c.contextlevel = :contextlevel
                WHERE (
                c.id {$contextsql}
                )
                )",
                $params
        );

        // Delete the user's deadline extensions.
        $DB->delete_records_select(
                deadline_deviation::TABLE,
                "submitter = :submitter AND exerciseid IN (
                SELECT lobject.id
                FROM {" . learning_object::TABLE . "} lobject
                JOIN {" . exercise_round::TABLE . "} exround ON exround.id = lobject.roundid
                JOIN {course_modules} cm ON cm.instance = exround.id
                JOIN {context} c ON c.instanceid = cm.id AND c.contextlevel = :contextlevel
                WHERE (
                c.id {$contextsql}
                )
                )",
                $params
        );

        // Delete the user's submission limit extensions.
        $DB->delete_records_select(
                submission_limit_deviation::TABLE,
                "submitter = :submitter AND exerciseid IN (
                SELECT lobject.id
                FROM {" . learning_object::TABLE . "} lobject
                JOIN {" . exercise_round::TABLE . "} exround ON exround.id = lobject.roundid
                JOIN {course_modules} cm ON cm.instance = exround.id
                JOIN {context} c ON c.instanceid = cm.id AND c.contextlevel = :contextlevel
                WHERE (
                c.id {$contextsql}
                )
                )",
                $params
        );
    }

    public static function delete_data_for_users(approved_userlist $userlist) {
        global $DB;
        $context = $userlist->get_context();
        $cm = get_coursemodule_from_id(exercise_round::TABLE, $context->instanceid);
        $userids = $userlist->get_userids();
        if (!$cm || empty($userids)) {
            return;
        }
        $exroundid = $cm->instance;

        list($userinsql, $userinparams) = $DB->get_in_or_equal($userids, SQL_PARAMS_NAMED);
        $params = array(
                'exroundid' => $exroundid,
        ) + $userinparams;

        $submissionsql = "exerciseid IN (
                SELECT id
                FROM {" . learning_object::TABLE . "} lobject
                WHERE lobject.roundid = :exroundid
                ) AND submitter {$userinsql}";
        // Delete submitted files before the submissions since the
        // submission ids must be available for selecting the corresponding files.
        $fs = get_file_storage();
        $fs->delete_area_files_select(
                $context->id,
                exercise_round::MODNAME,
                submission::SUBMITTED_FILES_FILEAREA,
                "IN (
                SELECT id
                FROM {" . submission::TABLE . "}
                WHERE {$submissionsql}
                )",
                $params
        );
        // Delete submissions.
        $DB->delete_records_select(submission::TABLE, $submissionsql, $params);

        // Delete deadline extensions for these users in the given context.
        $DB->delete_records_select(deadline_deviation::TABLE, $submissionsql, $params);

        // Delete submission limit extensions for these users in the given context.
        $DB->delete_records_select(submission_limit_deviation::TABLE, $submissionsql, $params);
    }
}