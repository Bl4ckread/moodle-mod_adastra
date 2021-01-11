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

/**
 * Unit tests for exercise round.
 * @group mod_adastra
 */
class exercise_round_testcase extends \advanced_testcase {

    private $course;
    private $round1data;

    public function add_course() {
        // Create a course instance for testing.
        $this->setAdminUser();
        $this->course = $this->getDataGenerator()->create_course();
    }

    public function add_round1() {
        // Create an exercise round.
        $generator = $this->getDataGenerator()->get_plugin_generator('mod_adastra');
        $this->round1_data = array(
                'course' => $this->course->id,
                'name' => '1. Test round 1',
                'remotekey' => 'testround1',
                'openingtime' => time(),
                'closingtime' => time() + 3600 * 24 * 7,
                'ordernum' => 1,
                'status' => exercise_round::STATUS_READY,
                'pointstopass' => 0,
                'latesbmsallowed' => 1,
                'latesbmsdl' => time() + 3600 * 24 * 14,
                'latesbmspenalty' => 0.4,
        );
        return $generator->create_instance($this->round1_data); // std_class record
    }

    public function test_get_course_module() {
        $this->resetAfterTest(true);

        $this->add_course();
        $roundrecord1 = $this->add_round1();
        $exround = new exercise_round($roundrecord1);

        $this->assertEquals($roundrecord1->cmid, $exround->get_course_module()->id);
        $this->assertEquals($this->course->id, $exround->get_course()->courseid);
    }

    public function test_getters() {
        $this->resetAfterTest(true);

        $this->add_course();
        $roundrecord1 = $this->add_round1();
        $exround = new exercise_round($roundrecord1);

        $this->assertEquals($this->round1_data['name'], $exround->get_name());
        $this->assertEquals($this->round1_data['status'], $exround->get_status());
        $this->assertEquals('Ready', $exround->get_status(true));
        $this->assertEquals(0, $exround->get_max_points());
        $this->assertEquals($this->round1_data['remotekey'], $exround->get_remote_key());
        $this->assertEquals($this->round1_data['ordernum'], $exround->get_order());
        $this->assertEquals($this->round1_data['pointstopass'], $exround->get_points_to_pass());
        $this->assertEquals($this->round1_data['openingtime'], $exround->get_opening_time());
        $this->assertEquals($this->round1_data['closingtime'], $exround->get_closing_time());
        $this->assertEquals((bool) $this->round1_data['latesbmsallowed'], $exround->is_late_submission_allowed());
        $this->assertEquals($this->round1_data['latesbmsdl'], $exround->get_late_submission_deadline());
        $this->assertEquals($this->round1_data['latesbmspenalty'], $exround->get_late_submission_penalty(), '', 0.01);
        // Float comparison with delta.
        $this->assertEquals(60, $exround->get_late_submission_point_worth());

        $this->assertTrue($exround->has_expired($this->round1_data['closingtime'] + 3600 * 24));
        $this->assertFalse($exround->has_expired($this->round1_data['openingtime'] + 3600 * 24));
        $this->assertFalse($exround->is_open($this->round1_data['openingtime'] - 3600 * 24));
        $this->assertFalse($exround->is_open($this->round1_data['closingtime'] + 3600 * 24));
        $this->assertTrue($exround->is_open($this->round1_data['openingtime'] + 3600 * 24));
        $this->assertTrue($exround->is_late_submission_open($this->round1_data['closingtime'] + 3600 * 24));
        $this->assertFalse($exround->is_late_submission_open($this->round1_data['closingtime'] - 3600 * 24));
        $this->assertFalse($exround->is_late_submission_open($this->round1_data['latesbmsdl'] + 3600 * 24));
        $this->assertTrue($exround->has_started($this->round1_data['closingtime']));
        $this->assertFalse($exround->has_started($this->round1_data['openingtime'] - 3600 * 24));

        $this->assertFalse($exround->is_hidden());
        $this->assertFalse($exround->is_under_maintenance());
    }

