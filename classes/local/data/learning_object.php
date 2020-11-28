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
 * Base class for learning objects (exercises and chapters).
 * Each learning object belongs to one exercise round
 * and one category. A learning object has a service URL that is used to connect to
 * the exercise service.
 */
abstract class learning_object extends \mod_adastra\local\data\database_object {
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
    public static function get_subtype_join_sql($subtype = \mod_adastra\local\data\exercise::TABLE,
            $fields = self::SQL_SELECT_ALL_FIELDS) {
        return sprintf(self::SQL_SUBTYPE_JOIN, $fields, $subtype);
    }

    /**
     * Create an object of a corresponding class from an existing database ID.
     *
     * @param int $id
     * @return \mod_adastra\local\data\exercise|\mod_adastra\local\data\chapter The new learning object.
     */
    public static function create_from_id($id) {
        global $DB;

        $where = ' WHERE lob.id = ?';
        $sql = self::get_subtype_join_sql(\mod_adastra\local\data\exercise::TABLE) . $where;
        $row = $DB->get_record_sql($sql, array($id), IGNORE_MISSING);
        if ($row !== false) {
            // This learning object is an exercise.
            return new \mod_adastra\local\data\exercise($row);
        } else {
            // No exercise was found, so this learning object should be a chapter.
            $sql = self::get_subtype_join_sql(\mod_adastra\local\data\chapter::TABLE) . $where;
            $row = $DB->get_record_sql($sql, array($id), MUST_EXIST);
            return new \mod_adastra\local\data\chapter($row);
        }
    }

    /**
     * Return the ID of this learning object (ID in the base table).
     *
     * @see \mod_adastra\local\data\database_object::get_id()
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
     * @return int
     */
    public function get_subtype_id() {
        // Assume that id field is from the subtype, see constant SQL_SELECT_ALL_FIELDS.
        return $this->record->id;
    }

    /**
     * Save the changes made to the learning object to DB.
     *
     * @return bool True.
     */
    public function save() {
        global $DB;
        // Must save to both base table and subtype table.
        // Subtype: $this->record->id should be the ID in the subtype table.
        $DB->update_record(static::TABLE, $this->record);

        // Must change the id value in the record for the base table.
        $record = clone $this->record;
        $record->id = $this->get_id();
        return $DB->update_record(self::TABLE, $record);
    }

    /**
     * Return the status for this learning object.
     *
     * @param boolean $asstring
     * @return string|int
     */
    public function get_status($asstring = false) {
        if ($asstring) {
            switch ((int) $this->record->status) {
                case self::STATUS_READY:
                    return get_string('statusready', \mod_adastra\local\data\exercise_round::MODNAME);
                    break;
                case self::STATUS_MAINTENANCE:
                    return get_string('statusmaintenance', \mod_adastra\local\data\exercise_round::MODNAME);
                    break;
                case self::STATUS_UNLISTED:
                    return get_string('statusunlisted', \mod_adastra\local\data\exercise_round::MODNAME);
                    break;
                default:
                    return get_string('statushidden', \mod_adastra\local\data\exercise_round::MODNAME);
            }

        }
        return (int) $this->record->status;
    }

    /**
     * Return the category of this learning object.
     *
     * @return \mod_adastra\local\data\category
     */
    public function get_category() {
        if (is_null($this->category)) {
            $this->category = \mod_adastra\local\data\category::create_from_id($this->record->categoryid);
        }
        return $this->category;
    }

    /**
     * Return the category ID for this learning object.
     *
     * @return int
     */
    public function get_category_id() {
        return $this->record->categoryid;
    }

    /**
     * Return the exercise round for this learning object.
     *
     * @return \mod_adastra\local\data\exercise_round
     */
    public function get_exercise_round() {
        if (is_null($this->exerciseround)) {
            $this->exerciseround = \mod_adastra\local\data\exercise_round::create_from_id($this->record->roundid);
        }
        return $this->exerciseround;
    }

    /**
     * Return the parent of this object. If the object does not exist, but the record holds an ID
     * for the parent, form a new parent object and return it.
     *
     * @return \mod_adastra\local\data\exercise|\mod_adastra\local\data\chapter The parent object.
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
     * Return the ID of the parent of this learning_object.
     *
     * @return int
     */
    public function get_parent_id() {
        if (empty($this->record->parentid)) {
            return null;
        }
        return (int) $this->record->parentid;
    }

