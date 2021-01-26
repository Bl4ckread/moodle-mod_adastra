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

namespace mod_adastra\form;

defined('MOODLE_INTERNAL') || die();
require_once($CFG->libdir . '/formslib.php');

use mod_adastra\local\data\exercise_round;

class edit_round_form extends \moodleform {

    protected $courseid;
    protected $editroundid;
    protected $introitemid;

    /**
     * Constructor.
     *
     * @param int $courseid Moodle course ID of the category.
     * @param int $introitemid Itemid for the intro HTML editor.
     * @param int $editroundid ID of the exercise round if editing, zero if creating new.
     * @param string $action Form action URL.
     */
    public function __construct($courseid, $introitemid, $editroundid = 0, $action = null) {
        $this->courseid = $courseid;
        $this->editroundid = $editroundid;
        $this->introitemid = $introitemid;
        parent::__construct($action); // Calls definition().
    }

    /**
     * Add fields for editing an exercise round that should be listed before the introeditor.
     * This method can be used by mod_form and this class to reuse the same code.
     *
     * @param moodleform_mod $mform A form instance.
     * @return void
     */
    public static function add_fields_before_intro($mform) {
        global $CFG;

        $mod = exercise_round::MODNAME; // For get_string().

        // Adding the standard "name" field.
        $mform->addElement('text', 'name', get_string('roundname', $mod), array('size' => '64'));
        if (!empty($CFG->formatstringstriptags)) {
            $mform->setType('name', PARAM_TEXT);
        } else {
            $mform->setType('name', PARAM_CLEAN);
        }
        $mform->addRule('name', null, 'required', null, 'client');
        $mform->addRule('name', get_string('maximumchars', '', 255), 'maxlength', 255, 'client');
        $mform->addHelpButton('name', 'roundname', $mod);
    }

    /**
     * Add fields for editing an exercise round that should be listed after the introeditor.
     * This method can be used by mod_form and this class to reuse the same code.
     *
     * @param moodleform_mod $mform A form instance.
     * @return void
     */
    public static function add_fields_after_intro($mform) {
        $mod = exercise_round::MODNAME; // For get_string().

        // Exercise round status.
        $mform->addElement('select', 'status', get_string('status', $mod), array(
                exercise_round::STATUS_READY => get_string('statusready', $mod),
                exercise_round::STATUS_HIDDEN => get_string('statushidden', $mod),
                exercise_round::STATUS_MAINTENANCE => get_string('statusmaintenance', $mod),
                exercise_round::STATUS_UNLISTED => get_string('statusunlisted', $mod),
        ));

        // Order amongst rounds.
        $mform->addElement('text', 'ordernum', get_string('ordernum', $mod));
        $mform->setType('ordernum', \PARAM_INT);
        $mform->addHelpButton('ordernum', 'ordernum', $mod);
        $mform->addRule('ordernum', null, 'required', null, 'client');
        $mform->addRule('ordernum', null, 'maxlength', 4, 'client');
        $mform->addRule('ordernum', null, 'numeric', null, 'client');

        // Remote key (URL component in A+).
        $mform->addElement('text', 'remotekey', get_string('remotekey', $mod));
        $mform->setType('remotekey', PARAM_NOTAGS);
        $mform->addHelpButton('remotekey', 'remotekey', $mod);
        $mform->addRule('remotekey', null, 'required', null, 'client');
        $mform->addRule('remotekey', null, 'maxlength', 128, 'client');

        // Points to pass.
        $mform->addElement('text', 'pointstopass', get_string('pointstopass', $mod));
        $mform->setType('pointstopass', PARAM_INT);
        $mform->addHelpButton('pointstopass', 'pointstopass', $mod);
        $mform->addRule('pointstopass', null, 'numeric', null, 'client');
        $mform->addRule('pointstopass', null, 'maxlength', 7, 'client');
        $mform->addRule('pointstopass', null, 'required', null, 'client');
        $mform->setDefault('pointstopass', 0);

        // Opening time.
        $mform->addElement('date_time_selector', 'openingtime', get_string('openingtime', $mod), array(
                'step' => 1, // Minutes step in the drop-down menu.
                'optional' => false, // Do not allow disabling the date.
        ));
        $mform->addHelpButton('openingtime', 'openingtime', $mod);
        $mform->addRule('openingtime', null, 'required', null, 'client');
        $mform->setDefault('openingtime', time());

        // Closing time.
        $mform->addElement('date_time_selector', 'closingtime', get_string('closingtime', $mod), array(
                'step' => 1, // Minutes step in the drop-down menu.
                'optional' => false, // Do not allow disabling the date.
        ));
        $mform->addHelpButton('closingtime', 'closingtime', $mod);
        $mform->addRule('closingtime', null, 'required', null, 'client');
        $mform->setDefault('closingtime', time());

        // Allow late submissions after the closing time?
        // 4th argument is the label displayed after checkbox, 5th arg: HTML attributes,
        // 6th: unchecked/checked values.
        $mform->addElement('advcheckbox', 'latesbmsallowed', get_string('latesbmsallowed', $mod), '', null, array(0, 1));
        $mform->addHelpButton('latesbmsallowed', 'latesbmsallowed', $mod);

        // Late submission deadline.
        $mform->addElement('date_time_selector', 'latesbmsdl', get_string('latesbmsdl', $mod), array(
            'step' => 1, // Minutes step in the drop-down menu.
            'optional' => false, // Do not allow disabling the date.
        ));
        $mform->addHelpButton('latesbmsdl', 'latesbmsdl', $mod);
        $mform->setDefault('latesbmsdl', array()); // Disabled by default -> not set.

        // Late submission penalty.
        $mform->addElement('text', 'latesbmspenalty', get_string('latesbmspenalty', $mod));
        $mform->setType('latesbmspenalty', PARAM_FLOAT); // Requires dot as the decimal separator.
        $mform->addHelpButton('latesbmspenalty', 'latesbmspenalty', $mod);
        $mform->addRule('latesbmspenalty', null, 'numeric', null, 'client');
        $mform->addRule('latesbmspenalty', null, 'maxlength', 5, 'client');
    }

