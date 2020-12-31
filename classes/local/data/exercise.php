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

namespace mod_adastra\local\data;

defined('MOODLE_INTERNAL') || die();

class exercise extends \mod_adastra\local\data\learning_object {
    const TABLE = 'adastra_exercises';

    /**
     * Return the number of maximum submissions allowed.
     * Zero means that there is no submission limit.
     * Negative values mean no limit and only the N latest
     * submissions are stored, but this method returns only zero.
     *
     * @return integer
     */
    public function get_max_submissions() : int {
        $val = (int) $this->record->maxsubmissions;
        if ($val <= 0) {
            return 0;
        }
        return $val;
    }

    /**
     * Return how many submissions per student are stored in the exercise
     * (assuming that students may submit an unlimited number of times).
     *
     * @return integer Zero for no storage limit or a positive integer that is the limit
     * Zero is returned if the exercise has limited the number of submissions since
     * the submission limit naturally limits the number of submissions stored as well.
     */
    public function get_submission_store_limit() : int {
        $val = (int) $this->record->maxsubmissions;
        if ($val < 0) {
            return -$val;
        }
        return 0;
    }

    /**
     * Return the number of points needed to pass the exercise.
     *
     * @return int
     */
    public function get_points_to_pass() {
        return $this->record->pointstopass;
    }

    /**
     * Return the maximum points for the exercise.
     *
     * @return int
     */
    public function get_max_points() {
        return $this->record->maxpoints;
    }

    /**
     * Get the maximum filesize for the submssion.
     *
     * @return int
     */
    public function get_submission_file_max_size() {
        return (int) $this->record->maxsbmssize;
    }

    /**
     * Override of the parent method is_submittable.
     *
     * @return boolean True for exercises.
     */
    public function is_submittable() {
        return true;
    }

    /**
     * Return true if assistant viewing is allowed.
     *
     * @return boolean
     */
    public function is_assistant_viewing_allowed() {
        return (bool) $this->record->allowastviewing;
    }

    /**
     * Return true if assistant grading is allowed.
     *
     * @return boolean
     */
    public function is_assistant_grading_allowed() {
        return (bool) $this->record->allowastgrading;
    }

    /**
     * Check whether the uploaded files obey the maximum file size constraint for submissions.
     *
     * @param array $files Supply the $_FILES superglobal or an array that
     * has the same structure and uncludes the file sizes.
     * @return bool True if all files obey the limit, false otherwise.
     */
    public function check_submission_file_sizes(array $files) {
        $maxsize = $this->get_submission_file_max_size();
        if ($maxsize == 0) {
            return true; // No limit.
        }
        foreach ($files as $name => $filearray) {
            if ($filearray['size'] > $maxsize) {
                return false;
            }
        }
        return true;
    }

    /**
     * Delete this exercise instance from the database, and possible child
     * learning objects. All submissions to this exercise are also deleted.
     *
     * @param boolean $updateroundmaxpoints If true, the max points of the
     * exercise round are updated here.
     * @return bool True.
     */
    public function delete_instance($updateroundmaxpoints = true) {
        global $DB;

        // All submitted files to this exercise (in Moodle file API) (file itemid is a submission id).
        $fs = \get_file_storage();
        $fs->delete_area_files_select(
            \context_module::instance($this->get_exercise_round()->get_course_module()->id)->id,
            \mod_adastra\local\data\exercise_round::MODNAME,
            \mod_adastra\local\data\submission::SUBMITTED_FILES_FILEAREA,
            'IN (SELECT id FROM {' . \mod_adastra\local\data\submission::TABLE . '} WHERE exerciseid = :adastraexerciseid)',
            array('adastraexerciseid' => $this->get_id())
        );
        // All submissions to this exercise.
        $DB->delete_records(\mod_adastra\local\data\submission::TABLE, array('exerciseid' => $this->get_id()));

        // Delete all deviations for this exercise.
        $this->delete_deviations();

        // This exercise (both lobject and exercise tables) and children.
        $res = parent::delete_instance();

        // Update round max points (this exercise must have been deleted from the DB before this.).
        if ($updateroundmaxpoints) {
            $this->get_exercise_round()->update_max_points();
        }

        return $res;
    }

    /**
     * Delete all deviations related to this exercise,
     * i.e. deadline and submission limit extensions.
     *
     * @return void
     */
    public function delete_deviations() {
        global $DB;

        $DB->delete_records(\mod_adastra\local\data\deadline_deviation::TABLE, array('exerciseid' => $this->get_id()));
        $DB->delete_records(\mod_adastra\local\data\submission_limit_deviation::TABLE, array('exerciseid' => $this->get_id()));
    }

