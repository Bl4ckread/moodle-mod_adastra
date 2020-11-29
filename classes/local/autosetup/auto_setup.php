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

namespace mod_adastra\local\autosetup;

defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot . '/course/lib.php');
require_once(__DIR__ . '/../../../teachers/editcourse_lib.php');
require_once($CFG->dirroot . '/enrol/locallib.php');

/**
 * Automatic configuration of course content based on the configuration
 * downloaded from the exercise service.
 *
 * Derived from A+ (a-plus/edit_course/operations/configure.py).
 */
class auto_setup {

    protected $numerateignoringmodules = false;
    protected $languages;
    protected $json;
    protected $existingroundswithchangingmaxpoints = array();

    public function __construct() {

    }

    /**
     * Configure course content (exercises, chapters, rounds and categories) based on
     * the configuration downloaded from the URL. Creates new content and updates
     * existing content in Moodle, depending on what already exists in Moodle. Hides
     * content that exists in Moodle but is not listed in the configuration.
     *
     * @param int $courseid Moodle course ID.
     * @param int $sectionnumber Moodle course section number (0-N) which new rounds are added to.
     * @param string $url URL which the configuration is downloaded from.
     * @param string $apikey API key used with the URL, null if not used.
     * @return array An array of error strings, empty if there were no errors.
     */
    public static function configure_content_from_url($courseid, $sectionnumber, $url, $apikey = null) {
        $setup = new self();
        try {
            list($response, $responseheaders) = \mod_adastra\local\protocol\remote_page::request($url, false, null, null, $apikey);
        } catch (\mod_adastra\local\protocol\remote_page_exception $e) {
            return array($e->getMessage());
        }

        $conf = \json_decode($response);
        if ($conf === null) {
            // Save API key and config URL.
            \mod_adastra\local\data\course_config::update_or_create($courseid, $sectionnumber, $apikey, $url);
            return array(\get_string('configjsonparseerror', \mod_adastra\local\data\exercise_round::MODNAME));
        }

        $lang = isset($conf->lang) ? $conf->lang : null;
        // Save API key and config URL.
        \mod_adastra\local\data\course_config::update_or_create(
            $courseid,
            $sectionnumber,
            $apikey,
            $url,
            null,
            null,
            $lang
        );

        return $setup->configure_content($courseid, $sectionnumber, $conf);
    }

