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

abstract class database_object {
    // Child classes must define constant TABLE (name of the database table).
    protected $record; // Database record, \stdClass.

    /**
     * Create object of the corresponding class from an existing database ID.
     *
     * @param int $id
     * @throws dml_exception If the record does not exist.
     */
    public static function create_from_id($id) {
        global $DB;
        $rec = $DB->get_record(static::TABLE, array('id' => $id), '*', MUST_EXIST);
        return new static($rec);
        /*
         * Class to instantiate is the class given in the static call:
         * \mod_astra\local\submission::create_from_id() returns instance of
         * \mod_astra\local\submission.
         */
    }

    /**
     * Create object from the given database record. The instance should already
     * exist in the database and have a valid id.
     *
     * @param \stdClass $record
     */
    public function __construct(\stdClass $record) {
        $this->record = $record;
    }

    /**
     * Return the id of the record as int.
     *
     * @return int The id.
     */
    public function get_id() {
        return (int) $this->record->id;
    }

    /**
     * Save the updated record to the database. It must exist in the database
     * before calling this method (meaning it has a valid id).
     *
     * @return boolean Success or failure (should always be true).
     * @throws dml_exception For any errors.
     */
    public function save() {
        global $DB;
        return $DB->update_record(static::TABLE, $this->record);
    }

    /**
     * Return the database record of the object (as a \stdClass).
     * Please do not use this method to change the state of the object
     * by modifying the record; use this when Moodle requires data as
     * a \stdClass.
     *
     * @return \stdClass The record.
     */
    public function get_record() {
        return $this->record;
    }
}