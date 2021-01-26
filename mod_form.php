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

/**
 * The main mod_adastra configuration form.
 *
 * @package     mod_adastra
 * @copyright   2020 Your Name <you@example.com>
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot.'/course/moodleform_mod.php');

/**
 * Module instance settings form.
 *
 * @package    mod_adastra
 * @copyright  2020 Your Name <you@example.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class mod_adastra_mod_form extends moodleform_mod {

    /**
     * Defines forms elements
     */
    public function definition() {
        global $CFG;

        $mform = $this->_form;
        $mod = mod_adastra\local\data\exercise_round::MODNAME; // For get_string().
        // All the addRule validation rules must match the limits in the DB schema:
        // table adastra in the file adastra/db/install.xml.

        // Adding the "general" fieldset, where all the common settings are shown.
        $mform->addElement('header', 'general', get_string('general', 'form'));

        // Warning to the user that this form should not be used.
        $mform->addElement(
                'static',
                'adastradonotuse',
                get_string('note', $mod),
                get_string('donotusemodform', $mod)
        );

        mod_adastra\form\edit_round_form::add_fields_before_intro($mform);

        // Adding the standard "intro" and "introformat" fields.
        $this->standard_intro_elements();

        mod_adastra\form\edit_round_form::add_fields_after_intro($mform);

        // Add standard elements, common to all modules.
        $this->standard_coursemodule_elements();

        // Add standard buttons, common to all modules.
        $this->add_action_buttons();
    }

    public function validation($data, $files) {
        global $COURSE;

        $errors = parent::validation($data, $files);

        $editroundid = 0;
        if (!empty($this->_instance)) {
            $editroundid = $this->_instance;
        }

        $errors = array_merge(
                $errors,
                mod_adastra\form\edit_round_form::common_validation($data, $files, $COURSE->id, $editroundid)
        );

        return $errors;
    }
}
