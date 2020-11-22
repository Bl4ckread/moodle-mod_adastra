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

namespace mod_adastra\local\data;

defined('MOODLE_INTERNAL') || die();

class chapter extends \mod_adastra\local\data\learning_object {
    const TABLE = 'adastra_chapters';

    /**
     * Return true if table of contents should be generated.
     *
     * @return boolean
     */
    public function should_generate_table_of_contents() {
        return (bool) $this->record->generatetoc;
    }

    /**
     * Extension of the get_template_context function from learning_object.
     *
     * @param boolean $includecoursemodule
     * @param boolean $includesiblins
     * @return \stdClass
     */
    public function get_template_context($includecoursemodule = true, $includesiblins = false) {
        $ctx = parent::get_template_context($includecoursemodule, $includesiblings);
        $ctx->generatetoc = $this->should_generate_table_of_contents();
        return $ctx;
    }
}