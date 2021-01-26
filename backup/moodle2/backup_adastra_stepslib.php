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


class backup_adastra_activity_structure_step extends backup_activity_structure_step {
    /**
     * Define the complete Ad Astra structure for backup, with file and id annotations.
     *
     * @return void
     */
    protected function define_structure() {

        // To know if we are including userinfo.
        $userinfo = $this->get_setting_value('userinfo');

        // Define each element separately.
        // Exercise round.
        $adastra = new backup_nested_element(
                'adastra',
                array('id'),
                array(
                        'name', 'intro', 'introformat', 'timecreated', 'timemodified', 'ordernum', 'status',
                        'grade', 'remotekey', 'pointstopass', 'openingtime', 'closingtime', 'latesbmsallowed',
                        'latesbmsdl', 'latesbmspenalty',
                )
        );
        $categories = new backup_nested_element('categories');
        $category = new backup_nested_element('category', array('id'),
                array(
                        'status', 'name', 'pointstopass',
                ));
        $learningobjects = new backup_nested_element('learningobjects');
        $learningobject = new backup_nested_element(
                'learningobject',
                array('id'),
                array(
                    'status', 'parentid', 'ordernum', 'remotekey', 'name', 'serviceurl',
                )
        );
        $exercise = new backup_nested_element(
                'exercise',
                array('id'),
                array(
                        'maxsubmissions', 'pointstopass', 'maxpoints',
                        'maxsbmssize', 'allowastviewing', 'allowastgrading',
                )
        );
        $chapter = new backup_nested_element(
                'chapter',
                array('id'),
                array(
                        'generatetoc',
                )
        );
        $submissions = new backup_nested_element('submissions');
        $submission = new backup_nested_element(
                'submission',
                array('id'),
                array(
                        'status', 'submissiontime', 'hash', 'submitter', 'grader',
                        'feedback', 'assistfeedback', 'grade', 'gradingtime', 'latepenaltyapplied',
                        'servicepoints', 'servicemaxpoints', 'submissiondata', 'gradingdata',
                )
        );
        $coursesetting = new backup_nested_element(
                'coursesetting',
                array('id'),
                array(
                        'apikey', 'configurl', 'sectionnum', 'modulenumbering', 'contentnumbering',
                )
        );
        $deadlinedeviations = new backup_nested_element('deadlinedeviations');
        $deadlinedeviation = new backup_nested_element(
                'deadlinedeviation',
                array('id'),
                array(
                        'submitter', 'extraminutes', 'withoutlatepenalty',
                )
        );
        $submitlimitdeviations = new backup_nested_element('submitlimitdeviations');
        $submitlimitdeviation = new backup_nested_element(
                'submitlimitdeviation',
                array('id'),
                array(
                        'submitter', 'extrasubmissions',
                )
        );

        // Build the tree.
        $adastra->add_child($categories);
        $categories->add_child($category);
        $category->add_child($learningobjects);

        $learningobjects->add_child($learningobject);
        $learningobject->add_child($exercise);
        $learningobject->add_child($chapter);

        $exercise->add_child($submissions);
        $submissions->add_child($submission);

        $adastra->add_child($coursesetting);

        $exercise->add_child($deadlinedeviations);
        $deadlinedeviations->add_child($deadlinedeviation);
        $exercise->add_child($submitlimitdeviations);
        $submitlimitdeviations->add_child($submitlimitdeviation);
        // All categories are stored under each round, thus restore operation
        // should not create categories if they already exist in the course (unique name).
        // Similar to the one course settings DB row.

        // Define sources.
        $adastra->set_source_table(mod_adastra\local\data\exercise_round::TABLE, array('id' => backup::VAR_ACTIVITYID));
        $category->set_source_table(mod_adastra\local\data\category::TABLE, array('course' => backup::VAR_COURSEID));
        $learningobject->set_source_table(mod_adastra\local\data\learning_object::TABLE,
                array('roundid' => backup::VAR_ACTIVITYID, 'categoryid' => backup::VAR_PARENTID),
                '(CASE WHEN parentid IS NULL THEN 1 ELSE 2 END), id ASC');
        // Sort top-level learning objects first (parentid null).
        $exercise->set_source_table(mod_adastra\local\data\exercise::TABLE, array('lobjectid' => backup::VAR_PARENTID));
        $chapter->set_source_table(mod_adastra\local\data\chapter::TABLE, array('lobjectid' => backup::VAR_PARENTID));
        $coursesetting->set_source_table(mod_adastra\local\data\course_config::TABLE, array('course' => backup::VAR_COURSEID));

        if ($userinfo) {
            $submission->set_source_table(mod_adastra\local\data\submission::TABLE, array(
                    'exerciseid' => '../../../id'
            ));
            $deadlinedeviation->set_source_table(mod_adastra\local\data\deadline_deviation::TABLE, array(
                    'exerciseid' => '../../../id'
            ));
            $submitlimitdeviation->set_source_table(mod_adastra\local\data\submission_limit_deviation::TABLE, array(
                    'exerciseid' => '../../../id'
            ));
        }

        // Define id annotations.
        $submission->annotate_ids('user', 'submitter');
        $submission->annotate_ids('user', 'grader');
        $deadlinedeviation->annotate_ids('user', 'submitter');
        $submitlimitdeviation->annotate_ids('user', 'submitter');

        // Define file annotations.
        $submission->annotate_files(
                mod_adastra\local\data\exercise_round::MODNAME,
                mod_adastra\local\data\submission::SUBMITTED_FILES_FILEAREA,
                'id'
        );

        // Return the root element (adastra), wrapped into a standard activity structure.
        return $this->prepare_activity_structure($adastra);
    }
}