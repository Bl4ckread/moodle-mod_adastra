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

class exercise_round extends \mod_adastra\local\database_object {
    const TABLE = 'adastra'; // Database table name.
    const MODNAME = 'mod_adastra'; // Module name for get_string().

    const STATUS_READY       = 0;
    const STATUS_HIDDEN      = 1;
    const STATUS_MAINTENANCE = 2;
    const STATUS_UNLISTED    = 3;

    private $cm; // Moodle course module as cm_info instance.
    private $courseconfig;

    public function __construct($adastra) {
        parent::__construct($adastra);
        $this->cm = $this->findcoursemodule();
    }

    /**
     * Find the Moodle course module corresponding to this Ad Astra activity instance.
     *
     * @return cm_info|null The Moodle course module. Null if it does not exist.
     */
    protected function findcoursemodule() {
        // The Moodle course module may not exist yet if the exercise round is being created.
        if (isset($this->getcourse()->instances[self::TABLE][$this->record->id])) {
            return $this->getcourse()->instances[self::TABLE][$this->record->id];
        } else {
            return null;
        }
    }

    /**
     * Return the Moodle course module corresponding to this Ad Astra activity instance.
     *
     * @return cm_info|null The Moodle course module. Null if it does not exist.
     */
    public function getcoursemodule() {
        if (is_null($this->cm)) {
            $this->cm = $this->findcoursemodule();
        }
        return $this->cm;
    }

    /**
     * Return a Moodle course_modinfo object corresponding to this Ad Astra activity instance.
     *
     * @return course_modinfo The course_modinfo object.
     */
    public function getcourse() {
        return get_fast_modinfo($this->record->course);
    }
}