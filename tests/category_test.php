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

/**
 * Unit tests for category.
 * @group mod_adastra
 */
class category_testcase extends \advanced_testcase {

    private $course;
    private $round1;

    public function setUp() {
        // Create a course instance for testing.
        $this->setAdminUser();
        $this->course = $this->getDataGenerator()->create_course();

        // Create an exercise round.
        $generator = $this->getDataGenerator()->get_plugin_generator('mod_adastra');
        $round1data = array(
                'course' => $this->course->id,
                'name' => '1. Test round 1',
                'remotekey' => 'testround1',
                'openingtime' => time(),
                'closingtime' => time() + 3600 * 24 * 7,
                'ordernum' => 1,
                'status' => \mod_adastra\local\data\exercise_round::STATUS_READY,
                'pointstopass' => 0,
                'latesbmsallowed' => 1,
                'latesbmsdl' => time() + 3600 * 24 * 14,
                'latesbmspenalty' => 0.4,
        );
        $record = $generator->create_instance($round1data); // stdClass record.
        $this->round1 = new \mod_adastra\local\data\exercise_round($record);
    }

    public function test_create_new() {
        global $DB;

        $this->resetAfterTest(true);

        $catdata = array(
                'course' => $this->course->id,
                'status' => \mod_adastra\local\data\category::STATUS_READY,
                'name' => 'Test category',
                'pointstopass' => 0,
        );
        $catid = \mod_adastra\local\data\category::create_new((object) $catdata);

        $this->assertNotEquals(0, $catid);
        $category = \mod_adastra\local\data\category::create_from_id($catid);

        $this->assertEquals($catdata['pointstopass'], $category->get_points_to_pass());
        $this->assertEquals($catdata['name'], $category->get_name());
        $this->assertEquals($catdata['status'], $category->get_status());

        // There should be only one category at this stage.
        $this->assertEquals(1, $DB->count_records(\mod_adastra\local\data\category::TABLE, array('course' => $this->course->id)));
    }

    public function test_get_categories_in_course() {
        $this->resetAfterTest(true);

        $catids = array();
        $numcats = 4;
        for ($i = 1; $i <= $numcats; ++$i) {
            $catdata = array(
                    'course' => $this->course->id,
                    'status' => ($i == 3 ? \mod_adastra\local\data\category::STATUS_HIDDEN : \mod_adastra\local\data\category::STATUS_READY),
                    'name' => "Test category $i",
                    'pointstopass' => 0,
            );
            $catids[] = \mod_adastra\local\data\category::create_new((object) $catdata);
        }

        $anothercourse = $this->getDataGenerator()->create_course();
        $catids[] = \mod_adastra\local\data\category::create_new((object) array(
                    'course' => $anothercourse->id,
                    'status' => \mod_adastra\local\data\category::STATUS_READY,
                    'name' => "Another test category 1",
                    'pointstopass' => 0,
        ));

        $fetchedcats = \mod_adastra\local\data\category::get_categories_in_course($this->course->id, false);
        $this->assertEquals($numcats - 1, count($fetchedcats)); // one cat is hidden
        for ($i = 1; $i <= $numcats; ++$i) {
            if ($i != 3) {
                $this->assertArrayHasKey($catids[$i - 1], $fetchedcats);
                $this->assertEquals($catids[$i - 1], $fetchedcats[$catids[$i - 1]]->get_id());
            }
        }

        $fetchedcatshidden = \mod_adastra\local\data\category::get_categories_in_course($this->course->id, true);
        $this->assertEquals($numcats, count($fetchedcatshidden));
        for ($i = 1; $i <= $numcats; ++$i) {
            $this->assertArrayHasKey($catids[$i - 1], $fetchedcatshidden);
            $this->assertEquals($catids[$i - 1], $fetchedcatshidden[$catids[$i - 1]]->get_id());
        }

        $fetchedcatshidden = \mod_adastra\local\data\category::get_categories_in_course($anothercourse->id, true);
        $this->assertEquals(1, count($fetchedcatshidden));
        $this->assertArrayHasKey($catids[count($catids) - 1], $fetchedcatshidden);
    }

