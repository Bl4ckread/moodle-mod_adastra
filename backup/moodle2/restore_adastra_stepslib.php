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

defined('MOODLE_INTERNAL') || die();

/**
 * Structure step to restore one Ad Astra activity.
 */
class restore_adastra_activity_structure_step extends restore_activity_structure_step {

    // Gather learning objects that have non-null parentid fields -> parentid is updated
    // in after_execute method after all learning objects have been restored and their
    // new IDs are known.
    private $learningobjectswithparents = array();

    protected function define_structure() {
        $paths = array();
        $userinfo = $this->get_setting_value('userinfo');

        $paths[] = new restore_path_element('adastra', '/activity/adastra');
        $paths[] = new restore_path_element('category', '/activty/adastra/categories/category');
        $paths[] = new restore_path_element(
                'learningobject',
                '/activity/adastra/categories/category/learningobjects/learningobject'
        );
        $paths[] = new restore_path_element(
                'exercise',
                '/activity/adastra/categories/category/learningobjects/learningobject/exercise'
        );
        $paths[] = new restore_path_element(
                'chapter',
                '/activity/adastra/categories/category/learningobjects/learningobject/chapter'
        );
        $paths[] = new restore_path_element(
                'coursesetting',
                '/activity/adastra/coursesetting'
        );

        if ($userinfo) {
            $paths[] = new restore_path_element(
                    'submission',
                    '/activity/adastra/categories/category/learningobjects/learningobject/exercise/submissions/submission'
            );
            $paths[] = new restore_path_element(
                    'deadlinedeviation',
                    '/activity/adastra/categories/category/learningobjects/learningobject/exercise/' .
                            'deadlinedeviations/deadlinedeviation'
            );
            $paths[] = new restore_path_element(
                    'submitlimitdeviation',
                    '/activity/adastra/categories/category/learningobjects/learningobject/exercise/' .
                            'submitlimitdeviations/submitlimitdeviation'
            );
        }

        // Return the paths wrapped into a standard activity structure.
        return $this->prepare_activity_structure($paths);
    }

    protected function process_adastra($data) {
        global $DB;

        $data = (object) $data;
        $oldid = $data->id;
        $data->course = $this->get_courseid();

        $data->timecreated = $this->apply_date_offset($data->timecreated);
        $data->timemodified = $this->apply_date_offset($data->timemodified);
        $data->openingtime = $this->apply_date_offset($data->openingtime);
        $data->closingtime = $this->apply_date_offset($data->closingtime);
        $data->latesbmsdl = $this->apply_date_offset($data->latesbmsdl);

        // New exercise rounds and learning objects are created during restore even if
        // objects with the same remote keys already exist in the Moodle course.
        // If the course is empty before restoring, that can not happen of course.
        // The teacher should check remote keys (and exercise service configuration) after
        // restoring if existing rounds/learning objects are duplicated in the restore process.

        // Insert the Ad Astra (exercise round) record.
        $newitemid = $DB->insert_record(mod_adastra\local\data\exercise_round::TABLE, $data);
        // Immediately after inserting "activity" record, call this.
        $this->apply_activity_instance($newitemid);

        if ($DB->count_records(mod_adastra\local\data\exercise_round::TABLE, array(
                        'course' => $data->course,
                        'remotekey' => $data->remotekey
                )) > 1
        ) {
            $this->get_logger()->process(
                    'The course probably was not empty before restoring and now there ' .
                            'are multiple exercise rounds with the same remote key. '
                            'You should check and update them manually. The same applies to learning objects (exercises/chapters).',
                    backup::LOG_INFO
            );
        }
    }

    protected function process_category($data) {
        global $DB;

        $data = (object) $data;
        $oldid = $data->id;
        $data->course = $this->get_courseid();
        // Each exercise round XML tree contains all the categories in the course, thus
        // we can't create a new category every time we find a category XML element.
        $existingcat = $DB->get_record(mod_adastra\local\data\category::TABLE, array(
                'course' => $data->course,
                'name' => $data->name,
        ));
        if ($existingcat === false) {
            // Does not yet exist.
            $newitemid = $DB->insert_record(mod_adastra\local\data\category::TABLE, $data);
        } else {
            // Do not modify the existing category.
            $newitemid = $existingcat->id;
        }

        $this->set_mapping('category', $oldid, $newitemid);
    }

