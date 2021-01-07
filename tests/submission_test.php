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

namespace mod_adastra\local;

defined('MOODLE_INTERNAL') || die();

require_once(dirname(__FILE__) .'/exercise_test_data.php');

/**
 * Unit tests for submission.
 * @group mod_adastra
 */
class submission_testcase extends \advanced_testcase {

    use exercise_test_data;

    private $tmpfiles = array();
    protected $timenow;

    public function setUp() {
        $this->timenow = time();
        $this->add_test_data();
    }

    public function test_create_new_submission() {
        global $DB;

        $this->resetAfterTest(true);

        $sid1 = \mod_adastra\local\data\submission::create_new_submission($this->exercises[1], $this->student->id);
        $sid2 = \mod_adastra\local\data\submission::create_new_submission($this->exercises[1], $this->student2->id, array(
                'somekey' => 17,
        ));

        $this->assertNotEquals(0, $sid1);
        $this->assertNotEquals(0, $sid2);
        $sbms1 = $DB->get_record(\mod_adastra\local\data\submission::TABLE, array('id' => $sid1));
        $sbms2 = $DB->get_record(\mod_adastra\local\data\submission::TABLE, array('id' => $sid2));
        $this->assertTrue($sbms1 !== false);
        $this->assertTrue($sbms2 !== false);

        $sbms1 = new \mod_adastra\local\data\submission($sbms1);
        $sbms2 = new \mod_adastra\local\data\submission($sbms2);
        $this->assertEquals(\mod_adastra\local\data\submission::STATUS_INITIALIZED, $sbms1->get_status());
        $this->assertEquals(\mod_adastra\local\data\submission::STATUS_INITIALIZED, $sbms2->get_status());
        $this->assertNotEquals($sbms1->get_hash(), $sbms2->get_hash());
        $this->assertEquals($this->exercises[1]->get_id(), $sbms1->get_exercise()->get_id());
        $this->assertEquals($this->exercises[1]->get_id(), $sbms2->get_exercise()->get_id());
        $this->assertEquals($this->student->id, $sbms1->get_submitter()->id);
        $this->assertEquals($this->student2->id, $sbms2->get_submitter()->id);
        $this->assertEquals(0, $sbms1->get_grade());
        $this->assertEquals(0, $sbms2->get_grade());
        $this->assertNull($sbms1->get_feedback());
        $this->assertNull($sbms2->get_feedback());

        $this->assertNull($sbms1->get_submission_data());
        $s2sbmsdata = $sbms2->get_submission_data();
        $this->assertNotEmpty($s2sbmsdata);
        $this->assertEquals(17, $s2sbmsdata->somekey);
    }

    public function test_safe_file_name() {
        $this->resetAfterTest(true);

        $this->assertEquals('myfile.txt', \mod_adastra\local\data\submission::safe_file_name('myfile.txt'));
        $this->assertEquals('myfile.txt', \mod_adastra\local\data\submission::safe_file_name('ÄÄÄööömyfile.txt'));
        $this->assertEquals('_myfile.txt', \mod_adastra\local\data\submission::safe_file_name('-myfile.txt'));
        $this->assertEquals('myfile.txt.', \mod_adastra\local\data\submission::safe_file_name('myfile.txt.ååå'));
        $this->assertEquals('myfile4567.txt', \mod_adastra\local\data\submission::safe_file_name('myfile4567.txt'));
        $this->assertEquals('file', \mod_adastra\local\data\submission::safe_file_name('ääööö'));
        $this->assertEquals('_myfile.txt', \mod_adastra\local\data\submission::safe_file_name('äää-myfile.txt'));
        $this->assertEquals('my_file.txt', \mod_adastra\local\data\submission::safe_file_name('my_file.txt'));
        $this->assertEquals('myfile.txt', \mod_adastra\local\data\submission::safe_file_name('ääämyfileöööö.txt'));
    }