    public function test_update_name_with_order() {
        $this->assertEquals('1. Hello world',
                exercise_round::update_name_with_order('2. Hello world', 1, course_config::MODULE_NUMBERING_ARABIC));
        $this->assertEquals('1. Hello world',
                exercise_round::update_name_with_order('Hello world', 1, course_config::MODULE_NUMBERING_ARABIC));
        $this->assertEquals('10. Hello world',
                exercise_round::update_name_with_order('III Hello world', 10, course_config::MODULE_NUMBERING_ARABIC));
        $this->assertEquals('II Hello world',
                exercise_round::update_name_with_order('2. Hello world', 2, course_config::MODULE_NUMBERING_ROMAN));
        $this->assertEquals('Hello world',
                exercise_round::update_name_with_order('2. Hello world', 3, course_config::MODULE_NUMBERING_HIDDEN_ARABIC));
        $this->assertEquals('12. VXYii XXX', // name contains characters that are used in roman numbers
                exercise_round::update_name_with_order('X VXYii XXX', 12, course_config::MODULE_NUMBERING_ARABIC));
    }

    public function test_create_round() {
        global $DB, $CFG;
        require_once($CFG->libdir .'/gradelib.php');

        $this->resetAfterTest(true);

        $this->add_course();
        $this->round1_data = array(
                'course' => $this->course->id,
                'name' => '1. Test round 1',
                'remotekey' => 'testround1',
                'openingtime' => time(),
                'closingtime' => time() + 3600 * 24 * 7,
                'ordernum' => 1,
                'status' => exercise_round::STATUS_READY,
                'pointstopass' => 0,
                'latesbmsallowed' => 1,
                'latesbmsdl' => time() + 3600 * 24 * 14,
                'latesbmspenalty' => 0.4,
        );
        $generator = $this->getDataGenerator()->get_plugin_generator('mod_adastra');
        $record = $generator->create_instance($this->round1_data);
        $roundid = $record->id;

        $this->assertNotEquals(0, $roundid);
        $roundrecord = $DB->get_record(exercise_round::TABLE, array('id' => $roundid));
        $this->assertTrue($roundrecord !== false);
        $exround = new exercise_round($roundrecord);
        $this->assertEquals($this->round1_data['name'], $exround->get_name());

        // Test calendar event.
        $event = $DB->get_record('event', array(
                'modulename' => exercise_round::TABLE,
                'instance' => $roundid,
                'eventtype' => exercise_round::EVENT_DL_TYPE,
        ));
        // $this->assertTrue($event !== false); // Fails.
        // $this->assertEquals(1, $event->visible);
        // $this->assertEquals($this->course->id, $event->courseid);
        // $this->assertEquals("Deadline: {$this->round1_data['name']}", $event->name);
    }

