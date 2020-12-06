<?php

namespace mod_adastra\local;

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
        // create a course instance for testing
        $this->setAdminUser();
        $this->course = $this->getDataGenerator()->create_course();
    
        $this->student = $this->getDataGenerator()->create_user();
        $this->student2 = $this->getDataGenerator()->create_user();
        
        // create 2 exercise rounds
        $generator = $this->getDataGenerator()->get_plugin_generator('mod_adastra');
        $round_data = array(
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
        $record = $generator->create_instance($round_data); // stdClass record
        $this->round1 = new \mod_adastra\local\data\exercise_round($record);
        $round_data = array(
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
        $record = $generator->create_instance($round_data); // stdClass record
        $this->round2 = new \mod_adastra\local\data\exercise_round($record);
    
        // create category
        $this->category = \mod_adastra\local\data\category::create_from_id(\mod_adastra\local\data\category::create_new((object) array(
                'course' => $this->course->id,
                'status' => \mod_adastra\local\data\category::STATUS_READY,
                'name' => 'Testing exercises',
                'pointstopass' => 0,
        )));
    
        // create exercises
        $this->exercises = array();
        $this->exercises[] = $this->add_exercise(array(), $this->round1, $this->category);
        $this->exercises[] = $this->add_exercise(array('parentid' => $this->exercises[0]->get_id()), $this->round1, $this->category);
        $this->exercises[] = $this->add_exercise(array('parentid' => $this->exercises[0]->get_id()), $this->round1, $this->category);
        $this->exercises[] = $this->add_exercise(array('parentid' => $this->exercises[2]->get_id()), $this->round1, $this->category);
        $this->exercises[] = $this->add_exercise(array(), $this->round1, $this->category);
        $this->exercises[] = $this->add_exercise(array(), $this->round1, $this->category);
        
        // create submissions
        $this->submissions = array();
        $this->submissions[] = \mod_adastra\local\data\submission::create_from_id(
                \mod_adastra\local\data\submission::create_new_submission($this->exercises[0], $this->student->id));
        $this->submissions[] = \mod_adastra\local\data\submission::create_from_id(
                \mod_adastra\local\data\submission::create_new_submission($this->exercises[0], $this->student->id,
                        null, \mod_adastra\local\data\submission::STATUS_ERROR));
        $this->submissions[] = \mod_adastra\local\data\submission::create_from_id(
                \mod_adastra\local\data\submission::create_new_submission($this->exercises[0], $this->student2->id));
    }
    
    protected function add_exercise(array $data, \mod_adastra\local\data\exercise_round $round, \mod_adastra\local\data\category $category) {
        static $counter = 0;
        ++$counter;
        $defaults = array(
                'status' => \mod_adastra\local\data\learning_object::STATUS_READY,
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
}