    public function configure_content($courseid, $sectionnumber, $conf) {
        global $DB;

        $this->json = $conf;
        $lang = isset($conf->lang) ? $conf->lang : array('en');
        if (!is_array($lang)) {
            $lang = array($lang);
        }
        $this->languages = $lang;

        if (!isset($conf->categories) || !\is_object($conf->categories)) {
            return array(\get_string('configcategoriesmissing', \mod_adastra\local\data\exercise_round::MODNAME));
        }
        if (!isset($conf->modules) || !\is_array($conf->modules)) {
            return array(\get_string('configmodulesmissing', \mod_adastra\local\data\exercise_round::MODNAME));
        }

        $errors = array();

        // Parse categories.
        $categories = $this->configure_categories($courseid, $conf->categories, $errors);
        /*
         * Section 0 should always exist and be visible by default (course home page).
         * NOTE: new activities become "orphaned" and unavailable when they are added to sections
         * that do not exist in the course, manually adding new sections to the course fixes it:
         * table course_format_options, fields name=numsections and value=int.
         * If the section has activities before creating the assignments, the section
         * contents need to be sorted afterwards.
         */
        $coursemodinfo = get_fast_modinfo($courseid, -1);
        $sectioninfo = $coursemodinfo->get_section_info($sectionnumber);
        if ($sectioninfo === null) {
            $mustsort = false;
            $sectionvisible = false;
        } else {
            $mustsort = trim($sectioninfo->sequence) != false;
            $sectionvisible = $sectioninfo->visible;
        }

        // Check whether the config defines course assistants and whether we can promote them
        // with the non-editing teacher role in Moodle.
        if (isset($conf->assistants)) {
            $assistantusers = $this->parse_student_id_list($conf->assistants, $errors);
        } else {
            $assistantusers = array();
        }
        $coursectx = \context_course::instance($courseid);
        $teacherroleid = $DB->get_field('role', 'id', array('shortname' => 'teacher')); // Non-editing teacher role.
        /*
         * Assume that the default Moodle role "non-editing teacher" exists in the Moodle site where
         * this plugin is installed and that it is a suitable role for course assistants.
         * An alternative would be to ask the user for the correct role?
         */
        if ($teacherroleid === false) {
            $assistantusers = array();
        } else if (!empty($assistantusers)) {
            if (
                    !\has_capability('moodle/role:assign', $coursectx) ||
                    !\array_key_exists($teacherroleid, \get_assignable_roles($coursectx))
            ) {
                $errors[] = \get_string('configuserrolesdisallowed', \mod_adastra\local\data\exercise_round::MODNAME);
                $assistantusers = array();
            }
        }
        /*
         * Enrol assistants to the course as non-editing teachers. Enrolment is needed so
         * that they may access the course page. They also gain non-editing teacher privileges
         * in the course. Function configure_exercise_round() will also give them the non-editing
         * teacher role in the course module contexts (exercise rounds), which is unnecessary if
         * the assistant has the role in the course level, but we may want to remove the course
         * level teacher role from the assistants.
         */
        self::enrol_users_to_course($assistantusers, $courseid, $teacherroleid, $errors);

        // Parse course modules (exercise rounds).
        $seenmodules = array();
        $seenexercises = array();
        $moduleorder = 0;
        $exerciseorder = 0;
        $this->numerateignoringmodules = isset($conf->numerateignoringmodules) ?
                $this->parse_bool($conf->numerateignoringmodules, $errors) :
                false;
        foreach ($conf->modules as $module) {
            try {
                list($moduleorder, $exerciseorder) = $this->configure_exercise_round(
                        $courseid,
                        $sectionnumber,
                        $sectionvisible,
                        $module,
                        $moduleorder,
                        $exerciseorder,
                        $categories,
                        $seenmodules,
                        $seenexercises,
                        $errors,
                        $assistantusers,
                        $teacherroleid
                );
            } catch (\Exception $e) {
                $errors[] = $e->getMessage();
            }
        }

        // Hide rounds and exercises/chapters that exist in Moodle but were not seen in the config.
        foreach (\mod_adastra\local\data\exercise_round::get_exercise_rounds_in_course($courseid, true) as $exround) {
            $updateroundmaxpoints = $exround->hide_or_delete_unseen_learning_objects($seenexercises);
            if (!\in_array($exround->get_id(), $seenmodules)) {
                if (count($exround->get_learning_objects(true, false)) == 0) {
                    // No exercises in the round, delete the round.
                    course_delete_module($exround->get_course_module()->id);
                    $updateroundmaxpoints = false;
                } else {
                    $exround->set_status(\mod_adastra\local\data\exercise_round::STATUS_HIDDEN);
                    $exround->save();
                    $updateroundmaxpoints = true;
                }
            }
            if ($updateroundmaxpoints) {
                $exround->update_max_points();
                /*
                 * When the max points change for the exercise round grade item in the gradebook,
                 * Moodle scales the grades with the existing point percentage and the new max points.
                 * That results in incorrect grades since the round grade is dependent on the exercise
                 * grades. Therefore, the round grades are inserted into the gradebook again here for
                 * each student that has made a submission to any exercise in the round.
                 */
                $exround->synchronize_grades();
            } else if (\in_array($exround->get_id(), $this->existingroundswithchangingmaxpoints)) {
                // Synchronize exercise round grades in the gradebook since the max points changed on an existing round.
                $exround->synchronize_grades();
            }
        }

        if ($mustsort && empty($errors)) {
            // Sort the activities in the section.
            \adastra_sort_activities_in_section($courseid, $sectionnumber);
            \rebuild_course_cache($courseid, true);
        }

        // Sort the grade items in the gradebook.
        \adastra_sort_gradebook_items($courseid);

        // Clean up obsolete categories.
        foreach (\mod_adastra\local\data\category::get_categories_in_course($courseid, true) as $cat) {
            if (
                    $cat->get_status() == \mod_adastra\local\data\category::STATUS_HIDDEN &&
                    $cat->count_learning_objects(true) == 0
            ) {
                $cat->delete();
            }
        }

        // Purge the exercise/learning object HTML description cache for the course.
        \mod_adastra\cache\exercise_cache::invalidate_course($courseid);

        return $errors;
    }

