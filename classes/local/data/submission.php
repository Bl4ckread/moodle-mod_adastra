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

class submission extends \mod_adastra\local\data\database_object {
    const TABLE = 'adastra_submissions';
    const SUBMITTED_FILES_FILEAREA = 'submittedfile'; // File area for Moodle file API.
    const STATUS_INITIALIZED = 0; // Has not been sent to the exercise service.
    const STATUS_WAITING     = 1; // Has been sent for grading.
    const STATUS_READY       = 2; // Has been graded.
    const STATUS_ERROR       = 3;
    const STATUS_REJECTED    = 4; // There are missing fields etc.

    const SAFE_FILENAME_CHARS = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ._-0123456789';

    // A cache of references to other records, used in corresponding getter methods.
    protected $exercise = null;
    protected $submitter = null;
    protected $grader = null;

    /**
     * Return the status of the submission, either as a number
     * or as a string corresponding to the status number.
     *
     * @param boolean $asstring
     * @param boolean $localized If false return a localized version of the status string.
     * @return int|string
     */
    public function get_status($asstring = false, $localized = true) {
        if ($asstring) {
            switch ((int) $this->record->status) {
                case self::STATUS_INITIALIZED:
                    return $localized
                        ? get_string('statusinitialized', \mod_adastra\local\data\exercise_round::MODNAME)
                        : 'initialized';
                    break;
                case self::STATUS_WAITING:
                    return $localized
                        ? get_string('statuswaiting', \mod_adastra\local\data\exercise_round::MODNAME)
                        : 'waiting';
                    break;
                case self::STATUS_READY:
                    return $localized
                        ? get_string('statusready', \mod_adastra\local\data\exercise_round::MODNAME)
                        : 'ready';
                    break;
                case self::STATUS_ERROR:
                    return $localized
                        ? get_string('statuserror', \mod_adastra\local\data\exercise_round::MODNAME)
                        : 'error';
                    break;
                case self::STATUS_REJECTED:
                    return $localized
                        ? get_string('statusrejected', \mod_adastra\local\data\exercise_round::MODNAME)
                        : 'rejected';
                    break;
                default:
                    return 'undefined';
            }
        }
        return (int) $this->record->status;
    }

    /**
     * Return the time this submission was made.
     *
     * @return int A Unix timestamp.
     */
    public function get_submission_time() {
        return (int) $this->record->submissiontime;
    }

    /**
     * Return the hash for this submission.
     *
     * @return string
     */
    public function get_hash() {
        return $this->record->hash;
    }

    /**
     * Return the exercise this submission is associated with.
     *
     * @return \mod_adastra\local\data\exercise
     */
    public function get_exercise() {
        if (is_null($this->exercise)) {
            $this->exercise = \mod_adastra\local\data\exercise::create_from_id($this->record->exerciseid);
        }
        return $this->exercise;
    }

    /**
     * Return the details of the submitter.
     *
     * @return \stdClass
     */
    public function get_submitter() {
        global $DB;
        if (is_null($this->submitter)) {
            $this->submitter = $DB->get_record('user', array('id' => $this->record->submitter), '*', MUST_EXIST);
        }
        return $this->submitter;
    }

    /**
     * Return the name of the submitter.
     *
     * @return string
     */
    public function get_submitter_name() {
        $user = $this->get_submitter();
        $name = fullname($user);
        if (empty($user->idnumber) || $user->idnumber === '(null)') {
            $name .= " ({$user->username})";
        } else {
            $name .= " ({$user->idnumber})";
        }
        return $name;
    }

    /**
     * Return the details of the grader.
     *
     * @return \stdClass
     */
    public function get_grader() {
        global $DB;
        if (empty($this->record->grader)) {
            return null;
        }
        if (is_null($this->grader)) {
            $this->grader = $DB->get_record('user', array('id' => $this->record->grader), '*', MUST_EXIST);
        }
        return $this->grader;
    }

