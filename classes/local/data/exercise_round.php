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

class exercise_round extends \mod_adastra\local\data\database_object {
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
        $this->cm = $this->find_course_module();
    }

    /**
     * Find the Moodle course module corresponding to this Ad Astra activity instance.
     *
     * @return cm_info|null The Moodle course module. Null if it does not exist.
     */
    protected function find_course_module() {
        // The Moodle course module may not exist yet if the exercise round is being created.
        if (isset($this->get_course()->instances[self::TABLE][$this->record->id])) {
            return $this->get_course()->instances[self::TABLE][$this->record->id];
        } else {
            return null;
        }
    }

    /**
     * Return the Moodle course module corresponding to this Ad Astra activity instance.
     *
     * @return cm_info|null The Moodle course module. Null if it does not exist.
     */
    public function get_course_module() {
        if (is_null($this->cm)) {
            $this->cm = $this->find_course_module();
        }
        return $this->cm;
    }

    /**
     * Return a Moodle course_modinfo object corresponding to this Ad Astra activity instance.
     *
     * @return course_modinfo The course_modinfo object.
     */
    public function get_course() {
        return get_fast_modinfo($this->record->course);
    }

    /**
     * Return the (Ad Astra) course configuration object of the course.
     * May return null if there is no configuration.
     *
     * @return null|\mod_adastra\local\data\course_config
     */
    public function get_course_config() {
        if (is_null($this->courseconfig)) {
            $this->courseconfig = \mod_adastra\local\data\course_config::get_for_course_id($this->record->course);
        }
        return $this->courseconfig;
    }

    /**
     * Return the name of this activity instance.
     *
     * @param string $lang
     * @param boolean $includealllang
     * @return string The name.
     */
    public function get_name(string $lang = null, bool $includealllang = false) {
        require_once(__DIR__.'/../../../locallib.php');
        if ($includealllang) {
            // Do not parse multilang values.
            return $this->record->name;
        }

        return adastra_parse_multilang_filter_localization($this->record->name, $lang);
    }

    /**
     * Get the intro of this activity instance.
     *
     * @param boolean $format
     * @return void
     */
    public function get_intro($format = false) {
        if ($format) {
            // Use Moodle filters for safe HTML output or other intro format types.
            return format_module_intro(self::TABLE, $this->record, $this->get_course_module()->id);
        }
        return $this->record->intro;
    }

    /**
     * Get the status of this activity instance.
     *
     * @param boolean $asstring
     * @return void
     */
    public function get_status($asstring = false) {
        if ($asstring) {
            switch ((int) $this->record->status) {
                case self::STATUS_READY:
                    return get_string('statusready', self::MODNAME);
                    break;
                case self::STATUS_MAINTENANCE:
                    return get_string('statusmaintenance', self::MODNAME);
                    break;
                case self::STATUS_UNLISTED:
                    return get_string('statusunlisted', self::MODNAME);
                    break;
                default:
                    return get_string('statushidden', self::MODNAME);
            }
        }
        return (int) $this->record->status;
    }

    /**
     * Get the maximum points for this activity.
     *
     * @return int
     */
    public function get_max_points() {
        return $this->record->grade;
    }

    /**
     * Get the unique module key used in the exercise service center.
     *
     * @return string
     */
    public function get_remote_key() {
        return $this->record->remotekey;
    }

    /**
     * Get the order number of this activity. Used for listing the exercise rounds,
     * smaller comes first.
     *
     * @return int
     */
    public function get_order() {
        return $this->record->ordernum;
    }

    /**
     * Get the number of points needed to pass the exercise round.
     *
     * @return int
     */
    public function get_points_to_pass() {
        return $this->record->pointstopass;
    }

    /**
     * Get the opening time of the exercise round.
     *
     * @return int  A Unix timestamp.
     */
    public function get_opening_time() {
        return $this->record->openingtime;
    }

    /**
     * Get the closing time of the exercise round
     *
     * @return int A Unix timestamp.
     */
    public function get_closing_time() {
        return $this->record->closingtime;
    }

    /**
     * Return true if late submission is allowed.
     *
     * @return boolean
     */
    public function is_late_submission_allowed() {
        return (bool) $this->record->latesbmsallowed;
    }

    /**
     * Get the deadline for late submissions
     *
     * @return int A Unix timestamp.
     */
    public function get_late_submission_deadline() {
        return $this->record->latesbmsdl;
    }

    /**
     * Get the penalty for late submissions. Values are between 0 and 1, as a multiplier
     * of points to reduce.
     *
     * @return float
     */
    public function get_late_submission_penalty() {
        return $this->record->latesbmspenalty;
    }

    /**
     * Return the percentage (between 0 and 100) that late submission points are worth.
     *
     * @return int Percentage.
     */
    public function get_late_submission_point_worth() {
        $pointworth = 0;
        if ($this->is_late_submission_allowed()) {
            $pointworth = (int) ((1.0 - $this->get_late_submission_penalty()) * 100.0);
        }
        return $pointworth;
    }

    /**
     * Return true if this exercise round has closed (not open adn the closing time has passed).
     *
     * @param int|null $when Time to check, null for current time.
     * @param boolean $checklatedeadline If true and late submissions are allowed, check if the late
     * deadline has passed instead of the normal closing time.
     * @return boolean
     */
    public function has_expired($when = null, bool $checklatedeadline = false) {
        if (is_null($when)) {
            $when = time();
        }
        if ($checklatedeadline && $this->is_late_submission_allowed()) {
            return $when > $this->get_late_submission_deadline();
        }
        return $when > $this->get_closing_time();
    }

    /**
     * Return true if the exercise round is open for submissions.
     *
     * @param int|null $when Time to check, null for current time.
     * @return boolean True if is open, false if not.
     */
    public function is_open($when = null) {
        if (is_null($when)) {
            $when = time();
        }
        return $this->get_opening_time() <= $when && $when <= $this->get_closing_time();
    }

    /**
     * Return true if the exercise round is in the late submission period.
     *
     * @param int|null $when Time to check, null for current time.
     * @return boolean True if yes, false if no.
     */
    public function is_late_submission_open($when = null) {
        if (is_null($when)) {
            $when = time();
        }
        return $this->is_late_submission_allowed() &&
            $this->get_closing_time() <= $when && $when <= $this->get_late_submission_deadline();
    }

    /**
     * Return true if this exercise round has opened at or before timestamp $when.
     *
     * @param int|null $when Time to check, null for current time.
     * @return boolean
     */
    public function has_started($when = null) {
        if (is_null($when)) {
            $when = time();
        }
        return $when >= $this->get_opening_time();
    }

    /**
     * Return true if tis exercise round has status "hidden".
     *
     * @return boolean
     */
    public function is_hidden() {
        return $this->get_status() === self::STATUS_HIDDEN;
    }

    /**
     * Return true if this exercise round has status "under maintenance".
     *
     * @return boolean
     */
    public function is_under_maintenance() {
        return $this->get_status() === self::STATUS_MAINTENANCE;
    }

    /**
     * Set the order number for this exercise round.
     *
     * @param int $order
     */
    public function set_order($order) {
        $this->record->ordernum = $order;
    }

    /**
     * Set the name for this exercise round.
     *
     * @param string $name
     */
    public function set_name($name) {
        $this->record->name = $name;
    }

    /**
     * Return an array of the learning objects in this round (as
     * \mod_adastra\local\data\learning_object instances).
     *
     * @param boolean $includehidden If true, hidden learning objects are included.
     * @param boolean $sort If true, the result array is sorted.
     * @return (sorted) array of \mod_adastra\local\data\learning_object instances
     */
    public function get_learning_objects($includehidden = false, $sort = true) {
        global $DB;

        $where = ' WHERE lob.roundid = ?';
        $params = array($this->get_id());

        if (!$includehidden) {
            $nothiddencats = \mod_adastra\local\data\category::get_categories_in_course($this->get_course()->courseid, false);
            $nothiddencatids = array_keys($nothiddencats);

            $where .= ' AND status != ? AND categoryid IN (' . implode(',', $nothiddencatids) . ')';
            $params[] = \mod_adastra\local\data\learning_object::STATUS_HIDDEN;
        }

        $exerciserecords = array();
        $chapterrecords = array();
        if ($includehidden || !empty($nothiddencatids)) {
            $exerciserecords = $DB->get_records_sql(
                \mod_adastra\local\data\learning_object::get_subtype_join_sql(\mod_adastra\local\data\exercise::TABLE) . $where,
                $params
            );
            $chapterrecords = $DB->get_records_sql(
                \mod_adastra\local\data\learning_object::get_subtype_join_sql(\mod_adastra\local\data\chapter::TABLE) . $where,
                $params
            );
        }
        // Gather all the learning objects of the round in a single array.
        $learningobjects = array();
        foreach ($exerciserecords as $ex) {
            $learningobjects[] = new \mod_adastra\local\data\exercise($ex);
        }
        foreach ($chapterrecords as $ch) {
            $learningobjects[] = new \mod_adastra\local\data\chapter($ch);
        }
        /*
         * Sort again since some learning objects may have parent objects, and combining
         * chapters and exercises messes up the order from the database. Output array
         * should be in the order that is used to print the exercises under the round.
         * Sorting and flattening the exercise tree is derived from A+ (a-plus/course/tree.py).
         */
        if ($sort) {
            return self::sort_round_learning_objects($learningobjects);
        } else {
            return $learningobjects; // No sorting.
        }
    }

    /**
     * Sort learning objects present in the exercise round.
     *
     * @param array $learningobjects
     * @param int $startparentid
     * @return array The learning objects sorted.
     */
    public static function sort_round_learning_objects(array $learningobjects, $startparentid = null) {
        $ordersortcallback = function ($obj1, $obj2) {
            $ord1 = $obj1->get_order();
            $ord2 = $obj2->get_order();
            if ($ord1 < $ord2) {
                return -1;
            } else if ($ord1 == $ord2) {
                return 0;
            } else {
                return 1;
            }
        };

        // Paremeter $parentid may be null to get top-level learning objects.
        $children = function($parentid) use ($learningobjects, &$ordersortcallback) {
            $childobjs = array();
            foreach ($learningobjects as $obj) {
                if ($obj->get_parent_id() == $parentid) {
                    $childobjs[] = $obj;
                }
            }
            // Sort children by ordernum, they all have the same parent.
            usort($childobjs, $ordersortcallback);
            return $childobjs;
        };

        $traverse = function($parentid) use (&$children, &$traverse) {
            $container = array();
            foreach ($children($parentid) as $child) {
                $container[] = $child;
                $container = array_merge($container, $traverse($child->get_id()));
            }
            return $container;
        };
        return $traverse(null);
    }

    /**
     * Return an array of the exercises in this round.
     *
     * @param boolean $includehidden If true, hidden exercises are included.
     * @param boolean $sort If true, the result array is sorted.
     * @return \mod_adastra\local\data\exercise[]
     */
    public function get_exercises($includehidden = false, $sort = true) {
        // Function array_filter keeps the old indexes/keys, so a numerically indexed array may
        // have discontinuous indexes.
        return array_filter($this->get_learning_objects($includehidden, $sort), function($lobj) {
            return $lobj->is_submittable();
        });
    }

    /**
     * Hide or delete learning objects in this round if they are not included in the giben array.
     * The object is deleted if it and its children have no submissions. Otherwise, it is hidden.
     *
     * @param array $seen An array of \mod_adastra\local\data\learning_object instances that have been seen.
     * @return boolean True if something was hidden or deleted, false otherwise.
     */
    public function hide_or_delete_unseen_learning_objects(array $seen) : bool {
        $children = array();
        $unseen = array();
        foreach ($this->get_learning_objects(true, false) as $lobj) {
            if (!in_array($lobj->get_id(), $seen)) {
                $unseen[] = $lobj;
            }

            // Array for easily finding the children of a learning object
            // without additional DB queries.
            $parentid = $lobj->get_parent_id();
            if ($parentid !== null) {
                if (!isset($children[$parentid])) {
                    $children[$parentid] = array();
                }
                $children[$parentid][] = $lobj;
            }
        }

        $anychildhassubmissions = function($learningobject) use ($children, &$anychildhassubmissions) {
            if (isset($children[$learningobject->get_id()])) {
                // Object $learningobject has children.
                foreach ($children[$learningobject->get_id()] as $child) {
                    if ($child->is_submittable() && $child->get_total_submitter_count() > 0) {
                        return true;
                    }
                    $res = $anychildhassubmissions($child);
                    if ($res) {
                        return true;
                    }
                }
            }
            return false;
        };

        $descendants = function($learningobject) use ($children, &$descendants) {
            $res = array();
            if (isset($children[$learningobject->get_id()])) {
                foreach ($children[$learningobject->get_id()] as $child) {
                    $res[] = $child->get_id();
                    $res = array_merge($res, $descendants($child));
                }
            }
            return $res;
        };

        $deleted = array();
        $changesmade = !empty($unseen);
        foreach ($unseen as $lobj) {
            if (in_array($lobj->get_id(), $deleted)) {
                continue;
            }
            $nodirectsubmissions = !$lobj->is_submittable() ||
                    $lobj->is_submittable() && $lobj->get_total_submitter_count() == 0;
            if ($nodirectsubmissions && !$anychildhassubmissions($lobj)) {
                // No submissions, delete.
                $lobj->delete_instance(false); // Deletes children too.
                $deleted[] = $lobj->get_id();
                $deleted = array_merge($deleted, $descendants($lobj));
            } else {
                $lobj->set_status(\mod_adastra\local\data\learning_object::STATUS_HIDDEN);
                $lobj->save();
            }
        }
        return $changesmade;
    }

    /**
     * Create or update the Moodle gradebook item for this exercise round.
     * In order to add grades for students, use the method update_grades.
     * This method does not create or update the grade items for the exercises
     * of the round.
     *
     * @param boolean $reset If true, delete all grades in the grade item.
     * @return int One of the grade_update return values: GRADE_UPDATE_OK, GRADE_UPDATE_FAILED,
     * GRADE_UPDATE_MULTIPLE or GRADE_UPDATE_ITEM_LOCKED.
     */
    public function update_gradebook_item($reset = false) {
        global $CFG, $DB;
        require_once($CFG->libdir . '/gradelib.php');
        require_once($CFG->libdir . '/grade/grade_item.php');

        $item = array();
        $item['itemname'] = $this->get_name(null, true);
        $item['hidden'] = (int) $this->is_hidden();
        // The hidden value must be zero or one. Integers above one are interpreted as timestamps (hidded until).

        // Update activity grading information ($item).
        if ($this->get_max_points() > 0) {
            $item['gradetype'] = GRADE_TYPE_VALUE; // Points.
            $item['grademax'] = $this->get_max_points();
            $item['grademin'] = 0; // Minimum allowed value (points cannot be below this).
            // Looks like minimum grade to pass (gradepass) cannnot be set in this API directly.
        } else {
            // Moodle core does not accept zero max points.
            $item['gradetype'] = GRADE_TYPE_NONE;
        }

        if ($reset) {
            $item['reset'] = true;
        }

        // Set course gradebook total grade aggregation method to "natural" (sum), because it is the only
        // one that allows setting the exercise round coefficients to zero.
        $this->set_gradebook_total_aggregation();

        // Create gradebook item.
        $res = grade_update(
                'mod/' . self::TABLE,
                $this->record->course,
                'mod',
                self::TABLE,
                $this->record->id,
                0,
                null,
                $item
        );

        if ($this->get_max_points() > 0 && $res === GRADE_UPDATE_OK) {
            // Paremeters to find the grade item from DB.
            $gradeparams = array(
                'itemtype' => 'mod',
                'itemmodule' => self::TABLE,
                'iteminstance' => $this->record->id,
                'itemnumber' => 0,
                'courseid' => $this->record->course,
            );
            $gi = \grade_item::fetch($gradeparams);
            if (
                    $gi &&
                    ($gi->gradepass != $this->get_points_to_pass() || $gi->aggregationcoef2 != 0.0 || $gi->weightoverride != 1)
            ) {
                // Set min points to pass.
                $gi->gradepass = $this->get_points_to_pass();
                // Set zero coefficient so that the course total is not affected by rounds.
                // Round grades are only sums of the exercise grades in the round.
                $gi->aggregationcoef2 = 0.0;
                $gi->weightoverride = 1;
                $gi->update('mod/' . self::TABLE);
            }

            /*
             * If some students already have grades for the round in the gradebook,
             * the changed coefficient may not be taken into account unless the course
             * final grades are computed again. The API function grade_regrade_final_grades($this->record->course)
             * is very heavy and should not be called here since the call must not be
             * repeated for each round when automatically updating course configuration
             * (auto setup). For now, we assume that the grade_regrade_final_grades
             * call is not needed since nobody has grades when the grade item is
             * initially created and its coefficient is then set to zero.
             */
        }

        return $res;
    }

    /**
     * Update the max points of this exercise round (based on the max points of exercises).
     * Updates the database and the gradebook item.
     *
     * @return boolean True.
     */
    public function update_max_points() {
        global $DB;

        $this->record->timemodified = time();
        $max = 0;
        foreach ($this->get_exercises(false, false) as $lobj) {
            // Chapters have no grading, so ignore them.
            // Only non-hidden exercises, but must check categories too.
            if (!$lobj->get_category()->is_hidden()) {
                $max += $lobj->get_max_points();
            }
        }
        $this->record->grade = $max;
        $result = $DB->update_record(self::TABLE, $this->record);
        $this->update_gradebook_item();

        return $result;
    }

    /**
     * Set the course gradebook total grade aggregation method to "natural" (sum).
     *
     * @return void
     */
    public function set_gradebook_total_aggregation() {
        global $CFG, $DB;
        require_once($CFG->libdir . '/grade/constants.php');
        require_once($CFG->libdir . '/grade/grade_category.php');

        $gradecategory = \grade_category::fetch(array(
                'courseid' => $this->record->course,
                'parent' => null,
        ));
        /*
         * The default course total category is automatically created by Moodle.
         * If the teacher creates additional gradebook categories they become
         * children to the root category.
         */
        if (
                $gradecategory &&
                ($gradecategory->aggregation != GRADE_AGGREGATE_SUM || $gradecategory->aggregateonlygraded != 0)
        ) {
            // Set course gradebook total grade aggregation method to "natural" i.e. sum.
            $gradecategory->aggregation = GRADE_CATEGORY_SUM;
            // Include ungraded assignments in the aggregation. Course total does not show
            // 100 % when only one exercise has been submitted with correct solution.
            $gradecategory->aggregateonlygraded = 0;
            $gradecategory->update('mod/' . self::TABLE);

            $gradeitem = $gradecategory->load_grade_item();
            $gradeitem->update('mod/' . self::TABLE);
        }

    }

    /**
     * Get the exercise rounds present in the course.
     *
     * @param int $courseid
     * @param boolean $includehidden
     * @return array An array of exercise rounds.
     */
    public static function get_exercise_rounds_in_course($courseid, $includehidden = false) {
        global $DB;
        $sort = 'ordernum ASC, openingtime ASC, closingtime ASC, id ASC';
        if ($includehidden) {
            $records = $DB->get_records(self::TABLE, array('course' => $courseid), $sort);
        } else {
            // Exclude hidden rounds.
            $temprecords = $DB->get_records_select(self::TABLE, 'course = ? AND status != ?',
                array($courseid, self::STATUS_HIDDEN), $sort);
            // Check course_module visibility too, since the status may be ready, but the
            // course_module might not be visible.
            $records = array();
            $coursemodules = get_fast_modinfo($courseid)->instances[self::TABLE] ?? array();
            foreach ($temprecords as $id => $rec) {
                if (isset($coursemodules[$id]) && $coursemodules[$id]->visible) {
                    $records[$id] = $rec;
                }
            }
        }
        $rounds = array();
        foreach ($records as $record) {
            $rounds[] = new self($record);
        }
        return $rounds;
    }

    /**
     * Return the context of the next or previous sibling.
     *
     * @param boolean $next Next if true, previous if false.
     * @return null|\stdClass The context.
     */
    protected function get_sibling_context($next = true) {
        // If $next true, get the next sibling; if false, get the previous sibling.
        global $DB;

        $context = \context_course::instance($this->record->course);
        $isteacher = has_capability('moodle/course:manageactivities', $context);
        $isassistant = has_capability('mod/adastra:viewallsubmissions', $context);

        $where = 'course = :course';
        $where .= ' AND ordernum ' . ($next ? '>' : '<') .' :ordernum';
        $params = array(
            'course' => $this->record->course,
            'ordernum' => $this->get_order(),
        );
        if ($isassistant && !$isteacher) {
            // Assistants do not see hidden rounds.
            $where .= ' AND status <> :status';
            $params['status'] = self::STATUS_HIDDEN;
        } else if (!$isteacher) {
            // Students see normally enabled rounds.
            $where .= ' AND status = :status';
            $params['status'] = self::STATUS_READY;
        }
        // Order the DB query so that the first record is the next/previous sibling.
        $results = $DB->get_records_select(self::TABLE, $where, $params,
                'ordernum '. ($next ? 'ASC' : 'DESC'),
                '*', 0, 1);
        $ctx = null;
        if (!empty($results)) {
            $sibling = new self(reset($results));
            $ctx = new \stdClass();
            $ctx->name = $sibling->get_name();
            $ctx->link = \mod_adastra\local\urls\urls::exercise_round($sibling);
            $ctx->accessible = $sibling->has_started();
        }
        return $ctx;
    }

    /**
     * Return the context for the next sibling.
     *
     * @see \mod_adastra\local\data\exercise_round::get_sibling_context()
     * @return \stdClass|null
     */
    public function get_next_sibling_context() {
        return $this->get_sibling_context(true);
    }

    /**
     * Return the context for the previous sibling.
     *
     * @see \mod_adastra\local\data\exercise_round::get_sibling_context()
     * @return \stdClass|null
     */
    public function get_previous_sibling_context() {
        return $this->get_sibling_context(false);
    }

    /**
     * Return the context for templating.
     *
     * @param boolean $includesiblings
     * @return \stdClass Context object.
     */
    public function get_template_context($includesiblings = false) {
        $ctx = new \stdClass();
        $ctx->id = $this->get_id();
        $ctx->openingtime = $this->get_opening_time();
        $ctx->closingtime = $this->get_closing_time();
        $ctx->name = $this->get_name();
        $ctx->latesubmissionsallowed = $this->is_late_submission_allowed();
        $ctx->latesubmissiondeadline = $this->get_late_submission_deadline();
        $ctx->latesubmissionpointworth = $this->get_late_submission_point_worth();
        $ctx->islatesubmissionopen = $this->is_late_submission_open();
        $ctx->showlatesubmissionpointworth = ($ctx->latesubmissionpointworth < 100);
        $ctx->latesubmissionpenalty = (int) ($this->get_late_submission_penalty() * 100); // Percent.
        $ctx->statusready = ($this->get_status() === self::STATUS_READY);
        // Property showlobjectpoints: true if the exercise round point progress panel
        // should display the exercise points for each exercise.
        $ctx->showlobjectpoints = ($this->get_status() === self::STATUS_READY || $this->get_status() === self::STATUS_UNLISTED);
        $ctx->statusmaintenance = ($this->get_status() === self::STATUS_MAINTENANCE);
        $ctx->introduction = \format_module_intro(self::TABLE, $this->record, $this->get_course_module()->id);
        $ctx->showrequiredpoints = ($ctx->statusready && $this->get_points_to_pass() > 0);
        $ctx->pointstopass = $this->get_points_to_pass();
        $ctx->expired = $this->has_expired();
        $ctx->open = $this->is_open();
        $ctx->hasstarted = $this->has_started();
        $ctx->notstarted = !$ctx->hasstarted;
        $ctx->statusstr = $this->get_status(true);
        $ctx->editurl = \mod_adastra\local\urls\urls::edit_exercise_round($this);
        $ctx->removeurl = \mod_adastra\local\urls\urls::delete_exercise_round($this);
        $ctx->url = \mod_adastra\local\urls\urls::exercise_round($this);
        $ctx->addnewexerciseurl = \mod_adastra\local\urls\urls::create_exercise($this);
        $ctx->addnewchapterurl = \mod_adastra\local\urls\urls::create_chapter($this);
        $context = \context_module::instance($this->get_course_module()->id);
        $ctx->iscoursestaff = \has_capability('mod/adastra:viewallsubmissions', $context);

        if ($includesiblings) {
            $ctx->next = $this->get_next_sibling_context();
            $ctx->previous = $this->get_previous_sibling_context();
        }

        return $ctx;
    }

    /**
     * Include exercises in the context for templating.
     *
     * @param boolean $includehidden
     * @return \stdClass Context object.
     */
    public function get_template_context_with_exercises($includehidden = false) {
        $ctx = $this->get_template_context();
        $ctx->allexercises = array();
        foreach ($this->get_learning_objects($includehidden) as $ex) {
            if ($ex->is_submittable()) {
                $ctx->allexercises[] = $ex->get_exercise_template_context(null, false, false);
            } else {
                $ctx->allexercises[] = $ex->get_template_context(false);
            }
        }
        $ctx->hasexercises = !empty($ctx->allexercises);
        return $ctx;
    }
}