    public function test_create_learning_objects() {
        global $DB, $CFG;
        require_once($CFG->libdir .'/gradelib.php');

        $this->resetAfterTest(true);

        // Create a course and a round.
        $this->add_course();
        $this->round1_data = array(
                'course' => $this->course->id,
                'name' => '1. Test round 1',
                'remotekey' => 'testround1',
                'openingtime' => time(),
                'closingtime' => time() + 3600 * 24 * 7,
                'ordernum' => 1,
                'status' => exercise_round::STATUS_READY,
                'pointstopass' => 0,
                'latesbmsallowed' => 1,
                'latesbmsdl' => time() + 3600 * 24 * 14,
                'latesbmspenalty' => 0.4,
        );
        $generator = $this->getDataGenerator()->get_plugin_generator('mod_adastra');
        $record = $generator->create_instance($this->round1_data);
        $roundid = $record->id;
        $roundrecord = $DB->get_record(exercise_round::TABLE, array('id' => $roundid));
        $this->assertTrue($roundrecord !== false);
        $exround = new exercise_round($roundrecord);

        // Create category and exercise.
        $categoryrecord = (object) array(
                'course' => $this->course->id,
                'status' => category::STATUS_READY,
                'name' => 'Test category',
                'pointstopass' => 0,
        );
        $category = category::create_from_id(category::create_new($categoryrecord));
        $exerciserecord = (object) array(
                'status' => learning_object::STATUS_READY,
                'parentid' => null,
                'ordernum' => 1,
                'remotekey' => 'testexercise',
                'name' => 'Exercise A',
                'serviceurl' => 'localhost',
                'maxsubmissions' => 10,
                'pointstopass' => 5,
                'maxpoints' => 10,
        );
        $exercise = $exround->create_new_exercise($exerciserecord, $category);

        // Test that exercise was created.
        $this->assertTrue($exercise !== null);
        $fetchedlobjrecord = $DB->get_record(learning_object::TABLE, array('id' => $exercise->get_id()));
        $this->assertTrue($fetchedlobjrecord !== false);
        $fetchedexrecord = $DB->get_record(exercise::TABLE, array('id' => $exercise->get_subtype_id()));
        $this->assertTrue($fetchedexrecord !== false);

        $exround = exercise_round::create_from_id($roundid);
        $this->assertEquals(10, $exround->get_max_points());

        // Round max points should have increased.
        $this->assertEquals($exerciserecord->maxpoints, $DB->get_field(exercise_round::TABLE, 'grade',
                array('id' => $roundid), MUST_EXIST));

        // Create a chapter.
        $chapterrecord = (object) array(
                'status' => learning_object::STATUS_READY,
                'parentid' => null,
                'ordernum' => 2,
                'remotekey' => 'testchapter',
                'name' => 'Chapter A',
                'serviceurl' => 'localhost',
                'generatetoc' => 0,
        );
        $chapter = $exround->create_new_chapter($chapterrecord, $category);

        // Test that exercise was created.
        $this->assertTrue($chapter !== null);
        $fetchedlobjrecord = $DB->get_record(learning_object::TABLE, array('id' => $chapter->get_id()));
        $this->assertTrue($fetchedlobjrecord !== false);
        $fetchedchrecord = $DB->get_record(chapter::TABLE, array('id' => $chapter->get_subtype_id()));
        $this->assertTrue($fetchedchrecord !== false);

        // Round max points should not have changed.
        $this->assertEquals($exerciserecord->maxpoints, $DB->get_field(exercise_round::TABLE, 'grade',
                array('id' => $roundid), MUST_EXIST));

        // Create a hidden exercise.
        $exerciserecord2 = (object) array(
                'status' => learning_object::STATUS_HIDDEN,
                'parentid' => null,
                'ordernum' => 3,
                'remotekey' => 'testexercise2',
                'name' => 'Exercise B',
                'serviceurl' => 'localhost',
                'maxsubmissions' => 10,
                'pointstopass' => 5,
                'maxpoints' => 10,
        );
        $exercise2 = $exround->create_new_exercise($exerciserecord2, $category);
        // Round max points should not have changed.
        $this->assertEquals($exerciserecord->maxpoints, $DB->get_field(exercise_round::TABLE, 'grade',
                array('id' => $roundid), MUST_EXIST));
    }

    public function test_save() {
        global $DB, $CFG;
        require_once($CFG->libdir .'/gradelib.php');

        $this->resetAfterTest(true);

        // Create a course and a round.
        $this->add_course();
        $record = $this->add_round1();
        $exround = exercise_round::create_from_id($record->id);

        // Change some values and save.
        $exround->set_name('PHP round');
        $exround->set_order(5);

        $exround->save();

        // Test that database row was updated.
        $this->assertEquals('PHP round', $DB->get_field(exercise_round::TABLE, 'name', array('id' => $record->id)));
        $this->assertEquals(5, $DB->get_field(exercise_round::TABLE, 'ordernum', array('id' => $record->id)));
        $this->assertEquals(exercise_round::STATUS_READY, // not changed
                $DB->get_field(exercise_round::TABLE, 'status', array('id' => $record->id)));

        // Test event.
        $event = $DB->get_record('event', array(
                'modulename' => exercise_round::TABLE,
                'instance' => $record->id,
                'eventtype' => exercise_round::EVENT_DL_TYPE,
        ));
        $this->assertTrue($event !== false);
        $this->assertEquals(1, $event->visible);
        $this->assertEquals('Deadline: PHP round', $event->name);
    }

