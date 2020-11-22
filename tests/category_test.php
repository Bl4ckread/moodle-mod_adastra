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

// Original implementation from:
// https://github.com/apluslms/moodle-mod_astra/blob/master/astra/tests/category_test.php

namespace mod_adastra\local;

/**
 * Unit tests for category.
 * @group mod_astra
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
}