    /**
     * Return an array of the learning objects that are direct children of
     * this learning object.
     *
     * @param boolean $includehidden If true, hidden learning objects are included.
     * @return \mod_adastra\local\data\learning_object[]
     */
    public function get_children($includehidden = false) {
        global $DB;

        $where = ' WHERE lob.parentid = ?';
        $orderby = ' ORDER BY ordernum ASC';
        $params = array($this->get_id());

        if ($includehidden) {
            $where .= $orderby;
        } else {
            $where .= ' AND lob.status != ?' . $orderby;
            $params[] = self::STATUS_HIDDEN;
        }
        $exsql = self::get_subtype_join_sql(\mod_adastra\local\data\exercise::TABLE) . $where;
        $chsql = self::get_subtype_join_sql(\mod_adastra\local\data\chapter::TABLE) . $where;
        $exerciserecords = $DB->get_records_sql($exsql, $params);
        $chapterrecords = $DB->get_records_sql($chsql, $params);

        // Gather learning objects into one array.
        $learningobjects = array();
        foreach ($exerciserecords as $ex) {
            $learningobjects[] = new \mod_adastra\local\data\exercise($ex);
        }
        foreach ($chapterrecords as $ch) {
            $learningobjects[] = new \mod_adastra\local\data\chapter($ch);
        }
        // Sort the combined array, compare ordernums since all objects have the same parent.
        usort($learningobjects, function($obj1, $obj2) {
            $ord1 = $obj1->get_order();
            $ord2 = $obj2->get_order();
            if ($ord1 < $ord2) {
                return -1;
            } else if ($ord1 == $ord2) {
                return 0;
            } else {
                return 1;
            }
        });

        return $learningobjects;
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
     * Return the name of the learning object.
     *
     * @param boolean $includeorder If true, the name is prepended with the content number.
     * @param null|string $lang The preferred language if the name is defined for multiple
     * languages. If null, the current language is used.
     * @param boolean $multilang If true, the name includes multiple languages and is returned
     * in the format of the Moodle multilang filter (<span lang=en class="multilang">).
     * @return string
     */
    public function get_name(
            bool $includeorder = true,
            string $lang = null,
            bool $multilang = false
    ) {
        require_once(__DIR__ . '/../../../locallib.php');

        // Number formatting based on A+ (a-plus/exercise/exercise_models.py).
        $number = '';
        if ($includeorder && $this->get_order() >= 0) {
            $conf = $this->get_exercise_round()->get_course_config();
            if ($conf !== null) {
                $contentnumbering = $conf->get_content_numbering();
                $modulenumbering = $conf->get_module_numbering();
            } else {
                $contentnumbering = \mod_adastra\local\data\course_config::get_default_content_numbering();
                $modulenumbering = \mod_adastra\local\data\course_config::get_default_module_numbering();
            }

            if ($contentnumbering == \mod_adastra\local\data\course_config::CONTENT_NUMBERING_ARABIC) {
                $number = $this->get_number();
                if (
                        $modulenumbering == \mod_adastra\local\data\course_config::MODULE_NUMBERING_ARABIC ||
                        $modulenumbering == \mod_adastra\local\data\course_config::MODULE_NUMBERING_HIDDEN_ARABIC
                ) {
                    $number = $this->get_exercise_round()->get_order() . $number . ' ';
                } else {
                    // Leave out the module number ($number starts with a dot).
                    $number = substr($number, 1) . ' ';
                }
            } else if ($contentnumbering == \mod_adastra\local\data\course_config::CONTENT_NUMBERING_ROMAN) {
                $number = adastra_roman_numeral($this->get_order()) . ' ';
            }
        }

        $name = adastra_parse_localization($this->record->name, $land, $multilang);
        if (is_array($name)) {
            if (count($name) > 1) {
                // Multilang with spans.
                $spans = array();
                foreach ($name as $langcode => $val) {
                    $spans[] = "<span lang=\"{$langcode}\" class=\"multilang\">{$val}</span>";
                }
                return $number . implode(' ', $spans);
            } else {
                $name = reset($name);
            }
        }
        return $number . $name;
    }

    /**
     * Return true if this object uses a wide column.
     *
     * @return boolean
     */
    public function get_use_wide_column() {
        return (bool) $this->record->usewidecolumn;
    }

    /**
     * Return true if this object is empty.
     *
     * @return boolean
     */
    public function is_empty() {
        return empty($this->record->serviceurl);
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
     * Set the status of the learning object as $status.
     *
     * @param int $status
     * @return void
     */
    public function set_status($status) {
        $this->record->status = $status;
    }

    /**
     * Set the order number of the learning object as $neworder.
     *
     * @param int $neworder
     * @return void
     */
    public function set_order($neworder) {
        $this->record->ordernum = $neworder;
    }

    /**
     * Set a new parent learning object to this learning object.
     *
     * @param int|null $newparentid Null or lobjectid of another learning object.
     * @return void
     */
    public function setparent($newparentid) {
        $this->record->parentid = $newparentid;
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

    /**
     * Delete this learning object from the database. Possible child learning objects
     * are also deleted.
     *
     * @return bool True.
     */
    public function delete_instance() {
        global $DB;

        foreach ($this->get_children(true) as $child) {
            $child->delete_instance();
        }

        // Delete this object, subtable and base table.
        $DB->delete_records(static::TABLE, array('id' => $this->get_subtype_id()));
        return $DB->delete_records(self::TABLE, array('id' => $this->get_id()));
    }

    /**
     * Return context of one of the siblings of the learning object for templating.
     *
     * @param boolean $next If true return the context of the next sibling, otherwise the previous sibling.
     * @return \stdClass
     */
    protected function get_sibling_context($next = true) {
        global $DB;

        $context = \context_module::instance($this->get_exercise_round()->get_course_module()->id);
        $isteacher = has_capability('/moodle/course:manageactivities', $context);
        $isassistant = has_capability('mod/adastra:viewallsubmissions', $context);

        $order = $this->get_order();
        $parentid = $this->get_parent_id();
        $params = array(
            'roundid' => $this->record->roundid,
            'ordernum' => $order,
            'parentid' => $parentid,
        );
        $where = 'roundid = :roundid';
        $where .= ' AND ordernum ' . ($next ? '>' : '<') . ' :ordernum';
        // Skip some uncommon details in the hierarchy of the round content and assume that
        // siblings are in the same level (they have the same parent).
        if ($parentid === null) {
            $where .= ' AND parentid IS NULL';
        } else {
            $where .= ' AND parentid = :parentid';
        }
        if ($isassistant && !$isteacher) {
            // Assistants do not see hidden objects.
            $where .= ' AND status <> :status';
            $params['status'] = self::STATUS_HIDDEN;
        } else if (!$isteacher) {
            // Students see objects that are normally enabled.
            $where .= ' AND status = :status';
            $params['status'] = self::STATUS_READY;
        }
        $sort = 'ordernum ' . ($next ? 'ASC' : 'DESC');

        $results = $DB->get_records_select(self::TABLE, $where, $params, $sort, '*', 0, 1);

        if (!empty($results)) {
            // The next object is inn the same round.
            $record = reset($results);
            $record->lobjectid = $record->id;
            // Hack: the record does not contain the data of the learning object subtype since the DB query did not join the tables.
            unset($record->id);
            // Use the chapter class here since this abstract learning object class may not be instantiated.
            // The subtype of the learning object is not needed here.
            $sibling = new \mod_adastra\local\data\chapter($record);
            $ctx = new \stdClass();
            $ctx->name = $sibling->get_name();
            $ctx->link = \mod_adastra\local\urls\urls::exercise($sibling);
            $ctx->accessible = $this->get_exercise_round()->has_started();
            return $ctx;
        } else {
            // The sibling is the next/previous round.
            if ($next) {
                return $this->get_exercise_round()->get_next_sibling_context();
            } else {
                return $this->get_exercise_round()->get_previous_sibling_context();
            }
        }
    }

    /**
     * Return the context of the next sibling.
     *
     * @return null|\stdClass The context.
     */
    public function get_next_sibling_context() {
        return $this->get_sibling_context(true);
    }

    /**
     * Return the context of the previous sibling.
     *
     * @return null|\stdClass The context.
     */
    public function get_previous_sibling_context() {
        return $this->get_sibling_context(false);
    }

    /**
     * Return the context used for templating for this learning object.
     *
     * @param boolean $includecoursemodule
     * @param boolean $includesiblings
     * @return \stdClass The context.
     */
    public function get_template_context($includecoursemodule = true, $includesiblings = false) {
        $ctx = new \stdClass();
        $ctx->url = \mod_adastra\local\urls\urls::exercise($this);
        $parent = $this->get_parent_object();
        if ($parent === null) {
            $ctx->parenturl = null;
        } else {
            $ctx->parenturl = \mod_adastra\local\urls\urls::exercise($parent);
        }
        $ctx->displayurl = \mod_adastra\local\urls\urls::exercise($this, false, false);
        $ctx->name = $this->get_name();
        $ctx->usewidecolumn = $this->get_use_wide_column();
        $ctx->editurl = \mod_adastra\local\urls\urls::edit_exercise($this);
        $ctx->removeurl = \mod_adastra\local\urls\urls::delete_exercise($this);

        if ($includecoursemodule) {
            $ctx->coursemodule = $this->get_exercise_round()->get_template_context();
        }
        $ctx->statusready = ($this->get_status() === self::STATUS_READY);
        $ctx->statusstr = $this->get_status(true);
        $ctx->statusunlisted = ($this->get_status() === self::STATUS_UNLISTED);
        $ctx->statusmaintenance = (
            $this->get_status() === self::STATUS_MAINTENANCE ||
            $this->get_exercise_round()->get_status() === \mod_adastra\local\data\exercise_round::STATUS_MAINTENANCE
        );
        $ctx->issubmittable = $this->is_submittable();

        $ctx->category = $this->get_category()->get_template_context(false);

        if ($includesiblings) {
            $ctx->next = $this->get_next_sibling_context();
            $ctx->previous = $this->get_previous_sibling_context();
        }

        return $ctx;
    }
}