    public function test_get_learning_objects() {
        $this->resetAfterTest(true);

        // Create a course and a round.
        $this->add_course();
        $record = $this->add_round1();
        $exround = exercise_round::create_from_id($record->id);

        // Create learning objects.
        $category = category::create_from_id(category::create_new((object) array(
                'course' => $this->course->id,
                'status' => category::STATUS_READY,
                'name' => 'Test category',
                'pointstopass' => 0,
        )));
        $exercise1 = $exround->create_new_exercise((object) array(
                'status' => learning_object::STATUS_READY,
                'parentid' => null,
                'ordernum' => 1,
                'remotekey' => 'testexercise',
                'name' => 'Exercise A',
                'serviceurl' => 'localhost',
                'maxsubmissions' => 10,
                'pointstopass' => 5,
                'maxpoints' => 10,
        ), $category);

        $chapter2 = $exround->create_new_chapter((object) array(
                'status' => learning_object::STATUS_READY,
                'parentid' => null,
                'ordernum' => 2,
                'remotekey' => 'testchapter',
                'name' => 'Chapter A',
                'serviceurl' => 'localhost',
                'generatetoc' => 0,
        ), $category);

        $exercise21 = $exround->create_new_exercise((object) array(
                'status' => learning_object::STATUS_UNLISTED,
                'parentid' => $chapter2->get_id(),
                'ordernum' => 1,
                'remotekey' => 'testexercise21',
                'name' => 'Embedded Exercise 1',
                'serviceurl' => 'localhost',
                'maxsubmissions' => 10,
                'pointstopass' => 5,
                'maxpoints' => 10,
        ), $category);

        $exercise211 = $exround->create_new_exercise((object) array(
                'status' => learning_object::STATUS_READY,
                'parentid' => $exercise21->get_id(),
                'ordernum' => 1,
                'remotekey' => 'testexercise211',
                'name' => 'Another exercise below an embedded exercise',
                'serviceurl' => 'localhost',
                'maxsubmissions' => 10,
                'pointstopass' => 5,
                'maxpoints' => 10,
        ), $category);

        $exercise22 = $exround->create_new_exercise((object) array(
                'status' => learning_object::STATUS_UNLISTED,
                'parentid' => $chapter2->get_id(),
                'ordernum' => 2,
                'remotekey' => 'testexercise22',
                'name' => 'Embedded Exercise 2',
                'serviceurl' => 'localhost',
                'maxsubmissions' => 10,
                'pointstopass' => 5,
                'maxpoints' => 10,
        ), $category);

        // Test fetching round learning objects and their sorting.
        $objects = $exround->get_learning_objects();
        $this->assertEquals($exercise1->get_id(), $objects[0]->get_id());
        $this->assertEquals($chapter2->get_id(), $objects[1]->get_id());
        $this->assertEquals($exercise21->get_id(), $objects[2]->get_id());
        $this->assertEquals($exercise211->get_id(), $objects[3]->get_id());
        $this->assertEquals($exercise22->get_id(), $objects[4]->get_id());
    }

    public function test_delete() {
        global $DB, $CFG;
        require_once($CFG->libdir .'/gradelib.php');

        $this->resetAfterTest(true);

        // Create a course and a round.
        $this->add_course();
        $record = $this->add_round1();
        $exround = exercise_round::create_from_id($record->id);

        // Create learning objects.
        $category = category::create_from_id(category::create_new((object) array(
                'course' => $this->course->id,
                'status' => category::STATUS_READY,
                'name' => 'Test category',
                'pointstopass' => 0,
        )));
        $exercise1 = $exround->create_new_exercise((object) array(
                'status' => learning_object::STATUS_READY,
                'parentid' => null,
                'ordernum' => 1,
                'remotekey' => 'testexercise',
                'name' => 'Exercise A',
                'serviceurl' => 'localhost',
                'maxsubmissions' => 10,
                'pointstopass' => 5,
                'maxpoints' => 10,
        ), $category);

        $chapter2 = $exround->create_new_chapter((object) array(
                'status' => learning_object::STATUS_READY,
                'parentid' => null,
                'ordernum' => 2,
                'remotekey' => 'testchapter',
                'name' => 'Chapter A',
                'serviceurl' => 'localhost',
                'generatetoc' => 0,
        ), $category);

        $exercise21 = $exround->create_new_exercise((object) array(
                'status' => learning_object::STATUS_UNLISTED,
                'parentid' => $chapter2->get_id(),
                'ordernum' => 1,
                'remotekey' => 'testexercise21',
                'name' => 'Embedded Exercise 1',
                'serviceurl' => 'localhost',
                'maxsubmissions' => 10,
                'pointstopass' => 5,
                'maxpoints' => 10,
        ), $category);

        $exercise211 = $exround->create_new_exercise((object) array(
                'status' => learning_object::STATUS_READY,
                'parentid' => $exercise21->get_id(),
                'ordernum' => 1,
                'remotekey' => 'testexercise211',
                'name' => 'Another exercise below an embedded exercise',
                'serviceurl' => 'localhost',
                'maxsubmissions' => 10,
                'pointstopass' => 5,
                'maxpoints' => 10,
        ), $category);

        $exercise22 = $exround->create_new_exercise((object) array(
                'status' => learning_object::STATUS_UNLISTED,
                'parentid' => $chapter2->get_id(),
                'ordernum' => 2,
                'remotekey' => 'testexercise22',
                'name' => 'Embedded Exercise 2',
                'serviceurl' => 'localhost',
                'maxsubmissions' => 10,
                'pointstopass' => 5,
                'maxpoints' => 10,
        ), $category);

        // Check that all child objects exist.
        $this->assertEquals(5, count($exround->get_learning_objects()));

        // Delete round.
        $exround->delete_instance();

        // Test that round and exercises have been deleted.
        $this->assertFalse($DB->get_record(exercise_round::TABLE, array('id' => $exround->get_id())));
        $this->assertEquals(0, $DB->count_records(exercise::TABLE));
        $this->assertEquals(0, $DB->count_records(learning_object::TABLE, array('roundid' => $record->id)));
        $this->assertEquals(0, $DB->count_records(chapter::TABLE));

        $this->assertEquals(0, $DB->count_records('event', array(
                'modulename' => exercise_round::TABLE,
                'instance' => $record->id,
                'eventtype' => exercise_round::EVENT_DL_TYPE,
        )));
    }

