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

namespace mod_adastra\form;

defined('MOODLE_INTERNAL') || die();

class autosetup_form extends \moodleform {

    private $defaultvalues; // Variable of class stdClass with keys apikey, configurl and sectionnum.

    public function __construct($defaultvalues, $action=null) {
        $this->defaultvalues = $defaultvalues;
        parent::__construct($action);
    }

    public function definition() {
        $mform = $this->_form;

        // Exercise service URL for configuration JSON.
        $mform->addElement('text', 'configurl', get_string('configurl', \mod_adastra\local\data\exercise_round::MODNAME));
        $mform->setType('configurl', PARAM_NOTAGS);
        $mform->addHelpButton('configurl', 'configurl', \mod_adastra\local\data\exercise_round::MODNAME);
        $mform->addRule('configurl', null, 'required', null, 'client');
        $mform->addRule('configurl', null, 'maxlength', 255, 'client');

        // Server API key.
        $mform->addElement('text', 'apikey', get_string('apikey', \mod_adastra\local\data\exercise_round::MODNAME));
        $mform->setType('apikey', PARAM_NOTAGS);
        $mform->addHelpButton('apikey', 'apikey', \mod_adastra\local\data\exercise_round::MODNAME);
        $mform->addRule('apikey', null, 'maxlength', 100, 'client');

        // Moodle course section number (0-N), to which the new activities are added.
        $mform->addElement('text', 'sectionnum', get_string('sectionnum', \mod_adastra\local\data\exercise_round::MODNAME));
        $mform->setType('sectionnum', PARAM_INT);
        $mform->addHelpButton('sectionnum', 'sectionnum', \mod_adastra\local\data\exercise_round::MODNAME);
        $mform->addRule('sectionnum', null, 'required', null, 'client');
        $mform->addRule('sectionnum', null, 'numeric', null, 'client');
        $mform->addRule('sectionnum', null, 'maxlength', 2, 'client');

        // Set default values to form fields.
        if (!is_null($this->defaultvalues)) {
            $mform->setDefault('configurl', $this->defaultvalues->configurl);
            $mform->setDefault('apikey', $this->defaultvalues->apikey);
            $mform->setDefault('sectionnum', $this->defaultvalues->sectionnum);
        }

        $this->add_action_buttons(true, get_string('apply', \mod_adastra\local\data\exercise_round::MODNAME));
    }
}