    /**
     * Return the name of the grader.
     *
     * @return string
     */
    public function get_grader_name() {
        $grader = $this->get_grader();
        if ($grader !== null) {
            return fullname($grader);
        }
        return null;
    }

    /**
     * Return the machine-generated feedback for the submission.
     *
     * @return string
     */
    public function get_feedback() {
        return $this->record->feedback;
    }

    /**
     * Return the assistant feedback for the submission.
     *
     * @return string
     */
    public function get_assistant_feedback() {
        return $this->record->assistfeedback;
    }

    /**
     * Return true if this submission has feedback from an assistant.
     *
     * @return boolean
     */
    public function has_assistant_feedback() {
        return !empty($this->record->assistantfeedback);
    }

    /**
     * Return points given to the submission.
     *
     * @return int
     */
    public function get_grade() {
        return (int) $this->record->grade;
    }

    /**
     * Return the time the submission was graded. The value
     * may be null for ungraded submissions, in which case zero is returned.
     *
     * @return integer A Unix timestamp.
     */
    public function get_grading_time() : int {
        return (int) $this->record->gradingtime;
    }

    /**
     * Return the late penalty applied to this submission.
     *
     * @return float|null
     */
    public function get_late_penalty_applied() {
        if (isset($this->record->latepenaltyapplied)) {
            return $this->record->latepenaltyapplied;
        }
        return null;
    }

    /**
     * Return the number of points given to this submission by the grading service.
     *
     * @return int
     */
    public function get_service_points() {
        return (int) $this->record->servicepoints;
    }

    /**
     * The maximum points that the grading service used in the grading.
     *
     * @return int
     */
    public function get_service_max_points() {
        return (int) $this->record->servicemaxpoints;
    }

    /**
     * Return the ordinal number of this submission (amongs the submissions
     * the student has submitted to the exercise).
     *
     * @return int
     */
    public function get_counter() {
        global $DB;

        return $DB->count_records_select(
            self::TABLE,
            'exerciseid = ? AND submitter = ? AND submissiontime <= ?',
            array(
                $this->record->exerciseid,
                $this->record->submitter,
                $this->record->submissiontime,
            ),
            'COUNT(id)'
        );
    }

    /**
     * Try to decode string$data as JSON.
     *
     * @param [type] $data
     * @return string|mixed Decoded JSOn, or string if decoding fails,
     * or null if $data is empty.
     */
    public static function try_to_decode_json($data) {
        if (is_null($data) || $data === '') {
            // Empty() considers '0' empty too, so avoid it.
            return null;
        }
        // Try to decode the json.
        $jsonobj = json_decode($data);
        if (is_null($jsonobj)) {
            // Cannot decode, return the original string.
            return $data;
        }
        return $jsonobj;
    }

    /**
     * Return the additional data for this submission.
     *
     * @return string|mixed Decoded JSOn, or string if decoding fails,
     * or null if $data is empty.
     */
    public function get_submission_data() {
        return self::try_to_decode_json($this->record->submissiondata);
    }

    /**
     * Return the additional grading data for this submission.
     *
     * @return string|mixed Decoded JSOn, or string if decoding fails,
     * or null if $data is empty.
     */
    public function get_grading_data() {
        return self::try_to_decode_json($this->record->gradingdata);
    }

    /**
     * Return true if the submission has been graded.
     *
     * @return boolean
     */
    public function is_graded() {
        return $this->get_status() === self::STATUS_READY;
    }

    /**
     * Set the status of this submission as waiting.
     *
     * @return void
     */
    public function set_waiting() {
        $this->record->status = self::STATUS_WAITING;
    }

    /**
     * Set the status of this submission as ready.
     *
     * @return void
     */
    public function set_ready() {
        $this->record->status = self::STATUS_READY;
    }

    /**
     * Set the status of this submission as error.
     *
     * @return void
     */
    public function set_error() {
        $this->record->status = self::STATUS_ERROR;
    }

    /**
     * Set the status of this submission as rejected.
     *
     * @return void
     */
    public function set_rejected() {
        $this->record->status = self::STATUS_REJECTED;
    }

