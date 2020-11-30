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

/**
 * Exercise category in a course. Each exercise (learning object) belongs to one category
 * and the category counts the total points in the category. A category can have
 * required points to pass that the student should earn in total from the
 * exercises in the category. Exercises in a category can be scattered across
 * multiple exercise rounds.
 *
 * Each instance of this class should correspond to one record in the categories
 * database table.
 */
class category extends database_object {
    const TABLE = 'adastra_categories';
    const STATUS_READY  = 0;
    const STATUS_HIDDEN = 1;
    const STATUS_NOTOTAL = 2;

    /**
     * Get the status of the category.
     *
     * @param boolean $asstring
     * @return int|string The status either as a number or a string describing the status.
     */
    public function get_status($asstring = false) {
        if ($asstring) {
            switch ((int) $this->record->status) {
                case self::STATUS_READY:
                    return get_string('statusready', \mod_adastra\local\data\exercise_round::MODNAME);
                    break;
                case self::STATUS_NOTOTAL:
                    return get_string('statusnototal', \mod_adastra\local\data\exercise_round::MODNAME);
                    break;
                default:
                    return get_string('statushidden', \mod_adastra\local\data\exercise_round::MODNAME);
            }
        }
        return (int) $this->record->status;
    }

    /**
     * Return the name of the category.
     *
     * @param string $lang
     * @return string
     */
    public function get_name(string $lang = null) {
        require_once(__DIR__ . '/../../../locallib.php');

        return adastra_parse_localization($this->record->name, $lang);
    }

    /**
     * Return the number of points needed to pass the category.
     *
     * @return int
     */
    public function get_points_to_pass() {
        return $this->record->pointstopass;
    }

    /**
     * Return true if the status of the category is set as hidden.
     *
     * @return boolean
     */
    public function is_hidden() {
        return $this->get_status() === self::STATUS_HIDDEN;
    }

    /**
     * Set the status of the category as hidden.
     *
     * @return void
     */
    public function set_hidden() {
        $this->record->status = self::STATUS_HIDDEN;
    }

    /**
     * Get the sql statement and params for querying learning objects.
     *
     * @param string $subtypetable
     * @param boolean $includehidden
     * @param string $fields
     * @return array An array with the sql and params.
     */
    public function get_learning_objects_sql($subtypetable, $includehidden = false, $fields = null) {
        if ($fields === null) {
            // Get all the fields by default.
            $sql = \mod_adastra\local\data\learning_object::get_subtype_join_sql($subtypetable) . ' WHERE lob.categoryid = ?';
        } else {
            $sql = \mod_adastra\local\data\learning_object::get_subtype_join_sql($subtypetable, $fields) . ' WHERE lob.categoryid = ?';
        }
        $params = array($this->get_id());

        if (!$includehidden) {
            $sql .= ' AND status != ?';
            $params[] = \mod_adastra\local\data\learning_object::STATUS_HIDDEN;
        }

        return array($sql, $params);
    }

    /**
     * Return all learning objects in this category.
     *
     * @param boolean $includehidden If true, hidden learning objects are included.
     * @return \mod_adastra\local\data\learning_object[] Indexed by learning object IDs.
     */
    public function get_learning_objects($includehidden = false) {
        global $DB;

        list($chapterssql, $chparams) = $this->get_learning_objects_sql(\mod_adastra\local\data\chapter::TABLE, $includehidden);
        $chapterrecords = $DB->get_records_sql($chapterssql, $chparams);

        $learningobjects = $this->get_exercises($includehidden);

        foreach ($chapterrecords as $rec) {
            $chapter = new \mod_adastra\local\data\chapter($rec);
            $learningobjects[$chapter->get_id()] = $chapter;
        }

        return $learningobjects;
    }

    /**
     * Return all exercises in this category.
     *
     * @param boolean $includehidden If true, hidden exercises are included.
     * @return \mod_adastra\local\data\exercise[] Indexed by exercise/learning object IDs.
     */
    public function get_exercises($includehidden = false) {
        global $DB;

        list($sql, $params) = $this->get_learning_objects_sql(\mod_adastra\local\data\exercise::TABLE, $includehidden);

        $exerciserecords = $DB->get_records_sql($sql, $params);

        $exercises = array();

        foreach ($exerciserecords as $rec) {
            $ex = new \mod_adastra\local\data\exercise($rec);
            $exercises[$ex->get_id()] = $ex;
        }

        return $exercises;
    }

