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

namespace mod_adastra\local\summary;

defined('MOODLE_INTERNAL') || die;

class user_category_summary {

    protected $user;
    protected $category;
    protected $exercisesummaries;

    /**
     * Create a summary of a user's status in a category for templates.
     * If $generate is true, the summary is generated here. Otherwise, $exercisesummaries
     * must contain all the exercise summaries for the student in this category and this
     * method will not do any database queries.
     *
     * @param \mod_adastra\local\data\category $category
     * @param [type] $user
     * @param array $exercisesummaries An array of user_exercise_summary objects.
     * @param boolean $generate
     */
    public function __construct(
        \mod_adastra\local\data\category $category,
        $user,
        array $exercisesummaries = array(),
        $generate = true
    ) {
        $this->user = $user;
        $this->category = $category;
        $this->exercisesummaries = $exercisesummaries;

        if ($generate) {
            $this->generate();
        }
    }

    /**
     * Generate the exercise summaries for this category.
     *
     * @return void
     */
    protected function generate() {
        // Usually this is not needed, hence we save coding work by doing
        // database queries for each exercise separately.
        $exercises = $this->category->get_exercises();
        foreach ($exercises as $ex) {
            $this->exercisesummaries[] = new user_exercise_summary($ex, $this->user);
        }
    }

    /**
     * Return the number of exercises in this category.
     *
     * @return int
     */
    public function get_exercise_count() {
        return \count($this->exercisesummaries);
    }

    /**
     * Return the max points for this category.
     *
     * @return int
     */
    public function get_max_points() {
        $max = 0;
        foreach ($this->exercisesummaries as $exsummary) {
            $max += $exsummary->getmaxpoints();
        }
        return $max;
    }

    /**
     * Return the total points the user has in this category.
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
     * Return the number of points required to pass this category.
     *
     * @return int
     */
    public function get_required_points() {
        return $this->category->get_points_to_pass();
    }

    /**
     * Return true if the user is missing points in this category.
     *
     * @return boolean
     */
    public function is_missing_points() {
        return $this->get_total_points() < $this->get_required_points();
    }

    /**
     * Return true if the user has passed this category.
     *
     * @return boolean
     */
    public function is_passed() {
        if ($this->is_missing_points()) {
            return false;
        }
        foreach ($this->exercisesummaries as $exsummary) {
            if (!$exsummary->is_passed()) {
                return false;
            }
        }
        return true;
    }

    /**
     * Return the number of submissions the user has made in this category.
     *
     * @return int
     */
    public function get_submission_count() {
        $count = 0;
        foreach ($this->exercisesummaries as $exsummary) {
            $count += $exsummary->get_submission_count();
        }
        return $count;
    }

    /**
     * Return true if the user has made any submissions in this category.
     *
     * @return boolean
     */
    public function is_submitted() {
        return $this->get_submission_count() > 0;
    }

    /**
     * Return the data for templating.
     *
     * @return \stdClass
     */
    public function get_template_context() {
        $totalpoints = $this->get_total_points();
        $maxpoints = $this->get_max_points();

        $ctx = new \stdClass();
        $ctx->fullscore = ($totalpoints >= $maxpoints);
        $ctx->passed = $this->is_passed();
        $ctx->missingpoints = $this->is_missing_points();
        $ctx->points = $totalpoints;
        $ctx->max = $maxpoints;
        $ctx->pointstopass = $this->get_required_points();
        $ctx->required = $this->get_required_points();
        $ctx->percentage = ($ctx->max == 0) ? 0 : round(100 * $ctx->points / $ctx->max);
        $ctx->requiredpercentage = ($ctx->max == 0) ? 0 : round(100 * $ctx->required / $ctx->max);
        return $ctx;
    }

    /**
     * Return this category.
     *
     * @return \mod_adastra\local\data\category
     */
    public function get_category() {
        return $this->category;
    }
}