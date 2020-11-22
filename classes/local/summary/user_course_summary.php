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

class user_course_summary {
    protected $user;
    protected $course;
    protected $exerciserounds;
    protected $modulesummariesbyroundid;
    protected $exercisecount = 0; // Number of exercises in the course.
    protected $categorysummaries;

    /**
     * Create a summary of the user's status in a course.
     *
     * @param \stdClass $course
     * @param \stdClass $user
     */
    public function __construct(\stdClass $course, $user) {
        $this->course = $course;
        $this->user = $user;

        // All exercise rounds in the course.
        $this->exerciserounds = \mod_adastra\local\data\exercise_round::get_exercise_rounds_in_course($course->id);

        // User_module_summary objects indexed by exercise round IDs.
        $this->modulesummariesbyroundid = array();

        // User_category_summary objects.
        $this->categorysummaries = array();

        $this->generate();
    }

    /**
     * Generate the summary for this Ad Astra instance.
     *
     * @return void
     */
    protected function generate() {
        global $DB;

        $roundids = array();
        foreach ($this->exerciserounds as $exround) {
            $roundids[] = $exround->get_id();
        }

        if (empty($roundids)) {
            $exerciserecords = array();
            $chapterrecords = array();
        } else {
            $where = ' WHERE lob.roundid IN (' . \implode(',', $roundids) . ')';
            $exerciserecords = $DB->get_records_sql(
                \mod_adastra\local\data\learning_object::get_subtype_join_sql(\mod_adastra\local\data\exercise::TABLE) . $where
            );
            $chapterrecords = $DB->get_records_sql(
                \mod_adastra\local\data\learning_object::get_subtype_join_sql(\mod_adastra\local\data\chapter::TABLE) . $where
            );
        }
        $this->exercisecount = \count($exerciserecords);
        $exercisesbyroundid = array(); // Exercises and chapters.
        $exerciseids = array(); // Only submittable exercises.
        // Only visible categories.
        $categories = \mod_adastra\local\data\category::get_categories_in_course($this->course->id);
        foreach ($roundids as $roundid) {
            $exercisesbyroundid[$roundid] = array();
        }
        foreach ($exerciserecords as $exrecord) {
            // Append exercises.
            // Filter out hidden exercises or exercises in hidden categories.
            if (
                $exrecord->status != \mod_adastra\local\data\learning_object::STATUS_HIDDEN &&
                isset($categories[$exrecord->categoryid])
            ) {
                $exercisesbyroundid[$exrecord->roundid][] = new \mod_adastra\local\data\exercise($exrecord);
                $exerciseids[] = $exrecord->lobjectid;
            }
        }
        foreach ($chapterrecords as $exrecord) {
            // Append chapters.
            if (
                $exrecord->status != \mod_adastra\local\data\learning_object::STATUS_HIDDEN &&
                isset($categories[$exrecord->categoryid])
            ) {
                $exercisesbyroundid[$exrecord->roundid][] = new \mod_adastra\local\data\chapter($exrecord);
            }
        }

        $exercisesummariesbycategoryid = array();
        foreach ($categories as $cat) {
            $exercisesummariesbycategoryid[$cat->get_id()] = array();
        }

        // Initialize array for holding the best submissions.
        $submissionsbyexerciseid = array();
        foreach ($roundids as $rid) {
            foreach ($exercisesbyroundid[$rid] as $ex) {
                if ($ex->is_submittable()) {
                    $submissionsbyexerciseid[$ex->get_id()] = array(
                        'count' => 0,
                        'best' => null,
                        'all' => array(),
                    );
                }
            }
        }

        // All submissions from the user in any visible exercise in the course.
        $sql = "SELECT id, status, submissiontime, exerciseid, submitter, grader, assistfeedback, grade,
                gradingtime, latepenaltyapplied, servicepoints, servicemaxpoints
                FROM {" . \mod_adastra\local\data\submissions::TABLE . "}
                WHERE submitter = ? AND exerciseid IN (" . implode(',', $exerciseids) . ")
                ORDER BY submissiontime DESC";

        if (!empty($exerciseids)) {
            $submissions = $DB->get_recordset_sql($sql, array($this->user->id));
            // Find best submissions.
            foreach ($submissions as $record) {
                $sbms = new \mod_adastra\local\data\submission($record);
                $exercisebest = &$submissionsbyexerciseid[$record->exerciseid];
                $exercisebest['all'][] = $sbms;
                $best = $exercisebest['best'];
                if (
                        $best === null ||
                        $sbms->get_grade() > $best->get_grade() ||
                        $sbms->get_grade() == $best->get_grade() && $sbms->get_submission_time() < $best->get_submission_time()
                ) {
                    $exercisebest['best'] = $sbms;
                }
                $exercisebest['count'] += 1;
            }
            $submissions->close();
        }

        // Make summary objects.
        foreach ($this->exerciserounds as $exround) {
            $exercisesummaries = array(); // User_exercise_summary objects for one exercise round.
            foreach ($exercisesbyroundid[$exround->get_id()] as $ex) {
                if ($ex->is_submittable()) {
                    $exercisebest = &$submissionsbyexerciseid[$ex->get_id()];
                    $exercisesummary = new user_exercise_summary(
                        $ex,
                        $this->user,
                        $exercisebest['count'],
                        $exercisebest['best'],
                        $exercisebest['all'],
                        $categories[$ex->get_category_id()],
                        false
                    );
                    $exercisesummaries[] = $exercisesummary;
                    $exercisesummariesbycategoryid[$ex->get_category_id()][] = $exercisesummary;
                }
            }
            $this->modulesummariesbyroundid[$exround->get_id()] = new user_module_summary(
                  $exround,
                  $this->user,
                  $exercisesummaries,
                  $exercisesbyroundid[$exround->get_id()],
                  false
            );
        }

        foreach ($categories as $cat) {
            $this->categorysummaries[] = new user_category_summary(
                $cat,
                $this->user,
                $exercisesummariesbycategoryid[$cat->get_id()],
                false
            );
        }
    }

    /**
     * Return the number of exercises in course.
     *
     * @return int
     */
    public function get_exercise_count() {
        return $this->exercisecount;
    }

    /**
     * Return the max points for this course.
     *
     * @return int
     */
    public function get_max_points() {
        $max = 0;
        foreach ($this->modulesummariesbyroundid as $modulesummary) {
            $max += $modulesummary->get_max_points();
        }
        return $max;
    }

    /**
     * Return the total points the user has in this course.
     *
     * @return int
     */
    public function get_total_points() {
        $total = 0;
        foreach ($this->modulesummariesbyroundid as $modulesummary) {
            $total += $modulesummary->get_total_points();
        }
    }

    /**
     * Return the exercise rounds in this course.
     *
     * @return \mod_adastra\local\data\exercise_round[]
     */
    public function get_exercise_rounds() {
        return $this->exerciserounds;
    }

    /**
     * Return the module summary for the round $roundid.
     *
     * @param int $roundid
     * @return user_module_summary
     */
    public function get_module_summary($roundid) {
        return $this->modulesummariesbyroundid[$roundid];
    }

    /**
     * Return the category sumamries for this course.
     *
     * @return user_category_summary[]
     */
    public function get_category_summaries() {
        return $this->categorysummaries;
    }
}