    public function test_get_learning_objects() {
        $this->resetAfterTest(true);

        $category = \mod_adastra\local\data\category::create_from_id(\mod_adastra\local\data\category::create_new((object) array(
                'course' => $this->course->id,
                'status' => \mod_adastra\local\data\category::STATUS_READY,
                'name' => 'Test category',
                'pointstopass' => 0,
        )));

        $exercise1 = $this->round1->create_new_exercise((object) array(
                'status' => \mod_adastra\local\data\learning_object::STATUS_READY,
                'parentid' => null,
                'ordernum' => 1,
                'remotekey' => 'testexercise',
                'name' => 'Exercise A',
                'serviceurl' => 'localhost',
                'maxsubmissions' => 10,
                'pointstopass' => 5,
                'maxpoints' => 10,
        ), $category);

        $chapter2 = $this->round1->create_new_chapter((object) array(
                'status' => \mod_adastra\local\data\learning_object::STATUS_READY,
                'parentid' => null,
                'ordernum' => 2,
                'remotekey' => 'testchapter',
                'name' => 'Chapter A',
                'serviceurl' => 'localhost',
                'generatetoc' => 0,
        ), $category);

        $exercise21 = $this->round1->create_new_exercise((object) array(
                'status' => \mod_adastra\local\data\learning_object::STATUS_UNLISTED,
                'parentid' => $chapter2->get_id(),
                'ordernum' => 1,
                'remotekey' => 'testexercise21',
                'name' => 'Embedded Exercise 1',
                'serviceurl' => 'localhost',
                'maxsubmissions' => 10,
                'pointstopass' => 5,
                'maxpoints' => 10,
        ), $category);

        $exercise211 = $this->round1->create_new_exercise((object) array(
                'status' => \mod_adastra\local\data\learning_object::STATUS_HIDDEN,
                'parentid' => $exercise21->get_id(),
                'ordernum' => 1,
                'remotekey' => 'testexercise211',
                'name' => 'Another exercise below an embedded exercise',
                'serviceurl' => 'localhost',
                'maxsubmissions' => 10,
                'pointstopass' => 5,
                'maxpoints' => 10,
        ), $category);

        $exercise22 = $this->round1->create_new_exercise((object) array(
                'status' => \mod_adastra\local\data\learning_object::STATUS_UNLISTED,
                'parentid' => $chapter2->get_id(),
                'ordernum' => 2,
                'remotekey' => 'testexercise22',
                'name' => 'Embedded Exercise 2',
                'serviceurl' => 'localhost',
                'maxsubmissions' => 10,
                'pointstopass' => 5,
                'maxpoints' => 10,
        ), $category);

        $objectsids = array($exercise1->get_id(), $chapter2->get_id(), $exercise21->get_id(),
                $exercise211->get_id(), $exercise22->get_id());

        $objects = $category->get_learning_objects(false);
        $this->assertEquals(4, count($objects)); // one object is hidden
        for ($i = 1; $i <= 5; ++$i) {
            if ($i != 4) {
                $this->assertArrayHasKey($objectsids[$i - 1], $objects);
                $this->assertEquals($objectsids[$i - 1], $objects[$objectsids[$i - 1]]->get_id());
            }
        }

        $objects = $category->get_learning_objects(true);
        $this->assertEquals(5, count($objects));
        for ($i = 1; $i <= 5; ++$i) {
            $this->assertArrayHasKey($objectsids[$i - 1], $objects);
            $this->assertEquals($objectsids[$i - 1], $objects[$objectsids[$i - 1]]->get_id());
        }
    }