    /**
     * Enrol users to the course, which allows them to access the course page.
     * Users can be enrolled with student or teacher roles.
     *
     * @param array $users An array of Moodle user records (\stdClass).
     * @param int $courseid Moodle course ID.
     * @param int $roleid ID of the role that is assigned to users in the enrolment.
     * If null, no role is assigned but the user is still enrolled.
     * @param array $errors Error messages that are appended to this array.
     * @return void
     */
    public static function enrol_users_to_course(array $users, $courseid, $roleid, array &$errors) {
        global $DB, $PAGE;

        if (empty($users)) {
            return;
        }

        $enrolid = $DB->get_field('enrol', 'id', array(
                'enrol' => 'manual',
                'courseid' => $courseid,
        ), \IGNORE_MISSING);
        // If manual enrolment is not supported, no users are enrolled.
        if ($enrolid === false) {
            $errors[] = \get_string('confignomanualenrol', \mod_adastra\local\data\exercise_round::MODNAME);
            return;
        }

        $enrolmanager = new \course_enrolment_manager($PAGE, \get_course($courseid));
        $instances = $enrolmanager->get_enrolment_instances();
        $plugins = $enrolmanager->get_enrolment_plugins(true); // Do not allow actions on disabled plugins.
        if (!\array_key_exists($enrolid, $instances)) {
            $erros[] = \get_string('invalidenrolinstance', 'enrol');
            return;
        }
        $instance = $instances[$enrolid];
        if (!isset($plugins[$instance->enrol])) {
            $errors[] = \get_string('enrolnotpermitted', 'enrol');
            return;
        }
        $plugin = $plugins[$instance->enrol];
        $coursectx = \context_course::instance($courseid);
        if ($plugin->allow_enrol($instance) && \has_capability('enrol/' . $plugin->get_name() . ':enrol', $coursectx)) {
            foreach ($users as $user) {
                $plugin->enrol_user($instance, $user->id, $roleid);
                // Function mark_user_dirty must be called after changing data that gets
                // cached in user sessions, e.g. new roles and enrolments.
                mark_user_dirty($user->id);
            }
        } else {
            $errors[] = \get_string('enrolnotpermitted', 'enrol');
        }
    }

