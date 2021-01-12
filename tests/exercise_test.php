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

require_once(dirname(__FILE__) .'/exercise_test_data.php');

/**
 * Unit tests for exercise.
 * @group mod_adastra
 */
class exercise_testcase extends \advanced_testcase {

    use exercise_test_data;

    protected $timenow;

    public function setUp() {
        $this->timenow = time();
        $this->add_test_data();
    }

    public function test_is_submission_allowed() {
        $this->resetAfterTest(true);

        $this->assertTrue($this->exercises[0]->is_submission_allowed($this->student));
    }

    public function test_student_has_submissions_left() {
        $this->resetAfterTest(true);

        $this->assertTrue($this->exercises[0]->student_has_submissions_left($this->student));

        $thirdsbms = submission::create_from_id(
            submission::create_new_submission($this->exercises[0], $this->student->id));

        $this->assertFalse($this->exercises[0]->student_has_submissions_left($this->student));

        // Add submit limit deviation.
        submission_limit_deviation::create_new($this->exercises[0]->get_id(), $this->student->id, 1);

        $this->assertTrue($this->exercises[0]->student_has_submissions_left($this->student));
    }

    public function test_student_has_access() {
        $this->resetAfterTest(true);

        $this->assertTrue($this->exercises[0]->student_has_access($this->student, $this->round1->get_opening_time() + 3600));
        $this->assertFalse($this->exercises[0]->student_has_access($this->student, $this->round1->get_opening_time() - 3600));
        $this->assertTrue($this->exercises[0]->student_has_access($this->student, $this->round1->get_closing_time() + 3600));
        $this->assertFalse($this->exercises[0]->student_has_access($this->student, $this->round1->get_late_submission_deadline() + 3600));
        $this->assertTrue($this->exercises[0]->student_has_access($this->student, $this->round1->get_late_submission_deadline() - 3600));

        // Add deadline deviation.
        deadline_deviation::create_new($this->exercises[0]->get_id(), $this->student->id, 60 * 24 * 8, true);
        // 8-day extension exceeds the original late submission deadline too since it was 7 days from the closing time.

        $this->assertFalse($this->exercises[0]->student_has_access($this->student, $this->round1->get_opening_time() - 3600));
        $this->assertTrue($this->exercises[0]->student_has_access($this->student, $this->round1->get_closing_time() + 3600));
        $this->assertTrue($this->exercises[0]->student_has_access($this->student, $this->round1->get_late_submission_deadline() + 3600 * 23));
        $this->assertTrue($this->exercises[0]->student_has_access($this->student, $this->round1->get_late_submission_deadline() - 3600));
        $this->assertFalse($this->exercises[0]->student_has_access($this->student, $this->round1->get_late_submission_deadline() + 3600 * 25));
        $this->assertTrue($this->exercises[0]->student_has_access($this->student, $this->round1->get_late_submission_deadline() + 3600 * 24));
        // The previous line should hit the last second of allowed access.
        $this->assertFalse($this->exercises[0]->student_has_access($this->student, $this->round1->get_late_submission_deadline() + 3600 * 24 + 1));
    }

    public function test_upload_submission_to_service() {
        $this->resetAfterTest(true);
        // TODO this depends on the exercise service and could maybe be moved to a separate testcase class.
    }

    public function test_get_submissions_for_student() {
        $this->resetAfterTest(true);

        $tosbms = function($record) {
            return new submission($record);
        };

        $submissions = $this->exercises[0]->get_submissions_for_student($this->student->id, false, 'submissiontime ASC, status ASC');
        // The submissions probably have the same submissiontime when they are created in set_up().
        $submissionsarray = array_map($tosbms, iterator_to_array($submissions, false));
        $this->assertEquals(2, count($submissionsarray));
        $this->assertEquals($this->submissions[0]->get_id(), $submissionsarray[0]->get_id());
        $this->assertEquals($this->submissions[1]->get_id(), $submissionsarray[1]->get_id());
        $submissions->close();

        $submissions = $this->exercises[0]->get_submissions_for_student($this->student->id, true);
        $submissionsarray = array_map($tosbms, iterator_to_array($submissions, false));
        $this->assertEquals(1, count($submissionsarray));
        $this->assertEquals($this->submissions[0]->get_id(), $submissionsarray[0]->get_id());
        $submissions->close();
    }

    public function test_get_submission_count_for_student() {
        $this->resetAfterTest(true);

        $this->assertEquals(2, $this->exercises[0]->get_submission_count_for_student($this->student->id, false));
        $this->assertEquals(1, $this->exercises[0]->get_submission_count_for_student($this->student->id, true));
    }

    public function test_get_best_submission_for_student() {
        $this->resetAfterTest(true);

        $this->assertNull($this->exercises[1]->get_best_submission_for_student($this->student->id));

        $this->submissions[0]->grade(8, 10, 'Test feedback');

        $this->assertEquals($this->submissions[0]->get_id(),
                $this->exercises[0]->get_best_submission_for_student($this->student->id)->get_id());
    }