    public function definition() {
        global $CFG;

        $mform = $this->_form;
        $mod = exercise_round::MODNAME; // For get_string().
        // All the addRule validation rules must match the limits in the DB schema:
        // table adastra in the file adastra/db/install.xml.

        self::add_fields_before_intro($mform);

        // Adding the "intro" and "introformat" fields (HTML editor).
        if ($this->editroundid) {
            list($course, $cm) = get_course_and_cm_from_instance($this->editroundid, exercise_round::TABLE);
            $context = \context_module::instance($cm->id);
        } else {
            $context = \context_module::instance($this->courseid);
        }
        $mform->addElement('editor', 'introeditor', get_string('moduleintro'), array('rows' => 10), array(
                'maxfiles' => 0,
                'maxbytes' => 0,
                'noclean' => true,
                'context' => $context,
                'subdirs' => false,
                'enable_filemanagement' => false,
                'changeformat' => 0,
                'trusttext' => 1,
        ));
        $mform->setType('introeditor', PARAM_RAW);
        $mform->addElement('hidden', 'introeditor[itemid]', $this->introitemid);

        self::add_fields_after_intro($mform);

        // Course section number, required if creating a new round, ignored if editing an existing one.
        if ($this->editroundid == 0) {
            $mform->addElement('text', 'sectionnumber', get_string('sectionnum', $mod));
            $mform->setType('sectionnumber', PARAM_INT);
            $mform->addHelpButton('sectionnumber', 'sectionnum', $mod);
            $mform->addRule('sectionnumber', null, 'numeric', null, 'client');
            $mform->addRule('sectionnumber', null, 'maxlength', 2, 'client');
            $mform->addRule('sectionnumber', null, 'required', null, 'client');
        }

        // Add standard buttons, common to all modules.
        $this->add_action_buttons();
    }

    /**
     * Validate form fields that are added in the other reusable static methods.
     * This method can be used my mod_form and this class to reuse the same code.
     *
     * @param array $data User input in the form.
     * @param array $files Files in the form.
     * @param int $courseid Moodle course ID of the exercise round.
     * @param int $editroundid ID of the exercise round, or zero if creating a new round.
     * @return array An array of errors indexed by form field names.
     */
    public static function common_validation($data, $files, $courseid, $editroundid = 0) {
        $mod = exercise_round::MODNAME; // For get_string().
        $errors = array();

        // If point values are given, they cannnot be negative.
        if ($data['pointstopass'] !== '' && $data['pointstopass'] < 0) {
            $errors['pointstopass'] = get_string('negativeerror', $mod);
        }

        // Closing time must be later than opening time.
        if ($data['closingtime'] !== '' && $data['openingtime'] !== '' &&
                $data['closingtime'] < $data['openingtime']
        ) {
            $errors['closingtime'] = get_string('closingbeforeopeningerror', $mod);
        }

        if ($data['latesbmsallowed'] == 1) {
            if (empty($data['latesbmsdl'])) {
                $errors['latesbmsdl'] = get_string('mustbesetwithlate', $mod);
            } else {
                // Late submission deadline must be later than the closing time.
                if ($data['closingtime'] !== '' && $data['latesbmsdl'] <= $data['closingtime']) {
                    $errors['latesbmsdl'] = get_string('latedlbeforeclosingerror', $mod);
                }
            }

            if (!isset($data['latesbmspenalty']) || $data['latesbmspenalty'] === '') {
                $errors['latesbmspenalty'] = get_string('mustbesetwithlate', $mod);
            } else {
                // Late submission penalty must be between 0 and 1.
                if ($data['latesbmspenalty'] < 0 || $data['latesbmspenalty'] > 1) {
                    $errors['latesbmspenalty'] = get_string('zerooneerror', $mod);
                }
            }
        }

        // Check that remote keys of exercise rounds are unique within a course.
        foreach (exercise_round::get_exercise_rounds_in_course($courseid, true) as $exround) {
            if ($editroundid != $exround->get_id() && $data['remotekey'] == $exround->get_remote_key()) {
                $errors['remotekey'] = get_string('duplicateroundremotekey', $mod);
            }
        }

        return $errors;
    }

    public function validation($data, $files) {
        $mod = exercise_round::MODNAME; // For get_string().
        $errors = parent::validation($data, $files);

        $errors = array_merge($errors, self::common_validation($data, $files, $this->courseid, $this->editroundid));

        // Require section number if creating a new round, must be non-negative.
        if ($this->editroundid == 0 && ($data['sectionnumber'] === '' || $data['sectionnumber'] < 0)) {
            $errors['sectionnumber'] = get_string('negativeerror', $mod);
        }
        return $errors;
    }
}