    /**
     * Configure one exercise round with its exercises. Updates an existing round
     * or creates a new one, similarly for the exercises.
     *
     * @param int $courseid Moodle course ID where the exercise round is added/modified.
     * @param int $sectionnumber Moodle course section number (0-N) which a new round is added to.
     * @param int|boolean $sectionvisible True if the course section is visible.
     * @param \sdtClass $module Configuration JSON.
     * @param int $moduleorder Ordering number for modules, if config JSON does not specify it.
     * @param int $exerciseorder Ordering number for exercises, if exercises are numerated ignoring modules.
     * @param array $categories Associative array of categories in the course, indexed by keys.
     * @param array $seenmodules Exercise round IDs that have been seen in the config JSON.
     * @param array $seenexercises Exercise IDs that have been seen in the config JSON.
     * @param array $errors
     * @param array $assistantusers User records (\stdClass) of the users that should be promoted
     * to non-editing teachers in the exercise round if any exercise has the "allow_assistant_grading" setting.
     * @param int $teacherroleid Moodle role ID of the role that assistants are given (usually non-editing teacher).
     * @return array ($moduleorder, $exerciseorder)
     */
    protected function configure_exercise_round(
            $courseid,
            $sectionnumber,
            $sectionvisible,
            \sdtClass $module,
            $moduleorder,
            $exerciseorder,
            array &$categories,
            array &$seenmodules,
            array &$seenexercises,
            array &$errors,
            array $assistantusers,
            $teacherroleid
    ) {
        global $DB;

        if (!isset($module->key)) {
            $erros[] = \get_string('configmodkeymissing', \mod_adastra\local\data\exercise_round::MODNAME);
            return;
        }
        // Either update existing exercise round or create new.
        $roundrecord = $DB->get_record(\mod_adastra\local\data\exercise_round::TABLE, array(
            'course' => $courseid,
            'remotekey' => $module->key,
        ));
        if ($roundrecord == false) {
            // Create new.
            $roundrecord = new \stdClass();
            $roundrecord->course = $courseid;
            $roundrecord->remotekey = $module->key;
        }

        if (isset($module->order)) {
            $order = $this->parse_int($module->order, $errors);
            if ($order !== null) {
                $roundrecord->ordernum = $order;
            } else {
                $roundrecord->ordernum = 1;
            }
        } else {
            $moduleorder += 1;
            $roundrecord->ordernum = $moduleorder;
        }
        $modulename = null;
        if (isset($module->title)) {
            $modulename = $this->format_localization_for_activity_name($module->title);
        } else if (isset($module->name)) {
            $modulename = $this->format_localization_for_activity_name($module->name);
        }
        if (!isset($modulename)) {
            $modulename = '-';
        }
        $courseconfig = \mod_adastra\local\data\course_config::get_for_course_id($courseid);
        if ($courseconfig) {
            $numberingstyle = $courseconfig->get_module_numbering();
        } else {
            $numberingstyle = \mod_adastra\local\data\course_config::get_default_module_numbering();
        }
        $roundrecord->name = \mod_adastra\local\data\exercise_round::update_name_with_order($modulename, $roundrecord->ordernum, $numberingstyle);
        // In order to show the ordinal number of the exercise round in the Moodle course page,
        // the number must be stored in the name.

        if (isset($module->status)) {
            $roundrecord->status = $this->parse_module_status($module->status, $erros);
        } else {
            // Default.
            $roundrecord->status = \mod_adastra\local\data\exercise_round::STATUS_READY;
        }

        if (isset($module->points_to_pass)) {
            $p = $this->parse_int($module->points_to_pass, $errors);
            if ($p !== null) {
                $roundrecord->pointstopass = $p;
            }
        }
        if (!isset($roundrecord->pointstopass)) {
            $roundrecord->pointstopass = 0; // Default.
        }

        if (isset($module->open)) {
            $d = $this->parse_date($module->open, $errors);
            if ($d !== null) {
                $roundrecord->openingtime = $d;
            }
        }
        if (!isset($roundrecord->openingtime)) {
            $roundrecord->openingtime = \time();
        }

        if (isset($module->close)) {
            $d = $this->parse_date($module->close, $errors);
            if ($d !== null) {
                $roundrecord->closingtime = $d;
            }
        } else if (isset($module->duration)) {
            $d = $this->parse_duration($roundrecord->openingtime, $module->duration, $errors);
            if ($d !== null) {
                $roundrecord->colsingtime = $d;
            }
        }
        if (!isset($roundrecord->closingtime)) {
            $roundrecord->closingtime = \time() + 1;
        }

        if (isset($module->late_close)) {
            $d = $this->parse_date($module->late_close, $errors);
            if ($d !== null) {
                $roundrecord->latesbmsdl = $d;
                $roundrecord->latesbmsallowed = 1;
            }
        } else if (isset($module->late_duration)) {
            $d = $this->parse_duration($roundrecord->closingtime, $module->late_duration, $errors);
            if ($d !== null) {
                $roundrecord->latesbmsdl = $d;
                $roundrecord->latesbmsallowed = 1;
            }
        } else {
            // Late submissions are not allowed.
            $roundrecord->latesbmsallowed = 0;
        }

        if (isset($module->late_penalty)) {
            $f = $this->parse_float($module->late_penalty, $errors);
            if ($f !== null) {
                $roundrecord->latesbmspenalty = $f;
            }
        }

        if (isset($module->introduction)) {
            $introtext = (string) $module->introduction;
        } else {
            $introtext = '';
        }
        $roundrecord->introeditor = array(
                'text' => $introtext,
                'format' => \FORMAT_HTML,
                'itemid' => 0,
        );

        // Moodle course module visibility.
        $roundrecord->visible =
                ($roundrecord->status != \mod_adastra\local\data\exercise_round::STATUS_HIDDEN && $sectionvisible)
                ? 1 : 0;
        $roundrecord->visibleoncoursepage = 1; // Zero only used for stealth activities.

        // The max points of the round depend on the exercises in the round.
        if (isset($module->children)) {
            $oldmaxpoints = null;
            if (isset($roundrecord->grade) && isset($roundrecord->id)) {
                // The round already exists in the database.
                // Check if the max points are going to change.
                $oldmaxpoints = $roundrecord->grade;
            }
            $roundrecord->grade = $this->get_total_max_points($module->children, $categories);
            if ($oldmaxpoints !== null && $oldmaxpoints != $roundrecord->grade) {
                // Keep track of existing rounds whose max points change.
                $this->existingroundswithchangingmaxpoints[] = $roundrecord->id;
            }
        } else {
            $roundrecord->grade = 0;
        }

        if (isset($roundrecord->id)) {
            // Update existing exercise round.
            // Settings for the Moodle course module.
            $exround = new \mod_adastra\local\data\exercise_round($roundrecord);
            $cm = $exround->get_course_module();
            $roundrecord->coursemodule = $cm->id; // Moodle course module ID.
            $roundrecord->cmidnumber = $cm->idnumber; // Keep the old Moodle course module idnumber.

            \update_module($roundrecord); // Throws moodle_exception.
        } else {
            // Create new exercise round.
            // Settings for the Moodle course module.
            $roundrecord->modulename = \mod_adastra\local\data\exercise_round::TABLE;
            $roundrecord->section = $sectionnumber;
            $roundrecord->cmidnumber = ''; // Moodle course module idnumber, unused.

            $moduleinfo = \create_module($roundrecord);
            $exround = \mod_adastra\local\data\exercise_round::create_from_id($moduleinfo->instance);
        }

        $seenmodules[] = $exround->get_id();

        if (!$this->numerateignoringmodules) {
            $exerciseorder = 0;
        }

        // Parse exercises/chapters in the exercise round.
        if (isset($module->children)) {
            $exerciseorder = $this->configure_learning_objects(
                    $categories,
                    $exround,
                    $module->children,
                    $seenexercises,
                    $errors,
                    null,
                    $exerciseorder
            );
        }

        /*
         * Add course assistants automatically to the Moodle course.
         * In Moodle, we can promote a user's role within an activity. Only exercise rounds
         * are represented as activities in this plugin, hence a user gains non-editing teacher
         * privileges in the whole exercise round if one exercise has the "allow assistant grading"
         * setting. Exercises have their own "allow assistant grading" and "allow assistant viewing"
         * settings that are used as additional access restrictions in addition to the Moodle capabilities.
         * This teacher role assignment in the course module level may be completely unnecessary if the
         * teacher role is also assigned in the course level, but we keep it here as a precaution
         * (e.g. if the responsible teacher does not want to give teacher role in the course level to
         * assistants, but only inn the course module level).
         */
        $autosetup = $this;
        $unusederrors = array();
        $hasallowassistantsetting = function($children) use ($autosetup, &$unusederrors, $hasallowassistantsetting) {
            foreach ($children as $child) {
                if (
                        isset($child->allow_assistant_grading) &&
                            $autosetup->parse_bool($child->allow_assistant_grading, $unusederrors)
                        ||
                        isset($child->allow_assistant_viewing) &&
                            $autosetup->parse_bool($child->allow_assistant_viewing, $unusederrors)
                ) {
                    return true;
                }
                if (isset($child->children) && $hasallowassistantsetting($child->children)) {
                    return true;
                }
            }
            return false;
        };
        if ($hasallowassistantsetting($module->children)) {
            // If some exercise in the round has allow_assitant_grading or allow_assistant_viewing,
            // promote the user's role in the whole round.
            foreach ($assistantusers as $astuser) {
                \role_assign($teacherroleid, $astuser->id, \context_module::instance($exround->get_course_module()->id));
                /*
                 * This role assigned in the course module level does not provide any access to the course
                 * itself (course home web page).
                 * Function mark_user_dirty must be called after changing data that gets
                 * cached in user sessions, e.g. new roles and enrolments.
                 */
                mark_user_dirty($astuser->id);
            }
        }

        return array($moduleorder, $exerciseorder);
    }

