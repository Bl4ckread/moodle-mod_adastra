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

defined('MOODLE_INTERNAL') || die();

// Define cache areas for the Moodle Cache API (Moodle Universal Cache, MUC).

$definitions = array(
    // Exercise HTML descriptions are cached so that they do not have to be retrieved from the exercise service every time.
    'exercisedesc' => array(
        'mod' => cache_store::MODE_APPLICATION, // Cache is shared across users.
        'simpledata' => true, // Scalar data (array of strings and ints).
        'simplekeys' => true,
    ),
);