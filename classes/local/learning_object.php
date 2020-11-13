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

/**
 * Base class for learning objects (exercises and chapters).
 * Each learning object belongs to one exercise round
 * and one category. A learning object has a service URL that is used to connect to
 * the exercise service.
 */
abstract class learning_object extends \mod_adastra\local\database_object {
    const TABLE = 'adastra_lobjects'; // Database table name.
    const STATUS_READY       = 0;
    const STATUS_HIDDEN      = 1;
    const STATUS_MAINTENANCE = 2;
    const STATUS_UNLISTED    = 3;

    // SQL fragment for joining a learning object base class table with a subtype table.
    // The self::TABLE constant cannot be used in this definition since old PHP versions
    // only support literal constants.
    // Usage of this constant: sprintf(mod_astra_learning_object::SQL_SUBTYPE_JOIN, fields, SUBTYPE_TABLE).
    const SQL_SUBTYPE_JOIN = 'SELECT %s FROM {astra_lobjects} lob JOIN {%s} ex ON lob.id = ex.lobjectid';
    // SQL fragment for selecting all fields in the subtype join query: this avoids the conflict of
    // id columns in both the base table and the subtype table. Id is taken from the subtype and
    // the subtype table should have a column lobjectid which is the id in the base table.
    const SQL_SELECT_ALL_FIELDS = 'ex.*,lob.status,lob.categoryid,lob.roundid,lob.parentid,' .
            'lob.ordernum,lob.remotekey,lob.name,lob.serviceurl,lob.usewidecolumn';

    // References to other records, used in corresponding getter methods.
    protected $category = null;
    protected $exerciseround = null;
    protected $parentobject = null;

    /**
     * Get the sql string for making a join query from learning_objects and a chosen subtypetable.
     *
     * @param string $subtype One of the subtype constants should be used.
     * @param string $fields The fields to be taken from the query.
     * @return string The sql query.
     */
    public static function get_subtype_join_sql($subtype = \mod_adastra\local\exercise::TABLE,
            $fields = self::SQL_SELECT_ALL_FIELDS) {
        return sprintf(self::SQL_SUBTYPE_JOIN, $fields, $subtype);
    }

    /**
     * Create an object of a corresponding class from an existing database ID.
     *
     * @param int $id
     * @return \mod_adastra\local\exercise|\mod_adastra\local\chapter The new learning object.
     */
    public static function create_from_id($id) {
        global $DB;

        $where = ' WHERE lob.id = ?';
        $sql = self::get_subtype_join_sql(\mod_adastra\local\exercise::TABLE) . $where;
        $row = $DB->get_record_sql($sql, array($id), IGNORE_MISSING);
        if ($row !== false) {
            // This learning object is an exercise.
            return new \mod_adastra\local\exercise($row);
        } else {
            // No exercise was found, so this learning object should be a chapter.
            $sql = self::get_subtype_join_sql(\mod_adastra\local\chapter::TABLE) . $where;
            $row = $DB->get_record_sql($sql, array($id), MUST_EXIST);
            return new \mod_adastra\local\chapter($row);
        }
    }

    /**
     * Return the ID of this learning object (ID in the base table).
     *
     * @see \mod_adastra\local\database_object::get_id()
     * @return int
     */
    public function get_id() {
        // Assume that id field is from the subtype, see constant SQL_SELECT_ALL_FIELDS.
        return $this->record->lobjectid;
    }

    /**
     * Return ID of this learning object in its subtype table
     * (different to the ID in the base table).
     *
     * @return void
     */
    public function get_subtype_id() {
        // Assume that id field is from the subtype, see constant SQL_SELECT_ALL_FIELDS.
        return $this->record->id;
    }

    /**
     * Return the parent of this object. If the object does not exist, but the record holds an ID
     * for the parent, form a new parent object and return it.
     *
     * @return \mod_adastra\local\exercise|\mod_adastra\local\chapter The parent object.
     */
    public function get_parent_object() {
        if (empty($this->record->parentid)) {
            return null;
        }
        if (is_null($this->parentobject)) {
            $this->parentobject = self::create_from_id($this->record->parentid);
        }
        return $this->parentobject;
    }

    /**
     * Return the order number for this object.
     *
     * @return void
     */
    public function get_order() {
        return (int) $this->record->ordernum;
    }

    /**
     * Return the remotekey used by this object.
     *
     * @return void
     */
    public function get_remote_key() {
        return $this->record->remotekey;
    }

    /**
     * Return the order numbers recursively, concatenated with dots from parent to the current one.
     *
     * @return void
     */
    public function get_number() {
        $parent = $this->get_parent_object();
        if ($parent !== null) {
            return $parent->get_number() . ".{$this->record->ordernum}";
        }
        return ".{$this->record->ordernum}";
    }

    /**
     * Return true if the object is hidden.
     *
     * @return boolean
     */
    public function is_hidden() {
        return $this->get_status() === self::STATUS_HIDDEN;
    }

    /**
     * Return true if the object is unlisted.
     *
     * @return boolean
     */
    public function is_unlisted() {
        return $this->get_status() === self::STATUS_UNLISTED;
    }

    /**
     * Return true if the object is under maintenance.
     *
     * @return boolean
     */
    public function is_under_maintenance() {
        return $this->get_status() === self::STATUS_MAINTENANCE;
    }

    /**
     * Chekc if learning_object is submittable.
     * This function will be overriden in inherited classes if necessary.
     *
     * @return boolean False is the default.
     */
    public function is_submittable() {
        return false;
    }

    public function get_template_context($includecoursemodule = true, $includesiblings = false) {
        // TODO
    }
}