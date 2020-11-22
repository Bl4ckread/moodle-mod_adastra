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
    public function get_gradebook_item_numer() {
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
     * Check if assistant viewing is allowed.
     *
     * @return boolean
     */
    public function is_assistant_viewing_allowed() {
        return (bool) $this->record->allowastviewing;
    }

    /**
     * Check if assistant grading is allowed.
     *
     * @return boolean
     */
    public function is_assistant_grading_allowed() {
        return (bool) $this->record->allowastgrading;
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