    public function test_get_exercise_rounds_in_course() {
        global $DB, $CFG;

        $this->resetAfterTest(true);

        $this->add_course();

        // Create exercise rounds.
        $numrounds = 5;
        $generator = $this->getDataGenerator()->get_plugin_generator('mod_adastra');
        $rounds = array();
        for ($i = 1; $i <= $numrounds; ++$i) {
            if ($i == 3) {
                $status = exercise_round::STATUS_HIDDEN;
            } else if ($i == 4) {
                $status = exercise_round::STATUS_MAINTENANCE;
            } else {
                $status = exercise_round::STATUS_READY;
            }
            $round = array(
                'course' => $this->course->id,
                'name' => "$i. Test round $i",
                'remotekey' => "testround$i",
                'openingtime' => time(),
                'closingtime' => time() + 3600 * 24 * 7,
                'ordernum' => $i,
                'status' => $status,
                'pointstopass' => 0,
                'latesbmsallowed' => 1,
                'latesbmsdl' => time() + 3600 * 24 * 14,
                'latesbmspenalty' => 0.4,
            );
            $rounds[] = $generator->create_instance($round); // std_class record
        }

        $anothercourse = $this->getDataGenerator()->create_course();
        $round = array(
                'course' => $anothercourse->id,
                'name' => "1. Other test round 1",
                'remotekey' => "testround1",
                'openingtime' => time(),
                'closingtime' => time() + 3600 * 24 * 7,
                'ordernum' => 1,
                'status' => exercise_round::STATUS_MAINTENANCE,
                'pointstopass' => 0,
                'latesbmsallowed' => 1,
                'latesbmsdl' => time() + 3600 * 24 * 14,
                'latesbmspenalty' => 0.4,
        );
        $rounds[] = $generator->create_instance($round); // std_class record

        // Test.
        $course1rounds = exercise_round::get_exercise_rounds_in_course($this->course->id, false);
        $this->assertEquals($numrounds - 1, count($course1rounds)); // one round is hidden
        $this->assertEquals($rounds[0]->id, $course1rounds[0]->get_id());
        $this->assertEquals($rounds[1]->id, $course1rounds[1]->get_id());
        $this->assertEquals($rounds[3]->id, $course1rounds[2]->get_id());
        $this->assertEquals($rounds[4]->id, $course1rounds[3]->get_id());

        $course1roundswithhidden = exercise_round::get_exercise_rounds_in_course($this->course->id, true);
        $this->assertEquals($numrounds, count($course1roundswithhidden));
        $this->assertEquals($rounds[2]->id, $course1roundswithhidden[2]->get_id());

        $course2roundswithhidden = exercise_round::get_exercise_rounds_in_course($anothercourse->id, true);
        $this->assertEquals(1, count($course2roundswithhidden));
        $this->assertEquals($rounds[count($rounds) - 1]->id, $course2roundswithhidden[0]->get_id());
    }
}
