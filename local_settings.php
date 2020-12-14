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

defined('MOODLE_INTERNAL') || die();

/*
 * Local settings defined with constants.
 * These are not expected to change after the plugin installation so
 * they are not saved in the Moodle configuration database.
 */

define('ADASTRA_REMOTE_PAGE_HOSTS_MAP', array());

//define('ADASTRA_OVERRIDE_SUBMISSION_HOST', null);

// Development and testing settings
//define('ASTRA_REMOTE_PAGE_HOSTS_MAP', array(
//    'grader:8080' => 'localhost:8080',
//));

define('ADASTRA_OVERRIDE_SUBMISSION_HOST', 'http://moodle');