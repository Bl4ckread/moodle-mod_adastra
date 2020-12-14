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

namespace mod_adastra\local\protocol;

defined('MOODLE_INTERNAL') || die();

/**
 * Simple class that represents an exercise (learning object) page retrieved
 * from an exercise service. This class gathers scalar data about the page
 * (HTML string content, boolean and integer values from meta data, etc.).
 *
 * Derived from A+ (a-plus/exercise/protocol/exercise_page.py).
 */
class exercise_page {

    public $exercise;
    public $content;
    public $meta = array();
    public $isloaded = false;
    public $isgraded = false;
    public $isaccepted = false;
    public $isrejected = false;
    public $iswait = false;
    public $points = 0;
    public $expires = 0;
    public $lastmodified = '';
    public $injectedcssurls;
    public $injectedjsurlsandinline;
    public $inlinejqueryscripts;

    public function __construct(\mod_adastra\local\data\learning_object $learningobject) {
        $this->exercise = $learningobject;
    }

    /**
     * Return template context for this page.
     *
     * @return \stdClass
     */
    public function get_template_context() {
        $data = new \stdClass();
        $data->content = $this->content;
        return $data;
    }
}