    /**
     * Return the best submission of the student to this exercise.
     * Note: heavy text fields such as feedback anf submission data are not
     * included in the returned submission object.
     *
     * @param int $userid The Moodle user ID of the student.
     * @return \mod_adastra\local\data\submission The best submission, or null
     * if there is no submission.
     */
    public function get_best_submission_for_student($userid) {
        global $DB;

        $submissions = $this->get_submissions_for_student($userid);
        // Order by submissiontime, earlier first.
        $bestsubmission = null;
        foreach ($submissions as $s) {
            $sbms = new \mod_adastra\local\data\submission($s);
            // Assume that the grade of a submission is zero if it was not accepted
            // due to submission limit or deadline.
            if ($bestsubmission === null || $sbms->get_grade() > $bestsubmission->get_grade()) {
                $bestsubmission = $sbms;
            }
        }
        $submissions->close();

        return $bestsubmission;
    }

    /**
     * Return the number of submissions the student has made in this exercise.
     *
     * @param int $userid
     * @param boolean $excludeerrors If true, the submissions with status error are not counted.
     * @return int
     */
    public function get_submission_count_for_student($userid, $excludeerrors = false) {
        global $DB;

        if ($excludeerrors) {
            // Exclude submissions with status error.
            $count = $DB->count_records_select(
                    \mod_adastra\local\data\submission::TABLE,
                    'exerciseid = ? AND submitter = ? AND status != ?',
                    array(
                            $this->get_id(),
                            $userid,
                            \mod_adastra\local\data\submissio::STATUS_ERROR,
                    ),
                    "COUNT('id')"
            );
        } else {
            $count = $DB->count_records(\mod_adastra\local\data\submission::TABLE, array(
                    'exerciseid' => $this->get_id(),
                    'submitter' => $userid,
            ));
        }
        return $count;
    }

    /**
     * Return the submissions for a student in this exercise.
     *
     * @param int $userid
     * @param boolean $excludeerrors
     * @param string $orderby
     * @param boolean $includefeedback
     * @param boolean $includeassistfeedback
     * @param boolean $includesbmsdata
     * @param boolean $includegradingdata
     * @return moodle_recordset An iterator of database records (\stdClass). The caller
     * of this method must call the close() method.
     */
    public function get_submissions_for_student (
            $userid,
            $excludeerrors = false,
            $orderby = 'submissiontime ASC',
            $includefeedback = false,
            $includeassistfeedback = false,
            $includesbmsdata = false,
            $includegradingdata = false
    ) {
        global $DB;

        $fields = 'id,status,submissiontime,hash,exerciseid,submitter,grader,' .
                'grade,gradingtime,latepenaltyapplied,servicepoints,servicemaxpoints';
        if ($includefeedback) {
            $fields .= ',feedback';
        }
        if ($includeassistfeedback) {
            $fields .= ',assistfeedback';
        }
        if ($includesbmsdata) {
            $fields .= ',submissiondata';
        }
        if ($includegradingdata) {
            $fields .= ',gradingdata';
        }

        if ($excludeerrors) {
            // Exclude submissions with status error.
            $submissions = $DB->get_recordset_select(
                    \mod_adastra\local\data\submission::TABLE,
                    'exerciseid = ? AND submitter = ? AND status != ?',
                    array(
                            $this->get_id(),
                            $userid,
                            \mod_adastra\local\data\submission::STATUS_ERROR,
                    ),
                    $orderby,
                    $fields
            );
        } else {
            $submissions = $DB->get_recordset(\mod_adastra\local\data\submission::TABLE, array(
                    'exerciseid' => $this->get_id(),
                    'submitter' => $userid,
            ), $orderby, $fields);
        }
        return $submissions;
    }

    /**
     * Check if the user has more submissions than what should be stored for the exercise.
     * The excess submissions are then removed. This method does nothing when the exercise
     * has limited the number of allowed submissions. This is intended for exercises that
     * allow unlimited submissions, but do not store all of them.
     *
     * @param int $userid ID of the user whose submissions are checked.
     * @return void
     */
    public function remove_submissions_exceeding_store_limit($userid) {
        $storelimit = $this->get_submission_store_limit();
        if ($storelimit <= 0) { // No store limit.
            return;
        }
        // How many old submissions to remove?
        $nremove = $this->get_submission_count_for_student($userid) - $storelimit;
        if ($nremove > 0) {
            $this->remove_n_oldest_submissions($nremove, $userid);
        }
    }

