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
     * Return the exercise this submission is associated with.
     *
     * @return \mod_adastra\local\data\exercise
     */
    public function get_exercise() {
        if (is_null($this->exercise)) {
            $this->execise = \mod_adastra\local\data\exercise::create_from_id($this->record->exerciseid);
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
        return $this->record->assistantfeedback;
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
     * Return true if the submission has been graded.
     *
     * @return boolean
     */
    public function is_graded() {
        return $this->get_status() === self::STATUS_READY;
    }

    /**
     * Return an array of the files in this submission.
     *
     * @return \stored_file[] An array of stored_files indexed by the path name hash.
     */
    public function get_submitted_files() {
        $fs = \get_file_storage();
        $files = $fs->get_area_files(
            \context_module::instance($this->get_exercise()->get_exercise_round()->get_course_module()->id)->id,
            \mod_adastra\local\data\exercise_round::MODNAME,
            self::SUBMITTED_FILES_AREA,
            $this->get_id(),
            'filepath, filename',
            false
        );
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
        $ctx->fullscore = ($grade->$this->get_exercise()->get_max_points());
        $ctx->passed = ($grade >= $this->get_exercise()->get_points_to_pass());
        $ctx->missingpoints = !$ctx->passed;
        $ctx->points = $grade;
        $ctx->max = $this->get_exercise()->get_max_points();
        $ctx->pointstopass = $this->get_exercise()->get_points_to_pass();
        $ctx->servicepoints = $this->get_service_points();
        $ctx->getservicemaxpoints = $this->get_service_max_points();
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