    public function test_get_parent_object() {
        $this->resetAfterTest(true);

        $this->assertNull($this->exercises[0]->get_parent_object());
        $this->assertEquals($this->exercises[0]->get_id(), $this->exercises[1]->get_parent_object()->get_id());
        $this->assertEquals($this->exercises[2]->get_id(), $this->exercises[3]->get_parent_object()->get_id());
        $this->assertNull($this->exercises[4]->get_parent_object());
    }

    public function test_get_children() {
        $this->resetAfterTest(true);

        $children = $this->exercises[0]->get_children();
        $this->assertEquals(2, count($children));
        $this->assertEquals($this->exercises[1]->get_id(), $children[0]->get_id());
        $this->assertEquals($this->exercises[2]->get_id(), $children[1]->get_id());

        $children = $this->exercises[3]->get_children();
        $this->assertEquals(0, count($children));

        $children = $this->exercises[4]->get_children();
        $this->assertEquals(0, count($children));
    }

    public function test_save() {
        $this->resetAfterTest(true);

        $ex = $this->exercises[0];
        $rec = $ex->get_record();
        $rec->status = learning_object::STATUS_MAINTENANCE;
        $rec->ordernum = 9;
        $rec->name = 'New exercise';
        $rec->maxpoints = 88;

        $ex->save();
        $ex = learning_object::create_from_id($ex->get_id());

        $this->assertEquals('1.9 New exercise', $ex->get_name());
        $this->assertEquals(9, $ex->get_order());
        $this->assertEquals(learning_object::STATUS_MAINTENANCE, $ex->get_status());
        $this->assertEquals(88, $ex->get_max_points());
    }

    public function test_delete() {
        global $DB, $CFG;
        require_once($CFG->libdir .'/gradelib.php');

        $this->resetAfterTest(true);

        $this->exercises[0]->delete_instance();

        // Exercises (learning objects), child objects.
        $this->assertFalse($DB->get_record(learning_object::TABLE, array('id' => $this->exercises[0]->get_id())));
        $this->assertFalse($DB->get_record(exercise::TABLE, array('id' => $this->exercises[0]->get_subtype_id())));

        $this->assertEquals(0, $DB->count_records(learning_object::TABLE, array('parentid' => $this->exercises[0]->get_id())));
        $this->assertFalse($DB->get_record(exercise::TABLE, array('id' => $this->exercises[1]->get_subtype_id())));
        $this->assertFalse($DB->get_record(exercise::TABLE, array('id' => $this->exercises[2]->get_subtype_id())));
        $this->assertFalse($DB->get_record(exercise::TABLE, array('id' => $this->exercises[3]->get_subtype_id())));
        $this->assertEquals(0, $DB->count_records(learning_object::TABLE, array('parentid' => $this->exercises[2]->get_id())));
        $this->assertEquals(0, $DB->count_records(chapter::TABLE)); // no chapters created in set_up

        // Submisssions, submitted files.
        $exerciseids = implode(',', array($this->exercises[0]->get_id(), $this->exercises[1]->get_id(),
                $this->exercises[2]->get_id(), $this->exercises[3]->get_id()));
        $this->assertEquals(0, $DB->count_records_select(submission::TABLE, "exerciseid IN ($exerciseids)"));
        $fs = get_file_storage();
        $this->assertTrue($fs->is_area_empty(\context_module::instance($this->round1->get_course_module()->id)->id,
                exercise_round::MODNAME, submission::SUBMITTED_FILES_FILEAREA,
                false, false));

        // Gradebook items.
        $gradeitems = grade_get_grades($this->course->id, 'mod', exercise_round::TABLE,
                $this->round1->get_id(), null)->items;
        $this->assertEquals(20, $gradeitems[0]->grademax); // round max points in gradebook
        $this->assertEquals(1, count($gradeitems));

        // Round max points.
        $this->assertEquals(20, $DB->get_field(exercise_round::TABLE, 'grade', array('id' => $this->round1->get_id())));
    }