    /**
     * Return the count of exercises in this category.
     *
     * @param boolean $includehidden
     * @return int The count of exercises.
     */
    public function count_exercises($includehidden = false) {
        global $DB;

        list($sql, $params) = $this->get_learning_objects_sql(\mod_adastra\local\data\exercise::TABLE,
                $includehidden, 'COUNT(lob.id)');

        return $DB->count_records_sql($sql, $params);
    }

    /**
     * Return the count of learning objects in this category.
     *
     * @param boolean $includehidden
     * @return int The count of learning objects.
     */
    public function count_learning_objects($includehidden = false) {
        global $DB;

        list($chsql, $chparams) = $this->get_learning_objects_sql(\mod_adastra\local\data\chapter::TABLE,
        $includehidden, 'COUNT(lob.id)');

        return $this->count_exercises($includehidden) + $DB->count_records_sql($chsql, $chparams);
    }

    /**
     * Return all categories in a course
     *
     * @param int $courseid
     * @param boolean $includehidden If true, hidden categories are included.
     * @return array An array of mod_adastra_category objects, indexed by category IDs.
     */
    public static function get_categories_in_course($courseid, $includehidden = false) {
        global $DB;
        if ($includehidden) {
            $records = $DB->get_records(self::TABLE, array('course' => $courseid));
        } else {
            $sql = 'SELECT * FROM {' . self::TABLE . '} WHERE course = ? AND status != ?';
            $params = array($courseid, self::STATUS_HIDDEN);
            $records = $DB->get_records_sql($sql, $params);
        }

        $categories = array();
        foreach ($records as $id => $record) {
            $categories[$id] = new self($record);
        }
        return $categories;
    }

    /**
     * Create a new category in the database.
     * @param stdClass $categoryRecord object with the fields required by the database table,
     * excluding id
     * @return int ID of the new database record, zero on failure
     */
    public static function create_new(\stdClass $categoryrecord) {
        global $DB;
        return $DB->insert_record(self::TABLE, $categoryrecord);
    }

    /**
     * Update an existing category record or create a new one if it does not exist yet
     * (based on course and the name).
     *
     * @param \stdClass $newrecord Must have at least course and name fields as they are used to look
     * up the record. Course and name are not modified in an existing record.
     * @return int ID of the new/modified record.
     */
    public static function update_or_create(\stdClass $newrecord) {
        global $DB;

        $catrecord = $DB->get_record(self::TABLE, array(
                'course' => $newrecord->course,
                'name' => $newrecord->name,
        ), '*', IGNORE_MISSING);
        if ($catrecord === false) {
            // Create new.
            return $DB->insert_record(self::TABLE, $newrecord);
        } else {
            // Update.
            if (isset($newrecord->status)) {
                $catrecord->status = $newrecord->status;
            }
            if (isset($newrecord->pointstopass)) {
                $catrecord->pointstopass = $newrecord->pointstopass;
            }
            $DB->update_record(self::TABLE, $catrecord);
            return $catrecord->id;
        }
    }

    /**
     * Delete this category instance from DB.
     *
     * @return bool True.
     */
    public function delete() {
        global $DB;

        // Delete learning objects in this category.
        foreach ($this->get_learning_objects(true) as $lobject) {
            $lobject->delete_instance();
        }

        return $DB->delete_records(self::TABLE, array('id' => $this->get_id()));
    }

    /**
     * TODO
     *
     * @param boolean $includelobjectcount
     * @return \stdClass TODO
     */
    public function get_template_context($includelobjectcount = true) {
        $ctx = new \stdClass();
        $ctx->name = $this->get_name();
        $ctx->editurl = \mod_adastra\local\urls\urls::edit_category($this);
        if ($includelobjectcount) {
            $ctx->haslearningobjects = ($this->count_learning_objects() > 0);
        }
        $ctx->removeurl = \mod_adastra\local\urls\urls::delete_category($this);
        $ctx->statusready = ($this->get_status() === self::STATUS_READY);
        $ctx->statusstr = $this->get_status(true);
        $ctx->statushidden = ($this->get_status() === self::STATUS_HIDDEN);
        $ctx->statusnototal = ($this->get_status() === self::STATUS_NOTOTAL);
        return $ctx;
    }
}