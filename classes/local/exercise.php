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

namespace mod_adastra\local;

defined('MOODLE_INTERNAL') || die();

class exercise extends \mod_adastra\local\learning_object {
    const TABLE = 'adastra_exercises';

    /**
     * Override of the parent method is_submittable.
     *
     * @return boolean True for exercises.
     */
    public function is_submittable() {
        return true;
    }

    public function get_exercise_template_context(stdClass $user = null, $includetotalsubmittercount = true,
            $includecoursemodule = true, $includesiblings = false) {
        // TODO
    }
}