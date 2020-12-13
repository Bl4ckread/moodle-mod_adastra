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

    public function test_student_has_submissions_left() {
        $this->resetAfterTest(true);

        $this->assertTrue($this->exercises[0]->student_has_submissions_left($this->student));

        $thirdsbms = \mod_adastra\local\data\submission::create_from_id(
            \mod_adastra\local\data\submission::create_new_submission($this->exercises[0], $this->student->id));

        $this->assertFalse($this->exercises[0]->student_has_submissions_left($this->student));

        // Add submit limit deviation.
        \mod_adastra\local\data\submission_limit_deviation::create_new($this->exercises[0]->get_id(), $this->student->id, 1);

        $this->assertTrue($this->exercises[0]->student_has_submissions_left($this->student));
    }
}