    /**
     * Set the feedback for this submission.
     *
     * @param string $newfeedback
     * @return void
     */
    public function set_feedback($newfeedback) {
        $this->record->feedback = $newfeedback;
    }

    /**
     * Set the assistant given (manual) feedback for this submission.
     *
     * @param string $newfeedback
     * @return void
     */
    public function set_assistant_feedback($newfeedback) {
        $this->record->assistfeedback = $newfeedback;
    }

    /**
     * Set the manual grader for this submission.
     *
     * @param \stdClass $user
     * @return void
     */
    public function set_grader(\stdClass $user) {
        $this->record->grader = $user->id;
        $this->grader = $user;
    }

    /**
     * Set points without setting any service points, scaling the value or
     * checking deadline and submission limit.
     *
     * @param int $grade New grade for the submission.
     * @return void
     */
    public function set_raw_grade($grade) {
        $this->record->grade = $grade;
    }

    /**
     * Create a new submission to an exercise.
     *
     * @param \mod_adastra\local\data\exercise $ex
     * @param int $submitterid ID of a Moodle user.
     * @param array $submissiondata associative array of submission data,
     * e.g. form input (not files) from the user. Keys should be strings (form input names).
     * Null if there is no data.
     * @param int $status
     * @param int|null $submissiontime Unix timestamp of the submission time. If null, uses
     * the current time.
     * @return int ID of the new submission record, zero on failure.
     */
    public static function create_new_submission(
            \mod_adastra\local\data\exercise $ex,
            $submitterid,
            $submissiondata = null,
            $status = self::STATUS_INITIALIZED,
            $submissiontime = null
    ) {
        global $DB;
        $row = new \stdClass();
        $row->status = $status;
        $row->submissiontime = ($submissiontime === null ? time() : $submissiontime);
        $row->hash = self::get_random_string();
        $row->exerciseid = $ex->get_id();
        $row->submitter = $submitterid;
        if ($submissiondata === null) {
            $row->submissiondata = null;
        } else {
            $row->submissiondata = self::submission_data_to_string($submissiondata);
        }

        $id = $DB->insert_record(self::TABLE, $row);
        return $id; // 0 if failed.
    }

    /**
     * Sanitize a file name to only include characters in SAFE_FILENAME_CHARS.
     *
     * @param string $filename
     * @return string A sanitized version of $filename.
     */
    public static function safe_file_name($filename) {
        $safechars = str_split(self::SAFE_FILENAME_CHARS);
        $safename = '';
        $len = strlen($filename);
        if ($len > 80) {
            $len = 80;
        }
        for ($i = 0; $i < $len; ++$i) {
            if (in_array($filename[$i], $safechars)) {
                $safename .= $filename[$i];
            }
        }
        if (empty($safename)) {
            return 'file';
        }
        if ($safename[0] == '-') { // Don't allow starting "-".
            return '_' . (substr($safename, 1) ?: '');
        }
        return $safename;
    }

    /**
     * Add a file (defined by $filepath, for example the file could first exist in /tmp/) to this
     * submission, i.e. create a new file in the Moodle file storage (for permanent storage).
     *
     * @param string $filename Base name of the file without path (filename that the user should see).
     * @param string $filekey Key to the file, e.g. the name attribute in HTML form input. The key should
     * be unique within the files of this submission.
     * @param string $filepath Full path to the file in the file system. This is the file that is added
     * to the submission.
     * @return void
     */
    public function add_submitted_file($filename, $filekey, $filepath) {
        if (empty($filename) || empty($filekey)) {
            return; // Sanity check, Moodle API checks that the file ($filepath) exists.
        }
        $fs = \get_file_storage();
        // Prepare file record object.
        $fileinfo = array(
                'contextid' => \context_module::instance($this->get_exercise()->get_exercise_round()->get_course_module()->id)->id,
                'component' => \mod_adastra\local\data\exercise_round::MODNAME,
                'filearea' => self::SUBMITTED_FILES_FILEAREA,
                'itemid' => $this->get_id(),
                'filepath' => "/{$filekey}/", // Any path beginning and ending in "/".
                'filename' => $filename, // Base name without path.
        );

        // Create Moodle file from a file in the file system.
        $fs->create_file_from_pathname($fileinfo, $filepath);
    }

