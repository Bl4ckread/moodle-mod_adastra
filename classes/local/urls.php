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

/**
 * A utility class that gathers all the URLs of the plugin in one place.
 */
class urls {

    /**
     * Form the base of the url.
     *
     * @return string The url base.
     */
    public static function base_url() {
        global $CFG;
        return $CFG->wwwroot . '/mod/' . \mod_adastra\local\exercise_round::TABLE;
    }

    /**
     * A function for building the urls.
     *
     * @param string $path
     * @param array $query
     * @param boolean $asmoodleurl
     * @param boolean $escaped
     * @param string $anchor
     * @return \moodle_url|string
     */
    private static function build_url($path, array $query, $asmoodleurl = false, $escaped = true, $anchor = null) {
        $url = new \moodle_url(self::base_url() . $path, $query, $anchor);
        if ($asmoodleurl) {
            return $url;
        } else {
            return $url->out($escaped);
            // $escaped true: use in HTML, ampersands (&) are escaped.
            // false: use in HTTP headers.
        }
    }

    /**
     * Form url for the exercise round page.
     *
     * @param \mod_adastra\local\exercise_round $exround
     * @param boolean $asmoodleurl If true, return an instance moodle_url, string otherwise.
     * @return \moodle_url|string
     */
    public static function exercise_round(\mod_adastra\local\exercise_round $exround, $asmoodleurl = false) {
        $query = array('id' => $exround->get_course_module()->id);
        return self::build_url('view.php', $query, $asmoodleurl);
    }

    /**
     * Form url for the create exercise round page.
     *
     * @param int $courseid
     * @param boolean $asmoodleurl If true, return an instance moodle_url, string otherwise.
     * @return \moodle_url|string
     */
    public static function create_exercise_round($courseid, $asmoodleurl = false) {
        $query = array('course' => $courseid);
        return self::build_url('/teachers/edit_round.php', $query, $asmoodleurl);
    }

    /**
     * Form url for the edit exercise round page.
     *
     * @param \mod_adastra\local\exercise_round $exround
     * @param boolean $asmoodleurl If true, return an instance moodle_url, string otherwise.
     * @return \moodle_url|string
     */
    public static function edit_exercise_round(\mod_adastra\local\exercise_round $exround, $asmoodleurl = false) {
        $query = array('id' => $exround->get_id());
        return self::build_url('/teachers/edit_round.php', $query, $asmoodleurl);
    }

    /**
     * Form url for the delete exercise round page.
     *
     * @param \mod_adastra\local\exercise_round $exround
     * @param boolean $asmoodleurl If true, return an instance moodle_url, string otherwise.
     * @return \moodle_url|string
     */
    public static function delete_exercise_round(\mod_adastra\local\exercise_round $exround, $asmoodleurl = false) {
        $query = array('id' => $exround->get_id(), 'type' => 'round');
        return self::build_url('/teachers/delete.php', $query, $asmoodleurl);
    }

    /**
     * Form url to a learning object (exercise or chapter).
     *
     * @param \mod_adastra\local\learning_object $ex
     * @param boolean $asmoodleurl If true, return an instance moodle_url, string otherwise.
     * @param boolean $directurl If false and the exercise is embedded in a chapter (i.e. it has a parent and its
     * status is unlisted), return the URL of the parent (chapter). Otherwise, return url to the exercise object itself
     * (independent exercise page).
     * @return \moodle_url|string
     */
    public static function exercise(\mod_adastra\local\learning_object $ex, $asmoodleurl = false, $directurl = true) {
        $anchor = null;
        $parent = $ex->get_parent_object();
        if (!$directurl && $parent && $ex->is_unlisted()) {
            $id = $parent->get_id();
            $anchor = 'chapter-exercise-' . $ex->get_order();
        } else {
            $id = $ex->get_id();
        }
        $query = array('id' => $id);
        return self::build_url('/exercise.php', $query, $asmoodleurl, true, $anchor);
    }

