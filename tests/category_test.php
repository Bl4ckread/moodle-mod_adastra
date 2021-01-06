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

// Original implementation from:
// https://github.com/apluslms/moodle-mod_astra/blob/master/astra/tests/category_test.php

namespace mod_adastra\local;

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

        $catIds = array();
        $numCats = 4;
        for ($i = 1; $i <= $numCats; ++$i) {
            $catData = array(
                    'course' => $this->course->id,
                    'status' => ($i == 3 ? \mod_adastra\local\data\category::STATUS_HIDDEN : \mod_adastra\local\data\category::STATUS_READY),
                    'name' => "Test category $i",
                    'pointstopass' => 0,
            );
            $catIds[] = \mod_adastra\local\data\category::create_new((object) $catData);
        }

        $anotherCourse = $this->getDataGenerator()->create_course();
        $catIds[] = \mod_adastra\local\data\category::create_new((object) array(
                    'course' => $anotherCourse->id,
                    'status' => \mod_adastra\local\data\category::STATUS_READY,
                    'name' => "Another test category 1",
                    'pointstopass' => 0,
        ));

        $fetchedCats = \mod_adastra\local\data\category::get_categories_in_course($this->course->id, false);
        $this->assertEquals($numCats - 1, count($fetchedCats)); // one cat is hidden
        for ($i = 1; $i <= $numCats; ++$i) {
            if ($i != 3) {
                $this->assertArrayHasKey($catIds[$i - 1], $fetchedCats);
                $this->assertEquals($catIds[$i - 1], $fetchedCats[$catIds[$i - 1]]->get_id());
            }
        }

        $fetchedCatsHidden = \mod_adastra\local\data\category::get_categories_in_course($this->course->id, true);
        $this->assertEquals($numCats, count($fetchedCatsHidden));
        for ($i = 1; $i <= $numCats; ++$i) {
            $this->assertArrayHasKey($catIds[$i - 1], $fetchedCatsHidden);
            $this->assertEquals($catIds[$i - 1], $fetchedCatsHidden[$catIds[$i - 1]]->get_id());
        }

        $fetchedCatsHidden = \mod_adastra\local\data\category::get_categories_in_course($anotherCourse->id, true);
        $this->assertEquals(1, count($fetchedCatsHidden));
        $this->assertArrayHasKey($catIds[count($catIds) - 1], $fetchedCatsHidden);
    }

    // public function test_get_learning_objects() {
    //     $this->resetAfterTest(true);

    //     $category = \mod_adastra\local\data\category::create_from_id(\mod_adastra\local\data\category::create_new((object) array(
    //         'course' => $this->course->id,
    //         'status' => \mod_adastra\local\data\category::STATUS_READY,
    //         'name' => 'Test category',
    //         'pointstopass' => 0,
    //     )));

    //     $exercise1 = $this->round1->create_new_exercise((object) array(
    //             'status' => \mod_adastra\local\data\learning_object::STATUS_READY,
    //             'parentid' => null,
    //             'ordernum' => 1,
    //             'remotekey' => 'testexercise',
    //             'name' => 'Exercise A',
    //             'serviceurl' => 'localhost',
    //             'maxsubmissions' => 10,
    //             'pointstopass' => 5,
    //             'maxpoints' => 10,
    //     ), $category);

    //     $chapter2 = $this->round1->create_new_chapter((object) array(
    //             'status' => \mod_adastra\local\data\learning_object::STATUS_READY,
    //             'parentid' => null,
    //             'ordernum' => 2,
    //             'remotekey' => 'testchapter',
    //             'name' => 'Chapter A',
    //             'serviceurl' => 'localhost',
    //             'generatetoc' => 0,
    //     ), $category);

    //     $exercise21 = $this->round1->create_new_exercise((object) array(
    //             'status' => \mod_adastra\local\data\learning_object::STATUS_UNLISTED,
    //             'parentid' => $chapter2->get_id(),
    //             'ordernum' => 1,
    //             'remotekey' => 'testexercise21',
    //             'name' => 'Embedded Exercise 1',
    //             'serviceurl' => 'localhost',
    //             'maxsubmissions' => 10,
    //             'pointstopass' => 5,
    //             'maxpoints' => 10,
    //     ), $category);

    //     $exercise211 = $this->round1->create_new_exercise((object) array(
    //             'status' => \mod_adastra\local\data\learning_object::STATUS_HIDDEN,
    //             'parentid' => $exercise21->get_id(),
    //             'ordernum' => 1,
    //             'remotekey' => 'testexercise211',
    //             'name' => 'Another exercise below an embedded exercise',
    //             'serviceurl' => 'localhost',
    //             'maxsubmissions' => 10,
    //             'pointstopass' => 5,
    //             'maxpoints' => 10,
    //     ), $category);

    //     $exercise22 = $this->round1->create_new_exercise((object) array(
    //             'status' => \mod_adastra\local\data\learning_object::STATUS_UNLISTED,
    //             'parentid' => $chapter2->get_id(),
    //             'ordernum' => 2,
    //             'remotekey' => 'testexercise22',
    //             'name' => 'Embedded Exercise 2',
    //             'serviceurl' => 'localhost',
    //             'maxsubmissions' => 10,
    //             'pointstopass' => 5,
    //             'maxpoints' => 10,
    //     ), $category);

    //     $objectsIds = array($exercise1->get_id(), $chapter2->get_id(), $exercise21->get_id(),
    //             $exercise211->get_id(), $exercise22->get_id());

    //     $objects = $category->get_learning_objects(false);
    //     $this->assertEquals(4, count($objects)); // one object is hidden
    //     for ($i = 1; $i <= 5; ++$i) {
    //         if ($i != 4) {
    //             $this->assertArrayHasKey($objectsIds[$i - 1], $objects);
    //             $this->assertEquals($objectsIds[$i - 1], $objects[$objectsIds[$i - 1]]->get_id());
    //         }
    //     }

    //     $objects = $category->get_learning_objects(true);
    //     $this->assertEquals(5, count($objects));
    //     for ($i = 1; $i <= 5; ++$i) {
    //         $this->assertArrayHasKey($objectsIds[$i - 1], $objects);
    //         $this->assertEquals($objectsIds[$i - 1], $objects[$objectsIds[$i - 1]]->get_id());
    //     }
    // }
}