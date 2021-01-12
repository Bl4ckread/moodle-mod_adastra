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

namespace mod_adastra\local\data;

defined('MOODLE_INTERNAL') || die();

trait exercise_test_data {

    protected $course;
    protected $round1;
    protected $round2;
    protected $category;
    protected $exercises;
    protected $submissions;
    protected $student;
    protected $student2;

    protected function add_test_data() {
        // Create a course instance for testing.
        $this->setAdminUser();
        $this->course = $this->getDataGenerator()->create_course();

        $this->student = $this->getDataGenerator()->create_user();
        $this->student2 = $this->getDataGenerator()->create_user();

        // Create 2 exercise rounds.
        $generator = $this->getDataGenerator()->get_plugin_generator('mod_adastra');
        $rounddata = array(
            'course' => $this->course->id,
            'name' => '1. Test round 1',
            'remotekey' => 'testround1',
            'openingtime' => $this->timenow,
            'closingtime' => $this->timenow + 3600 * 24 * 7,
            'ordernum' => 1,
            'status' => exercise_round::STATUS_READY,
            'pointstopass' => 0,
            'latesbmsallowed' => 1,
            'latesbmsdl' => $this->timenow + 3600 * 24 * 14,
            'latesbmspenalty' => 0.4,
        );
        $record = $generator->create_instance($rounddata); // std_class record
        $this->round1 = new exercise_round($record);
        $rounddata = array(
            'course' => $this->course->id,
            'name' => '2. Test round 2',
            'remotekey' => 'testround2',
            'openingtime' => $this->timenow,
            'closingtime' => $this->timenow + 3600 * 24 * 7,
            'ordernum' => 2,
            'status' => exercise_round::STATUS_READY,
            'pointstopass' => 0,
            'latesbmsallowed' => 1,
            'latesbmsdl' => $this->timenow + 3600 * 24 * 14,
            'latesbmspenalty' => 0.4,
        );
        $record = $generator->create_instance($rounddata); // std_class record
        $this->round2 = new exercise_round($record);

        // Create category.
        $this->category = category::create_from_id(category::create_new((object) array(
            'course' => $this->course->id,
            'status' => category::STATUS_READY,
            'name' => 'Testing exercises',
            'pointstopass' => 0,
        )));

        // Create exercises.
        $this->exercises = array();
        $this->exercises[] = $this->add_exercise(array(), $this->round1, $this->category);
        $this->exercises[] = $this->add_exercise(array('parentid' => $this->exercises[0]->get_id()), $this->round1, $this->category);
        $this->exercises[] = $this->add_exercise(array('parentid' => $this->exercises[0]->get_id()), $this->round1, $this->category);
        $this->exercises[] = $this->add_exercise(array('parentid' => $this->exercises[2]->get_id()), $this->round1, $this->category);
        $this->exercises[] = $this->add_exercise(array(), $this->round1, $this->category);
        $this->exercises[] = $this->add_exercise(array(), $this->round1, $this->category);

        // Create submissions.
        $this->submissions = array();
        $this->submissions[] = submission::create_from_id(
            submission::create_new_submission($this->exercises[0], $this->student->id));
        $this->submissions[] = submission::create_from_id(
            submission::create_new_submission($this->exercises[0], $this->student->id,
                    null, submission::STATUS_ERROR));
        $this->submissions[] = submission::create_from_id(
            submission::create_new_submission($this->exercises[0], $this->student2->id));
    }

    protected function add_exercise(array $data, exercise_round $round, category $category) {
        static $counter = 0;
        ++$counter;
        $defaults = array(
            'status' => learning_object::STATUS_READY,
            'parentid' => null,
            'ordernum' => $counter,
            'remotekey' => "testexercise$counter",
            'name' => "Exercise $counter",
            'serviceurl' => 'http://localhost',
            'maxsubmissions' => 3,
            'pointstopass' => 5,
            'maxpoints' => 10,
        );
        foreach ($defaults as $key => $val) {
            if (!isset($data[$key])) {
                $data[$key] = $val;
            }
        }
        return $round->create_new_exercise((object) $data, $category);
    }

    protected function add_submission($exerciseorid, $submitterorid, array $data=null) {
        global $DB;

        static $counter = 0;
        ++$counter;

        if (!is_int($exerciseorid) && !is_numeric($exerciseorid)) {
            $exerciseorid = $exerciseorid->get_id();
        }
        $exerciseorid = (int) $exerciseorid;
        if (!is_int($submitterorid) && !is_numeric($submitterorid)) {
            $submitterorid = $submitterorid->id;
        }
        if ($data === null) {
            $data = array();
        }

        $defaults = array(
        'status' => submission::STATUS_READY,
        'submissiontime' => $this->timenow + $counter,
        'hash' => submission::get_random_string(),
        'exerciseid' => $exerciseorid,
        'submitter' => $submitterorid,
        'feedback' => 'test feedback',
        'grade' => 0,
        'gradingtime' => $this->timenow + $counter + 1,
        'servicepoints' => 0,
        'servicemaxpoints' => 10,
        );
        foreach ($defaults as $key => $val) {
            if (!isset($data[$key])) {
                $data[$key] = $val;
            }
        }
        $id = $DB->insert_record(submission::TABLE, (object) $data);
        return submission::create_from_id($id);
    }
}

