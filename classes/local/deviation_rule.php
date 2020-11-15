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

abstract class deviation_rule extends \mod_adastra\local\database_object {
    // Subclasses must define constant TABLE.

    // Cache of variables.
    protected $exercise = null;
    protected $submitter = null;

    /**
     * Return the deviation as an object of the inheriting class that this function
     * is statically called from.
     *
     * @param int $exerciseid
     * @param int $userid
     */
    public static function find_deviation($exerciseid, $userid) {
        global $DB;

        $record = $DB->get_record(static::TABLE, array(
            'submitter' => $userid,
            'exerciseid' => $exerciseid,
        ));
        if ($record === false) {
            return null;
        } else {
            return new static($record);
            // Creates an instance of the class that is used to call this method statically.
        }
    }

    /**
     * Return the exercise this rule is associated with.
     *
     * @return \mod_adastra\local\exercise
     */
    public function get_exercise() {
        if ($this->exercise === null) {
            $this->exercise = \mod_adastra\local\exercise::create_from_id($this->record->exerciseid);
        }
        return $this->exercise;
    }
}