    public function remove_n_oldest_submissions(int $numsubmissions, $userid) {
        $submissions = $this->get_submissions_for_student($userid);
        // The oldest submissions come first in the iterator.
        foreach ($submissions as $record) {
            if ($numsubmissions <= 0) {
                break;
            }
            $sbms = new \mod_adastra\local\data\submission($record);
            // Update gradebook in the last iteration when the submission is deleted.
            $sbms->delete($numsubmissions <= 1);
            --$numsubmissions;
        }
        $submissions->close();
    }

    /**
     * Return the number of users that have submitted to this exercise.
     *
     * @return int
     */
    public function get_total_submitter_count() {
        global $DB;
        return $DB->count_records_select(
                \mod_adastra\local\data\submission::TABLE,
                'exerciseid = ?',
                array($this->get_id()),
                'COUNT(DISTINCT submitter)',
        );
    }

    /**
     * Return the template context of all submissions from a user.
     *
     * @param int $userid
     * @param \mod_adastra\local\data\submission $current The current submission. If set,
     * one submission is marked as the current submission with an additional variable currentsubmission.
     * @return \stdClass[]
     */
    public function get_submissions_template_context($userid, \mod_adastra\local\data\submission $current = null) {
        // Latest submission first.
        $submissions = $this->get_submissions_for_student($userid, false, 'submissiontime DESC', false, true);
        // Assistant feedback is included in the submissions so that templates
        // may mark which submissions in the list have assistant feedback.
        $objects = array();
        foreach ($submissions as $record) {
            $objects[] = new \mod_adastra\local\data\submission($record);
        }
        $submissions->close();

        return self::submissions_template_context($objects, $current);
    }

    /**
     * Return the template context objects for the given submissions.
     * The submissions should be submitted by the same user to the same exercise
     * and the array should be sorted by the submission time (latest submission first).
     *
     * @param array $submissions An arrya of \mod_adastra\local\data\submission objects.
     * @param \mod_adastra\local\data\submission $currentsubmission If set, one submission is marked
     * as the current submission with and additional variable currentsubmission.
     * @return stdClass[] An array of context objects.
     */
    public static function submissions_template_context(
        array $submissions,
        \mod_adastra\local\data\submission $currentsubmission = null
    ) {
        $ctx = array();
        $nth = count($submissions);
        foreach ($submissions as $sbms) {
            $obj = $sbms->get_template_context();
            $obj->nth = $nth;
            $nth--;
            if (isset($currentsubmission) && $sbms->get_id() == $currentsubmission->get_id()) {
                $obj->currentsubmission = true;
            }
            $ctx[] = $obj;
        }

        return $ctx;
    }

    /**
     * Return the data for templating.
     *
     * @param \stdClass $user
     * @param boolean $includetotalsubmittercount
     * @param boolean $includecoursemodule
     * @param boolean $includesiblings
     * @return \stdClass
     */
    public function get_exercise_template_context(\stdClass $user = null, $includetotalsubmittercount = true,
            $includecoursemodule = true, $includesiblings = false) {
        $ctx = parent::get_template_context($includecoursemodule, $includesiblings);
        $ctx->submissionlisturl = \mod_adastra\local\urls\urls::submission_list($this);
        $ctx->infourl = \mod_adastra\local\urls\urls::exercise_info($this);

        $ctx->maxpoints = $this->get_max_points();
        $ctx->maxsubmissions = $this->get_max_submissions();
        if ($user !== null) {
            $ctx->maxsubmissionsforuser = $this->get_max_submissions_for_student($user);
            if ($ctx->maxsubmissionsforuser > 0) {
                // Number of extra submissions given to the student.
                $ctx->submitlimitdeviation = $ctx->maxsubmissionsforuser - $ctx->maxsubmissions;
            } else {
                $ctx->submitlimitdeviation = 0;
            }

            $dldeviation = \mod_adastra\local\data\deadline_deviation::find_deviation($this->get_id(), $user->id);
            if ($dldeviation !== null) {
                $ctx->deadline = $dldeviation->get_new_deadline();
                $ctx->dlextendedminutes = $dldeviation->get_extra_time();
            } else {
                $ctx->deadline = $this->get_exercise_round()->get_closing_time();
                $ctx->dlextendedminutes = 0;
            }
        }

        $ctx->pointstopass = $this->get_points_to_pass();
        if ($includetotalsubmittercount) {
            $ctx->totalsubmittercount = $this->get_total_submitter_count(); // Heavy DB query.
        }
        $ctx->allowassistantgrading = $this->is_assistant_grading_allowed();
        $ctx->allowassistantviewing = $this->is_assistant_viewing_allowed();
        $context = \context_module::instance($this->get_exercise_round()->get_course_module()->id);
        $ctx->canviewsubmissions = (
            $ctx->allowassistantviewing &&
            has_capability('mod/adastra:viewallsubmissions', $context) ||
            has_capability('mod/adastra:addinstance', $context) // Editing teacher can always view.
        );
        return $ctx;
    }

