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

namespace mod_adastra\cache;

defined('MOODLE_INTERNAL') || die();

class exercise_cache {

    const KEY_PREFIX = 'exercise'; // Used for cache key.
    const CACHE_API_AREA = 'exercisedesc'; // Cache API area name, define in adastra/db/caches.php.

    protected $learningobject;
    protected $language;
    protected $userid;
    protected $data; // Data stored in the cache.
    protected $cache; // Moodle cache object.

    /**
     * Return the key, formed by concatenating KEY_PREFIX, $learningobjectid and $language
     * separated with underscores.
     *
     * @param int $learningobjectid
     * @param string $language
     * @return string
     */
    protected static function key($learningobjectid, $language) {
        // Concatenate KEY_PREFIX, exercise lobjectid and language.
        return self::KEY_PREFIX . '_' . $learningobjectid . '_' . $language;
    }

    /**
     * Purge all learning objects in the given course from the cache.
     *
     * @param int $courseid
     * @return int The number of items successfully removed from the cache.
     */
    public static function invalidate_course($courseid) {
        global $DB;

        $categoryids = array_keys(\mod_adastra\local\data\category::get_categories_in_course($courseid, true));
        if (empty($categoryids)) {
            // No categories, no learning objects in the coruse, no cache to clear.
            return 0;
        }

        $cache = \cache::make(\mod_adastra\local\data\exercise_round::MODNAME, self::CACHE_API_AREA);
        $keys = array();

        // All learning objects in the course.
        $learningobjectids = $DB->get_records_sql(
                'SELECT id FROM {' . \mod_adastra\local\data\learning_object::TABLE . '} lob
                WHERE lob.categoryid IN (' . implode(',', $categoryids) . ')'
        );
        // Returns an array of \stdClass, objects have field id, array_map takes the ids out.
        $learningobjectids = array_map(function($record) {
            return $record->id;
        }, $learningobjectids);
        // All languages that the course may use (languages enabled in the Moodle site).
        $languages = array_keys(get_string_manager()->get_list_of_translations());
        // Language codes, e.g. en for English.

        foreach ($learningobjectids as $lobjid) {
            foreach ($languages as $lang) {
                $keys[] = self::key($lobjid, $lang);
            }
        }

        return $cache->delete_many($keys);
    }
}