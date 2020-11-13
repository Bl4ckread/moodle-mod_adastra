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

namespace mod_adastra\local;

defined('MOODLE_INTERNAL') || die();

class course_config extends \mod_adastra\local\database_object {
    const TABLE = 'adastra_course_settings';

    // Numbering of exercise rounds.
    const MODULE_NUMBERING_NONE          = 0;
    const MODULE_NUMBERING_ARABIC        = 1; // 1, 2, ...
    const MODULE_NUMBERING_ROMAN         = 2; // I, II, ...
    const MODULE_NUMBERING_HIDDEN_ARABIC = 3;
    // No number in the module name but exercise numbers start with the module number, e.g., 1.1.

    // Numbering of objects in rounds.
    const CONTENT_NUMBERING_NONE   = 0;
    const CONTENT_NUMBERING_ARABIC = 1;
    const CONTENT_NUMBERING_ROMAN  = 2;

    const DEFAULT_LANGUAGES = array('en');

    /**
     * Create a new or update an existing row in the database.
     *
     * @param int $courseid
     * @param int $sectionnumber
     * @param string $apikey
     * @param string $configurl
     * @param int $modulenumbering
     * @param int $contentnumbering
     * @param string $language
     * @return void
     */
    public static function updateorcreate($courseid, $sectionnumber, $apikey = null, $configurl = null,
            $modulenumbering = null, $contentnumbering = null, $language = null) {
        global $DB;

        $row = $DB->get_record(self::TABLE, array('course' => $courseid), '*', IGNORE_MISSING);
        if ($row === false) {
            // Create new.
            $newrow = new stdClass();
            $newrow->course = $courseid;
            $newrow->sectionnum = $sectionnumber;
            $newrow->apikey = $apikey;
            $newrow->configurl = $configurl;
            if ($modulenumbering !== null) {
                $newrow->modulenumbering = $modulenumbering;
            }
            if ($contentnumbering !== null) {
                $newrow->contentnumbering = $contentnumbering;
            }
            if ($language !== null) {
                $newrow->lang = self::preparelangstring($language);
            }
            $id = $DB->insert_record(self::TABLE, $newrow);
            return $id != 0;
        } else {
            // Update row.
            if ($sectionnumber !== null) {
                $row->sectionnum = $sectionnumber;
            }
            if ($apikey !== null) {
                $row->apikey = $apikey;
            }
            if ($configurl !== null) {
                $row->configurl = $configurl;
            }
            if ($modulenumbering !== null) {
                $row->modulenumbering = $modulenumbering;
            }
            if ($contentnumbering !== null) {
                $row->contentnumbering = $contentnumbering;
            }
            if ($language !== null) {
                $row->lang = self::preparelangstring($language);
            }
            return $DB->update_record(self::TABLE, $row);
        }
    }

    /**
     * Prepare languages to a suitable format.
     *
     * @param string[]|string $langs
     * @return string The formatted language string.
     */
    public static function preparelangstring($langs) : string {
        if (empty($langs)) {
            return '';
        } else if (is_array($langs)) {
            return '|' . implode('|', $langs) . '|';
        }
        return substr($langs, 0, 5); // At most five first characters.
    }

}