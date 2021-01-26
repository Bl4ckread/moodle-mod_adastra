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

class user_module_summary {

    protected $exround;
    protected $user;
    protected $exercisesummaries;
    protected $learningobjects;
    protected $latestsubmissiontime;

    /**
     * Create a a summary of a user's status in one exercise round.
     * If $generate is true, the summary is generated here. Otherwise, $exercisesummaries
     * must contain all the exercise summaries for the student in this round and this method
     * will not do any database queries. Likewise, $learningobjects must contain the visible
     * learning objects if $generate is false.
     *
     * @param \mod_adastra\local\data\exercise_round $exround
     * @param \stdClass $user
     * @param array $exercisesummaries An array of user_exercise_summary objects.
     * @param array $learningobjects An array of \mod_adastra\local\data\learning_object objects,
     * i.e. the visible exercises and chapters of the round.
     * @param boolean $generate
     */
    public function __construct(
            \mod_adastra\local\data\exercise_round $exround,
            $user,
            array $exercisesummaries = array(),
            array $learningobjects = array(),
            $generate = true
    ) {
        $this->exround = $exround;
        $this->user = $user;
        $this->exercisesummaries = $exercisesummaries;
        $this->learningobjects = (
            empty($learningobjects) ?
            $learningobjects :
            \mod_adastra\local\data\exercise_round::sort_round_learning_objects($learningobjects)
        );
        $this->latestsubmissiontime = 0; // Time of the latest submission the user has made in the round.
        foreach ($exercisesummaries as $exsummary) {
            /*
             * The best submissions are not necessarily the latest, but it does not matter
             * since the latest submission time is only used when the round summary is generated
             * here and this loop is then not used.
             */
            $best = $exsummary->get_best_submission();
            if ($best !== null && $best->get_submission_time() > $this->latestsubmissiontime) {
                $this->latestsubmissiontime = $best->get_submission_time();
            }
        }

        if ($generate) {
            $this->generate();
        }
    }

    /**
     * Generate the summary data for this exercise round.
     *
     * @return void
     */
    protected function generate() {
        global $DB;

        // All visible learning objects (exercises and chapters) in the round.
        $lobjects = $this->exround->get_learning_objects(false, true);
        $this->learningobjects = $lobjects;
        $submissionsbyexerciseid = array();
        $exerciseids = array();
        foreach ($lobjects as $ex) {
            if ($ex->is_submittable()) {
                $submissionsbyexerciseid[$ex->get_id()] = array(
                    'count' => 0, // Number of submissions.
                    'best' => null, // Best submission.
                    'all' => array(),
                );
                $exerciseids[] = $ex->get_id();
            }
        }

        // All submissions from the user in any visible exercise in the exercise round.
        $sql = "SELECT id, status, submissiontime, exerciseid, submitter, grader,
        assistfeedback, grade, gradingtime, latepenaltyapplied, servicepoints, servicemaxpoints
        FROM {" . \mod_adastra\local\data\submission::TABLE . "}
        WHERE submitter = ? AND exerciseid IN (" . implode(',', $exerciseids) . ")
        ORDER BY submissiontime DESC";

        if (!empty($exerciseids)) {
            $submissions = $DB->get_recordset_sql($sql, array($this->user->id));
            // Find the best submission for each exercise.
            foreach ($submissions as $record) {
                $sbms = new \mod_adastra\local\data\submission($record);
                $exercisebest = &$submissionsbyexerciseid[$record->exerciseid];
                $best = $exercisebest['best'];
                if (
                        $best === null ||
                        $sbms->get_grade() > $best->get_grade() ||
                        $sbms->get_submission_time() < $best->get_submission_time() && $sbms->get_grade() == $best->get_grade()
                ) {
                    $exercisebest['best'] = $sbms;
                }
                $exercisebest['count'] += 1;
                $exercisebest['all'][] = $sbms;

                if ($sbms->get_submission_time() > $this->latestsubmissiontime) {
                    $this->latestsubmissiontime = $sbms->get_submission_time();
                }
            }

            $submissions->close();
        }

        // Create exercise summary objects.
        $this->exercisesummaries = array();
        foreach ($lobjects as $ex) {
            if ($ex->is_submittable()) {
                $this->exercisesummaries[] = new user_exercise_summary(
                    $ex,
                    $this->user,
                    $submissionsbyexerciseid[$ex->get_id()]['count'],
                    $submissionsbyexerciseid[$ex->get_id()]['best'],
                    $submissionsbyexerciseid[$ex->get_id()]['all'],
                    null,
                    false
                );
            }
        }
    }

