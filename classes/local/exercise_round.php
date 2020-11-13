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

namespace mod_adastra\local;

defined('MOODLE_INTERNAL') || die();

class exercise_round extends \mod_adastra\local\database_object {
    const TABLE = 'adastra'; // Database table name.
    const MODNAME = 'mod_adastra'; // Module name for get_string().

    const STATUS_READY       = 0;
    const STATUS_HIDDEN      = 1;
    const STATUS_MAINTENANCE = 2;
    const STATUS_UNLISTED    = 3;

    private $cm; // Moodle course module as cm_info instance.
    private $courseconfig;

    public function __construct($adastra) {
        parent::__construct($adastra);
        $this->cm = $this->find_course_module();
    }

    /**
     * Find the Moodle course module corresponding to this Ad Astra activity instance.
     *
     * @return cm_info|null The Moodle course module. Null if it does not exist.
     */
    protected function find_course_module() {
        // The Moodle course module may not exist yet if the exercise round is being created.
        if (isset($this->get_course()->instances[self::TABLE][$this->record->id])) {
            return $this->get_course()->instances[self::TABLE][$this->record->id];
        } else {
            return null;
        }
    }

    /**
     * Return the Moodle course module corresponding to this Ad Astra activity instance.
     *
     * @return cm_info|null The Moodle course module. Null if it does not exist.
     */
    public function get_course_module() {
        if (is_null($this->cm)) {
            $this->cm = $this->find_course_module();
        }
        return $this->cm;
    }

    /**
     * Return a Moodle course_modinfo object corresponding to this Ad Astra activity instance.
     *
     * @return course_modinfo The course_modinfo object.
     */
    public function get_course() {
        return get_fast_modinfo($this->record->course);
    }

    /**
     * Return the name of this activity instance.
     *
     * @param string $lang
     * @param boolean $includealllang
     * @return string The name.
     */
    public function get_name(string $lang = null, bool $includealllang = false) {
        require_once(__DIR__.'/locallib.php');
        if ($includealllang) {
            // Do not parse multilang values.
            return $this->record->name;
        }

        return adastra_parse_multilang_filter_localization($this->record->name, $lang);
    }

    /**
     * Get the intro of this activity instance.
     *
     * @param boolean $format
     * @return void
     */
    public function get_intro($format = false) {
        if ($format) {
            // Use Moodle filters for safe HTML output or other intro format types.
            return format_module_intro(self::TABLE, $this->record, $this->get_course_module()->id);
        }
        return $this->record->intro;
    }

    /**
     * Get the status of this activity instance.
     *
     * @param boolean $asstring
     * @return void
     */
    public function get_status($asstring = false) {
        if ($asstring) {
            switch ((int) $this->record->status) {
                case self::STATUS_READY:
                    return get_string('statusready', self::MODNAME);
                    break;
                case self::STATUS_MAINTENANCE:
                    return get_string('statusmaintenance', self::MODNAME);
                    break;
                case self::STATUS_UNLISTED:
                    return get_string('statusunlisted', self::MODNAME);
                    break;
                default:
                    return get_string('statushidden', self::MODNAME);
            }
        }
        return (int) $this->record->status;
    }

    /**
     * Get the maximum points for this activity.
     *
     * @return int
     */
    public function get_max_points() {
        return $this->record->grade;
    }

    /**
     * Get the unique module key used in the exercise service center.
     *
     * @return string
     */
    public function get_remote_key() {
        return $this->record->remotekey;
    }

    /**
     * Get the order number of this activity. Used for listing the exercise rounds,
     * smaller comes first.
     *
     * @return int
     */
    public function get_order() {
        return $this->record->ordernum;
    }

    /**
     * Get the number of points needed to pass the exercise round.
     *
     * @return int
     */
    public function get_points_to_pass() {
        return $this->record->pointstopass;
    }

    /**
     * Get the opening time of the exercise round.
     *
     * @return int  A Unix timestamp.
     */
    public function get_opening_time() {
        return $this->record->openingtime;
    }

    /**
     * Get the closing time of the exercise round
     *
     * @return int A Unix timestamp.
     */
    public function get_closing_time() {
        return $this->record->closingtime;
    }