    public function test_add_submitted_file() {
        $this->resetAfterTest(true);

        $this->tmp_files = array(); // create temp files in the filesystem, they must be removed later
        for ($i = 1; $i <= 3; ++$i) {
            $tmpfilepath = tempnam(sys_get_temp_dir(), 'tmp');
            file_put_contents($tmpfilepath, 'Some submission file content '. $i);
            $this->tmp_files[] = $tmpfilepath;

            $this->submissions[0]->add_submitted_file("mycode$i.java", "exercise$i", $tmpfilepath);
        }

        $fs = get_file_storage();
        $files = $fs->get_area_files(\context_module::instance($this->submissions[0]->get_exercise()->get_exercise_round()->get_course_module()->id)->id,
                \mod_adastra\local\data\exercise_round::MODNAME, \mod_adastra\local\data\submission::SUBMITTED_FILES_FILEAREA,
                $this->submissions[0]->get_id(),
                'itemid, filepath, filename', false);

        $this->assertEquals(3, count($files));
        $files = array_values($files);
        $i = 1;
        while ($i <= 3) {
            $this->assertEquals("mycode$i.java", $files[$i - 1]->get_filename());
            $this->assertEquals("Some submission file content $i", $files[$i - 1]->get_content());
            $this->assertEquals("/exercise$i/", $files[$i - 1]->get_filepath());
            ++$i;
        }

        // Remove temp files.
        foreach ($this->tmp_files as $tmpfile) {
            @unlink($tmpfile);
        }
        $this->tmp_files = array();
        // Method tear_down removes the files if this method is interrupted by an assertion error.
    }

    public function tear_down() {
        foreach ($this->tmp_files as $tmpfile) {
            @unlink($tmpfile);
        }
    }

    public function test_get_submitted_files() {
        $this->resetAfterTest(true);

        $this->tmp_files = array(); // create temp files in the filesystem, they must be removed later
        for ($i = 1; $i <= 3; ++$i) {
            $tmpfilepath = tempnam(sys_get_temp_dir(), 'tmp');
            file_put_contents($tmpfilepath, 'Some submission file content '. $i);
            $this->tmp_files[] = $tmpfilepath;

            $this->submissions[0]->add_submitted_file("mycode$i.java", "exercise$i", $tmpfilepath);
        }

        $files = $this->submissions[0]->get_submitted_files();

        $this->assertEquals(3, count($files));
        $files = array_values($files);
        $i = 1;
        while ($i <= 3) {
            $this->assertEquals("mycode$i.java", $files[$i - 1]->get_filename());
            $this->assertEquals("Some submission file content $i", $files[$i - 1]->get_content());
            $this->assertEquals("/exercise$i/", $files[$i - 1]->get_filepath());
            ++$i;
        }

        // Remove temp files.
        foreach ($this->tmp_files as $tmpfile) {
            @unlink($tmpfile);
        }
        $this->tmp_files = array();
        // Method tear_down removes the files if this method is interrupted by an assertion error.
    }

