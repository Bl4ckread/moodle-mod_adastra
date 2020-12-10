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

defined('MOODLE_INTERNAL') || die();

class exercise_round_page implements \renderable, \templatable {

    protected $exround;
    protected $modulesummary;

    /**
     * Construct a new exercise_round_page object.
     *
     * @param \mod_adastra\local\data\exercise_round $exround
     * @param \stdClass $user
     */
    public function __construct(\mod_adastra\local\data\exercise_round $exround, \stdClass $user) {
        $this->exround = $exround;
        $this->modulesummary = new \mod_adastra\local\summary\user_module_summary($exround, $user);
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
        $data->iscoursestaff = \has_capability('mod/adastra:viewallsubmissions', $ctx);
        $iseditingteacher = \has_capability('mod/adastra:addinstance', $ctx);
        $data->coursemodule = $this->exround->get_template_context(true);
        $data->modulesummary = $this->modulesummary->get_template_context();
        $data->modulesummary->classes = 'float-right'; // CSS classes.
        $data->modulecontents = $this->modulesummary->get_module_points_panel_template_context(false, !$iseditingteacher);

        $data->indexurl = \mod_adastra\local\urls\urls::rounds_index($this->exround->get_course_module()->course);
        $data->todatestr = new \mod_adastra\local\helper\date_to_string();

        $data->toc = self::get_round_table_of_contents_context($this->exround, $this->modulesummary->get_learning_objects());

        return $data;
        // Should return an \stdClass with properties that are only made of simple types:
        // int, string, bool, float, \stdClass or arrays of these types.
    }

    /**
     * Return table of contents context for an exercise round.
     *
     * @param \mod_adastra\local\data\exercise_round $exround
     * @param \mod_adastra\local\data\learning_object[] $learningobjects An array of learning objects
     * in the round, sorted to the display order. If this parameter is not provided, the database
     * is queried here.
     * @return \stdClass
     */
    public static function get_round_table_of_contents_context(
            \mod_adastra\local\data\exercise_round $exround,
            array $learningobjects = null
    ) {
        $toc = $exround->get_template_context();
        if ($learningobjects === null) {
            $learningobjects = $exround->get_learning_objects(false, true);
        }
        $toc->lobjects = \mod_adastra\output\index_page::build_round_lobjects_context_for_toc($learningobjects);
        return $toc;
    }
}