    /**
     * Check if late submission is allowed.
     *
     * @return boolean
     */
    public function is_late_submission_allowed() {
        return (bool) $this->record->latesbmsallowed;
    }

    /**
     * Get the deadline for late submissions
     *
     * @return int A Unix timestamp.
     */
    public function get_late_submission_deadline() {
        return $this->record->latesmbsdl;
    }

    /**
     * Get the penalty for late submissions. Values are between 0 and 1, as a multiplier
     * of points to reduce.
     *
     * @return float
     */
    public function get_late_submission_penalty() {
        return $this->record->latesbmspenalty;
    }

    /**
     * Return the percentage (between 0 and 100) that late submission points are worth.
     *
     * @return int Percentage.
     */
    public function get_late_submission_point_worth() {
        $pointworth = 0;
        if ($this->is_late_submission_allowed()) {
            $pointworth = (int) ((1.0 - $this->get_late_submission_penalty()) * 100.0);
        }
        return $pointworth;
    }

    /**
     * Return true if this exercise round has closed (not open adn the closing time has passed).
     *
     * @param int|null $when Time to check, null for current time.
     * @param boolean $checklatedeadline If true and late submissions are allowed, check if the late
     * deadline has passed instead of the normal closing time.
     * @return boolean
     */
    public function has_expired($when = null, bool $checklatedeadline = false) {
        if (is_null($when)) {
            $when = time();
        }
        if ($checklatedeadline && $this->is_late_submission_allowed()) {
            return $when > $this->get_late_submission_deadline();
        }
        return $when > $this->get_closing_time();
    }

    /**
     * Check if the exercise round is open for submissions.
     *
     * @param int|null $when Time to check, null for current time.
     * @return boolean True if is open, false if not.
     */
    public function is_open($when = null) {
        if (is_null($when)) {
            $when = time();
        }
        return $this->get_opening_time() <= $when && $when <= $this->get_closing_time();
    }

    /**
     * Check if the exercise round is in the late submission period.
     *
     * @param int|null $when Time to check, null for current time.
     * @return boolean True if yes, false if no.
     */
    public function is_late_submission_open($when = null) {
        if (is_null($when)) {
            $when = time();
        }
        return $this->is_late_submission_allowed() &&
            $this->get_closing_time() <= $when && $when <= $this->get_late_submission_deadline();
    }

    /**
     * Return true if this exercise round has opened at or before timestamp $when.
     *
     * @param int|null $when Time to check, null for current time.
     * @return boolean
     */
    public function has_started($when = null) {
        if (is_null($when)) {
            $when = time();
        }
        return $when >= $this->get_opening_time();
    }

    /**
     * Return true if tis exercise round has status "hidden".
     *
     * @return boolean
     */
    public function is_hidden() {
        return $this->get_status() === self::STATUS_HIDDEN;
    }

    /**
     * Return true if this exercise round has status "under maintenance".
     *
     * @return boolean
     */
    public function is_under_maintenance() {
        return $this->get_status() === self::STATUS_MAINTENANCE;
    }

    /**
     * Set the order number for this exercise round.
     *
     * @param int $order
     */
    public function set_order($order) {
        $this->record->ordernum = $order;
    }

    /**
     * Set the name for this exercise round.
     *
     * @param string $name
     */
    public function set_name($name) {
        $this->record->name = $name;
    }

    public function get_learning_objects($includehidden = false, $sort = true) {
        // TODO
    }

    /**
     * Get the exercise rounds present in the course.
     *
     * @param int $courseid
     * @param boolean $includehidden
     * @return array An array of exercise rounds.
     */
    public static function get_exercise_rounds_in_course($courseid, $includehidden = false) {
        global $DB;
        $sort = 'ordernum ASC, openingtime ASC, closingtime ASC, id ASC';
        if ($includehidden) {
            $records = $DB->get_records(self::TABLE, array('course' => $courseid), $sort);
        } else {
            // Exclude hidden rounds.
            $temprecords = $DB->get_records_select(self::TABLE, 'course = ? AND status != ?',
            array($courseid, self::STATUS_HIDDEN), $sort);
            // Check course_module visibility too, since the status may be ready, but the
            // course_module might not be visible.
            $records = array();
            $coursemodules = get_fast_modinfo($courseid)->instances[self::TABLE] ?? array();
            foreach ($temprecords as $id => $rec) {
                if (isset($coursemodules[$id]) && $coursemodules[$id]->visible) {
                    $records[$id] = $rec;
                }
            }
        }
        $rounds = array();
        foreach ($records as $record) {
            $rounds[] = new self($record);
        }
        return $rounds;
    }

