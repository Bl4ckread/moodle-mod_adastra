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
     * Return the gradebook item number for the exercise.
     *
     * @return int
     */
    public function get_gradebook_item_number() {
        return $this->record->gradeitemnumber;
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

        // Delete exercise gradebook item.
        $this->delete_gradebook_item();

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
     * Delete the Moodle gradebook item for this exercise.
     *
     * @return int GRADE_UPDATE_OK or GRADE_UPDATE_FAILED (or GRADE_UPDATE_MULTIPLE).
     */
    public function delete_gradebook_item() {
        global $CFG;
        require_once($CFG->libdir . '/gradelib.php');
        return grade_update(
                'mod/' . \mod_adastra\local\data\exercise_round::TABLE,
                $this->get_exercise_round()->get_course()->courseid,
                'mod',
                \mod_adastra\local\data\exercise_round::TABLE,
                $this->get_exercise_round()->get_id(),
                $this->get_gradebook_item_number(),
                null,
                array('deleted' => 1)
        );
    }

    /**
     * Create or update the Moodle gradebook item for this exercise.
     * In order to add grades for students, use the method update_grades.
     *
     * @param boolean $reset If true, delete all grades in the grade item.
     * @return int grade_update return value (one of GRADE_UPDATE_OK, GRADE_UPDATE_FAILED,
     * GRADE_UPDATE_MULTIPLE or GRADE_UPDATE_ITEM_LOCKED).
     */
    public function update_gradebook_item($reset = false) {
        global $CFG, $DB;
        require_once($CFG->libdir . '/gradelib.php');
        require_once($CFG->libdir . '/grade/grade_item.php');

        $item = array();
        $item['itemname'] = $this->get_name(true, null, true);
        $item['hidden'] = (int) (
                $this->is_hidden() ||
                $this->get_exercise_round()->is_hidden() ||
                $this->get_category()->is_hidden()
        ); // The hidden value must be zero or one. Integers above one are interpreted as timestamps (hidden until).

        // Update exercise grading information ($item).
        if ($this->get_max_points() > 0) {
            $item['gradetype'] = GRADE_TYPE_VALUE; // Points.
            $item['grademax'] = $this->get_max_points();
            $item['grademin'] = 0; // Minimum allowed value (points cannot be below this).
            // Looks like minimum grade to pass (gradepass) can't be set in this API directly.
        } else {
            $item['gradetype'] = GRADE_TYPE_NONE;
        }

        if ($reset) {
            $item['reset'] = true;
        }

        $courseid = $this->get_exercise_round()->get_course()->courseid;

        // Create gradebook item.
        $res = grade_update(
            'mod/' . \mod_adastra\local\data\exercise_round::TABLE,
            $courseid,
            'mod',
            \mod_adastra\local\data\exercise_round::TABLE,
            $this->record->roundid,
            $this->get_gradebook_item_number(),
            null,
            $item
        );

        // Parameters to find the grade item from DB.
        $gradeitemparams = array(
            'itemtype' => 'mod',
            'itemmodule' => \mod_adastra\local\data\exercise_round::TABLE,
            'iteminstance' => $this->record->roundid,
            'itemnumber' => $this->get_gradebook_item_number(),
            'courseid' => $courseid,
        );
        $gi = \grade_item::fetch($gradeitemparams);
        if ($gi && $gi->gradepass != $this->get_points_to_pass()) {
            // Set min points to pass.
            $gi->gradepass = $this->get_points_to_pass();
            $gi->update('mod/' . \mod_adastra\local\data\exercise_round::TABLE);
        }

        return $res;
    }

    /**
     * Save changes made to this exercise.
     *
     * @param boolean $skipgradebook If true don't update gradebook.
     * @return boolean True.
     */
    public function save($skipgradebook = false) {
        if (!$skipgradebook) {
            $this->update_gradebook_item();
        }

        return parent::save();
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
}