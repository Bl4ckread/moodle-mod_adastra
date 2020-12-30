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

class submission_plain_page implements \renderable, \templatable {

    protected $exround;
    protected $exercise;
    protected $submission;
    protected $user;
    protected $exercisesummary;

    public function __construct(
            \mod_adastra\local\data\exercise_round $exround,
            \mod_adastra\local\data\exercise $exercise,
            \mod_adastra\local\data\submission $submission
    ) {
        $this->exround = $exround;
        $this->exercise = $exercise;
        $this->submission = $submission;
        $this->user = $submission->get_submitter();
        $this->exercisesummary = new \mod_adastra\local\summary\user_exercise_summary($exercise, $this->user);
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
        $data->iscoursestaff = ($this->exercise->is_assistant_viewing_allowed() &&
                has_capability('mod/adastra:viewallsubmissions', $ctx)) ||
                has_capability('mod/adastra:addinstance', $ctx);

        $data->exercise = $this->exercise->get_exercise_template_context($this->user, false, false);
        $data->submission = $this->submission->get_template_context(true, false);
        $data->summary = $this->exercisesummary->get_template_context();

        $data->todatestr = new \mod_adastra\local\helper\date_to_string();
        $data->filesizeformatter = new \mod_adastra\local\helper\file_size_formatter();

        return $data;
    }
}