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

namespace mod_adastra\local\urls;

defined('MOODLE_INTERNAL') || die();

/**
 * A utility class that gathers all the URLs of the plugin in one place.
 */
class urls {
    public static function baseurl() {
        global $CFG;
        return $CFG->wwwroot . '/mod/' . \mod_adastra\local\exercise_round::TABLE;
    }

    private static function buildurl($path, array $query, $asmoodleurl = false, $escaped = true, $anchor = null) {
        $url = new \moodle_url(self::baseurl() . $path, $query, $anchor);
        if ($asmoodleurl) {
            return $url;
        } else {
            return $url->out($escaped);
            // $escaped true: use in HTML, ampersands (&) are escaped.
            // false: use in HTTP headers.
        }
    }

    public static function editcourse($courseid, $asmdlurl = false) {
        $query = array('course' => $courseid);
        return self::buildurl('/teachers/edit_course.php', $query, $asmdlurl);
    }
}