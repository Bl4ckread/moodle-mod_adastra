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

namespace mod_adastra\output;

defined('MOODLE_INTERNAL') || die();

class edit_course_page implements \renderable, \templatable {
    protected $courseid;
    protected $modulenumbering;
    protected $contentnumbering;

    public function __construct($courseid) {
        global $DB;

        $this->courseid = $courseid;

        $coursesettings = $DB->get_record(\mod_adastra\local\course_config::TABLE, array('course' => $courseid));
        if ($coursesettings === false) {
            $this->modulenumbering = \mod_adastra\local\course_config::MODULE_NUMBERING_ARABIC;
            $this->contentnumbering = \mod_adastra\local\course_config::CONTENT_NUMERING_ARABIC;
        } else {
            $conf = new \mod_adastra\local\course_config($coursesettings);
            $this->modulenumbering = $conf->get_module_numbering();
            $this->contentnumbering = $conf->get_content_numbering();
        }
    }

    public function export_for_template(\renderer_base $output) {
        $ctx = new \stdClass();
        $ctx->autosetupurl = \mod_adastra\local\urls::auto_setup($this->courseid);
        $ctx->categories = array();
        foreach (\mod_adastra\local\category::get_categories_in_course($this->courseid, true) as $cat) {
            $ctx->categories[] = $cat->get_template_context();
        }
        $ctx->createcategoryurl = \mod_adastra\local\urls::create_category($this->courseid);
        $ctx->coursemodules = array();
        foreach (\mod_adastra\local\exercise_round::get_exercise_rounds_in_course($this->courseid, true) as $exround) {
            $ctx->course_modules[] = $exround->get_template_context_with_exercises(true);
        }
    }
}