    /**
     * Form url to the create exercise page.
     *
     * @param \mod_adastra\local\exercise_round $exround
     * @param boolean $asmoodleurl If true, return an instance moodle_url, string otherwise.
     * @return \moodle_url|string
     */
    public static function create_exercise(\mod_adastra\local\exercise_round $exround, $asmoodleurl = false) {
        $query = array('round' => $exround->get_id(), 'type' => 'exercise');
        return self::build_url('/teachers/edit_exercise.php', $query, $asmoodleurl);
    }

    /**
     * Form url to the edit exercise page.
     *
     * @param \mod_adastra\local\learning_object $ex
     * @param boolean $asmoodleurl If true, return an instance moodle_url, string otherwise.
     * @return \moodle_url|string
     */
    public static function edit_exercise(\mod_adastra\local\learning_object $ex, $asmoodleurl = false) {
        $query = array('id' => $ex->get_id());
        return self::build_url('/teachers/edit_exercise.php', $query, $asmoodleurl);
    }

    /**
     * Form url to the delete exercise page.
     *
     * @param \mod_adastra\local\learning_object $ex
     * @param boolean $asmoodleurl If true, return an instance moodle_url, string otherwise.
     * @return \moodle_url|string
     */
    public static function delete_exercise(\mod_adastra\local\learning_object $ex, $asmoodleurl = false) {
        $query = array('id' => $ex->get_id(), 'type' => 'exercise');
        return self::build_url('/teachers/delete.php', $query, $asmoodleurl);
    }

    /**
     * Form url to the create chapter page.
     *
     * @param \mod_adastra\local\exercise_round $ex
     * @param boolean $asmoodleurl If true, return an instance moodle_url, string otherwise.
     * @return \moodle_url|string
     */
    public static function create_chapter(\mod_adastra\local\exercise_round $ex, $asmoodleurl = false) {
        $query = array('round' => $exround->get_id(), 'type' => 'chapter');
        return self::build_url('/teachers/edit_exercise.php', $query, $asmoodleurl);
    }

    /**
     * Form url for the creating a category page.
     *
     * @param int $courseid
     * @param boolean $asmoodleurl If true, return an instance moodle_url, string otherwise.
     * @return \moodle_url|string
     */
    public static function create_category($courseid, $asmoodleurl = false) {
        $query = array('course' => $courseid);
        return self::build_url('/teachers/edit_category.php', $query, $asmoodleurl);
    }

    /**
     * Form url for the editing a category page.
     *
     * @param \mod_adastra\local\category $cat
     * @param boolean $asmoodleurl If true, return an instance moodle_url, string otherwise.
     * @return \moodle_url|string
     */
    public static function edit_category(\mod_adastra\local\category $cat, $asmoodleurl = false) {
        $query = array('id' => $cat->get_id());
        return self::build_url('teachers/edit_category.php', $query, $asmoodleurl);
    }

    /**
     * Form url for the deleting a category page.
     *
     * @param \mod_adastra\local\category $cat
     * @param boolean $asmoodleurl If true, return an instance moodle_url, string otherwise.
     * @return \moodle_url|string
     */
    public static function delete_category(\mod_adastra\local\category $cat, $asmoodleurl= false) {
        $query = array('id' => $cat->get_id(), 'type' => 'category');
        return self::build_url('teachers/delete.php', $query, $asmoodleurl);
    }

    /**
     * Form url for the course editing page.
     *
     * @param int $courseid
     * @param boolean $asmoodleurl If true, return an instance moodle_url, string otherwise.
     * @return \moodle_url|string
     */
    public static function edit_course($courseid, $asmoodleurl = false) {
        $query = array('course' => $courseid);
        return self::build_url('/teachers/edit_course.php', $query, $asmoodleurl);
    }

    /**
     * Form url for the auto setup page.
     *
     * @param int $courseid
     * @param boolean $asmoodleurl If true, return an instance moodle_url, string otherwise.
     * @return \moodle_url|string
     */
    public static function auto_setup($courseid, $asmoodleurl = false) {
        $query = array('id' => $courseid);
        return self::build_url('/index.php', $query, $asmoodleurl);
    }
}