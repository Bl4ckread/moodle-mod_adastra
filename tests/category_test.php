<?php
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
        // create a course instance for testing
        $this->setAdminUser();
        $this->course = $this->getDataGenerator()->create_course();
        
        // // create an exercise round
        $generator = $this->getDataGenerator()->get_plugin_generator('mod_adastra');
        $round1_data = array(
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
        $record = $generator->create_instance($round1_data); // stdClass record
        $this->round1 = new \mod_adastra\local\data\exercise_round($record);
    }
    
    public function test_create_new() {
        global $DB;
        
        $this->resetAfterTest(true);
        
        $catData = array(
                'course' => $this->course->id,
                'status' => \mod_adastra\local\data\category::STATUS_READY,
                'name' => 'Test category',
                'pointstopass' => 0,
        );
        $catId = \mod_adastra\local\data\category::create_new((object) $catData);
        
        $this->assertNotEquals(0, $catId);
        $category = \mod_adastra\local\data\category::create_from_id($catId);
        
        $this->assertEquals($catData['pointstopass'], $category->get_points_to_pass());
        $this->assertEquals($catData['name'], $category->get_name());
        $this->assertEquals($catData['status'], $category->get_status());
        
        // // there should be only one category at this stage
        $this->assertEquals(1, $DB->count_records(\mod_adastra\local\data\category::TABLE, array('course' => $this->course->id)));
    }
}