    public function test_grade() {
        global $CFG;
        require_once($CFG->libdir .'/gradelib.php');

        $this->resetAfterTest(true);

        $this->submissions[0]->grade(80, 100, 'Good feedback', array('extra' => 8));
        $sbms = \mod_adastra\local\data\submission::create_from_id($this->submissions[0]->get_id());
        // $this->assertEquals(8, $sbms->get_grade()); // in helper method.
        // $this->assertEquals('Good feedback', $sbms->get_feedback());.
        $this->assertNotEmpty($sbms->get_grading_data());
        $this->assertEquals(8, $sbms->get_grading_data()->extra);
        $this->assertEquals($this->student->id, $sbms->get_submitter()->id);
        $this->assertEmpty($sbms->get_assistant_feedback());
        $this->assertEquals(80, $sbms->get_service_points());
        $this->assertEquals(100, $sbms->get_service_max_points());
        $this->grade_test_helper($sbms, 8, 8, 8, 'Good feedback');

        // New third submission.
        $sbms = \mod_adastra\local\data\submission::create_from_id(
                \mod_adastra\local\data\submission::create_new_submission($this->exercises[0], $this->student->id, null,
                        \mod_adastra\local\data\submission::STATUS_INITIALIZED,
                        $this->exercises[0]->get_exercise_round()->get_closing_time() - 3600 * 24));
        $sbms->grade(15, 15, 'Some feedback');
        $this->grade_test_helper($sbms, 10, 10, 10, 'Some feedback');

        // New fourth submission, exceeds submission limit.
        $sbms = \mod_adastra\local\data\submission::create_from_id(
                \mod_adastra\local\data\submission::create_new_submission($this->exercises[0], $this->student->id, null,
                        \mod_adastra\local\data\submission::STATUS_INITIALIZED,
                        $this->exercises[0]->get_exercise_round()->get_closing_time() - 3600 * 12));
        $sbms->grade(17, 17, 'Some other feedback');
        $this->grade_test_helper($sbms, 0, 10, 10, 'Some other feedback');

        // Grade again, ignore deadline but submission limit is still active.
        $sbms->grade(10, 10, 'Some feedback', null, true);
        $this->grade_test_helper($sbms, 0, 10, 10, 'Some feedback');

        // Add submission limit deviation.
        \mod_adastra\local\data\submission_limit_deviation::create_new($sbms->get_exercise()->get_id(), $this->student->id, 1);
        $sbms->grade(17, 17, 'Some feedback 2');
        $this->grade_test_helper($sbms, 10, 10, 10, 'Some feedback 2');

        // New submission, different exercise.
        $sbms = \mod_adastra\local\data\submission::create_from_id(
                \mod_adastra\local\data\submission::create_new_submission($this->exercises[1], $this->student->id, null,
                        \mod_adastra\local\data\submission::STATUS_INITIALIZED,
                        $this->exercises[1]->get_exercise_round()->get_late_submission_deadline() + 3600)); // Late from late deadline.
        $sbms->grade(10, 10, 'Some feedback 3');
        $this->grade_test_helper($sbms, 0, 0, 10, 'Some feedback 3');

        // Different student, late.
        $sbms = \mod_adastra\local\data\submission::create_from_id(
                \mod_adastra\local\data\submission::create_new_submission($this->exercises[0], $this->student2->id, null,
                        \mod_adastra\local\data\submission::STATUS_INITIALIZED,
                        $this->exercises[0]->get_exercise_round()->get_closing_time() + 3600)); // Late.
        $sbms->grade(10, 10, 'Some feedback');
        $this->grade_test_helper($sbms, 6, 6, 6, 'Some feedback');

        // Another exercise, check round total grade.
        $sbms = \mod_adastra\local\data\submission::create_from_id(
                \mod_adastra\local\data\submission::create_new_submission($this->exercises[1], $this->student2->id, null,
                        \mod_adastra\local\data\submission::STATUS_INITIALIZED,
                        $this->exercises[0]->get_exercise_round()->get_closing_time() - 3600 * 24));
        $sbms->grade(20, 20, 'Some new feedback');
        $this->grade_test_helper($sbms, 10, 10, 16, 'Some new feedback');
    }

    protected function grade_test_helper(\mod_adastra\local\data\submission $sbms, $expectedgrade,
            $expectedbestgrade, $expectedroundgrade, $expectedfeedback) {

        $this->assertEquals($expectedgrade, $sbms->get_grade());
        $this->assertEquals($expectedfeedback, $sbms->get_feedback());
        $this->assertEquals(
            $expectedbestgrade,
            $sbms->get_exercise()->get_best_submission_for_student($sbms->get_submitter()->id)->get_grade()
        );
        // Gradebook.
        $gradeitems = grade_get_grades($this->course->id, 'mod', \mod_adastra\local\data\exercise_round::TABLE,
                $sbms->get_exercise()->get_exercise_round()->get_id(), $sbms->get_submitter()->id)->items;
        $this->assertEquals($expectedroundgrade,
                $gradeitems[0]->grades[$sbms->get_submitter()->id]->grade); // Round total.
    }