    /**
     * Return the total summed max points from the given visible learning objects
     * (that are assued to be in the same)
     *
     * @param array $conf An array of objects.
     * @param array $categories
     * @return number The total max points.
     */
    protected function get_total_max_points(array $conf, array $categories) {
        $totalmax = 0;
        $errors = array();
        foreach ($conf as $o) {
            $status = \mod_adastra\local\data\learning_object::STATUS_READY;
            if (isset($o->status)) {
                $status = $this->parse_learning_object_status($o->status, $errors);
            }
            $catstatus = \mod_adastra\local\data\category::STATUS_READY;
            if (isset($categories[$o->category])) {
                $cat = $categories[$o->category];
                $catstatus = $cat->get_status();
            }
            $visible = ($status != \mod_adastra\local\data\learning_object::STATUS_HIDDEN &&
                    $catstatus != \mod_adastra\local\data\category::STATUS_HIDDEN);
            if (isset($o->maxpoints) && $visible) {
                $maxpoints = $this->parse_int($o->max_points, $errors);
                if ($maxpoints !== null) {
                    $totalmax += $maxpoints;
                }
            }
            if (isset($o->children)) {
                $totalmax += $this->get_total_max_points($o->children, $categories);
            }
        }
        return $totalmax;
    }

    protected function configure_categories($courseid, \stdClass $categoriesconf, &$errors) {
        $categories = array();
        $seencats = array();
        foreach ($categoriesconf as $key => $cat) {
            if (!isset($cat->name)) {
                $errors[] = \get_string('configcatnamemissing', \mod_adastra\local\data\exercise_round::MODNAME);
                continue;
            }
            $catrecord = new \stdClass();
            $catrecord->course = $courseid;
            $catrecord->name = $this->format_localization($cat->name);
            if (isset($cat->status)) {
                $catrecord->status = $this->parse_category_status($cat->status, $errors);
            } else {
                $catrecord->status = \mod_adastra\local\data\category::STATUS_READY;
            }
            if (isset($cat->points_to_pass)) {
                $catrecord->pointstopass = $this->parse_int($cat->points_to_pass, $errors);
                if ($catrecord->pointstopass === null) {
                    unset($catrecord->pointstopass);
                }
            }
            $category = \mod_adastra\local\data\category::create_from_id(\mod_adastra\local\data\category::update_or_create($catrecord));
            $categories[$key] = $category;
            $seencats[] = $category->get_id();
        }

        // Hide categories that exist in Moodle but were not seen in the config.
        foreach (\mod_adastra\local\data\category::get_categories_in_course($courseid) as $id => $cat) {
            if (!\in_array($id, $seencats)) {
                $cat->set_hidden();
                $cat->save();
            }
        }

        return $categories;
    }

    protected function configure_learning_objects(
            array &$categories,
            \mod_adastra\local\data\exercise_round $exround,
            array $config,
            array &$seen,
            array &$errors,
            \mod_adastra\local\data\learning_object $parent = null,
            $n = 0
    ) {
        // TODO
    }

    protected function format_localization($elem) : string {
        // TODO
    }

    protected function format_localization_for_activity_name($elem) : string {
        // TODO
    }

    protected function parse_learning_object_status($value, &$errors) {
        // TODO
    }

    protected function parse_module_status($value, &$errors) {
        // TODO
    }

    protected function parse_category_status($value, &$errors) {

    }

    protected function parse_int($value, &$errors) {
        // TODO
    }

    protected function parse_float($value, &$errors) {
        // TODO
    }

    protected function parse_bool($value, &$errors) {
        // TODO
    }

    protected function parse_date($value, &$errors) {
        // TODO
    }

    protected function parse_duration($openingtime, $duration, &$errors) {
        // TODO
    }

    protected function parse_student_id_list($studentids, &$errors) {
        // TODO
    }
}