    /**
     * Return the URL used for loading the exercise page from the exercise service or for
     * uploading a submission for grading (service URL with GET query parameters).
     *
     * @param string $submissionurl Value for the submission_url GET query argument.
     * @param string|int $uid User ID, if many users form a group, the IDs should be given
     * in the format "1-2-3" (i.e., separated by dash).
     * @param int $submissionordinalnumber The ordinal number of the submission which is
     * uploaded for grading or the submission for which the exercise description is downloaded.
     * @param string $language Language of the content of the page, e.g. 'en' for English. Value of
     * lang query parameter in the grader protocol.
     * @return string
     */
    protected function build_service_url($submissionurl, $uid, $submissionordinalnumber, $language) {
        if (defined('ADASTRA_OVERRIDE_SUBMISSION_HOST') && ADASTRA_OVERRIDE_SUBMISSION_HOST !== null) {
            // Modify the host of submission URL.
            $urlcomp = parse_url($submissionurl);
            $submissionurl = ADASTRA_OVERRIDE_SUBMISSION_HOST .
                    ($urlcomp['path'] ?? '/') .
                    (isset($urlcomp['query']) ? ('?' . $urlcomp['query']) : '') .
                    (isset($urlcomp['fragment']) ? ('#' . $urlcomp['fragment']) : '');
        }
        $querydata = array(
                'submission_url' => $submissionurl,
                'post_url' => \mod_adastra\local\urls\urls::new_submission_handler($this),
                'max_points' => $this->get_max_points(),
                'uid' => $uid,
                'ordinal_number' => $submissionordinalnumber,
                'lang' => $language,
        );
        return $this->get_service_url() . '?' . http_build_query($querydata, 'i_', '&');
    }

    /**
     * Upload the submission to the exercise service for grading and store the results
     * if the submission is graded synchronously.
     *
     * @param \mod_adastra\local\data\submission $submission
     * @param boolean $nopenalties
     * @param array $files Submitted files. An associative array of \stdClass objects that have
     * fields filename (original base name), filepath (full file path in Moodle, e.g. under /tmp)
     * and mimetype (e.g. "text/plain"). The array keys are the keys used in HTTP POST data. If
     * $files is null, this method reads the submission files from the database and adds them to
     * the upload automatically.
     * @param boolean $deletefiles If true and $files is a non-empty array, the files are deleted
     * here from the file system.
     * @throws \mod_adastra\local\protocol\remote_page_exception If there are errors in connecting
     * to the server.
     * @throws \Exception If there are errors in handling the files.
     * @return \mod_adastra\local\protocol\exercise_page The feedback page.
     */
    public function upload_submission_to_service(
            \mod_adastra\local\data\submission $submission,
            $nopenalties = false,
            array $files = null,
            $deletefiles = false
    ) {
        $sbmsdata = $submission->get_submission_data();
        if ($sbmsdata !== null) {
            $sbmsdata = (array) $sbmsdata;
        }

        if (is_null($files)) {
            $deletefiles = true;
            $files = $submission->prepare_submission_files_for_upload();
        }

        $courseconfig = \mod_adastra\local\data\course_config::get_for_course_id(
                $submission->get_exercise()->get_exercise_round()->get_course()->courseid
        );
        $apikey = ($courseconfig ? $courseconfig->get_api_key() : null);
        if (empty($apikey)) {
            $apikey = null; // Course config gives an empty string if not set.
        }

        $language = $this->get_exercise_round()->check_course_lang(current_language());
        $serviceurl = $this->build_service_url(
                \mod_adastra\local\urls\urls::async_grade_submission($submission),
                $submission->get_record()->submitter,
                $submission->get_counter(),
                $language
        );
        try {
            $remotepage = new \mod_adastra\local\protocol\remote_page($serviceurl, true, $sbmsdata, $files, $apikey);
        } catch (\mod_adastra\local\protocol\remote_page_exception $e) {
            if ($deletefiles) {
                foreach ($files as $f) {
                    @unlink($f->filepath);
                }
            }
            // Error logging.
            if ($e instanceof \mod_adastra\local\protocol\service_connection_exception) {
                $event = \mod_adastra\event\service_connection_failed::create(array(
                        'context' => \context_module::instance($this->get_exercise_round()->get_course_module()->id),
                        'other' => array(
                                'error' => $e->getMessage(),
                                'url' => $serviceurl,
                                'objtable' => \mod_adastra\local\data\submission::TABLE,
                                'objid' => $submission->get_id(),
                        )
                ));
                $event->trigger();
            } else if ($e instanceof \mod_adastra\local\protocol\exercise_service_exception) {
                $event = \mod_adastra\event\exercise_service_failed::create(array(
                        'context' => \context_module::instance($this->get_exercise_round()->get_course_module()->id),
                        'other' => array(
                                'error' => $e->getMessage(),
                                'url' => $serviceurl,
                                'objtable' => \mod_adastra\local\data\submission::TABLE,
                                'objid' => $submission->get_id(),
                        )
                ));
                $event->trigger();
            }
            throw $e;
        } // PHP 5.4 has no finally block.

        $remotepage->set_learning_object($this);
        $page = $remotepage->load_feedback_page($this, $submission, $nopenalties);

        if ($deletefiles) {
            foreach ($files as $f) {
                @unlink($f->filepath);
            }
        }
        return $page;
    }

