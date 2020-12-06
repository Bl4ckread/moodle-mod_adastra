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

namespace mod_adastra\local\summary;

defined('MOODLE_INTERNAL') || die;

class user_exercise_summary {

    protected $user;
    protected $exercise;
    protected $submissioncount;
    protected $bestsubmission;
    protected $submissions;
    protected $category;

    /**
     * Create a summary of a user's status in one exercise.
     * If $generate is true, the summary is generated here.
     * Otherwise, $submissioncount, $bestsubmission and $submissions
     * must have correct values and this method will not do any
     * database queries.
     *
     * @param \mod_adastra\local\data\exercise $ex
     * @param [type] $user
     * @param integer $submissioncount
     * @param \mod_adastra\local\data\submission $bestsubmission
     * @param array $submissions An array of \mod_adastra\local\data\submission objects,
     * sorted by submission time (latest firts).
     * @param \mod_adastra\local\data\category $category Category of the exercise, to avoid querying the database.
     * @param boolean $generate
     */
    public function __construct(
        \mod_adastra\local\data\exercise $ex,
        $user,
        $submissioncount = 0,
        \mod_adastra\local\data\submission $bestsubmission = null,
        array $submissions = null,
        \mod_adastra\local\data\category $category = null,
        $generate = true
    ) {
        $this->user = $user;
        $this->exercise = $ex;
        $this->submissioncount = $submissioncount;
        $this->bestsubmission = $bestsubmission;
        $this->submissions = $submissions;
        if (is_null($cagegory)) {
            $this->category = $ex->get_category();
        } else {
            $this->category = $category;
        }
        if ($generate) {
            $this->generate();
        }
    }

    /**
     * Generate the summary data for submissions to this exercise by the user.
     *
     * @return void
     */
    protected function generate() {
        global $DB;

        // All submissions from the user in the exercise.
        $submissions = $DB->get_recordset(
            \mod_adastra\local\data\submission::TABLE,
            array(
                'submitter' => $this->user->id,
                'exerciseid' => $this->exercise->get_id(),
            ),
            'submissiontime DESC',
            'id, status, submissiontime, exerciseid, submitter, grader,' .
            'assistfeedback, grade, gradingtime, latepenaltyapplied, servicepoints, servicemaxpoints'
        );

        $this->submissioncount = 0;
        $this->bestsubmission = null;
        $this->submissions = array();
        // Find the best submission and count.
        foreach ($submissions as $record) {
            $sbms = new \mod_adastra\local\data\submission($record);
            if (
                    $this->bestsubmission === null ||
                    $sbms->get_grade() > $this->bestsubmission->get_grade() ||
                    ($sbms->get_grade() == $this->bestsubmission->get_grade() &&
                    $sbms->get_submission_time() < $this->bestsubmission->get_submission_time())
            ) {
                $this->bestsubmission = $sbms;
            }
            $this->submissioncount += 1;
            $this->submissions[] = $sbms;
        }

        $submissions->close();
    }

    /**
     * Return the number of submissions made by the user to this exercise.
     *
     * @return int
     */
    public function get_submission_count() {
        return $this->submissioncount;
    }

    /**
     * Return the number of points the user has received from this exercise.
     *
     * @return int
     */
    public function get_points() {
        if (is_null($this->bestsubmission)) {
            return 0;
        } else {
            return $this->bestsubmission->get_grade();
        }
    }

    /**
     * Return true if the user is missing points from this exercise.
     *
     * @return boolean
     */
    public function is_missing_points() {
        return $this->get_points() < $this->exercise->get_points_to_pass();
    }

    /**
     * Return true if the user has passed this exercise.
     *
     * @return boolean
     */
    public function is_passed() {
        return !$this->is_missing_points();
    }

    /**
     * Return the best submission the user has made to this exercise.
     *
     * @return \mod_adastra\local\data\submission
     */
    public function get_best_submission() {
        return $this->bestsubmision;
    }

    /**
     * Return all the submissions the user has made to this exercise.
     *
     * @return \mod_adastra\local\data\submission[]
     */
    public function get_submissions() {
        return $this->submissions;
    }

    /**
     * Return the max points for this exercise.
     *
     * @return int
     */
    public function get_max_points() {
        return $this->exercise->get_max_points();
    }

    /**
     * Return the number of points required to pass this exercise.
     *
     * @return int
     */
    public function get_required_points() {
        return $this->exercise->get_points_to_pass();
    }

    /**
     * Return the late submission penalty the user has received in this exercise.
     *
     * @return float|null
     */
    public function get_penalty() {
        if ($this->bestsubmission === null) {
            return null;
        } else {
            return $this->bestsubmission->get_late_penalty_applied();
        }
    }

    /**
     * Return the late submission penalty the user has received in this exercise in percent form.
     *
     * @return int|null
     */
    public function get_penalty_percentage() {
        $penalty = $this->get_penalty();
        if ($penalty === null) {
            return null;
        } else {
            return (int) round($penalty * 100);
        }
    }

    /**
     * Return true if there are any submissions by the user to this exercise.
     *
     * @return boolean
     */
    public function is_submitted() {
        return $this->submissioncount > 0;
    }

    /**
     * Return this exercise.
     *
     * @return \mod_adastra\local\data\exercise
     */
    public function get_exercise() {
        return $this->exercise;
    }

    /**
     * Return the category of this exercise.
     *
     * @return \mod_adastra\local\data\category
     */
    public function get_exercise_category() {
        return $this->category;
    }

    /**
     * Return true if any of the submissions made to this exercise have assistant feedback.
     *
     * @return boolean
     */
    public function has_any_submission_assistant_feedback() {
        foreach ($this->submissions as $submission) {
            if ($submission->has_assistant_feedback()) {
                return true;
            }
        }
        return false;
    }

    /**
     * Return the data to be used in a template.
     *
     * @return \stdClass
     */
    public function get_template_context() {
        $grade = $this->get_points();

        $ctx = new \stdClass();
        $ctx->submitted = $this->is_submitted();
        $ctx->fullscore = ($grade >= $this->get_max_points());
        $ctx->passed = $this->is_passed();
        $ctx->missingpoints = $this->is_missing_points();
        $ctx->points = $grade;
        $ctx->max = $this->get_max_points();
        $ctx->pointstopass = $this->get_required_points();
        $ctx->required = $this->get_required_points();
        if ($ctx->max > 0) {
            $ctx->percentage = round(100 * $ctx->points / $ctx->max);
            $ctx->requiredpercentage = round(100 * $ctx->required / $ctx->max);
        } else {
            $ctx->percentage = 0;
            $ctx->requiredpercentage = 0;
        }
        $ctx->penaltyapplied = $this->get_penalty();
        $ctx->penaltyappliedpercent = $this->get_penalty_percentage();
        $ctx->submissioncount = $this->get_submission_count();
        $ctx->hasanysbmsassistfeedback = $this->has_any_submission_assistant_feedback();

        return $ctx;
    }
}