    protected function get_sibling_context($next = true) {
        // TODO
    }

    /**
     * Return the context for the next sibling.
     *
     * @see \mod_adastra\local\exercise_round::get_sibling_context()
     * @return stdClass|null
     */
    public function get_next_sibling_context() {
        return $this->get_sibling_context(true);
    }

    /**
     * Return the context for the previous sibling.
     *
     * @see \mod_adastra\local\exercise_round::get_sibling_context()
     * @return stdClass|null
     */
    public function get_previous_sibling_context() {
        return $this->get_sibling_context(false);
    }

    /**
     * Return the context for templating.
     *
     * @param boolean $includesiblings
     * @return stdClass Context object.
     */
    public function get_template_context($includesiblings = false) {
        $ctx = new stdClass();
        $ctx->id = $this->get_id();
        $ctx->openingtime = $this->get_opening_time();
        $ctx->closingtime = $this->get_closing_time();
        $ctx->name = $this->get_name();
        $ctx->latesubmissionsallowed = $this->is_late_submissions_allowed();
        $ctx->latesubmissiondeadline = $this->get_late_submission_deadline();
        $ctx->latesubmissionpointworth = $this->get_late_submission_point_worth();
        $ctx->islatesubmissionopen = $this->is_late_submission_open();
        $ctx->showlatesubmissionpointworth = ($ctx->latesubmissionpointworth < 100);
        $ctx->latesubmissionpenalty = (int) ($this->get_late_submission_penalty() * 100); // Percent.
        $ctx->statusready = ($this->get_status() === self::STATUS_READY);
        // show_lobject_points: true if the exercise round point progress panel should display the exercise points for each exercise.
        $ctx->showlobjectpoints = ($this->get_status() === self::STATUS_READY || $this->get_status() === self::STATUS_UNLISTED);
        $ctx->statusmaintenance = ($this->get_status() === self::STATUS_MAINTENANCE);
        $ctx->introduction = \format_module_intro(self::TABLE, $this->record, $this->get_course_module()->id);
        $ctx->showrequiredpoints = ($ctx->statusready && $this->get_points_to_pass() > 0);
        $ctx->pointstopass = $this->get_points_to_pass();
        $ctx->expired = $this->has_expired();
        $ctx->open = $this->is_open();
        $ctx->hasstarted = $this->has_started();
        $ctx->notstarted = !$ctx->hasstarted;
        $ctx->statusstr = $this->get_status(true);
        $ctx->editurl = \mod_adastra\local\urls::edit_exercise_round($this);
        $ctx->removeurl = \mod_adastra\local\urls::delete_exercise_round($this);
        $ctx->url = \mod_adastra\local\urls::exercise_round($this);
        $ctx->addnewexerciseurl = \mod_adastra\local\urls::create_exercise($this);
        $ctx->addnewchapterurl = \mod_adastra\local\urls::create_chapter($this);
        $context = \context_module::instance($this->get_course_module()->id);
        $ctx->iscoursestaff = \has_capability('mod/adastra:viewallsubmissions', $context);

        if ($includesiblings) {
            $ctx->next = $this->get_next_sibling_context();
            $ctx->previous = $this->get_previous_sibling_context();
        }

        return $ctx;
    }

    /**
     * Include exercises in the context for templating.
     *
     * @param boolean $includehidden
     * @return stdClass Context object.
     */
    public function get_template_context_with_exercises($includehidden = false) {
        $ctx = $this->get_template_context();
        $ctx->allexercises = array();
        foreach ($this->get_learning_objects($includehidden) as $ex) {
            if ($ex->is_submittable()) {
                $ctx->allexercises[] = $ex->get_exercise_template_context(null, false, false);
            } else {
                $ctx->allexercises[] = $ex->get_template_context(false);
            }
        }
        $ctx->hasexercises = !empty($ctx->allexercises);
        return $ctx;
    }
}