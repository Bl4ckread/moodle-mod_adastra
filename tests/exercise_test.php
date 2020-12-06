<?php

namespace mod_adastra\local;

require_once(dirname(__FILE__) .'/exercise_test_data.php');

/**
 * Unit tests for exercise.
 * @group mod_astra
 */
class mod_astra_exercise_testcase extends \advanced_testcase {
    
    use exercise_test_data;
    
    public function setUp() {
        $this->add_test_data();
    }
    
    public function test_is_submission_allowed() {
        $this->resetAfterTest(true);
        
        $this->assertTrue($this->exercises[0]->is_submission_allowed($this->student));
    }
}