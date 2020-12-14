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



namespace mod_adastra\output;

defined('MOODLE_INTERNAL') || die;

require_once(__DIR__ . '/../../locallib.php');

class exercise_plain_page implements \renderable, \templatable {

    protected $exround;
    protected $learningobject;
    protected $exercisesummary; // If the learning object is an exercise.
    protected $user;
    protected $errormsg;
    protected $submission;
    protected $wait;

    public function __construct(
            \mod_adastra\local\data\exercise_round $exround,
            \mod_adastra\local\data\learning_object $learningobject,
            \stdClass $user,
            $errormsg = null,
            $submission = null,
            $waitforasyncgrading = false
    ) {
        $this->exround = $exround;
        $this->learningobject = $learningobject;
        $this->user = $user;
        if ($learningobject->is_submittable()) {
            $this->exercisesummary = new \mod_adastra\summary\user_exercise_summary($learningobject, $user);
        } else {
            $this->exercisesummary = null;
        }
        $this->errormsg = $errormsg;
        $this->submission = $submission; // If set, the page includes the feedback instead of the exercise description.
        $this->wait = $waitforasyncgrading;
        // If submission is set and $wait is true, tell the frontend JS to poll for the status of the submission
        // until the grading is complete.
    }

    /**
     * Export this data so it can be used as the context for a mustache template.
     *
     * @param \renderer_base $output
     * @return \stdClass
     */
    public function export_for_template(\renderer_base $output) {
        $data = new \stdClass();
        $ctx = \context_module::instance($this->exround->get_course_module()->id);
        $data->iscoursestaff = has_capability('mod/adastra:viewallsubmissions', $ctx);

        $statusmaintenance = ($this->exround->is_under_maintenance() || $this->learningobject->is_under_maintenance());
        $notstarted = !$this->exround->has_started();

        if ($this->submission !== null) {
            // Show feedback.
            $data->page = new \stdClass();
            $data->page->content = adastra_filter_exercise_content($this->submission->get_feedback(), $ctx);
            $data->page->iswait = $this->wait;
            $data->submission = $this->submission->get_template_context();
        } else if (!($statusmaintenance || $notstarted) || $data->iscoursestaff) {
            // Download exercise description from the exercise service.
            try {
                $page = $this->learningobject->load($this->user->id);
                $page->content = adastra_filter_exercise_content($page->content, $ctx);
                $data->page = $page->get_template_context(); // Has content field.
            } catch (\mod_adastra\protocol\remote_page_exception $e) {
                $data->error = \get_string('serviceconnectionfailed', \mod_adastra\local\data\exercise_round::MODNAME);
                $page = new \stdClass();
                $page->content = '';
                $data->page = $page;
            }
        } else if ($statusmaintenance) {
            $data->page = new \stdClass();
            $data->page->content = '<p>' . get_string('undermaintenance', \mod_adastra\local\data\exercise_round::MODNAME) . '</p>';
        } else {
            // Not started.
            $data->page = new \stdClass();
            $data->page->content = '<p>' . get_string('notopenedyet', \mod_adastra\local\data\exercise_round::MODNAME) . '</p>';
        }

        if (!is_null($this->errormsg)) {
            if (isset($data->error)) {
                $data->error .= '<br>' . $this->errormsg;
            } else {
                $data->error = $this->errormsg;
            }
        }

        $data->module = $this->exround->get_template_context();

        if ($this->learningobject->is_submittable()) {
            $data->exercise = $this->learningobject->get_exercise_template_context($this->user, false, false);
            $data->submissions = $this->learningobject->get_submissions_template_context($this->user->id);

            $data->summary = $this->exercisesummary->get_template_context();
            // Add a field to the summary object.
            if ($this->exercisesummary->get_best_submission() !== null) {
                $data->summary->bestsubmissionurl = \mod_adastra\local\urls\urls::submission($this->exercisesummary->get_best_submission());
            } else {
                $data->summary->bestsubmissionurl = null;
            }
        } else {
            $data->exercise = $this->learningobject->get_template_context(false);
        }

        $data->todatestr = new \mod_adastra\local\helper\date_to_string();

        return $data;
    }
}