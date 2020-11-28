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

/**
 * Add edit course page to the navbar.
 *
 * @param moodle_page $page $PAGE
 * @param int $courseid
 * @param bool $active
 * @return navigation_node The new navnode.
 */
function adastra_edit_course_navbar(moodle_page $page, $courseid, $active = true) {
    $coursenav = $page->navigation->find($courseid, navigation_node::TYPE_COURSE);
    $editnav = $coursenav->add(get_string('editcourse', \mod_adastra\local\data\exercise_round::MODNAME),
            \mod_adastra\local\urls\urls::edit_course($courseid, true),
            navigation_node::TYPE_CUSTOM, null, 'editcourse');
    if ($active) {
        $editnav->make_active();
    }
    return $editnav;
}

/**
 * Add both edit course and another page to the navbar.
 *
 * @param moodle_page $page
 * @param int $courseid
 * @param string $title Title for the new page.
 * @param moodle_url $url URL of the new page.
 * @param string $navkey Navbar key for the new page.
 * @return navigation_node
 */
function adastra_edit_course_navbar_add(moodle_page $page, $courseid, $title, moodle_url $url, $navkey) {
    $editcoursenav = adastra_edit_course_navbar($page, $courseid, false);
    $nav = $editcoursenav->add($title, $url, navigation_node::TYPE_CUSTOM, null, $navkey);
    $nav->make_active();
    return $nav;
}