    /**
     * Return the total submission count for this exercise round.
     *
     * @return int
     */
    public function get_total_submission_count() {
        $totalsubmissioncount = 0;
        foreach ($this->exercisesummaries as $exsummary) {
            $totalsubmissioncount += $exsummary->get_submission_count();
        }
        return $totalsubmissioncount;
    }

    /**
     * Return the total points the user has received for this exercise round.
     *
     * @return int
     */
    public function get_total_points() {
        $points = 0;
        foreach ($this->exercisesummaries as $exsummary) {
            $points += $exsummary->get_points();
        }
        return $points;
    }

    /**
     * Return the max points for this exercise round.
     *
     * @return int
     */
    public function get_max_points() {
        return $this->exround->get_max_points();
    }

    /**
     * Return true if the user is missing points in this exercise round.
     *
     * @return boolean
     */
    public function is_missing_points() {
        return $this->get_total_points() < $this->exround->get_points_to_pass();
    }

    /**
     * Return the number of points required to pass this exercise round.
     *
     * @return int
     */
    public function get_required_points() {
        return $this->exround->get_points_to_pass();
    }

    /**
     * Return true if the user has passed this exercise round.
     *
     * @return boolean
     */
    public function is_passed() {
        if ($this->is_missing_points()) {
            return false;
        } else {
            foreach ($this->exercisesummaries as $exsummary) {
                if (!$exsummary->is_passed()) {
                    return false;
                }
            }
            return true;
        }
    }

    /**
     * Return the number of exercises in this round.
     *
     * @return int
     */
    public function get_exercise_count() {
        return \count($this->exercisesummaries);
    }

    /**
     * Return true if the user has made any submissions to this exercise round.
     *
     * @return boolean
     */
    public function is_submitted() {
        return $this->get_total_submission_count() > 0;
    }

    /**
     * Return the time of the latest submission to this exercise round.
     *
     * @return int A Unix timestamp.
     */
    public function get_latest_submission_time() {
        return $this->latestsubmissiontime;
    }

    /**
     * Return the learning objects in this exercise round.
     *
     * @return \mod_adastra\local\data\learning_object[]
     */
    public function get_learning_objects() {
        return $this->learningobjects;
    }

    /**
     * Return the data for templating.
     *
     * @return \stdClass
     */
    public function get_template_context() {
        $totalpoints = $this->get_total_points();

        $ctx = new \stdClass();
        $ctx->submitted = $this->is_submitted();
        $ctx->fullscore = ($totalpoints >= $this->get_max_points());
        $ctx->passed = $this->is_passed();
        $ctx->missingpoints = $this->is_missing_points();
        $ctx->points = $totalpoints;
        $ctx->max = $this->get_max_points();
        $ctx->pointstopass = $this->get_required_points();
        $ctx->required = $this->get_required_points();
        $ctx->percentage = ($ctx->max == 0) ? 0 : round(100 * $ctx->points / $ctx->max);
        $ctx->requiredpercentage = ($ctx->max == 0) ? 0 : round(100 * $ctx->required / $ctx->max);
        return $ctx;
    }

    /**
     * Return the data for module points panel template.
     *
     * @param boolean $requireassistantviewingforsubmissions
     * @param boolean $requireastviewingforallsbmslink
     * @return \stdClass
     */
    public function get_module_points_panel_template_context(
            $requireassistantviewingforsubmissions = false,
            $requireastviewingforallsbmslink = true
    ) {
        $ctx = array();
        $exsummariesbyid = array();
        foreach ($this->exercisesummaries as $exsum) {
            $exsummariesbyid[$exsum->get_exercise()->get_id()] = $exsum;
        }
        foreach ($this->learningobjects as $lobject) {
            $data = new \stdClass();
            if ($lobject->is_submittable()) {
                $exercisesummary = $exsummariesbyid[$lobject->get_id()];
                $data->exercise = $lobject->get_exercise_template_context(
                    $this->user,
                    false,
                    false
                );
                if (!$requireassistantviewingforsubmissions || $lobject->is_assistant_viewing_allowed()) {
                    $data->submissions = \mod_adastra\local\data\exercise::submissions_template_context(
                            $exercisesummary->get_submissions()
                    );
                } else {
                    $data->sbmsrequireastview = true;
                }
                if (!$requireastviewingforallsbmslink || $lobject->is_assistant_viewing_allowed()) {
                    $data->showallsbmslink = true;
                }
                $data->exercisesummary = $exercisesummary->get_template_context();
            } else {
                $data->exercise = $lobject->get_template_context(false);
            }
            $ctx[] = $data;
        }

        return $ctx;
    }
}