    /**
     * Return the URL used for loading the exercise page from the exercise service or uploading
     * a submission for grading (service URL with GET query parameters).
     *
     * @param string|int $userid
     * @param int $submissionordinalnumber
     * @param string $language
     * @return string
     */
    public function get_load_url($userid, $submissionordinalnumber, $language) {
        return $this->build_service_url(
            \mod_adastra\local\urls\urls::async_new_submission($this, $userid),
            $userid,
            $submissionordinalnumber,
            $language
        );
    }

    /**
     * Return the number of maximum submissions for the student, including possible deviations.
     *
     * @param \stdClass $user
     * @return int
     */
    public function get_max_submissions_for_student(\stdClass $user) {
        $max = $this->get_max_submissions(); // Zero means no limit.
        $deviation = \mod_adastra\local\data\submission_limit_deviation::find_deviation($this->get_id(), $user->id);
        if ($deviation !== null && $max !== 0) {
            return $max + $deviation->get_extra_submissions();
        }
        return $max;
    }

    /**
     * Return true if student still has submissions left.
     *
     * @param \stdClass $user
     * @return boolean
     */
    public function student_has_submissions_left(\stdClass $user) {
        if ($this->get_max_submissions() == 0) {
            return true;
        }
        return $this->get_submission_count_for_student($user->id) < $this->get_max_submissions_for_student($user);
    }

    /**
     * Return true if student has access to the exercise.
     *
     * @param \stdClass $user
     * @param int $when A Unix timestamp.
     * @return boolean
     */
    public function student_has_access(\stdClass $user, $when = null) {
        // Check deadlines.
        if ($when === null) {
            $when = time();
        }
        $exround = $this->get_exercise_round();
        if ($exround->is_open($when) || $exround->is_late_submission_open($when)) {
            return true;
        }
        if ($exround->has_started($when)) {
            // Check deviations.
            $deviation = \mod_adastra\local\data\deadline_deviation::find_deviation($this->get_id(), $user->id);
            if ($deviation !== null && $when <= $deviation->get_new_deadline()) {
                return true;
            }
        }
        return false;
    }

    /**
     * Return true if $user is allowed to make a submission.
     *
     * @param \stdClass $user
     * @return boolean
     */
    public function is_submission_allowed(\stdClass $user) {
        $context = \context_module::instance($this->get_exercise_round()->get_course_module()->id);
        if (
                has_capability('mod/adastra:addinstance', $context, $user) ||
                has_capability('mod/adastra:viewallsubmissions', $context, $user)
        ) {
            // Always allow for teachers.
            return true;
        }
        if (!$this->student_has_access($user)) {
            return false;
        }
        if (!$this->student_has_submissions_left($user)) {
            return false;
        }
        return true;
    }

    /**
     * Generate a hash of this exercise for the user. The hash is based on a secret key.
     *
     * @param int $userid Moodle user ID of the user whom the hash is generated for.
     * @return string
     */
    public function get_async_hash($userid) {
        $secretkey = get_config(\mod_adastra\local\data\exercise_round::MODNAME, 'secretkey');
        if (empty($secretkey)) {
            throw new \moodle_exception('nosecretkeyset', mod_adastra\local\data\exercise_round::MODNAME);
        }
        $identifier = "{$userid}." . $this->get_id();
        return \hash_hmac('sha256', $identifier, $secretkey);
    }
}