    public function test_update_or_create() {
        global $DB;

        $this->resetAfterTest(true);

        $catdata = array(
                'course' => $this->course->id,
                'name' => 'Some category',
                'status' => \mod_adastra\local\data\category::STATUS_READY,
                'pointstopass' => 5,
        );
        $newcatid = \mod_adastra\local\data\category::update_or_create((object) $catdata); // Should create new category.

        $this->assertEquals(1, $DB->count_records(\mod_adastra\local\data\category::TABLE, array('course' => $this->course->id)));
        $cat = \mod_adastra\local\data\category::create_from_id($newcatid);
        $this->assertEquals('Some category', $cat->get_name());
        $this->assertEquals(\mod_adastra\local\data\category::STATUS_READY, $cat->get_status());

        // Update the category.
        $catdata['status'] = \mod_adastra\local\data\category::STATUS_HIDDEN;
        $catdata['pointstopass'] = 17;
        $catid = \mod_adastra\local\data\category::update_or_create((object) $catdata);

        $this->assertEquals($newcatid, $catid);
        $cat = \mod_adastra\local\data\category::create_from_id($catid);
        $this->assertEquals('Some category', $cat->get_name());
        $this->assertEquals(\mod_adastra\local\data\category::STATUS_HIDDEN, $cat->get_status());
        $this->assertEquals(17, $cat->get_points_to_pass());
        $this->assertEquals($catid, $cat->get_id());
    }

    public function test_delete() {
        global $DB;

        $this->resetAfterTest(true);

        // Create categorories, rounds, and exercises.
        $category = \mod_adastra\local\data\category::create_from_id(\mod_adastra\local\data\category::create_new((object) array(
                'course' => $this->course->id,
                'status' => \mod_adastra\local\data\category::STATUS_READY,
                'name' => 'Test category',
                'pointstopass' => 0,
        )));
        $category2 = \mod_adastra\local\data\category::create_from_id(\mod_adastra\local\data\category::create_new((object) array(
                'course' => $this->course->id,
                'status' => \mod_adastra\local\data\category::STATUS_READY,
                'name' => 'Test category 2',
                'pointstopass' => 0,
        )));

        $generator = $this->getDataGenerator()->get_plugin_generator('mod_adastra');
        $round2data = array(
                'course' => $this->course->id,
                'name' => '2. Test round 2',
                'remotekey' => 'testround2',
                'openingtime' => time(),
                'closingtime' => time() + 3600 * 24 * 7,
                'ordernum' => 2,
                'status' => \mod_adastra\local\data\exercise_round::STATUS_READY,
                'pointstopass' => 0,
                'latesbmsallowed' => 1,
                'latesbmsdl' => time() + 3600 * 24 * 14,
                'latesbmspenalty' => 0.4,
        );
        $record = $generator->create_instance($round2data); // Std_class record.
        $round2 = new \mod_adastra\local\data\exercise_round($record);

        for ($i = 0; $i < 5; ++$i) {
            $round = ($i % 2 == 0 ? $this->round1 : $round2);
            $cat = ($i % 2 == 0 ? $category : $category2);

            $round->create_new_exercise((object) array(
                    'status' => \mod_adastra\local\data\learning_object::STATUS_READY,
                    'parentid' => null,
                    'ordernum' => $i + 1,
                    'remotekey' => "testexercise$i",
                    'name' => "Exercise $i",
                    'serviceurl' => 'localhost',
                    'maxsubmissions' => 10,
                    'pointstopass' => 5,
                    'maxpoints' => 10,
            ), $cat);
        }

        // Test delete.
        $category->delete();
        $this->assertEquals(1, $DB->count_records(\mod_adastra\local\data\category::TABLE, array('course' => $this->course->id)));
        $cats = \mod_adastra\local\data\category::get_categories_in_course($this->course->id);
        $this->assertEquals($category2->get_id(), current($cats)->get_id());
        $this->assertEquals(0, $DB->count_records(\mod_adastra\local\data\learning_object::TABLE, array('categoryid' => $category->get_id())));
        // The other category should remain unchanged.
        $this->assertEquals(2, $DB->count_records(\mod_adastra\local\data\learning_object::TABLE, array('categoryid' => $category2->get_id())));
    }
}