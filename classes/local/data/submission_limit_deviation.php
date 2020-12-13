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

namespace mod_adastra\local\data;

defined('MOODLE_INTERNAL') || die();

class submission_limit_deviation extends \mod_adastra\local\data\deviation_rule {
    const TABLE = 'adastra_maxsbms_devs';

    public function get_extra_submissions() {
        return (int) $this->record->extrasubmissions;
    }

    public static function create_new($exerciseid, $userid, $extrasubmissions) {
        global $DB;

        if (self::find_deviation($exerciseid, $userid) === null) {
            // Does not exist yet.
            $record = new \stdClass();
            $record->submitter = $userid;
            $record->exerciseid = $exerciseid;
            $record->extrasubmissions = $extrasubmissions;
            return $DB->insert_record(self::TABLE, $record);
        } else {
            // User already has a deviation in the exercise.
            return null;
        }
    }
}