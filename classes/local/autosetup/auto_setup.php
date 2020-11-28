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
            if (!\has_capability('moodle/role:assign', $coursectx) || !\array_key_exists($teacherroleid, \get_assignable_roles($coursectx))) {
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
            // TODO
        }
    }

    public static function enrol_users_to_course(array $users, $courseid, $roleid, array &$errors) {
        // TODO
    }

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
        // TODO
    }

    protected function configure_categories($courseid, \stdClass $categoriesconf, &$errors) {
        // TODO
    }

    protected function parse_bool($value, &$errors) {
        // TODO
    }

    protected function parse_student_id_list($studentids, &$errors) {
        // TODO
    }
}