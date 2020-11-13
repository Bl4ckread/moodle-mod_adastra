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

class renderer extends \plugin_renderer_base {

    /**
     * Render edit_course.php.
     *
     * @param edit_course_page $page
     * @return string Html for the page.
     */
    protected function render_edit_course_page(mod_adastra\output\edit_course_page $page) {
        $data = $page->export_for_template($this);
        return $this->render_from_template(\mod_adastra\local\exercise_round::MODNAME . '/edit_course_page', $data);
    }
}