    /**
     * Return an array of the files in this submission.
     *
     * @return stored_file[] An array of stored_files indexed by path name hash.
     */
    public function get_submitted_files() {
        $fs = \get_file_storage();
        $files = $fs->get_area_files(
                \context_module::instance($this->get_exercise()->get_exercise_round()->get_course_module()->id)->id,
                \mod_adastra\local\data\exercise_round::MODNAME,
                self::SUBMITTED_FILES_FILEAREA,
                $this->get_id(),
                'filepath, filename',
                false
        );
        return $files;
    }

    /**
     * Copy the submitted files of this submission to a temporary directory and
     * return the full file paths to those files with original file base names and mime types.
     *
     * @throws \Exception If there are errors in the file operations.
     * @return \stdClass[] An array of objects, each of which has fields filename, filepath and mimetype.
     */
    public function prepare_submission_files_for_upload() {
        $storedfiles = $this->get_submitted_files();
        $files = array();
        $error = null;
        foreach ($storedfiles as $file) {
            $obj = new \stdClass();
            $obj->filename = $file->get_filename(); // Original name that the user sees.
            $obj->mimetype = $file->get_mimetype();

            // To obtain a full path to the file in the file system, the Moodle
            // stored file has to be first copied to a temp directory.
            $temppath = $file->copy_content_to_temp();
            if (empty($temppath)) {
                $error = 'Copying Moodle stored file to a temporary path failed.';
                break;
            }
            $obj->filepath = $temppath;

            $key = substr($file->get_filepath(), 1, -1); // Remove the slashes (/) from the start and end.
            if (empty($key)) {
                // This should not happen, since the path is always defined in the method add_submitted_file().
                $error = 'No POST data key for file ' . $obj->filename;
                break;
            }

            $files[$key] = $obj;
        }

        if (isset($error)) {
            // Remove temp files created thus far.
            foreach ($files as $f) {
                @unlink($f->filepath);
            }
            throw new \Exception($error);
        }

        return $files;
    }

    /**
     * Encode a submissiondata array into a json string.
     *
     * @param array $submissiondata
     * @return string
     */
    public static function submission_data_to_string(array $submissiondata) {
        $json = json_encode($submissiondata);
        if ($json === false) {
            return null; // Failed to encode.
        }
        return $json;
    }

    /**
     * Return a string of a random character sequence.
     *
     * @param integer $length Length of the generated string.
     * @param boolean $specialcharacters If true, include common special characters. If false, alphanumeric only.
     * @return string
     */
    public static function get_random_string($length = 32, $specialcharacters = false) {
        // Digits 0-9, alphabets a-z, A-Z.
        $chars = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';
        if ($specialcharacters) {
            $chars .= '!"#%&/()=?+@{[]},.-_:;*\'\\';
        }
        $rmax = strlen($chars) - 1; // Max value for rand, inclusive.
        $res = '';
        for ($i = 0; $i < $length; ++$i) {
            $res .= substr($chars, mt_rand(0, $rmax), 1);
        }
        return $res;
    }

    /**
     * Remove this submission and its submitted files from the database.
     *
     * @param boolean $updategradebook If true, the points in the gradebook are updated
     * (best points left in the exercise and the round).
     * @return boolean True.
     */
    public function delete($updategradebook = true) {
        global $DB;
        // Delete submitted files from Moodle file API.
        $fs = get_file_storage();
        $fs->delete_area_files(
                \context_module::instance($this->get_exercise()->get_exercise_round()->get_course_module()->id)->id,
                \mod_adastra\local\data\exercise_round::MODNAME,
                self::SUBMITTED_FILES_FILEAREA,
                $this->record->id
        );
        $DB->delete_records(self::TABLE, array('id' => $this->record->id));

        if ($updategradebook) {
            $this->get_exercise()->get_exercise_round()->write_all_grades_to_gradebook($this->record->submitter);
        }
        return true;
    }