    public function test_delete_with_files() {
        global $DB, $CFG;
        require_once($CFG->libdir .'/gradelib.php');

        $this->resetAfterTest(true);

        // Create new (the only) submission for an exercise.
        $sbms = \mod_adastra\local\data\submission::create_from_id(
                \mod_adastra\local\data\submission::create_new_submission($this->exercises[1], $this->student->id, null,
                        \mod_adastra\local\data\submission::STATUS_INITIALIZED,
                        $this->exercises[1]->get_exercise_round()->get_closing_time() - 3600 * 24));
        $this->tmp_files = array(); // Create temp files in the filesystem, they must be removed later.
        for ($i = 1; $i <= 3; ++$i) {
            $tmpfilepath = tempnam(sys_get_temp_dir(), 'tmp');
            file_put_contents($tmpfilepath, 'Some submission file content '. $i);
            $this->tmp_files[] = $tmpfilepath;

            $sbms->add_submitted_file("mycode$i.java", "exercise$i", $tmpfilepath);
        }

        $sbms->grade(50, 100, 'Great feedback');
        $sbms->delete();

        $fetchedsbms = $DB->get_record(\mod_adastra\local\data\submission::TABLE, array('id' => $sbms->get_id()));
        $this->assertFalse($fetchedsbms);
        $fs = get_file_storage();
        $files = $fs->get_area_files(\context_module::instance($sbms->get_exercise()->get_exercise_round()->get_course_module()->id)->id,
                \mod_adastra\local\data\exercise_round::MODNAME, \mod_adastra\local\data\submission::SUBMITTED_FILES_FILEAREA,
                $sbms->get_id(),
                'itemid, filepath, filename', false);
        $this->assertEmpty($files);
        // Gradebook.
        $gradeitems = grade_get_grades($this->course->id, 'mod', \mod_adastra\local\data\exercise_round::TABLE,
                $sbms->get_exercise()->get_exercise_round()->get_id(), $sbms->get_submitter()->id)->items;
        $this->assertEquals(0,
                $gradeitems[0]->grades[$sbms->get_submitter()->id]->grade);

        // Remove temp files.
        foreach ($this->tmp_files as $tmpfile) {
            @unlink($tmpfile);
        }
        $this->tmp_files = array();
        // Method tear_down removes the files if this method is interrupted by an assertion error.
    }

    public function test_delete() {
        global $DB, $CFG;
        require_once($CFG->libdir .'/gradelib.php');

        $this->resetAfterTest(true);

        // Grade a submission.
        $this->submissions[0]->grade(50, 100, 'First feedback');
        // Create new submission and grade it better.
        $sbms = \mod_adastra\local\data\submission::create_from_id(
                \mod_adastra\local\data\submission::create_new_submission($this->exercises[0], $this->student->id, null,
                        \mod_adastra\local\data\submission::STATUS_INITIALIZED,
                        $this->exercises[0]->get_exercise_round()->get_closing_time() - 3600 * 24));
        $sbms->grade(90, 100, 'Best feedback');
        // Gradebook should show these points as the best.
        $gradeitems = grade_get_grades($this->course->id, 'mod', \mod_adastra\local\data\exercise_round::TABLE,
                $sbms->get_exercise()->get_exercise_round()->get_id(), $sbms->get_submitter()->id)->items;
        $this->assertEquals(9, $gradeitems[0]->grades[$sbms->get_submitter()->id]->grade);
        // Delete the best submission.
        $sbms->delete();

        $fetchedsbms = $DB->get_record(\mod_adastra\local\data\submission::TABLE, array('id' => $sbms->get_id()));
        $this->assertFalse($fetchedsbms);
        // Gradebook should show the first points as the best.
        $gradeitems = grade_get_grades($this->course->id, 'mod', \mod_adastra\local\data\exercise_round::TABLE,
                $sbms->get_exercise()->get_exercise_round()->get_id(), $sbms->get_submitter()->id)->items;
        $this->assertEquals(5, $gradeitems[0]->grades[$sbms->get_submitter()->id]->grade);
    }