    protected function process_learningobject($data) {
        global $DB;

        $data = (object) $data;
        $oldid = $data->id;
        $data->categoryid = $this->get_new_parentid('category');
        $data->roundid = $this->get_new_parentid('adastra');

        if ($data->parentid !== null) {
            $oldparentid = $data->parentid;
            $data->parentid = $this->get_mappingid('learningobject', $data->parentid, null);
            if ($data->parentid === null) {
                // Mapping was not found because the parent was not defined before the child in the XML.
                // Update this parentid later.
                $lobject = array(
                        'id' => $oldid,
                        'parentid' => $oldparentid,
                );
                $this->learningobjectswithparents[] = (object) $lobject;
            }
        }

        $newitemid = $DB->insert_record(mod_adastra\local\data\learning_object::TABLE, $data);
        $this->set_mapping('learningobject', $oldid, $newitemid);
    }

    protected function process_exercise($data) {
        global $DB;

        $data = (object) $data;
        $oldid = $data->id;
        $data->lobjectid = $this->get_new_parentid('learningobject');
        $newitemid = $DB->insert_record(mod_adastra\local\data\exercise::TABLE, $data);
    }

    protected function process_chapter($data) {
        global $DB;

        $data = (object) $data;
        $oldid = $data->id;
        $data->lobjectid = $this->get_new_parentid('learningobject');
        $newitemid = $DB->insert_record(mod_adastra\local\data\chapter::TABLE, $data);
    }

    protected function process_coursesetting($data) {
        global $DB;

        $data = (object) $data;
        $oldid = $data->id;
        $data->course = $this->get_courseid();
        // Each exercise round XML tree contains one course settings element, thus
        // we only create a new settings row if it does not yet exist in the course.
        $existingsetting = $DB->get_record(mod_adastra\local\data\course_config::TABLE, array('course' => $data->course));
        if ($existingsetting === false) {
            $newitemid = $DB->insert_record(mod_adastra\local\data\course_config::TABLE, $data);
        }
    }

    protected function process_submission($data) {
        global $DB;

        $data = (object) $data;
        $oldid = $data->id;

        $data->submissiontime = $this->apply_date_offset($data->submissiontime);
        $data->exerciseid = $this->get_new_parentid('learningobject');
        $data->submitter = $this->get_mappingid('user', $data->submitter);
        $data->grader = $this->get_mappingid('user', $data->grader);
        $data->gradingtime = $this->apply_date_offset($data->gradingtime);

        $newitemid = $DB->insert_record(mod_adastra\local\data\submission::TABLE, $data);
        // Set mapping for restoring submitted files.
        $this->set_mapping('submission', $oldid, $newitemid, true);
    }

    protected function process_deadlinedeviation($data) {
        global $DB;

        $data = (object) $data;
        $oldid = $data->id;
        $data->submitter = $this->get_mappingid('user', $data->submitter);
        $data->exerciseid = $this->get_new_parentid('learningobject');

        $newitemid = $DB->insert_record(mod_adastra\local\data\deadline_deviation::TABLE, $data);
    }

    protected function process_submitlimitdeviation($data) {
        global $DB;

        $data = (object) $data;
        $oldid = $data->id;
        $data->submitter = $this->get_mappingid('user', $data->submitter);
        $data->exerciseid = $this->get_new_parentid('learningobject');

        $newitemid = $DB->insert_record(mod_adastra\local\data\submission_limit_deviation::TABLE, $data);
    }

    protected function after_execute() {
        global $DB;

        // Restore submitted files.
        $this->add_related_files(
                mod_adastra\local\data\exercise_round::MODNAME,
                mod_adastra\local\data\submission::SUBMITTED_FILES_FILEAREA,
                'submission'
        );

        // Fix learning object parentids.
        foreach ($this->learningobjectswithparents as $oldlobject) {
            $newlobject = new stdClass();
            $newlobject->parentid = $this->get_mappingid('learningobject', $oldlobject->parentid);
            $newlobject->id = $this->get_mappingid('learningobject', $oldlobject->id);

            if (!$newlobject->id || !$newlobject->parentid) {
                // Mapping not found even though all learning objects have been restored.
                debugging('restore_adastra_activity_structure_step::after_execute: ' .
                        'learning object mapping not found while fixing parentids');
            } else {
                $DB->update_record(mod_adastra\local\data\learning_object::TABLE, $newlobject);
            }
        }
    }
}