    /**
     * Grade this submission (with machine-generated feedback).
     *
     * @param int $servicepoints Points from the exercise service.
     * @param int $servicemaxpoints Max points used by the exercise service.
     * @param string $feedback Feedback to the student in HTML.
     * @param array $gradingdata An associative array of extra data about grading.
     * @param boolean $nopenalties If ture, no deadline penalties are used.
     * @return void
     */
    public function grade($servicepoints, $servicemaxpoints, $feedback, $gradingdata = null, $nopenalties = false) {
        $this->record->status = self::STATUS_READY;
        $this->record->feedback = $feedback;
        $this->record->gradingtime = time();
        $this->set_points($servicepoints, $servicemaxpoints);
        if ($gradingdata === null) {
            $this->record->gradingdata = null;
        } else {
            $this->record->gradingdata = self::submission_data_to_string($gradingdata);
        }

        $this->save();
        $this->get_exercise()->get_exercise_round()->write_all_grades_to_gradebook($this->record->submitter);
    }

    /**
     * Set the points for this submission. If the given maximum points are different
     * than the ones for the exercise this submission is for, the points will be scaled.
     * The method also checks if the submission is late and it it is, by default
     * applies the late_submission_penalty set for the exercise round. If $nopenalties
     * is true, the penalty is not applied. The updated database record is not saved here.
     *
     * @param int $points
     * @param int $maxpoints
     * @param boolean $nopenalties
     * @return void
     */
    public function set_points($points, $maxpoints, $nopenalties = false) {
        $exercise = $this->get_exercise();
        $this->record->servicepoints = $points;
        $this->record->servicemaxpoints = $maxpoints;

        // Scale the given points to the maximum points for the exercise.
        if ($maxpoints > 0) {
            $adjustedgrade = ($exercise->get_max_points() * $points / $maxpoints);
        } else {
            $adjustedgrade = 0.0;
        }

        // Check if this submission was done late. If it was, reduce the points with late
        // submission penalty. No less than 0 points are given. This is not done if $nopenalties
        // is true.
        $latecode = $this->is_late();
        if (!$nopenalties && $latecode > 0) {
            if ($latecode === 1) {
                // Late, use penalty.
                $this->record->latepenaltyapplied = $this->get_exercise()->get_exercise_round()->get_late_submission_penalty();
            } else {
                // Too late (late submission deadline has passed), zero points.
                $this->record->latepenaltyapplied = 1;
            }
            $adjustedgrade -= ($adjustedgrade * $this->record->latepenaltyapplied);
        } else {
            // In time or penalties are ignored.
            $this->record->latepenaltyapplied = null;
        }

        $adjustedgrade = round($adjustedgrade);

        // Check submit limit.
        $submissions = $this->get_exercise()->get_submissions_for_student($this->record->submitter);
        $count = 0;
        foreach ($submissions as $record) {
            if ($record->id != $this->record->id) {
                $sbms = new \mod_adastra\local\data\submission($record);
                // Count the ordinal number for this submission ("how manieth submission").
                if ($record->submissiontime <= $this->get_submission_time()) {
                    $count += 1;
                }
            }
        }
        $submissions->close();
        $count += 1;
        $maxsubmissions = $this->get_exercise()->get_max_submissions_for_student($this->get_submitter());
        if ($maxsubmissions > 0 && $count > $maxsubmissions) {
            // This submission exceeded the submission limit.
            $this->record->grade = 0;
        } else {
            $this->record->grade = $adjustedgrade;
        }
    }