    public function test_gradebook() {
        global $DB, $CFG;
        require_once($CFG->libdir .'/gradelib.php');

        $this->resetAfterTest(true);

        $category2 = \mod_adastra\local\data\category::create_from_id(\mod_adastra\local\data\category::create_new((object) array(
                'course' => $this->course->id,
                'status' => \mod_adastra\local\data\category::STATUS_READY,
                'name' => 'Another category',
                'pointstopass' => 0,
        )));

        // Create exercises.
        $exercisesround2 = array();
        $exercisesround2[] = $this->add_exercise(array('maxpoints' => 15), $this->round2, $category2);
        $exercisesround2[] = $this->add_exercise(array('parentid' => $exercisesround2[0]->get_id()), $this->round2, $this->category);
        $exercisesround2[] = $this->add_exercise(array('parentid' => $exercisesround2[0]->get_id()), $this->round2, $this->category);
        $exercisesround2[] = $this->add_exercise(array('parentid' => $exercisesround2[1]->get_id()), $this->round2, $this->category);
        $exercisesround2[] = $this->add_exercise(array(), $this->round2, $this->category);
        $exercisesround2[] = $this->add_exercise(array(), $this->round2, $category2);

        $now = time();
        $submissionids = array();

        // Create more submissions.
        $submissionids[] = $DB->insert_record(\mod_adastra\local\data\submission::TABLE, (object) array(
            'status' => \mod_adastra\local\data\submission::STATUS_READY,
            'submissiontime' => $now + 1,
            'hash' => \mod_adastra\local\data\submission::get_random_string(),
            'exerciseid' => $this->exercises[0]->get_id(),
            'submitter' => $this->student->id,
            'feedback' => 'test feedback',
            'grade' => 7,
            'gradingtime' => $now + 1,
            'servicepoints' => 7,
            'servicemaxpoints' => 10,
        ));
        $submissionids[] = $DB->insert_record(\mod_adastra\local\data\submission::TABLE, (object) array(
            'status' => \mod_adastra\local\data\submission::STATUS_READY,
            'submissiontime' => $now + 2,
            'hash' => \mod_adastra\local\data\submission::get_random_string(),
            'exerciseid' => $this->exercises[0]->get_id(),
            'submitter' => $this->student2->id,
            'feedback' => 'test feedback',
            'grade' => 8,
            'gradingtime' => $now + 2,
            'servicepoints' => 8,
            'servicemaxpoints' => 10,
        ));
        $submissionids[] = $DB->insert_record(\mod_adastra\local\data\submission::TABLE, (object) array(
            'status' => \mod_adastra\local\data\submission::STATUS_READY,
            'submissiontime' => $now + 3,
            'hash' => \mod_adastra\local\data\submission::get_random_string(),
            'exerciseid' => $this->exercises[0]->get_id(),
            'submitter' => $this->student2->id,
            'feedback' => 'test feedback',
            'grade' => 1,
            'gradingtime' => $now + 3,
            'servicepoints' => 1,
            'servicemaxpoints' => 10,
        ));
        // Student 7, student2 8.
        // $this->exercises[1].
        $submissionids[] = $DB->insert_record(\mod_adastra\local\data\submission::TABLE, (object) array(
            'status' => \mod_adastra\local\data\submission::STATUS_READY,
            'submissiontime' => $now + 4,
            'hash' => \mod_adastra\local\data\submission::get_random_string(),
            'exerciseid' => $this->exercises[1]->get_id(),
            'submitter' => $this->student->id,
            'feedback' => 'test feedback',
            'grade' => 0,
            'gradingtime' => $now + 4,
            'servicepoints' => 0,
            'servicemaxpoints' => 10,
        ));
        $submissionids[] = $DB->insert_record(\mod_adastra\local\data\submission::TABLE, (object) array(
            'status' => \mod_adastra\local\data\submission::STATUS_READY,
            'submissiontime' => $now + 5,
            'hash' => \mod_adastra\local\data\submission::get_random_string(),
            'exerciseid' => $this->exercises[1]->get_id(),
            'submitter' => $this->student->id,
            'feedback' => 'test feedback',
            'grade' => 10,
            'gradingtime' => $now + 5,
            'servicepoints' => 10,
            'servicemaxpoints' => 10,
        ));
        $submissionids[] = $DB->insert_record(\mod_adastra\local\data\submission::TABLE, (object) array(
            'status' => \mod_adastra\local\data\submission::STATUS_READY,
            'submissiontime' => $now + 6,
            'hash' => \mod_adastra\local\data\submission::get_random_string(),
            'exerciseid' => $this->exercises[1]->get_id(),
            'submitter' => $this->student->id,
            'feedback' => 'test feedback',
            'grade' => 9,
            'gradingtime' => $now + 6,
            'servicepoints' => 9,
            'servicemaxpoints' => 10,
        ));
        $submissionids[] = $DB->insert_record(\mod_adastra\local\data\submission::TABLE, (object) array(
            'status' => \mod_adastra\local\data\submission::STATUS_READY,
            'submissiontime' => $now + 7,
            'hash' => \mod_adastra\local\data\submission::get_random_string(),
            'exerciseid' => $this->exercises[1]->get_id(),
            'submitter' => $this->student2->id,
            'feedback' => 'test feedback',
            'grade' => 6,
            'gradingtime' => $now + 7,
            'servicepoints' => 6,
            'servicemaxpoints' => 10,
        ));
        $submissionids[] = $DB->insert_record(\mod_adastra\local\data\submission::TABLE, (object) array(
            'status' => \mod_adastra\local\data\submission::STATUS_READY,
            'submissiontime' => $now + 8,
            'hash' => \mod_adastra\local\data\submission::get_random_string(),
            'exerciseid' => $this->exercises[1]->get_id(),
            'submitter' => $this->student2->id,
            'feedback' => 'test feedback',
            'grade' => 5,
            'gradingtime' => $now + 8,
            'servicepoints' => 5,
            'servicemaxpoints' => 10,
        ));
        // Student 17, student2 14.
        // $this->exercises[3].
        $submissionids[] = $DB->insert_record(\mod_adastra\local\data\submission::TABLE, (object) array(
            'status' => \mod_adastra\local\data\submission::STATUS_READY,
            'submissiontime' => $now + 9,
            'hash' => \mod_adastra\local\data\submission::get_random_string(),
            'exerciseid' => $this->exercises[3]->get_id(),
            'submitter' => $this->student->id,
            'feedback' => 'test feedback',
            'grade' => 2,
            'gradingtime' => $now + 9,
            'servicepoints' => 2,
            'servicemaxpoints' => 10,
        ));
        $submissionids[] = $DB->insert_record(\mod_adastra\local\data\submission::TABLE, (object) array(
            'status' => \mod_adastra\local\data\submission::STATUS_READY,
            'submissiontime' => $now + 10,
            'hash' => \mod_adastra\local\data\submission::get_random_string(),
            'exerciseid' => $this->exercises[3]->get_id(),
            'submitter' => $this->student->id,
            'feedback' => 'test feedback',
            'grade' => 3,
            'gradingtime' => $now + 10,
            'servicepoints' => 3,
            'servicemaxpoints' => 10,
        ));
        $submissionids[] = $DB->insert_record(\mod_adastra\local\data\submission::TABLE, (object) array(
            'status' => \mod_adastra\local\data\submission::STATUS_READY,
            'submissiontime' => $now + 11,
            'hash' => \mod_adastra\local\data\submission::get_random_string(),
            'exerciseid' => $this->exercises[3]->get_id(),
            'submitter' => $this->student2->id,
            'feedback' => 'test feedback',
            'grade' => 4,
            'gradingtime' => $now + 11,
            'servicepoints' => 4,
            'servicemaxpoints' => 10,
        ));
        // Student 20, student2 18.
        // $exercisesround2[0].
        $submissionids[] = $DB->insert_record(\mod_adastra\local\data\submission::TABLE, (object) array(
            'status' => \mod_adastra\local\data\submission::STATUS_READY,
            'submissiontime' => $now + 12,
            'hash' => \mod_adastra\local\data\submission::get_random_string(),
            'exerciseid' => $exercisesround2[0]->get_id(),
            'submitter' => $this->student->id,
            'feedback' => 'test feedback',
            'grade' => 11,
            'gradingtime' => $now + 12,
            'servicepoints' => 11,
            'servicemaxpoints' => 15,
        ));
        $submissionids[] = $DB->insert_record(\mod_adastra\local\data\submission::TABLE, (object) array(
            'status' => \mod_adastra\local\data\submission::STATUS_READY,
            'submissiontime' => $now + 13,
            'hash' => \mod_adastra\local\data\submission::get_random_string(),
            'exerciseid' => $exercisesround2[0]->get_id(),
            'submitter' => $this->student->id,
            'feedback' => 'test feedback',
            'grade' => 10,
            'gradingtime' => $now + 13,
            'servicepoints' => 10,
            'servicemaxpoints' => 15,
        ));
        $submissionids[] = $DB->insert_record(\mod_adastra\local\data\submission::TABLE, (object) array(
            'status' => \mod_adastra\local\data\submission::STATUS_READY,
            'submissiontime' => $now + 14,
            'hash' => \mod_adastra\local\data\submission::get_random_string(),
            'exerciseid' => $exercisesround2[0]->get_id(),
            'submitter' => $this->student2->id,
            'feedback' => 'test feedback',
            'grade' => 3,
            'gradingtime' => $now + 14,
            'servicepoints' => 3,
            'servicemaxpoints' => 15,
        ));
        // Student 11, student2 3.
        // $exercisesround2[1].
        $submissionids[] = $DB->insert_record(\mod_adastra\local\data\submission::TABLE, (object) array(
            'status' => \mod_adastra\local\data\submission::STATUS_READY,
            'submissiontime' => $now + 15,
            'hash' => \mod_adastra\local\data\submission::get_random_string(),
            'exerciseid' => $exercisesround2[1]->get_id(),
            'submitter' => $this->student->id,
            'feedback' => 'test feedback',
            'grade' => 7,
            'gradingtime' => $now + 15,
            'servicepoints' => 7,
            'servicemaxpoints' => 10,
        ));
        // Student 18, student2 3.
        // $exercisesround2[2].
        $submissionids[] = $DB->insert_record(\mod_adastra\local\data\submission::TABLE, (object) array(
            'status' => \mod_adastra\local\data\submission::STATUS_READY,
            'submissiontime' => $now + 16,
            'hash' => \mod_adastra\local\data\submission::get_random_string(),
            'exerciseid' => $exercisesround2[2]->get_id(),
            'submitter' => $this->student2->id,
            'feedback' => 'test feedback',
            'grade' => 0,
            'gradingtime' => $now + 16,
            'servicepoints' => 0,
            'servicemaxpoints' => 10,
        ));
        // Student 18, student2 3.
        // $exercisesround2[3].
        $submissionids[] = $DB->insert_record(\mod_adastra\local\data\submission::TABLE, (object) array(
            'status' => \mod_adastra\local\data\submission::STATUS_READY,
            'submissiontime' => $now + 17,
            'hash' => \mod_adastra\local\data\submission::get_random_string(),
            'exerciseid' => $exercisesround2[3]->get_id(),
            'submitter' => $this->student2->id,
            'feedback' => 'test feedback',
            'grade' => 6,
            'gradingtime' => $now + 17,
            'servicepoints' => 6,
            'servicemaxpoints' => 10,
        ));
        $submissionids[] = $DB->insert_record(\mod_adastra\local\data\submission::TABLE, (object) array(
            'status' => \mod_adastra\local\data\submission::STATUS_READY,
            'submissiontime' => $now + 18,
            'hash' => \mod_adastra\local\data\submission::get_random_string(),
            'exerciseid' => $exercisesround2[3]->get_id(),
            'submitter' => $this->student2->id,
            'feedback' => 'test feedback',
            'grade' => 4,
            'gradingtime' => $now + 18,
            'servicepoints' => 4,
            'servicemaxpoints' => 10,
        ));
        $submissionids[] = $DB->insert_record(\mod_adastra\local\data\submission::TABLE, (object) array(
            'status' => \mod_adastra\local\data\submission::STATUS_WAITING,
            'submissiontime' => $now + 19,
            'hash' => \mod_adastra\local\data\submission::get_random_string(),
            'exerciseid' => $exercisesround2[3]->get_id(),
            'submitter' => $this->student->id,
            'feedback' => null,
            'grade' => 0,
            'gradingtime' => $now + 19,
            'servicepoints' => 0,
            'servicemaxpoints' => 0,
        ));
        // Student 18, student2 9.

        // Update gradebook.
        $this->round1->write_all_grades_to_gradebook($this->student->id);
        $this->round2->write_all_grades_to_gradebook($this->student->id);
        $this->round1->write_all_grades_to_gradebook($this->student2->id);
        $this->round2->write_all_grades_to_gradebook($this->student2->id);

        // Check that the gradebook has correct grades.
        $gradinginfo1 = grade_get_grades(
            $this->course->id,
            'mod',
            \mod_adastra\local\data\exercise_round::TABLE,
            $this->round1->get_id(),
            array(
                $this->student->id,
                $this->student2->id
            )
        );
        $gradinginfo2 = grade_get_grades(
            $this->course->id,
            'mod',
            \mod_adastra\local\data\exercise_round::TABLE,
            $this->round2->get_id(),
            array(
                $this->student->id,
                $this->student2->id
            )
        );
        $items1 = $gradinginfo1->items;
        $items2 = $gradinginfo2->items;
        $this->assertEquals(1, count($items1)); // Only the exercise round has a grade item.
        $this->assertEquals(1, count($items2));
        $this->assertEquals(20, $items1[0]->grades[$this->student->id]->grade);
        $this->grade_test_helper(
            \mod_adastra\local\data\submission::create_from_id($submissionids[0]), // Student.
            7, // Expected submission grade.
            7, // Expected best exercise grade.
            20, // Expected exercise round grade.
            'test feedback'
        );
        $this->grade_test_helper(
            \mod_adastra\local\data\submission::create_from_id($submissionids[2]), // Student2.
            1,
            8,
            18,
            'test feedback'
        );
        $this->grade_test_helper(
            \mod_adastra\local\data\submission::create_from_id($submissionids[11]),
            11,
            11,
            18,
            'test feedback'
        );
        $this->grade_test_helper(
            \mod_adastra\local\data\submission::create_from_id($submissionids[17]), // Student2.
            4,
            6,
            9,
            'test feedback'
        );

        // Delete grades from the gradebook and write grades again for everyone.
        grade_update(
            'mod/'. \mod_adastra\local\data\exercise_round::TABLE,
            $this->course->id,
            'mod',
            \mod_adastra\local\data\exercise_round::TABLE,
            $this->round1->get_id(),
            0,
            null,
            array('reset' => true)
        );
        grade_update(
            'mod/'. \mod_adastra\local\data\exercise_round::TABLE,
            $this->course->id,
            'mod',
            \mod_adastra\local\data\exercise_round::TABLE,
            $this->round2->get_id(),
            0,
            null,
            array('reset' => true)
        );
        $this->round1->write_all_grades_to_gradebook();
        $this->round2->write_all_grades_to_gradebook();
        $gradinginfo1 = grade_get_grades(
            $this->course->id,
            'mod',
            \mod_adastra\local\data\exercise_round::TABLE,
            $this->round1->get_id(),
            array(
                $this->student->id,
                $this->student2->id
            )
        );
        $gradinginfo2 = grade_get_grades(
            $this->course->id,
            'mod',
            \mod_adastra\local\data\exercise_round::TABLE,
            $this->round2->get_id(),
            array(
                $this->student->id,
                $this->student2->id
            )
        );
        $items1 = $gradinginfo1->items;
        $items2 = $gradinginfo2->items;
        $this->assertEquals(1, count($items1)); // Only the exercise round has a grade item.
        $this->assertEquals(1, count($items2));
        $this->assertEquals(20, $items1[0]->grades[$this->student->id]->grade);
        $this->grade_test_helper(
            \mod_adastra\local\data\submission::create_from_id($submissionids[0]), // Student.
            7, // Expected submission grade.
            7, // Expected best exercise grade.
            20, // Expected exercise round grade.
            'test feedback'
        );
        $this->grade_test_helper(
            \mod_adastra\local\data\submission::create_from_id($submissionids[2]), // Student2.
            1,
            8,
            18,
            'test feedback'
        );
        $this->grade_test_helper(
            \mod_adastra\local\data\submission::create_from_id($submissionids[11]),
            11,
            11,
            18,
            'test feedback'
        );
        $this->grade_test_helper(
            \mod_adastra\local\data\submission::create_from_id($submissionids[17]), // Student2.
            4,
            6,
            9,
            'test feedback'
        );
    }

    public function test_gradebook_enrolled() {
        global $DB, $CFG;
        require_once($CFG->libdir .'/gradelib.php');

        $this->resetAfterTest(true);

        $user1 = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->enrol_user($user1->id, $this->course->id);
        // Create submissions.
        $submissions = array();
        $submissions[] = $this->add_submission($this->exercises[0], $user1, array(
            'grade' => 5,
        ));
        $this->round1->write_all_grades_to_gradebook($user1->id);
        $this->grade_test_helper($submissions[0], 5, 5, 5, 'test feedback');

        $submissions[] = $this->add_submission($this->exercises[0], $user1, array(
            'grade' => 7,
        ));
        $this->round1->write_all_grades_to_gradebook();
        $this->grade_test_helper($submissions[1], 7, 7, 7, 'test feedback');

        $submissions[] = $this->add_submission($this->exercises[0], $user1, array(
            'grade' => 6,
        ));
        $this->round1->write_all_grades_to_gradebook($user1->id);
        $this->grade_test_helper($submissions[2], 6, 7, 7, 'test feedback');
    }
}
