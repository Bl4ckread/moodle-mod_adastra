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
     * Create a new cache object.
     *
     * @param \mod_adastra\local\data\learning_object $laerningobject
     * @param string $language
     * @param int $userid
     */
    public function __construct(\mod_adastra\local\data\learning_object $learningobject, $language, $userid) {
        $this->learningobject = $learningobject;
        $this->language = $language;
        $this->userid = $userid;

        // Initialize Moodle cache API.
        $this->cache = \cache::make(\mod_adastra\local\data\exercise_round::MODNAME, self::CACHE_API_AREA);

        // Check if key is found in cache.
        // Is the data stale?
        $key = $this->get_key();
        $this->data = $this->cache->get($key);
        if ($this->needs_generation()) {
            $this->generate_data();
            // Set data to cache if it is cacheable.
            if ($this->data['expires'] > time()) {
                $this->cache->set($key, $this->data);
            }
        }
    }

    /**
     * Return the key for this cache instance.
     *
     * @return string
     */
    protected function get_key() {
        return self::key($this->learningobject->get_id(), $this->language);
    }

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
     * Return true if there is no data or if the data has expired.
     *
     * @return bool
     */
    protected function needs_generation() {
        return $this->data === false || $this->data['expires'] < time();
    }

    /**
     * Generate data for this cache instance.
     *
     * @return void
     */
    protected function generate_data() {
        try {
            $lastmodified = !empty($this->data) ? $this->data['lastmodified'] : null;
            $page = $this->learningobject->load_page($this->userid, $this->language, $lastmodified);
            $this->data = array(
                    'content' => $page->content,
                    'expires' => $page->expires,
                    'lastmodified' => $page->lastmodified,
                    'injectedcssurls' => $page->injectedcssurls,
                    'injectedjsurlsandinline' => $page->injectedjsurlsandinline,
                    'inlinejqueryscripts' => $page->inlinejqueryscripts,
            );
        } catch (\mod_adastra\local\protocol\remote_page_not_modified $e) {
            // Set new expires value.
            $expires = $e->expires();
            if ($expires) {
                $this->data['expires'] = $expires;
            }
        }
    }

    /**
     * Return the content of the data in this cache instance.
     *
     * @return string
     */
    public function get_content() {
        return $this->data['content'];
    }

    /**
     * Return the expire time of the data in this cache instane.
     *
     * @return int A Unix timestamp.
     */
    public function get_expires() {
        return $this->data['expires'];
    }

    /**
     * Return when the data in this cache instance was last modified.
     *
     * @return int A Unix timestamp.
     */
    public function get_last_modified() {
        return $this->data['lastmodified'];
    }

    /**
     * Return the injected css URLs in this cache instance.
     *
     * @return array
     */
    public function get_injected_css_urls() {
        return $this->data['injectedcssurls'];
    }

    /**
     * Return the injected js urls and inline code in this cache instance.
     *
     * @return array
     */
    public function get_injected_js_urls_and_inline() {
        return $this->data['injectedjsurlsandinline'];
    }

    /**
     * Return the inline jquery scripts in this cache instance.
     *
     * @return array
     */
    public function get_inline_jquery_scripts() {
        return $this->data['inlinejqueryscripts'];
    }

    /**
     * Invalidate this cache instance.
     *
     * @return bool
     */
    public function invalidate_instance() {
        return $this->cache->delete($this->get_key());
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