    /**
     * Check if this submission was submitted after the exercise round closing time.
     * Deadline deviation is taken into account.
     * Interpretation of return values:
     * 0, if this submission was submitted in time.
     * 1, if it was late and the late penalty should be applied.
     * 2, if it was late and shall not be accepted (gains zero points).
     *
     * @return int
     */
    public function is_late() {
        $exround = $this->get_exercise()->get_exercise_round();
        if ($this->get_submission_time() <= $exround->get_closing_time()) {
            return 0;
        }
        // Check deadline deviations/extensions for specific students.
        $deviation = \mod_adastra\local\data\deadline_deviation::find_deviation(
                $this->get_exercise()->get_id(),
                $this->record->submitter
        );
        if ($deviation !== null && $this->get_submission_time() <= $deviation->get_new_deadline()) {
            if ($deviation->use_late_penalty()) {
                return 1;
            } else {
                return 0;
            }
        }

        if ($exround->is_late_submission_open($this->get_submission_time())) {
            return 1;
        }

        return 2;
    }

    /**
     * Return a Moodle gradebook compatible grade object describing the grade given
     * to this submission.
     *
     * @return \stdClass A grade object.
     */
    public function get_grade_object() {
        $grade = new \stdClass();
        $grade->rawgrade = $this->get_grade();
        $grade->userid = $this->record->submitter; // Student.
        // User ID of the grader: use the student's ID if the submission was graded only automatically.
        $grade->usermodified = empty($this->record->grader) ? $this->record->submitter : $this->record->grader;
        $grade->dategraded = $this->get_grading_time(); // Timestamp.
        $grade->datesubmitted = $this->get_submission_time(); // Timestamp.
        return $grade;
    }

    /**
     * Return the context data for this submission used for templating.
     *
     * @param boolean $includefeedbackandfiles
     * @param boolean $includesbmsandgradingdata
     * @param boolean $includemanualgrader
     * @return \stdClass
     */
    public function get_template_context(
            $includefeedbackandfiles = false,
            $includesbmsandgradingdata = false,
            $includemanualgrader = false
    ) {
        global $OUTPUT;

        $ctx = new \stdClass();
        $ctx->url = \mod_adastra\local\urls\urls::submission($this);
        $ctx->pollurl = \mod_adastra\local\urls\urls::poll_submission_status($this);
        $ctx->inspecturl = \mod_adastra\local\urls\urls::inspect_submission($this);
        $ctx->submissiontime = $this->get_submission_time();
        $ctx->gradingtime = $this->get_grading_time();
        $ctx->state = $this->get_status();
        $ctx->statuswait = ($this->get_status() === self::STATUS_WAITING);
        $grade = $this->get_grade();
        $ctx->submitted = true;
        $ctx->fullscore = ($grade >= $this->get_exercise()->get_max_points());
        $ctx->passed = ($grade >= $this->get_exercise()->get_points_to_pass());
        $ctx->missingpoints = !$ctx->passed;
        $ctx->points = $grade;
        $ctx->max = $this->get_exercise()->get_max_points();
        $ctx->pointstopass = $this->get_exercise()->get_points_to_pass();
        $ctx->servicepoints = $this->get_service_points();
        $ctx->servicemaxpoints = $this->get_service_max_points();
        $ctx->latepenaltyapplied = $this->get_late_penalty_applied();
        if ($ctx->latepenaltyapplied !== null) {
            $ctx->latepenaltyappliedpercent = (int) round($ctx->latepenaltyapplied * 100);
        }
        $ctx->submittername = $this->get_submitter_name();
        $courseid = $this->get_exercise()->get_exercise_round()->get_course()->courseid;
        $user = $this->get_submitter();
        $ctx->submitterresultsurl = \mod_adastra\local\urls\urls::user_results($courseid, $user->id);
        $ctx->submitterprofilepic = $OUTPUT->user_picture($user, array('courseid' => $courseid));
        $assistantfeedback = $this->get_assistant_feedback();
        $ctx->hasassistantfeedback = !empty($assistantfeedback); // Empty supports only variables.

        if ($includemanualgrader) {
            $manualgrader = $this->get_grader();
            if ($manualgrader !== null) {
                $ctx->manualgradername = $this->get_grader_name();
                $ctx->manualgraderresultsurl = \mod_adastra\local\urls\urls::user_results($courseid, $manualgrader->id);
                $ctx->manualgraderprofilepic = $OUTPUT->user_picture($mannualgrader, array('courseid' => $courseid));
            }
        }

        if ($this->is_graded()) {
            $ctx->isgraded = true;
        } else {
            $ctx->status = $this->get_status(true); // Set status only for non-graded submissions.
            $ctx->isgraded = false;
        }

        if ($includefeedbackandfiles) {
            $ctx->files = $this->get_files_template_context();
            $ctx->hasfiles = !empty($ctx->files);
            $context = \context_module::instance($this->get_exercise()->get_exercise_round()->get_course_module()->id);
            $ctx->feedback = adastra_filter_exercise_content($this->get_feedback(), $context);
            $ctx->assistantfeedback = adastra_filter_exercise_content($assistantfeedback, $context);
        }

        if ($includesbmsandgradingdata) {
            $ctx->submissiondata = self::convert_json_data_to_template_context($this->get_submission_data());
            $ctx->gradingdata = self::convert_json_data_to_template_context($this->get_grading_data());
        }

        return $ctx;
    }