    public function test_remove_n_oldest_submissions() {
        $this->resetAfterTest(true);

        // Create a new exercise.
        $exercise = $this->round1->create_new_exercise((object) array(
                'name' => '',
                'status' => learning_object::STATUS_READY,
                'parentid' => null,
                'ordernum' => 7,
                'remotekey' => "testexercise7",
                'serviceurl' => 'http://localhost',
                'maxsubmissions' => -2,
                'pointstopass' => 5,
                'maxpoints' => 10,
        ), $this->category);

        // Create submissions.
        submission::create_new_submission($exercise, $this->student->id,
                null, submission::STATUS_INITIALIZED,
                $this->round1->get_opening_time() + 60);
        submission::create_new_submission($exercise, $this->student->id,
                null, submission::STATUS_INITIALIZED,
                $this->round1->get_opening_time() + 61);
        submission::create_new_submission($exercise, $this->student->id,
                null, submission::STATUS_INITIALIZED,
                $this->round1->get_opening_time() + 62);
        submission::create_new_submission($exercise, $this->student->id,
                null, submission::STATUS_INITIALIZED,
                $this->round1->get_opening_time() + 63);
        submission::create_new_submission($exercise, $this->student->id,
                null, submission::STATUS_INITIALIZED,
                $this->round1->get_opening_time() + 64);
        $this->assertEquals(5, $exercise->get_submission_count_for_student($this->student->id));

        // Remove the two oldest submissions and run tests.
        $exercise->remove_n_oldest_submissions(2, $this->student->id);
        $this->assertEquals(3, $exercise->get_submission_count_for_student($this->student->id));
        $submissions = $exercise->get_submissions_for_student($this->student->id);
        // Check that the oldest submissions were removed.
        foreach ($submissions as $sbms) {
            $this->assertTrue($sbms->submissiontime >= $this->round1->get_opening_time() + 62);
        }
        $submissions->close();
        // Check that the submissions in an unrelated exercise were not affected.
        $this->assertEquals(2, $this->exercises[0]->get_submission_count_for_student($this->student->id));
    }

    public function test_remove_submissions_exceeding_store_limit() {
        $this->resetAfterTest(true);

        // Create a new exercise.
        $exercise = $this->round1->create_new_exercise((object) array(
                'name' => '',
                'status' => learning_object::STATUS_READY,
                'parentid' => null,
                'ordernum' => 7,
                'remotekey' => "testexercise7",
                'serviceurl' => 'http://localhost',
                'maxsubmissions' => -2,
                'pointstopass' => 5,
                'maxpoints' => 10,
        ), $this->category);

        // Create submissions.
        $sid = submission::create_new_submission($exercise, $this->student->id,
                null, submission::STATUS_INITIALIZED,
                $this->round1->get_opening_time() + 60);
        $submission = submission::create_from_id($sid);
        $submission->grade(5, 10, 'feedback');
        $sid = submission::create_new_submission($exercise, $this->student->id,
                null, submission::STATUS_INITIALIZED,
                $this->round1->get_opening_time() + 61);
        $submission = submission::create_from_id($sid);
        $submission->grade(6, 10, 'feedback');
        $sid = submission::create_new_submission($exercise, $this->student->id,
                null, submission::STATUS_INITIALIZED,
                $this->round1->get_opening_time() + 62);
        $submission = submission::create_from_id($sid);
        $submission->grade(7, 10, 'feedback');
        $sid = submission::create_new_submission($exercise, $this->student->id,
                null, submission::STATUS_INITIALIZED,
                $this->round1->get_opening_time() + 63);
        $submission = submission::create_from_id($sid);
        $submission->grade(6, 10, 'feedback');
        $sid = submission::create_new_submission($exercise, $this->student->id,
                null, submission::STATUS_INITIALIZED,
                $this->round1->get_opening_time() + 64);
        $submission = submission::create_from_id($sid);
        $submission->grade(4, 10, 'feedback');
        $this->assertEquals(5, $exercise->get_submission_count_for_student($this->student->id));
        $this->check_gradebook_grade(7, $exercise, $this->student);

        // Remove the submissions that exceed the limit and run tests.
        $exercise->remove_submissions_exceeding_store_limit($this->student->id);
        $this->assertEquals(2, $exercise->get_submission_count_for_student($this->student->id));
        $submissions = $exercise->get_submissions_for_student($this->student->id);
        // Check that the oldest submissions were removed.
        foreach ($submissions as $sbms) {
            $this->assertTrue($sbms->submissiontime >= $this->round1->get_opening_time() + 63);
        }
        $submissions->close();
        $this->check_gradebook_grade(6, $exercise, $this->student);
        // Check that the submissions in an unrelated exercise were not affected.
        $this->assertEquals(2, $this->exercises[0]->get_submission_count_for_student($this->student->id));

        // Remove excessive submissions again, but nothing should be removed this time.
        $exercise->remove_submissions_exceeding_store_limit($this->student->id);
        $this->assertEquals(2, $exercise->get_submission_count_for_student($this->student->id));
        $submissions = $exercise->get_submissions_for_student($this->student->id);
        // Check that the oldest submissions were removed.
        foreach ($submissions as $sbms) {
            $this->assertTrue($sbms->submissiontime >= $this->round1->get_opening_time() + 63);
        }
        $submissions->close();
        $this->check_gradebook_grade(6, $exercise, $this->student);
    }

    protected function check_gradebook_grade(int $expectedgrade, exercise $exercise, \stdClass $user) {
        global $CFG;
        require_once($CFG->libdir .'/gradelib.php');

        $gradeitems = grade_get_grades(
            $exercise->get_exercise_round()->get_course()->courseid,
            'mod',
            exercise_round::TABLE,
            $exercise->get_exercise_round()->get_id(),
            $user->id
        )->items;
        $gotgrade = isset($gradeitems[0]->grades[$user->id]->grade) ? $gradeitems[0]->grades[$user->id]->grade : null;
        $this->assertEquals($expectedgrade, $gotgrade);
    }
}