    /**
     * Return the template context for the given JSON data. It separates top-level
     * keys and values so that the keys may be emphasized in the template.
     *
     * @param array|scalar|null $jsondata Decoded JSON data.
     * @return null if the input is null. Otherwise, a numerically indexed array that
     * contains nested arrays. The nested arrays are pairs that have keys "key" and "value".
     */
    public function convert_json_data_to_template_context($jsondata) {
        if ($jsondata === null) {
            return null;
        } else if (is_scalar($jsondata)) {
            // Not an array nor an object, so no key-value pairs.
            if (is_bool($jsondata)) {
                // Conver booleans to strings that look like booleans, not integers.
                $jsondata = $jsondata ? 'true' : 'false';
            }
            return array(
                array(
                    'key' => '-',
                    'value' => $jsondata,
                ),
            );
        } else {
            $res = array();
            foreach ($jsondata as $key => $val) {
                if (!is_string($val) && !is_numeric($val)) {
                    $val = json_encode($val, JSON_PRETTY_PRINT);
                }
                $res[] = array(
                    'key' => $key,
                    'value' => $val,
                );
            }
            return $res;
        }
    }

    /**
     * Return true if file of the given MIME type should be passed to the user
     * (i.e. it is a binary file, e.g. an image or a pdf).
     *
     * @param string $mimetype
     * @return boolean
     */
    public static function is_file_passed($mimetype) {
        $types = array('image/jpeg', 'image/png', 'image/gif', 'application/pdf');
        return in_array($mimetype, $types);
    }

    /**
     * Return the template context for the files in this submission.
     *
     * @return \stdClass[]
     */
    public function get_files_template_context() {
        $files = array();
        $moodlefiles = $this->get_submitted_files();
        foreach ($moodlefiles as $file) {
            $filectx = new \stdClass();
            $url = \moodle_url::make_pluginfile_url(
                $file->get_contextid(),
                \mod_adastra\local\data\exercise_round::MODNAME,
                self::SUBMITTED_FILES_FILEAREA,
                $file->get_itemid(),
                $file->get_filepath(),
                $file->get_filename(),
                false
            );
            $urlforcedl = \moodle_url::make_pluginfile_url(
                $file->get_contextid(),
                \mod_adastra\local\data\exercise_round::MODNAME,
                self::SUBMITTED_FILES_FILEAREA,
                $file->get_itemid(),
                $file->get_filepath(),
                $file->get_filename(),
                true
            );
            $filectx->absoluteurl = $url->out();
            $filectx->absoluteurlforcedl = $urlforcedl->out();
            $filectx->ispassed = self::is_file_passed($file->get_mimetype());
            $filectx->filename = $file->get_filename(); // Base name, not full path.
            $filectx->size = $file->get_filesize(); // Int, in bytes.
            $files[] = $filectx;
        }
        return $files;
    }
}